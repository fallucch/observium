<?php

$scale_min = "0";
$scale_max = "60";

include("includes/graphs/common.inc.php");

$rrd_options .= " COMMENT:'                          Min     Last   Max\\n'";

$rrd_options .= " DEF:sensor=$rrd_filename:sensor:AVERAGE";
$rrd_options .= " DEF:sensor_max=$rrd_filename:sensor:MAX";
$rrd_options .= " DEF:sensor_min=$rrd_filename:sensor:MIN";
$rrd_options .= " CDEF:sensor_diff=sensor_max,sensor_min,-";
$rrd_options .= " AREA:sensor_min";
$rrd_options .= " AREA:sensor_diff#c5c5c5::STACK";

$rrd_options .= " LINE1.5:sensor#cc0000:'" . rrdtool_escape($sensor['sensor_descr'],21)."'";
$rrd_options .= " GPRINT:sensor_min:MIN:%4.1lfC";
$rrd_options .= " GPRINT:sensor:LAST:%4.1lfC";
$rrd_options .= " GPRINT:sensor_max:MAX:%4.1lfC\\\\l";

if (is_numeric($sensor['sensor_limit'])) $rrd_options .= " HRULE:".$sensor['sensor_limit']."#999999::dashes";
if (is_numeric($sensor['sensor_limit_low'])) $rrd_options .= " HRULE:".$sensor['sensor_limit_low']."#999999::dashes";

#wtfbroken code.
if ($_GET['previous'] == 'yes')
{
  $rrd_options .= " DEF:sensorX=$rrd_filename:sensor:AVERAGE:start=".$prev_from.":end=".$from;
  $rrd_options .= " LINE1.5:sensorX#0000cc:'Prev " . rrdtool_escape($sensor['sensor_descr'],18)."'";
  $rrd_options .= " SHIFT:sensorX:$period";
  $rrd_options .= " GPRINT:sensorX$current_id:MIN:%5.2lfA";
  $rrd_options .= " GPRINT:sensorX:LAST:%5.2lfA";
  $rrd_options .= " GPRINT:sensorX:MAX:%5.2lfA\\\\l";
}

?>
