<?php

require 'includes/html/graphs/common.inc.php';

$scale_min = 0;
$colours = 'mixed';
$nototal = (($width < 224) ? 1 : 0);
$unit_text = 'Buffer';
$rrd_filename = Rrd::name($device['hostname'], ['app', 'ntp-server', $app->app_id]);
$array = [
    'buffer_recv' => ['descr' => 'Received'],
    'buffer_used' => ['descr' => 'Used'],
    'buffer_free' => ['descr' => 'Free'],
];

$i = 0;

if (Rrd::checkRrdExists($rrd_filename)) {
    foreach ($array as $ds => $var) {
        $rrd_list[$i]['filename'] = $rrd_filename;
        $rrd_list[$i]['descr'] = $var['descr'];
        $rrd_list[$i]['ds'] = $ds;
        $rrd_list[$i]['colour'] = \App\Facades\LibrenmsConfig::get("graph_colours.$colours.$i");
        $i++;
    }
} else {
    throw new \LibreNMS\Exceptions\RrdGraphException("No Data file $rrd_filename");
}

require 'includes/html/graphs/generic_multi_line.inc.php';
