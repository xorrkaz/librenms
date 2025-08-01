<?php

$name = 'suricata';
$unit_text = 'pkt/s';
$descr = 'DCE RPC TCP';
$ds = 'data';

if (isset($vars['sinstance'])) {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___app_layer__tx__dcerpc_tcp']);
} else {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___app_layer__tx__dcerpc_tcp']);
}

require 'includes/html/graphs/generic_stats.inc.php';
