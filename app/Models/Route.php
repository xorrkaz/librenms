<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Route extends DeviceRelatedModel
{
    protected $table = 'route';
    protected $primaryKey = 'route_id';
    public static $translateProto = [
        'undefined',
        'other',
        'local',
        'netmgmt',
        'icmp',
        'egp',
        'ggp',
        'hello',
        'rip',
        'isIs',
        'esIs',
        'ciscoIgrp',
        'bbnSpfIgp',
        'ospf',
        'ospfv3',
        'bgp',
        'idpr',
        'ciscoEigrp',
        'dvmrp',
    ];

    public static $translateType = [
        'undefined',
        'other',
        'reject',
        'local',
        'remote',
        'blackhole',
    ];

    public $timestamps = true;

    // ---- Define Relationships ----
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Port, $this>
     */
    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'port_id', 'port_id');
    }
}
