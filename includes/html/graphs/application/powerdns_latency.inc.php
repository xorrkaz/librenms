<?php

require 'includes/html/graphs/common.inc.php';

$scale_min = 0;
$ds = 'latency';
$colour_area = 'F6F6F6';
$colour_line = 'B3D0DB';
$colour_area_max = 'FFEE99';
$graph_max = 100;
$unit_text = 'Latency';
$rrd_filename = Rrd::name($device['hostname'], ['app', 'powerdns', $app->app_id]);

require 'includes/html/graphs/generic_simplex.inc.php';
