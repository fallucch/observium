<?php

$query = "SELECT *,DATE_FORMAT(datetime, '%D %b %Y %T') as humandate  FROM `eventlog` WHERE `host` = '$_GET[id]' ORDER BY `datetime` DESC LIMIT 0,250";
$data = mysql_query($query);
echo('<table cellspacing="0" cellpadding="2" width="100%">');

while ($entry = mysql_fetch_assoc($data))
{
  include("includes/print-event.inc.php");
}

echo('</table>');

?>