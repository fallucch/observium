<?php

// FIXME new url format

if (is_integer($i/2)) { $bg_colour = $list_colour_a; } else { $bg_colour = $list_colour_b; }

echo("<tr bgcolor='$bg_colour'>");

echo("<td width=200 class=entity-title><a href='routing/vrf/".$vrf['mplsVpnVrfRouteDistinguisher']."/'>" . $vrf['vrf_name'] . "</a></td>");
echo("<td width=150 class=small>" . $vrf['mplsVpnVrfDescription'] . "</td>");
echo("<td width=100 class=small>" . $vrf['mplsVpnVrfRouteDistinguisher'] . "</td>");

echo('<td class="entity">');
foreach (dbFetchRows("SELECT * FROM ports WHERE `device_id` = ? AND `ifVrf` = ?", array($device['device_id'], $vrf['vrf_id'])) as $port)
{
  if ($vars['view'] == "graphs")
  {
    $graph_type = "port_" . $vars['graph'];
    echo("<div style='display: block; padding: 2px; margin: 2px; min-width: 139px; max-width:139px; min-height:85px; max-height:85px; text-align: center; float: left; background-color: #e9e9e9;'>
    <div style='font-weight: bold;'>".makeshortif($port['ifDescr'])."</div>
    <a href='device/".$device['device_id']."/port/".$port['port_id']."/' onmouseover=\"return overlib('\
    <div style=\'font-size: 16px; padding:5px; font-weight: bold; color: #e5e5e5;\'>".$device['hostname']." - ".$port['ifDescr']."</div>\
    ".$port['ifAlias']." \
    <img src=\'graph.php?type=$graph_type&amp;id=".$port['port_id']."&amp;from=".$config['time']['twoday']."&amp;to=".$config['time']['now']."&amp;width=450&amp;height=150\'>\
    ', CENTER, LEFT, FGCOLOR, '#e5e5e5', BGCOLOR, '#e5e5e5', WIDTH, 400, HEIGHT, 150);\" onmouseout=\"return nd();\"  >".
    "<img src='graph.php?type=$graph_type&amp;id=".$port['port_id']."&amp;from=".$config['time']['twoday']."&amp;to=".$config['time']['now']."&amp;width=132&amp;height=40&amp;legend=no'>
    </a>
    <div style='font-size: 9px;'>".truncate(short_port_descr($port['ifAlias']), 22, '')."</div>
   </div>");
  } else {
    echo($vrf['port_sep'] . generate_port_link($port, makeshortif($port['ifDescr'])));
    $vrf['port_sep'] = ", ";
  }
}

echo("</td>");
echo("</tr>");

?>
