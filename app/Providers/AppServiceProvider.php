<?php

namespace App\Providers;

use App\Facades\LibrenmsConfig;
use App\Guards\ApiTokenGuard;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LibreNMS\Cache\PermissionsCache;
use LibreNMS\Util\IP;
use LibreNMS\Util\Validate;
use LibreNMS\Util\Version;
use Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerGeocoder();

        $this->app->singleton('permissions', function () {
            return new PermissionsCache();
        });
        $this->app->singleton('device-cache', function () {
            return new \LibreNMS\Cache\Device();
        });
        $this->app->singleton('port-cache', function () {
            return new \LibreNMS\Cache\Port();
        });
        $this->app->singleton('git', function () {
            return new \LibreNMS\Util\Git();
        });

        $this->app->bind(\App\Models\Device::class, function (Application $app) {
            /** @var \LibreNMS\Cache\Device $cache */
            $cache = $app->make('device-cache');

            return $cache->hasPrimary() ? $cache->getPrimary() : new \App\Models\Device;
        });

        $this->app->singleton('sensor-discovery', function (Application $app) {
            return new \App\Discovery\Sensor($app->make('device-cache')->getPrimary());
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->bootCustomBladeDirectives();
        $this->bootCustomValidators();
        $this->configureMorphAliases();
        $this->bootObservers();
        Version::registerAboutCommand();

        $this->bootAuth();
    }

    private function bootCustomBladeDirectives(): void
    {
        Blade::if('config', function ($key, $value = true) {
            return LibrenmsConfig::get($key) == $value;
        });
        Blade::if('notconfig', function ($key) {
            return ! LibrenmsConfig::get($key);
        });
        Blade::if('admin', function () {
            return auth()->check() && auth()->user()->isAdmin();
        });

        Blade::directive('deviceUrl', function ($arguments) {
            return "<?php echo \LibreNMS\Util\Url::deviceUrl($arguments); ?>";
        });

        // Graphing
        Blade::directive('signedGraphUrl', function ($vars) {
            return "<?php echo \LibreNMS\Util\Url::forExternalGraph($vars); ?>";
        });

        Blade::directive('signedGraphTag', function ($vars) {
            return "<?php echo '<img class=\"librenms-graph\" src=\"' . \LibreNMS\Util\Url::forExternalGraph($vars) . '\" />'; ?>";
        });

        Blade::directive('graphImage', function ($vars, $flags = 0) {
            return "<?php echo \LibreNMS\Util\Graph::getImageData($vars, $flags); ?>";
        });

        Blade::directive('vuei18n', function () {
            return "<?php
             \$manifest_file = public_path('js/lang/manifest.json');
             \$manifest = is_readable(\$manifest_file) ? json_decode(file_get_contents(\$manifest_file), true) : [];
             \$locales = array_unique(['en', app()->getLocale()]);
             echo implode(PHP_EOL, array_map(fn (\$locale) => '<script src=\"' . asset(\$manifest[\$locale] ?? \"/js/lang/\$locale.js\") . '\"></script>', \$locales));
 ?>";
        });
    }

    private function configureMorphAliases(): void
    {
        $sensor_types = [];
        foreach (\LibreNMS\Enum\Sensor::values() as $sensor_type) {
            $sensor_types[$sensor_type] = Sensor::class;
        }
        Relation::morphMap(array_merge([
            'interface' => \App\Models\Port::class,
            'sensor' => Sensor::class,
            'device' => \App\Models\Device::class,
            'device_group' => \App\Models\DeviceGroup::class,
            'location' => \App\Models\Location::class,
        ], $sensor_types));
    }

    private function registerGeocoder()
    {
        $this->app->alias(\LibreNMS\Interfaces\Geocoder::class, 'geocoder');
        $this->app->bind(\LibreNMS\Interfaces\Geocoder::class, function ($app) {
            $engine = LibrenmsConfig::get('geoloc.engine');

            switch ($engine) {
                case 'mapquest':
                    Log::debug('MapQuest geocode engine');

                    return $app->make(\App\ApiClients\MapquestApi::class);
                case 'bing':
                    Log::debug('Bing geocode engine');

                    return $app->make(\App\ApiClients\BingApi::class);
                case 'openstreetmap':
                    Log::debug('OpenStreetMap geocode engine');

                    return $app->make(\App\ApiClients\NominatimApi::class);
                default:
                case 'google':
                    Log::debug('Google Maps geocode engine');

                    return $app->make(\App\ApiClients\GoogleMapsApi::class);
            }
        });
    }

    private function bootObservers()
    {
        \App\Models\Device::observe(\App\Observers\DeviceObserver::class);
        \App\Models\Mempool::observe(\App\Observers\MempoolObserver::class);
        \App\Models\Package::observe(\App\Observers\PackageObserver::class);
        \App\Models\Qos::observe(\App\Observers\QosObserver::class);
        Sensor::observe(\App\Observers\SensorObserver::class);
        \App\Models\Service::observe(\App\Observers\ServiceObserver::class);
        \App\Models\Storage::observe(\App\Observers\StorageObserver::class);
        \App\Models\Stp::observe(\App\Observers\StpObserver::class);
        User::observe(\App\Observers\UserObserver::class);
        \App\Models\Vminfo::observe(\App\Observers\VminfoObserver::class);
        \App\Models\WirelessSensor::observe(\App\Observers\WirelessSensorObserver::class);
    }

    private function bootCustomValidators()
    {
        Validator::extend('alpha_space', function ($attribute, $value) {
            return preg_match('/^[\w\s]+$/u', $value);
        });

        Validator::extend('ip_or_hostname', function ($attribute, $value, $parameters, $validator) {
            $ip = substr($value, 0, strpos($value, '/') ?: strlen($value)); // allow prefixes too

            return IP::isValid($ip) || Validate::hostname($value);
        });

        Validator::extend('is_regex', function ($attribute, $value) {
            return @preg_match($value, '') !== false;
        });

        Validator::extend('zero_or_exists', function ($attribute, $value, $parameters, $validator) {
            if ($value === 0 || $value === '0') {
                return true;
            }

            $validator = Validator::make([$attribute => $value], [$attribute => 'exists:' . implode(',', $parameters)]);

            return $validator->passes();
        }, trans('validation.exists'));

        Validator::extend('url_or_xml', function ($attribute, $value): bool {
            if (! is_string($value)) {
                return false;
            }

            if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
                return true;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($value);
            if ($xml !== false) {
                return true;
            }

            return false;
        });

        Validator::extend('array_keys_not_empty', function ($attribute, $value): bool {
            if (! is_array($value)) {
                return false;
            }

            foreach ($value as $key => $_) {
                if (is_string($key) && strlen(trim($key)) == 0) {
                    return false;
                }
            }

            return true;
        });
    }

    public function bootAuth(): void
    {
        Auth::provider('legacy', function ($app, array $config) {
            return new LegacyUserProvider();
        });

        Auth::provider('token_provider', function ($app, array $config) {
            return new TokenUserProvider();
        });

        Auth::extend('token_driver', function ($app, $name, array $config) {
            $userProvider = $app->make(TokenUserProvider::class);
            $request = $app->make('request');

            return new ApiTokenGuard($userProvider, $request);
        });

        Gate::define('global-admin', function (User $user) {
            return $user->hasAnyRole('admin', 'demo');
        });
        Gate::define('admin', function (User $user) {
            return $user->hasRole('admin');
        });
        Gate::define('global-read', function (User $user) {
            return $user->hasAnyRole('admin', 'global-read');
        });
        Gate::define('device', function (User $user, $device) {
            return $user->canAccessDevice($device);
        });

        // define super admin and global read
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('admin')) {
                return true;  // super admin
            }

            if (in_array($ability, ['view', 'viewAny']) && $user->hasRole('global-read')) {
                return true; // global read access
            }

            return null;
        });
    }
}
