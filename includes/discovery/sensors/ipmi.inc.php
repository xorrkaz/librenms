<?php

use App\Facades\LibrenmsConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// IPMI - We can discover this on poll!
if ($ipmi['host'] = get_dev_attrib($device, 'ipmi_hostname')) {
    echo 'IPMI : ';
    $ipmi['port'] = filter_var(get_dev_attrib($device, 'ipmi_port'), FILTER_VALIDATE_INT) ?: '623';
    $ipmi['user'] = get_dev_attrib($device, 'ipmi_username');
    $ipmi['password'] = get_dev_attrib($device, 'ipmi_password');
    $ipmi['kg_key'] = get_dev_attrib($device, 'ipmi_kg_key');

    $cmd = [LibrenmsConfig::get('ipmitool', 'ipmitool')];
    if (LibrenmsConfig::get('own_hostname') != $device['hostname'] || $ipmi['host'] != 'localhost') {
        if (empty($ipmi['kg_key']) || is_null($ipmi['kg_key'])) {
            array_push($cmd, '-H', $ipmi['host'], '-p', $ipmi['port'], '-U', $ipmi['user'], '-P', $ipmi['password'], '-L', 'USER');
        } else {
            array_push($cmd, '-H', $ipmi['host'], '-p', $ipmi['port'], '-U', $ipmi['user'], '-P', $ipmi['password'], '-y', $ipmi['kg_key'], '-L', 'USER');
        }
    }

    foreach (LibrenmsConfig::get('ipmi.type', []) as $ipmi_type) {
        // Check if the IPMI type is available, catch segfaults of ipmitool/freeipmi.
        try {
            Log::debug('Trying IPMI type: ' . $ipmi_type);
            $results = explode(PHP_EOL, external_exec(array_merge($cmd, ['-I', $ipmi_type, 'sensor'])));

            $results = array_values(array_filter($results, function ($line) {
                return ! Str::contains($line, 'discrete');
            }));

            if (! empty($results)) {
                set_dev_attrib($device, 'ipmi_type', $ipmi_type);
                echo "$ipmi_type ";
                break;
            }
        } catch (\Exception $e) {
            Log::error('IPMI Discovery error occurred: ' . $e->getMessage());
        }
    }

    $index = 0;

    sort($results);
    foreach ($results as $sensor) {
        // BB +1.1V IOH     | 1.089      | Volts      | ok    | na        | 1.027     | 1.054     | 1.146     | 1.177     | na
        $values = array_map('trim', explode('|', $sensor));
        [$desc,$current,$unit,$state,$low_nonrecoverable,$low_limit,$low_warn,$high_warn,$high_limit,$high_nonrecoverable] = $values;

        $index++;
        if ($current != 'na' && LibrenmsConfig::has("ipmi_unit.$unit")) {
            discover_sensor(
                null,
                LibrenmsConfig::get("ipmi_unit.$unit"),
                $device,
                $desc,
                $index,
                'ipmi',
                $desc,
                '1',
                '1',
                $low_limit == 'na' ? null : $low_limit,
                $low_warn == 'na' ? null : $low_warn,
                $high_warn == 'na' ? null : $high_warn,
                $high_limit == 'na' ? null : $high_limit,
                $current,
                'ipmi'
            );
        }
    }

    echo "\n";
}

$sensorDiscovery = app('sensor-discovery');
$sensorDiscovery->sync(sensor_class: 'voltage', poller_type: 'ipmi');
$sensorDiscovery->sync(sensor_class: 'temperature', poller_type: 'ipmi');
$sensorDiscovery->sync(sensor_class: 'fanspeed', poller_type: 'ipmi');
$sensorDiscovery->sync(sensor_class: 'power', poller_type: 'ipmi');
