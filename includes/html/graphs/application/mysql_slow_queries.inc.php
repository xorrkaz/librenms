<?php

require 'includes/html/graphs/common.inc.php';

$rrd_filename = Rrd::name($device['hostname'], ['app', 'mysql', $app->app_id]);

$array = ['SQs' => 'Slow queries'];

$i = 0;
if (Rrd::checkRrdExists($rrd_filename)) {
    foreach ($array as $ds => $var) {
        $rrd_list[$i]['filename'] = $rrd_filename;
        if (is_array($var)) {
            $rrd_list[$i]['descr'] = $var['descr'];
        } else {
            $rrd_list[$i]['descr'] = $var;
        }

        $rrd_list[$i]['ds'] = $ds;
        $i++;
    }
} else {
    throw new \LibreNMS\Exceptions\RrdGraphException("No Data file $rrd_filename");
}

$colours = 'mixed';
$nototal = 1;
$unit_text = 'Queries';

require 'includes/html/graphs/generic_multi_simplex_seperated.inc.php';
