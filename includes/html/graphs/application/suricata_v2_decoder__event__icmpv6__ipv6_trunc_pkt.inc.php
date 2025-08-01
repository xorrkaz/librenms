<?php

$name = 'suricata';
$unit_text = 'ICMPv6 pkts/s';
$descr = 'Trunc Pkt';
$ds = 'data';

if (isset($vars['sinstance'])) {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'instance_' . $vars['sinstance'] . '___decoder__event__icmpv6__ipv6_trunc_pkt']);
} else {
    $rrd_filename = Rrd::name($device['hostname'], ['app', $name, $app->app_id, 'totals___decoder__event__icmpv6__ipv6_trunc_pkt']);
}

require 'includes/html/graphs/generic_stats.inc.php';
