<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 *   These functions perform rewrites on strings and numbers.
 *
 * @package    observium
 * @subpackage functions
 * @author     Adam Armstrong <adama@memetic.org>
 * @copyright  (C) 2006 - 2012 Adam Armstrong
 *
 */

function rewrite_location($location)
{
  /// FIXME - also check the database for rewrites?
  // Also roll this all up into some kind of 'rewrite_device' function? - adama
  global $config, $debug;

  if (isset($config['location_map'][$location]))
  {
    $location = $config['location_map'][$location];
  }

  return $location;
}

function formatMac($mac)
{
  $mac = preg_replace("/(..)(..)(..)(..)(..)(..)/", "\\1:\\2:\\3:\\4:\\5:\\6", $mac);

  return $mac;
}

function rewrite_entity_descr ($descr)
{
  $descr = str_replace("Distributed Forwarding Card", "DFC", $descr);
  $descr = preg_replace("/7600 Series SPA Interface Processor-/", "7600 SIP-", $descr);
  $descr = preg_replace("/Rev\.\ [0-9\.]+\ /", "", $descr);
  $descr = preg_replace("/12000 Series Performance Route Processor/", "12000 PRP", $descr);
  $descr = preg_replace("/^12000/", "", $descr);
  $descr = preg_replace("/Gigabit Ethernet/", "GigE", $descr);
  $descr = preg_replace("/^ASR1000\ /", "", $descr);
  $descr = str_replace("Routing Processor", "RP", $descr);
  $descr = str_replace("Route Processor", "RP", $descr);
  $descr = str_replace("Switching Processor", "SP", $descr);
  $descr = str_replace("Sub-Module", "Module ", $descr);
  $descr = str_replace("DFC Card", "DFC", $descr);
  $descr = str_replace("Centralized Forwarding Card", "CFC", $descr);
  $descr = str_replace("Power Supply Module", "PSU ", $descr);
  $descr = str_replace("/Voltage Sensor/", "Voltage", $descr);
  $descr = preg_replace("/^temperatures /", "", $descr);
  $descr = preg_replace("/^voltages /", "", $descr);

  return $descr;
}

/**
 * Humanize Device
 *
 *   Process the $device array to add/modify elements.
 *
 * @param array $device
 * @return none
 */

function humanize_device(&$device)
{
  global $config;

  $device['state'] = unserialize($device['device_state']);

  // Set the HTML class and Tab color for the device based on status
  if ($device['status'] == '0')
  {
    $device['html_row_class'] = "error";
    $device['html_tab_colour'] = "#cc0000";
  } else {
    $device['html_row_class'] = "";
    /// This one looks too bright and out of place - adama
    #$device['html_tab_colour'] = "#194BBF";
    /// This one matches the logo. changes are not finished, lets see if we can add colour elsewhere. - adama
    $device['html_tab_colour'] = "#194B7F"; // Fucking dull gay colour, but at least there's a semicolon now - tom
                                            // Your mum's a semicolon - adama
  }
  if ($device['ignore'] == '1')
  {
    $device['html_row_class'] = "warning";
    $device['html_tab_colour'] = "#aaaaaa";
    if ($device['status'] == '1')
    {
      $device['html_row_class'] = "";
      $device['html_tab_colour'] = "#009900"; // Why green for ignore? Confusing!
    }
  }
  if ($device['disabled'] == '1')
  {
    $device['html_row_class'] = "warning";
    $device['html_tab_colour'] = "#aaaaaa";
  }

  $device['icon'] = getImage($device);

  // Set the name we print for the OS
  $device['os_text'] = $config['os'][$device['os']]['text'];

  // Mark this device as being humanized
  $device['humanized_device'] = TRUE;
}


/**
 * Humanize BGP Peer
 *
 * Returns a the $peer array with processed information:
 * row_class, table_tab_colour, state_class, admin_class
 *
 * @param array $peer
 * @return array $peer
 *
 */

function humanize_bgp (&$peer)
{
  // Peer is disabled, set all things grey.
  if ($peer['bgpPeerAdminStatus'] == "stop")
  {
       $peer['table_tab_colour'] = "#aaaaaa"; $peer['html_row_class'] = "warning"; $peer['state_class'] = "muted"; $peer['admin_class'] = "muted"; $peer['alert']=0; $peer['disabled']=1;
  } elseif ($peer['bgpPeerAdminStatus'] == "start" || $peer['bgpPeerAdminStatus'] == "running" ) {
       // Peer is enabled, set state green and check other things
       $peer['admin_class'] = "text-success";
       if ($peer['bgpPeerState'] == "established")
       {
         $peer['state_class'] = "text-success"; $peer['table_tab_colour'] = "#194B7F"; $peer['html_row_class'] = "";
       } else {
         $peer['state_class'] = "text-error"; $peer['table_tab_colour'] = "#cc0000"; $peer['html_row_class'] = "error";
       }
  }

  if ($peer['bgpPeerRemoteAs'] == $peer['bgpLocalAs'])                                    { $peer['peer_type'] = "<span style='color: #00f;'>iBGP</span>"; }
  elseif ($peer['bgpPeerRemoteAS'] >= '64512' && $peer['bgpPeerRemoteAS'] <= '65535')     { $peer['peer_type'] = "<span style='color: #f00;'>Priv eBGP</span>"; }
  else                                                                                    { $peer['peer_type'] = "<span style='color: #0a0;'>eBGP</span>"; }

  $peer['human_localip']  = (strstr($peer['bgpPeerLocalAddr'],  ':')) ? Net_IPv6::compress($peer['bgpPeerLocalAddr'])  : $peer['bgpPeerLocalAddr'];
  $peer['human_remoteip'] = (strstr($peer['bgpPeerRemoteAddr'], ':')) ? Net_IPv6::compress($peer['bgpPeerRemoteAddr']) : $peer['bgpPeerRemoteAddr'];
}


/**
 * Humanize port.
 *
 * Returns a the $port array with processed information:
 * label, humans_speed, human_type, html_class and human_mac
 * row_class, table_tab_colour
 *
 * @param array $ports
 * @return array $ports
 *
 */

function humanize_port(&$port)
{
  global $config;

  // Process port data to make it pretty for printing. EVOLUTION, BITCHES.
  // Lots of hacky shit will end up here with if (os);

  $device = device_by_id_cache($port['device_id']);
  $os = $device['os'];

  $port['human_speed'] = humanspeed($port['ifSpeed']);
  $port['human_type']  = fixiftype($port['ifType']);
  $port['html_class']  = ifclass($port['ifOperStatus'], $port['ifAdminStatus']);
  $port['human_mac']   = formatMac($port['ifPhysAddress']);

  if (isset($config['os'][$os]['ifname']))
  {
    $port['label'] = $port['ifName'];

    if ($port['ifName'] == "")
    {
      $port['label'] = $port['ifDescr'];
    } else {
      $port['label'] = $port['ifName'];
    }

  } elseif (isset($config['os'][$os]['ifalias']))
  {
    $port['label'] = $port['ifAlias'];
  } else {
    $port['label'] = $port['ifDescr'];
    if (isset($config['os'][$os]['ifindex']))
    {
      $port['label'] = $port['label'] . " " . $port['ifIndex'];
    }
  }

  if ($device['os'] == "speedtouch")
  {
    list($port['label']) = explode("thomson", $port['label']);
  }

  if ($port['ifAdminStatus'] == "down")                                                 { $port['table_tab_colour'] = "#aaaaaa"; $port['row_class'] = "";
  } elseif ($port['ifAdminStatus'] == "up" && $port['ifOperStatus']== "down")           { $port['table_tab_colour'] = "#cc0000"; $port['row_class'] = "error";
  } elseif ($port['ifAdminStatus'] == "up" && $port['ifOperStatus']== "lowerLayerDown") { $port['table_tab_colour'] = "#ff6600"; $port['row_class'] = "warning";
  } elseif ($port['ifAdminStatus'] == "up" && $port['ifOperStatus']== "up")             { $port['table_tab_colour'] = "#194B7F"; $port['row_class'] = ""; }

  $port['humanized'] = TRUE; /// Set this so we can check it later.

}

$rewrite_entSensorType = array (
  'celsius' => 'C',
  'unknown' => '',
  'specialEnum' => 'C',
  'watts' => 'W',
  'truthvalue' => '',
);

$translate_ifOperStatus = array(
  "1" => "up",
  "2" => "down",
  "3" => "testing",
  "4" => "unknown",
  "5" => "dormant",
  "6" => "notPresent",
  "7" => "lowerLayerDown",
);

function translate_ifOperStatus ($ifOperStatus)
{
  global $translate_ifOperStatus;

  if ($translate_ifOperStatus['$ifOperStatus'])
  {
    $ifOperStatus = $translate_ifOperStatus['$ifOperStatus'];
  }

  return $ifOperStatus;
}

$translate_ifAdminStatus = array(
  "1" => "up",
  "2" => "down",
  "3" => "testing",
);

function translate_ifAdminStatus ($ifAdminStatus)
{
  global $translate_ifAdminStatus;

  if ($translate_ifAdminStatus[$ifAdminStatus])
  {
    $ifAdminStatus = $translate_ifAdminStatus[$ifAdminStatus];
  }

  return $ifAdminStatus;
}

$rewrite_junos_hardware = array(
  '.1.3.6.1.4.1.4874.1.1.1.6.2' => 'E120',
  '.1.3.6.1.4.1.4874.1.1.1.6.1' => 'E320',
  '.1.3.6.1.4.1.4874.1.1.1.1.1' => 'ERX1400',
  '.1.3.6.1.4.1.4874.1.1.1.1.3' => 'ERX1440',
  '.1.3.6.1.4.1.4874.1.1.1.1.5' => 'ERX310',
  '.1.3.6.1.4.1.4874.1.1.1.1.2' => 'ERX700',
  '.1.3.6.1.4.1.4874.1.1.1.1.4' => 'ERX705',
  '.1.3.6.1.4.1.2636.1.1.1.2.43' => 'EX2200',
  '.1.3.6.1.4.1.2636.1.1.1.2.30' => 'EX3200',
  '.1.3.6.1.4.1.2636.1.1.1.2.76' => 'EX3300',
  '.1.3.6.1.4.1.2636.1.1.1.2.31' => 'EX4200',
  '.1.3.6.1.4.1.2636.1.1.1.2.44' => 'EX4500',
  '.1.3.6.1.4.1.2636.1.1.1.2.74' => 'EX6210',
  '.1.3.6.1.4.1.2636.1.1.1.2.32' => 'EX8208',
  '.1.3.6.1.4.1.2636.1.1.1.2.33' => 'EX8216',
  '.1.3.6.1.4.1.2636.1.1.1.2.16' => 'IRM',
  '.1.3.6.1.4.1.2636.1.1.1.2.13' => 'J2300',
  '.1.3.6.1.4.1.2636.1.1.1.2.23' => 'J2320',
  '.1.3.6.1.4.1.2636.1.1.1.2.24' => 'J2350',
  '.1.3.6.1.4.1.2636.1.1.1.2.14' => 'J4300',
  '.1.3.6.1.4.1.2636.1.1.1.2.22' => 'J4320',
  '.1.3.6.1.4.1.2636.1.1.1.2.19' => 'J4350',
  '.1.3.6.1.4.1.2636.1.1.1.2.15' => 'J6300',
  '.1.3.6.1.4.1.2636.1.1.1.2.20' => 'J6350',
  '.1.3.6.1.4.1.2636.1.1.1.2.38' => 'JCS1200',
  '.1.3.6.1.4.1.2636.10' => 'BX7000',
  '.1.3.6.1.4.1.12532.252.2.1' => 'SA-2000',
  '.1.3.6.1.4.1.12532.252.6.1' => 'SA-6000',
  '.1.3.6.1.4.1.4874.1.1.1.5.1' => 'UMC Sys Mgmt',
  '.1.3.6.1.4.1.2636.3.41.1.1.5.4' => 'WXC1800',
  '.1.3.6.1.4.1.2636.3.41.1.1.5.1' => 'WXC250',
  '.1.3.6.1.4.1.2636.3.41.1.1.5.5' => 'WXC2600',
  '.1.3.6.1.4.1.2636.3.41.1.1.5.6' => 'WXC3400',
  '.1.3.6.1.4.1.2636.3.41.1.1.5.2' => 'WXC500',
  '.1.3.6.1.4.1.2636.3.41.1.1.5.3' => 'WXC590',
  '.1.3.6.1.4.1.2636.3.41.1.1.5.7' => 'WXC7800',
  '.1.3.6.1.4.1.2636.1.1.1.2.4' => 'M10',
  '.1.3.6.1.4.1.2636.1.1.1.2.11' => 'M10i',
  '.1.3.6.1.4.1.2636.1.1.1.2.18' => 'M120',
  '.1.3.6.1.4.1.2636.1.1.1.2.3' => 'M160',
  '.1.3.6.1.4.1.2636.1.1.1.2.2' => 'M20',
  '.1.3.6.1.4.1.2636.1.1.1.2.9' => 'M320',
  '.1.3.6.1.4.1.2636.1.1.1.2.1' => 'M40',
  '.1.3.6.1.4.1.2636.1.1.1.2.8' => 'M40e',
  '.1.3.6.1.4.1.2636.1.1.1.2.5' => 'M5',
  '.1.3.6.1.4.1.2636.1.1.1.2.10' => 'M7i',
  '.1.3.6.1.4.1.2636.1.1.1.2.68' => 'MAG6610',
  '.1.3.6.1.4.1.2636.1.1.1.2.67' => 'MAG6611',
  '.1.3.6.1.4.1.2636.1.1.1.2.66' => 'MAG8600',
  '.1.3.6.1.4.1.2636.1.1.1.2.89' => 'MX10',
  '.1.3.6.1.4.1.2636.1.1.1.2.29' => 'MX240',
  '.1.3.6.1.4.1.2636.1.1.1.2.88' => 'MX40',
  '.1.3.6.1.4.1.2636.1.1.1.2.25' => 'MX480',
  '.1.3.6.1.4.1.2636.1.1.1.2.90' => 'MX5',
  '.1.3.6.1.4.1.2636.1.1.1.2.57' => 'MX80',
  '.1.3.6.1.4.1.2636.1.1.1.2.21' => 'MX960',
  '.1.3.6.1.4.1.3224.1.1' => 'Netscreen',
  '.1.3.6.1.4.1.3224.1.3' => 'Netscreen 10',
  '.1.3.6.1.4.1.3224.1.4' => 'Netscreen 100',
  '.1.3.6.1.4.1.3224.1.5' => 'Netscreen 1000',
  '.1.3.6.1.4.1.3224.1.9' => 'Netscreen 204',
  '.1.3.6.1.4.1.3224.1.10' => 'Netscreen 208',
  '.1.3.6.1.4.1.3224.1.8' => 'Netscreen 25',
  '.1.3.6.1.4.1.3224.1.2' => 'Netscreen 5',
  '.1.3.6.1.4.1.3224.1.7' => 'Netscreen 50',
  '.1.3.6.1.4.1.3224.1.6' => 'Netscreen 500',
  '.1.3.6.1.4.1.3224.1.13' => 'Netscreen 5000',
  '.1.3.6.1.4.1.3224.1.14' => 'Netscreen 5GT',
  '.1.3.6.1.4.1.3224.1.17' => 'Netscreen 5GT-ADSL-A',
  '.1.3.6.1.4.1.3224.1.23' => 'Netscreen 5GT-ADSL-A-WLAN',
  '.1.3.6.1.4.1.3224.1.19' => 'Netscreen 5GT-ADSL-B',
  '.1.3.6.1.4.1.3224.1.25' => 'Netscreen 5GT-ADSL-B-WLAN',
  '.1.3.6.1.4.1.3224.1.21' => 'Netscreen 5GT-WLAN',
  '.1.3.6.1.4.1.3224.1.12' => 'Netscreen 5XP',
  '.1.3.6.1.4.1.3224.1.11' => 'Netscreen 5XT',
  '.1.3.6.1.4.1.3224.1.15' => 'Netscreen Client',
  '.1.3.6.1.4.1.3224.1.28' => 'Netscreen ISG1000',
  '.1.3.6.1.4.1.3224.1.16' => 'Netscreen ISG2000',
  '.1.3.6.1.4.1.3224.1.52' => 'Netscreen SSG140',
  '.1.3.6.1.4.1.3224.1.53' => 'Netscreen SSG140',
  '.1.3.6.1.4.1.3224.1.35' => 'Netscreen SSG20',
  '.1.3.6.1.4.1.3224.1.36' => 'Netscreen SSG20-WLAN',
  '.1.3.6.1.4.1.3224.1.54' => 'Netscreen SSG320',
  '.1.3.6.1.4.1.3224.1.55' => 'Netscreen SSG350',
  '.1.3.6.1.4.1.3224.1.29' => 'Netscreen SSG5',
  '.1.3.6.1.4.1.3224.1.30' => 'Netscreen SSG5-ISDN',
  '.1.3.6.1.4.1.3224.1.33' => 'Netscreen SSG5-ISDN-WLAN',
  '.1.3.6.1.4.1.3224.1.31' => 'Netscreen SSG5-v92',
  '.1.3.6.1.4.1.3224.1.34' => 'Netscreen SSG5-v92-WLAN',
  '.1.3.6.1.4.1.3224.1.32' => 'Netscreen SSG5-WLAN',
  '.1.3.6.1.4.1.3224.1.50' => 'Netscreen SSG520',
  '.1.3.6.1.4.1.3224.1.18' => 'Netscreen SSG550',
  '.1.3.6.1.4.1.3224.1.51' => 'Netscreen SSG550',
  '.1.3.6.1.4.1.2636.1.1.1.2.84' => 'QFX3000',
  '.1.3.6.1.4.1.2636.1.1.1.2.85' => 'QFX5000',
  '.1.3.6.1.4.1.2636.1.1.1.2.82' => 'QFX Switch',
  '.1.3.6.1.4.1.2636.1.1.1.2.41' => 'SRX100',
  '.1.3.6.1.4.1.2636.1.1.1.2.64' => 'SRX110',
  '.1.3.6.1.4.1.2636.1.1.1.2.49' => 'SRX1400',
  '.1.3.6.1.4.1.2636.1.1.1.2.36' => 'SRX210',
  '.1.3.6.1.4.1.2636.1.1.1.2.58' => 'SRX220',
  '.1.3.6.1.4.1.2636.1.1.1.2.39' => 'SRX240',
  '.1.3.6.1.4.1.2636.1.1.1.2.35' => 'SRX3400',
  '.1.3.6.1.4.1.2636.1.1.1.2.34' => 'SRX3600',
  '.1.3.6.1.4.1.2636.1.1.1.2.86' => 'SRX550',
  '.1.3.6.1.4.1.2636.1.1.1.2.28' => 'SRX5600',
  '.1.3.6.1.4.1.2636.1.1.1.2.26' => 'SRX5800',
  '.1.3.6.1.4.1.2636.1.1.1.2.40' => 'SRX650',
  '.1.3.6.1.4.1.2636.1.1.1.2.27' => 'T1600',
  '.1.3.6.1.4.1.2636.1.1.1.2.7' => 'T320',
  '.1.3.6.1.4.1.2636.1.1.1.2.6' => 'T640',
  '.1.3.6.1.4.1.2636.1.1.1.2.17' => 'TX',
  '.1.3.6.1.4.1.2636.1.1.1.2.37' => 'TXPlus',
);

# FIXME needs a rewrite, preferrably in form above? ie cat3524tXLEn etc
$rewrite_cisco_hardware = array(
  '.1.3.6.1.4.1.9.1.275' => 'C2948G-L3',
);

$rewrite_ftos_hardware = array (
  '.1.3.6.1.4.1.6027.1.1.1'=> 'E1200',
  '.1.3.6.1.4.1.6027.1.1.2'=> 'E600',
  '.1.3.6.1.4.1.6027.1.1.3'=> 'E300',
  '.1.3.6.1.4.1.6027.1.1.4'=> 'E610',
  '.1.3.6.1.4.1.6027.1.1.5'=> 'E1200i',
  '.1.3.6.1.4.1.6027.1.2.1'=> 'C300',
  '.1.3.6.1.4.1.6027.1.2.2'=> 'C150',
  '.1.3.6.1.4.1.6027.1.3.1'=> 'S50',
  '.1.3.6.1.4.1.6027.1.3.2'=> 'S50E',
  '.1.3.6.1.4.1.6027.1.3.3'=> 'S50V',
  '.1.3.6.1.4.1.6027.1.3.4'=> 'S25P-AC',
  '.1.3.6.1.4.1.6027.1.3.5'=> 'S2410CP',
  '.1.3.6.1.4.1.6027.1.3.6'=> 'S2410P',
  '.1.3.6.1.4.1.6027.1.3.7'=> 'S50N-AC',
  '.1.3.6.1.4.1.6027.1.3.8'=> 'S50N-DC',
  '.1.3.6.1.4.1.6027.1.3.9'=> 'S25P-DC',
  '.1.3.6.1.4.1.6027.1.3.10'=> 'S25V',
  '.1.3.6.1.4.1.6027.1.3.11'=> 'S25N',
  '.1.3.6.1.4.1.6027.1.3.12'=> 'S60',
  '.1.3.6.1.4.1.6027.1.3.13'=> 'S55',
  '.1.3.6.1.4.1.6027.1.3.14'=> 'S4810',
  '.1.3.6.1.4.1.6027.1.3.15'=> 'Z9000'
);

$rewrite_fortinet_hardware = array(
  '.1.3.6.1.4.1.12356.102.1.1000' => 'FortiAnalyzer 100',
  '.1.3.6.1.4.1.12356.102.1.10002' => 'FortiAnalyzer 1000B',
  '.1.3.6.1.4.1.12356.102.1.1001' => 'FortiAnalyzer 100A',
  '.1.3.6.1.4.1.12356.102.1.1002' => 'FortiAnalyzer 100B',
  '.1.3.6.1.4.1.12356.102.1.20000' => 'FortiAnalyzer 2000',
  '.1.3.6.1.4.1.12356.102.1.20001' => 'FortiAnalyzer 2000A',
  '.1.3.6.1.4.1.12356.102.1.4000' => 'FortiAnalyzer 400',
  '.1.3.6.1.4.1.12356.102.1.40000' => 'FortiAnalyzer 4000',
  '.1.3.6.1.4.1.12356.102.1.40001' => 'FortiAnalyzer 4000A',
  '.1.3.6.1.4.1.12356.102.1.4002' => 'FortiAnalyzer 400B',
  '.1.3.6.1.4.1.12356.102.1.8000' => 'FortiAnalyzer 800',
  '.1.3.6.1.4.1.12356.102.1.8002' => 'FortiAnalyzer 800B',
  '.1.3.6.1.4.1.12356.101.1.1000' => 'FortiGate 100',
  '.1.3.6.1.4.1.12356.101.1.10000' => 'FortiGate 1000',
  '.1.3.6.1.4.1.12356.101.1.10001' => 'FortiGate 1000A',
  '.1.3.6.1.4.1.12356.101.1.10002' => 'FortiGate 1000AFA2',
  '.1.3.6.1.4.1.12356.101.1.10003' => 'FortiGate 1000ALENC',
  '.1.3.6.1.4.1.12356.101.1.1001' => 'FortiGate 100A',
  '.1.3.6.1.4.1.12356.101.1.1002' => 'FortiGate 110C',
  '.1.3.6.1.4.1.12356.101.1.1003' => 'FortiGate 111C',
  '.1.3.6.1.4.1.12356.101.1.2000' => 'FortiGate 200',
  '.1.3.6.1.4.1.12356.101.1.20000' => 'FortiGate 2000',
  '.1.3.6.1.4.1.12356.101.1.2001' => 'FortiGate 200A',
  '.1.3.6.1.4.1.12356.101.1.2002' => 'FortiGate 224B',
  '.1.3.6.1.4.1.12356.101.1.2003' => 'FortiGate 200A',
  '.1.3.6.1.4.1.12356.101.1.3000' => 'FortiGate 300',
  '.1.3.6.1.4.1.12356.101.1.30000' => 'FortiGate 3000',
  '.1.3.6.1.4.1.12356.101.1.3001' => 'FortiGate 300A',
  '.1.3.6.1.4.1.12356.101.1.30160' => 'FortiGate 3016B',
  '.1.3.6.1.4.1.12356.101.1.302' => 'FortiGate 30B',
  '.1.3.6.1.4.1.12356.101.1.3002' => 'FortiGate 310B',
  '.1.3.6.1.4.1.12356.101.1.36000' => 'FortiGate 3600',
  '.1.3.6.1.4.1.12356.101.1.36003' => 'FortiGate 3600A',
  '.1.3.6.1.4.1.12356.101.1.38100' => 'FortiGate 3810A',
  '.1.3.6.1.4.1.12356.101.1.4000' => 'FortiGate 400',
  '.1.3.6.1.4.1.12356.101.1.40000' => 'FortiGate 4000',
  '.1.3.6.1.4.1.12356.101.1.4001' => 'FortiGate 400A',
  '.1.3.6.1.4.1.12356.101.1.5000' => 'FortiGate 500',
  '.1.3.6.1.4.1.12356.101.1.50000' => 'FortiGate 5000',
  '.1.3.6.1.4.1.12356.101.1.50010' => 'FortiGate 5001',
  '.1.3.6.1.4.1.12356.101.1.50011' => 'FortiGate 5001A',
  '.1.3.6.1.4.1.12356.101.1.50012' => 'FortiGate 5001FA2',
  '.1.3.6.1.4.1.12356.101.1.50021' => 'FortiGate 5002A',
  '.1.3.6.1.4.1.12356.101.1.50001' => 'FortiGate 5002FB2',
  '.1.3.6.1.4.1.12356.101.1.50040' => 'FortiGate 5004',
  '.1.3.6.1.4.1.12356.101.1.50050' => 'FortiGate 5005',
  '.1.3.6.1.4.1.12356.101.1.50051' => 'FortiGate 5005FA2',
  '.1.3.6.1.4.1.12356.101.1.5001' => 'FortiGate 500A',
  '.1.3.6.1.4.1.12356.101.1.500' => 'FortiGate 50A',
  '.1.3.6.1.4.1.12356.101.1.501' => 'FortiGate 50AM',
  '.1.3.6.1.4.1.12356.101.1.502' => 'FortiGate 50B',
  '.1.3.6.1.4.1.12356.101.1.504' => 'FortiGate 51B',
  '.1.3.6.1.4.1.12356.101.1.600' => 'FortiGate 60',
  '.1.3.6.1.4.1.12356.101.1.6201' => 'FortiGate 600D',
  '.1.3.6.1.4.1.12356.101.1.602' => 'FortiGate 60ADSL',
  '.1.3.6.1.4.1.12356.101.1.603' => 'FortiGate 60B',
  '.1.3.6.1.4.1.12356.101.1.601' => 'FortiGate 60M',
  '.1.3.6.1.4.1.12356.101.1.6200' => 'FortiGate 620B',
  '.1.3.6.1.4.1.12356.101.1.8000' => 'FortiGate 800',
  '.1.3.6.1.4.1.12356.101.1.8001' => 'FortiGate 800F',
  '.1.3.6.1.4.1.12356.101.1.800' => 'FortiGate 80C',
  '.1.3.6.1.4.1.12356.1688' => 'FortiMail 2000A',
  '.1.3.6.1.4.1.12356.103.1.1000' => 'FortiManager 100',
  '.1.3.6.1.4.1.12356.103.1.20000' => 'FortiManager 2000XL',
  '.1.3.6.1.4.1.12356.103.1.30000' => 'FortiManager 3000',
  '.1.3.6.1.4.1.12356.103.1.30002' => 'FortiManager 3000B',
  '.1.3.6.1.4.1.12356.103.1.4000' => 'FortiManager 400',
  '.1.3.6.1.4.1.12356.103.1.4001' => 'FortiManager 400A',
  '.1.3.6.1.4.1.12356.106.1.50030' => 'FortiSwitch 5003A',
  '.1.3.6.1.4.1.12356.101.1.510' => 'FortiWiFi 50B',
  '.1.3.6.1.4.1.12356.101.1.610' => 'FortiWiFi 60',
  '.1.3.6.1.4.1.12356.101.1.611' => 'FortiWiFi 60A',
  '.1.3.6.1.4.1.12356.101.1.612' => 'FortiWiFi 60AM',
  '.1.3.6.1.4.1.12356.101.1.613' => 'FortiWiFi 60B'
);

$rewrite_extreme_hardware = array (
  'ags100-24t' => 'AGS 100-24t',
  'ags150-24p' => 'AGS 150-24p',
  'alpine3802' => 'Alpine 3802',
  'alpine3804' => 'Alpine 3804',
  'alpine3808' => 'Alpine 3808',
  'altitude300' => 'Altitude 300',
  'altitude350' => 'Altitude 350',
  'altitude3510' => 'Altitude 3510',
  'altitude3550' => 'Altitude 3550',
  'altitude360' => 'Altitude 360',
  'altitude450' => 'Altitude 450',
  'altitude4610' => 'Altitude 4610',
  'altitude4620' => 'Altitude 4620',
  'altitude4700' => 'Altitude 4700',
  'bd10808' => 'Black Diamond 10808',
  'bd12802' => 'Black Diamond 12802',
  'bd12804' => 'Black Diamond 12804',
  'bd20804' => 'Black Diamond 20804',
  'bd20808' => 'Black Diamond 20808',
  'bd8806' => 'Black Diamond 8806',
  'bd8810' => 'Black Diamond 8810',
  'bdx8' => 'Black Diamond X8',
  'blackDiamond6800' => 'Black Diamond 6800',
  'blackDiamond6804' => 'Black Diamond 6804',
  'blackDiamond6808' => 'Black Diamond 6808',
  'blackDiamond6816' => 'Black Diamond 6816',
  'e4g-200' => 'E4G-200',
  'e4g-400' => 'E4G-400',
  'enetSwitch24Port' => 'EnetSwitch 24Port',
  'nwi-e450a' => 'NWI-e450a',
  'sentriantAG200' => 'Sentriant AG200',
  'sentriantAGSW' => 'Sentriant AGSW',
  'sentriantCE150' => 'Sentriant CE150',
  'sentriantNG300' => 'Sentriant NG300',
  'sentriantPS200v1' => 'Sentriant PS200v1',
  'summit1' => 'Summit 1',
  'summit1iSX' => 'Summit 1iSX',
  'summit1iTX' => 'Summit 1iTX',
  'summit2' => 'Summit 2',
  'summit200-24' => 'Summit 200-24',
  'summit200-24fx' => 'Summit 200-24fx',
  'summit200-48' => 'Summit 200-48',
  'summit24' => 'Summit 24',
  'summit24e2SX' => 'Summit 24e2SX',
  'summit24e2TX' => 'Summit 24e2TX',
  'summit24e3' => 'Summit 24e3',
  'summit3' => 'Summit 3',
  'summit300-24' => 'Summit 300-24',
  'summit300-48' => 'Summit 300-48',
  'summit4' => 'Summit 4',
  'summit400-24p' => 'Summit 400-24p',
  'summit400-24t' => 'Summit 400-24t',
  'summit400-48t' => 'Summit 400-48t',
  'summit48' => 'Summit 48',
  'summit48i' => 'Summit 48i',
  'summit48si' => 'Summit 48si',
  'summit4fx' => 'Summit 4fx',
  'summit5i' => 'Summit 5i',
  'summit5iLX' => 'Summit 5iLX',
  'summit5iTX' => 'Summit 5iTX',
  'summit7iSX' => 'Summit 7iSX',
  'summit7iTX' => 'Summit 7iTX',
  'summitPx1' => 'Summit Px1',
  'summitStack' => 'Summit Stack',
  'summitVer2Stack' => 'Summit Stack V2',
  'summitWM100' => 'Summit WM100',
  'summitWM1000' => 'Summit WM1000',
  'summitWM100Lite' => 'Summit WM100Lite',
  'summitWM20' => 'Summit WM20',
  'summitWM200' => 'Summit WM200',
  'summitWM2000' => 'Summit WM2000',
  'summitWM3400' => 'Summit WM3400',
  'summitWM3600' => 'Summit WM3600',
  'summitWM3700' => 'Summit WM3700',
  'summitX150-24p' => 'Summit X150-24p',
  'summitX150-24t' => 'Summit X150-24t',
  'summitX150-24tDC' => 'Summit X150-24tDC',
  'summitX150-24x' => 'Summit X150-24x',
  'summitX150-24xDC' => 'Summit X150-24xDC',
  'summitX150-48p' => 'Summit X150-48p',
  'summitX150-48t' => 'Summit X150-48t',
  'summitX150-48tDC' => 'Summit X150-48tDC',
  'summitX250-24p' => 'Summit X250-24p',
  'summitX250-24t' => 'Summit X250-24t',
  'summitX250-24tDC' => 'Summit X250-24tDC',
  'summitX250-24x' => 'Summit X250-24x',
  'summitX250-24xDC' => 'Summit X250-24xDC',
  'summitX250-48p' => 'Summit X250-48p',
  'summitX250-48t' => 'Summit X250-48t',
  'summitX250-48tDC' => 'Summit X250-48tDC',
  'summitX350-24t' => 'Summit X350-24t',
  'summitX350-48t' => 'Summit X350-48t',
  'summitX440-24p' => 'Summit X440-24p',
  'summitX440-24p-10G' => 'Summit X440-24p-10G',
  'summitX440-24t' => 'Summit X440-24t',
  'summitX440-24t-10G' => 'Summit X440-24t-10G',
  'summitX440-24tdc' => 'Summit X440-24tdc',
  'summitX440-24x' => 'Summit X440-24x',
  'summitX440-24x-10g' => 'Summit X440-24x-10g',
  'summitX440-48p' => 'Summit X440-48p',
  'summitX440-48p-10G' => 'Summit X440-48p-10G',
  'summitX440-48t' => 'Summit X440-48t',
  'summitX440-48t-10G' => 'Summit X440-48t-10G',
  'summitX440-48tdc' => 'Summit X440-48tdc',
  'summitX440-8p' => 'Summit X440-8p',
  'summitX440-8t' => 'Summit X440-8t',
  'summitX440-L2-24t' => 'Summit X440-L2-24t',
  'summitX440-L2-48t' => 'Summit X440-L2-48t',
  'summitX450-24t' => 'Summit X450-24t',
  'summitX450-24x' => 'Summit X450-24x',
  'summitX450a-24t ' => 'Summit X450a-24t ',
  'summitX450a-24tDC' => 'Summit X450a-24tDC',
  'summitX450a-24x' => 'Summit X450a-24x',
  'summitX450a-24xDC' => 'Summit X450a-24xDC',
  'summitX450a-48t' => 'Summit X450a-48t',
  'summitX450a-48tDC' => 'Summit X450a-48tDC',
  'summitX450e-24p ' => 'Summit X450e-24p ',
  'summitX450e-24t' => 'Summit X450e-24t',
  'summitX450e-48p' => 'Summit X450e-48p',
  'summitX450e-48t' => 'Summit X450e-48t',
  'summitX460-24p' => 'Summit X460-24p',
  'summitX460-24t' => 'Summit X460-24t',
  'summitX460-24x' => 'Summit X460-24x',
  'summitX460-48p' => 'Summit X460-48p',
  'summitX460-48t' => 'Summit X460-48t',
  'summitX460-48x' => 'Summit X460-48x',
  'summitX480-24x' => 'Summit X480-24x',
  'summitX480-24x-10G4X' => 'Summit X480-24x-10G4X',
  'summitX480-24x-40G4X' => 'Summit X480-24x-40G4X',
  'summitX480-24x-SS' => 'Summit X480-24x-SS',
  'summitX480-24x-SS128' => 'Summit X480-24x-SS128',
  'summitX480-24x-SSV80' => 'Summit X480-24x-SSV80',
  'summitX480-48t' => 'Summit X480-48t',
  'summitX480-48t-10G4X' => 'Summit X480-48t-10G4X',
  'summitX480-48t-40G4X' => 'Summit X480-48t-40G4X',
  'summitX480-48t-SS' => 'Summit X480-48t-SS',
  'summitX480-48t-SS128' => 'Summit X480-48t-SS128',
  'summitX480-48t-SSV80' => 'Summit X480-48t-SSV80',
  'summitX480-48x' => 'Summit X480-48x',
  'summitX480-48x-10G4X' => 'Summit X480-48x-10G4X',
  'summitX480-48x-40G4X' => 'Summit X480-48x-40G4X',
  'summitX480-48x-SS' => 'Summit X480-48x-SS',
  'summitX480-48x-SS128' => 'Summit X480-48x-SS128',
  'summitX480-48x-SSV80' => 'Summit X480-48x-SSV80',
  'summitX650-24t' => 'Summit X650-24t',
  'summitX650-24t-10G8X' => 'Summit X650-24t-10G8X',
  'summitX650-24t-40G4X' => 'Summit X650-24t-40G4X',
  'summitX650-24t-SS' => 'Summit X650-24t-SS',
  'summitX650-24t-SS256' => 'Summit X650-24t-SS256',
  'summitX650-24t-SS512' => 'Summit X650-24t-SS512',
  'summitX650-24t-SSns' => 'Summit X650-24t-SSns',
  'summitX650-24x' => 'Summit X650-24x',
  'summitX650-24x-10G8X' => 'Summit X650-24x-10G8X',
  'summitX650-24x-40G4X' => 'Summit X650-24x-40G4X',
  'summitX650-24x-SS' => 'Summit X650-24x-SS',
  'summitX650-24x-SS256' => 'Summit X650-24x-SS256',
  'summitX650-24x-SS512' => 'Summit X650-24x-SS512',
  'summitX650-24x-SSns' => 'Summit X650-24x-SSns',
  'summitX670-48x' => 'Summit X670-48x',
  'summitX670v-48t' => 'Summit X670v-48t',
  'summitX670v-48x' => 'Summit X670v-48x',
);

$rewrite_ironware_hardware = array(
  'snFIWGSwitch' => 'Stackable FastIron workgroup',
  'snFIBBSwitch' => 'Stackable FastIron backbone',
  'snNIRouter' => 'Stackable NetIron',
  'snSI' => 'Stackable ServerIron',
  'snSIXL' => 'Stackable ServerIronXL',
  'snSIXLTCS' => 'Stackable ServerIronXL TCS',
  'snTISwitch' => 'Stackable TurboIron',
  'snTIRouter' => 'Stackable TurboIron',
  'snT8Switch' => 'Stackable TurboIron 8',
  'snT8Router' => 'Stackable TurboIron 8',
  'snT8SIXLG' => 'Stackable ServerIronXLG',
  'snBI4000Switch' => 'BigIron 4000',
  'snBI4000Router' => 'BigIron 4000',
  'snBI4000SI' => 'BigServerIron',
  'snBI8000Switch' => 'BigIron 8000',
  'snBI8000Router' => 'BigIron 8000',
  'snBI8000SI' => 'BigServerIron',
  'snFI2Switch' => 'FastIron II',
  'snFI2Router' => 'FastIron II',
  'snFI2PlusSwitch' => 'FastIron II Plus',
  'snFI2PlusRouter' => 'FastIron II Plus',
  'snNI400Router' => 'NetIron 400',
  'snNI800Router' => 'NetIron 800',
  'snFI2GCSwitch' => 'FastIron II GC',
  'snFI2GCRouter' => 'FastIron II GC',
  'snFI2PlusGCSwitch' => 'FastIron II Plus GC',
  'snFI2PlusGCRouter' => 'FastIron II Plus GC',
  'snBI15000Switch' => 'BigIron 15000',
  'snBI15000Router' => 'BigIron 15000',
  'snNI1500Router' => 'NetIron 1500',
  'snFI3Switch' => 'FastIron III',
  'snFI3Router' => 'FastIron III',
  'snFI3GCSwitch' => 'FastIron III GC',
  'snFI3GCRouter' => 'FastIron III GC',
  'snSI400Switch' => 'ServerIron 400',
  'snSI400Router' => 'ServerIron 400',
  'snSI800Switch' => 'ServerIron800',
  'snSI800Router' => 'ServerIron800',
  'snSI1500Switch' => 'ServerIron1500',
  'snSI1500Router' => 'ServerIron1500',
  'sn4802Switch' => 'Stackable 4802',
  'sn4802Router' => 'Stackable 4802',
  'sn4802SI' => 'Stackable 4802 ServerIron',
  'snFI400Switch' => 'FastIron 400',
  'snFI400Router' => 'FastIron 400',
  'snFI800Switch' => 'FastIron800',
  'snFI800Router' => 'FastIron800',
  'snFI1500Switch' => 'FastIron1500',
  'snFI1500Router' => 'FastIron1500',
  'snFES2402' => 'FES 2402',
  'snFES2402Switch' => 'FES2402',
  'snFES2402Router' => 'FES2402',
  'snFES4802' => 'FES 4802',
  'snFES4802Switch' => 'FES4802',
  'snFES4802Router' => 'FES4802',
  'snFES9604' => 'FES 9604',
  'snFES9604Switch' => 'FES9604',
  'snFES9604Router' => 'FES9604',
  'snFES12GCF' => 'FES 12GCF ',
  'snFES12GCFSwitch' => 'FES12GCF ',
  'snFES12GCFRouter' => 'FES12GCF',
  'snFES2402P' => 'FES 2402 POE ',
  'snFES4802P' => 'FES 4802 POE ',
  'snNI4802Switch' => 'NetIron 4802',
  'snNI4802Router' => 'NetIron 4802',
  'snBIMG8Switch' => 'BigIron MG8',
  'snBIMG8Router' => 'BigIron MG8',
  'snNI40GRouter' => 'NetIron 40G',
  'snFESX424' => 'FES 24G',
  'snFESX424Switch' => 'FESX424',
  'snFESX424Router' => 'FESX424',
  'snFESX424Prem' => 'FES 24G-PREM',
  'snFESX424PremSwitch' => 'FESX424-PREM',
  'snFESX424PremRouter' => 'FESX424-PREM',
  'snFESX424Plus1XG' => 'FES 24G + 1 10G',
  'snFESX424Plus1XGSwitch' => 'FESX424+1XG',
  'snFESX424Plus1XGRouter' => 'FESX424+1XG',
  'snFESX424Plus1XGPrem' => 'FES 24G + 1 10G-PREM',
  'snFESX424Plus1XGPremSwitch' => 'FESX424+1XG-PREM',
  'snFESX424Plus1XGPremRouter' => 'FESX424+1XG-PREM',
  'snFESX424Plus2XG' => 'FES 24G + 2 10G',
  'snFESX424Plus2XGSwitch' => 'FESX424+2XG',
  'snFESX424Plus2XGRouter' => 'FESX424+2XG',
  'snFESX424Plus2XGPrem' => 'FES 24G + 2 10G-PREM',
  'snFESX424Plus2XGPremSwitch' => 'FESX424+2XG-PREM',
  'snFESX424Plus2XGPremRouter' => 'FESX424+2XG-PREM',
  'snFESX448' => 'FES 48G',
  'snFESX448Switch' => 'FESX448',
  'snFESX448Router' => 'FESX448',
  'snFESX448Prem' => 'FES 48G-PREM',
  'snFESX448PremSwitch' => 'FESX448-PREM',
  'snFESX448PremRouter' => 'FESX448-PREM',
  'snFESX448Plus1XG' => 'FES 48G + 1 10G',
  'snFESX448Plus1XGSwitch' => 'FESX448+1XG',
  'snFESX448Plus1XGRouter' => 'FESX448+1XG',
  'snFESX448Plus1XGPrem' => 'FES 48G + 1 10G-PREM',
  'snFESX448Plus1XGPremSwitch' => 'FESX448+1XG-PREM',
  'snFESX448Plus1XGPremRouter' => 'FESX448+1XG-PREM',
  'snFESX448Plus2XG' => 'FES 48G + 2 10G',
  'snFESX448Plus2XGSwitch' => 'FESX448+2XG',
  'snFESX448Plus2XGRouter' => 'FESX448+2XG',
  'snFESX448Plus2XGPrem' => 'FES 48G + 2 10G-PREM',
  'snFESX448Plus2XGPremSwitch' => 'FESX448+2XG-PREM',
  'snFESX448Plus2XGPremRouter' => 'FESX448+2XG-PREM',
  'snFESX424Fiber' => 'FESFiber 24G',
  'snFESX424FiberSwitch' => 'FESX424Fiber',
  'snFESX424FiberRouter' => 'FESX424Fiber',
  'snFESX424FiberPrem' => 'FESFiber 24G-PREM',
  'snFESX424FiberPremSwitch' => 'FESX424Fiber-PREM',
  'snFESX424FiberPremRouter' => 'FESX424Fiber-PREM',
  'snFESX424FiberPlus1XG' => 'FESFiber 24G + 1 10G',
  'snFESX424FiberPlus1XGSwitch' => 'FESX424Fiber+1XG',
  'snFESX424FiberPlus1XGRouter' => 'FESX424Fiber+1XG',
  'snFESX424FiberPlus1XGPrem' => 'FESFiber 24G + 1 10G-PREM',
  'snFESX424FiberPlus1XGPremSwitch' => 'FESX424Fiber+1XG-PREM',
  'snFESX424FiberPlus1XGPremRouter' => 'FESX424Fiber+1XG-PREM',
  'snFESX424FiberPlus2XG' => 'FESFiber 24G + 2 10G',
  'snFESX424FiberPlus2XGSwitch' => 'FESX424Fiber+2XG',
  'snFESX424FiberPlus2XGRouter' => 'FESX424Fiber+2XG',
  'snFESX424FiberPlus2XGPrem' => 'FESFiber 24G + 2 10G-PREM',
  'snFESX424FiberPlus2XGPremSwitch' => 'FESX424Fiber+2XG-PREM',
  'snFESX424FiberPlus2XGPremRouter' => 'FESX424Fiber+2XG-PREM',
  'snFESX448Fiber' => 'FESFiber 48G',
  'snFESX448FiberSwitch' => 'FESX448Fiber',
  'snFESX448FiberRouter' => 'FESX448Fiber',
  'snFESX448FiberPrem' => 'FESFiber 48G-PREM',
  'snFESX448FiberPremSwitch' => 'FESX448Fiber-PREM',
  'snFESX448FiberPremRouter' => 'FESX448Fiber-PREM',
  'snFESX448FiberPlus1XG' => 'FESFiber 48G + 1 10G',
  'snFESX448FiberPlus1XGSwitch' => 'FESX448Fiber+1XG',
  'snFESX448FiberPlus1XGRouter' => 'FESX448Fiber+1XG',
  'snFESX448FiberPlus1XGPrem' => 'FESFiber 48G + 1 10G-PREM',
  'snFESX448FiberPlus1XGPremSwitch' => 'FESX448Fiber+1XG-PREM',
  'snFESX448FiberPlus1XGPremRouter' => 'FESX448Fiber+1XG-PREM',
  'snFESX448FiberPlus2XG' => 'FESFiber 48G + 2 10G',
  'snFESX448FiberPlus2XGSwitch' => 'FESX448Fiber+2XG',
  'snFESX448FiberPlus2XGRouter' => 'FESX448+2XG',
  'snFESX448FiberPlus2XGPrem' => 'FESFiber 48G + 2 10G-PREM',
  'snFESX448FiberPlus2XGPremSwitch' => 'FESX448Fiber+2XG-PREM',
  'snFESX448FiberPlus2XGPremRouter' => 'FESX448Fiber+2XG-PREM',
  'snFESX424P' => 'FES 24G POE',
  'snFESX424P' => 'FESX424POE',
  'snFESX424P' => 'FESX424POE',
  'snFESX424P' => 'FES 24GPOE-PREM',
  'snFESX424P' => 'FESX424POE-PREM',
  'snFESX424P' => 'FESX424POE-PREM',
  'snFESX424P' => 'FES 24GPOE + 1 10G',
  'snFESX424P' => 'FESX424POE+1XG',
  'snFESX424P' => 'FESX424POE+1XG',
  'snFESX424P' => 'FES 24GPOE + 1 10G-PREM',
  'snFESX424P' => 'FESX424POE+1XG-PREM',
  'snFESX424P' => 'FESX424POE+1XG-PREM',
  'snFESX424P' => 'FES 24GPOE + 2 10G',
  'snFESX424P' => 'FESX424POE+2XG',
  'snFESX424P' => 'FESX424POE+2XG',
  'snFESX424P' => 'FES 24GPOE + 2 10G-PREM',
  'snFESX424P' => 'FESX424POE+2XG-PREM',
  'snFESX424P' => 'FESX424POE+2XG-PREM',
  'snFESX624' => 'FastIron Edge V6 Switch(FES) 24G',
  'snFESX624Switch' => 'FESX624',
  'snFESX624Router' => 'FESX624',
  'snFESX624Prem' => 'FastIron Edge V6 Switch(FES) 24G-PREM',
  'snFESX624PremSwitch' => 'FESX624-PREM',
  'snFESX624PremRouter' => 'FESX624-PREM',
  'snFESX624Plus1XG' => 'FastIron Edge V6 Switch(FES) 24G + 1 10G',
  'snFESX624Plus1XGSwitch' => 'FESX624+1XG',
  'snFESX624Plus1XGRouter' => 'FESX624+1XG',
  'snFESX624Plus1XGPrem' => 'FastIron Edge V6 Switch(FES) 24G + 1 10G-PREM',
  'snFESX624Plus1XGPremSwitch' => 'FESX624+1XG-PREM',
  'snFESX624Plus1XGPremRouter' => 'FESX624+1XG-PREM',
  'snFESX624Plus2XG' => 'FastIron Edge V6 Switch(FES) 24G + 2 10G',
  'snFESX624Plus2XGSwitch' => 'FESX624+2XG',
  'snFESX624Plus2XGRouter' => 'FESX624+2XG',
  'snFESX624Plus2XGPrem' => 'FastIron Edge V6 Switch(FES) 24G + 2 10G-PREM',
  'snFESX624Plus2XGPremSwitch' => 'FESX624+2XG-PREM',
  'snFESX624Plus2XGPremRouter' => 'FESX624+2XG-PREM',
  'snFESX648' => 'FastIron Edge V6 Switch(FES) 48G',
  'snFESX648Switch' => 'FESX648',
  'snFESX648Router' => 'FESX648',
  'snFESX648Prem' => 'FastIron Edge V6 Switch(FES) 48G-PREM',
  'snFESX648PremSwitch' => 'FESX648-PREM',
  'snFESX648PremRouter' => 'FESX648-PREM',
  'snFESX648Plus1XG' => 'FastIron Edge V6 Switch(FES) 48G + 1 10G',
  'snFESX648Plus1XGSwitch' => 'FESX648+1XG',
  'snFESX648Plus1XGRouter' => 'FESX648+1XG',
  'snFESX648Plus1XGPrem' => 'FastIron Edge V6 Switch(FES) 48G + 1 10G-PREM',
  'snFESX648Plus1XGPremSwitch' => 'FESX648+1XG-PREM',
  'snFESX648Plus1XGPremRouter' => 'FESX648+1XG-PREM',
  'snFESX648Plus2XG' => 'FastIron Edge V6 Switch(FES) 48G + 2 10G',
  'snFESX648Plus2XGSwitch' => 'FESX648+2XG',
  'snFESX648Plus2XGRouter' => 'FESX648+2XG',
  'snFESX648Plus2XGPrem' => 'FastIron Edge V6 Switch(FES) 48G + 2 10G-PREM',
  'snFESX648Plus2XGPremSwitch' => 'FESX648+2XG-PREM',
  'snFESX648Plus2XGPremRouter' => 'FESX648+2XG-PREM',
  'snFESX624Fiber' => 'FastIron V6 Edge Switch(FES)Fiber 24G',
  'snFESX624FiberSwitch' => 'FESX624Fiber',
  'snFESX624FiberRouter' => 'FESX624Fiber',
  'snFESX624FiberPrem' => 'FastIron Edge V6 Switch(FES)Fiber 24G-PREM',
  'snFESX624FiberPremSwitch' => 'FESX624Fiber-PREM',
  'snFESX624FiberPremRouter' => 'FESX624Fiber-PREM',
  'snFESX624FiberPlus1XG' => 'FastIron Edge V6 Switch(FES)Fiber 24G + 1 10G',
  'snFESX624FiberPlus1XGSwitch' => 'FESX624Fiber+1XG',
  'snFESX624FiberPlus1XGRouter' => 'FESX624Fiber+1XG',
  'snFESX624FiberPlus1XGPrem' => 'FastIron Edge V6 Switch(FES)Fiber 24G + 1 10G-PREM',
  'snFESX624FiberPlus1XGPremSwitch' => 'FESX624Fiber+1XG-PREM',
  'snFESX624FiberPlus1XGPremRouter' => 'FESX624Fiber+1XG-PREM',
  'snFESX624FiberPlus2XG' => 'FastIron Edge V6 Switch(FES)Fiber 24G + 2 10G',
  'snFESX624FiberPlus2XGSwitch' => 'FESX624Fiber+2XG',
  'snFESX624FiberPlus2XGRouter' => 'FESX624Fiber+2XG',
  'snFESX624FiberPlus2XGPrem' => 'FastIron Edge V6 Switch(FES)Fiber 24G + 2 10G-PREM',
  'snFESX624FiberPlus2XGPremSwitch' => 'FESX624Fiber+2XG-PREM',
  'snFESX624FiberPlus2XGPremRouter' => 'FESX624Fiber+2XG-PREM',
  'snFESX648Fiber' => 'FastIron Edge V6 Switch(FES)Fiber 48G',
  'snFESX648FiberSwitch' => 'FESX648Fiber',
  'snFESX648FiberRouter' => 'FESX648Fiber',
  'snFESX648FiberPrem' => 'FastIron Edge V6 Switch(FES)Fiber 48G-PREM',
  'snFESX648FiberPremSwitch' => 'FESX648Fiber-PREM',
  'snFESX648FiberPremRouter' => 'FESX648Fiber-PREM',
  'snFESX648FiberPlus1XG' => 'FastIron Edge V6 Switch(FES)Fiber 48G + 1 10G',
  'snFESX648FiberPlus1XGSwitch' => 'FESX648Fiber+1XG',
  'snFESX648FiberPlus1XGRouter' => 'FESX648Fiber+1XG',
  'snFESX648FiberPlus1XGPrem' => 'FastIron Edge V6 Switch(FES)Fiber 48G + 1 10G-PREM',
  'snFESX648FiberPlus1XGPremSwitch' => 'FESX648Fiber+1XG-PREM',
  'snFESX648FiberPlus1XGPremRouter' => 'FESX648Fiber+1XG-PREM',
  'snFESX648FiberPlus2XG' => 'FastIron Edge V6 Switch(FES)Fiber 48G + 2 10G',
  'snFESX648FiberPlus2XGSwitch' => 'FESX648Fiber+2XG',
  'snFESX648FiberPlus2XGRouter' => 'FESX648+2XG',
  'snFESX648FiberPlus2XGPrem' => 'FastIron Edge V6 Switch(FES)Fiber 48G + 2 10G-PREM',
  'snFESX648FiberPlus2XGPremSwitch' => 'FESX648Fiber+2XG-PREM',
  'snFESX648FiberPlus2XGPremRouter' => 'FESX648Fiber+2XG-PREM',
  'snFESX624P' => 'FastIron Edge V6 Switch(FES) 24G POE',
  'snFESX624P' => 'FESX624POE',
  'snFESX624P' => 'FESX624POE',
  'snFESX624P' => 'FastIron Edge V6 Switch(FES) 24GPOE-PREM',
  'snFESX624P' => 'FESX624POE-PREM',
  'snFESX624P' => 'FESX624POE-PREM',
  'snFESX624P' => 'FastIron Edge V6 Switch(FES) 24GPOE + 1 10G',
  'snFESX624P' => 'FESX624POE+1XG',
  'snFESX624P' => 'FESX624POE+1XG',
  'snFESX624P' => 'FastIron Edge V6 Switch(FES) 24GPOE + 1 10G-PREM',
  'snFESX624P' => 'FESX624POE+1XG-PREM',
  'snFESX624P' => 'FESX624POE+1XG-PREM',
  'snFESX624P' => 'FastIron Edge V6 Switch(FES) 24GPOE + 2 10G',
  'snFESX624P' => 'FESX624POE+2XG',
  'snFESX624P' => 'FESX624POE+2XG',
  'snFESX624P' => 'FastIron Edge V6 Switch(FES) 24GPOE + 2 10G-PREM',
  'snFESX624P' => 'FESX624POE+2XG-PREM',
  'snFESX624P' => 'FESX624POE+2XG-PREM',
  'snFWSX424' => 'FWSX24G',
  'snFWSX424Switch' => 'FWSX424',
  'snFWSX424Router' => 'FWSX424',
  'snFWSX424Plus1XG' => 'FWSX24G + 1 10G',
  'snFWSX424Plus1XGSwitch' => 'FWSX424+1XG',
  'snFWSX424Plus1XGRouter' => 'FWSX424+1XG',
  'snFWSX424Plus2XG' => 'FWSX24G + 2 10G',
  'snFWSX424Plus2XGSwitch' => 'FWSX424+2XG',
  'snFWSX424Plus2XGRouter' => 'FWSX424+2XG',
  'snFWSX448' => 'FWSX48G',
  'snFWSX448Switch' => 'FWSX448',
  'snFWSX448Router' => 'FWSX448',
  'snFWSX448Plus1XG' => 'FWSX48G + 1 10G',
  'snFWSX448Plus1XGSwitch' => 'FWSX448+1XG',
  'snFWSX448Plus1XGRouter' => 'FWSX448+1XG',
  'snFWSX448Plus2XG' => 'FWSX448G+2XG',
  'snFWSX448Plus2XGSwitch' => 'FWSX448+2XG',
  'snFWSX448Plus2XGRouter' => 'FWSX448+2XG',
  'snFastIronSuperXFamily' => 'FastIron SuperX Family',
  'snFastIronSuperX' => 'FastIron SuperX',
  'snFastIronSuperXSwitch' => 'FastIron SuperX Switch',
  'snFastIronSuperXRouter' => 'FastIron SuperX Router',
  'snFastIronSuperXBaseL3Switch' => 'FastIron SuperX Base L3 Switch',
  'snFastIronSuperXPrem' => 'FastIron SuperX Premium',
  'snFastIronSuperXPremSwitch' => 'FastIron SuperX Premium Switch',
  'snFastIronSuperXPremRouter' => 'FastIron SuperX Premium Router',
  'snFastIronSuperXPremBaseL3Switch' => 'FastIron SuperX Premium Base L3 Switch',
  'snFastIronSuperX800' => 'FastIron SuperX 800 ',
  'snFastIronSuperX800Switch' => 'FastIron SuperX 800 Switch',
  'snFastIronSuperX800Router' => 'FastIron SuperX 800 Router',
  'snFastIronSuperX800BaseL3Switch' => 'FastIron SuperX 800 Base L3 Switch',
  'snFastIronSuperX800Prem' => 'FastIron SuperX 800 Premium',
  'snFastIronSuperX800PremSwitch' => 'FastIron SuperX 800 Premium Switch',
  'snFastIronSuperX800PremRouter' => 'FastIron SuperX 800 Premium Router',
  'snFastIronSuperX800PremBaseL3Switch' => 'FastIron SuperX 800 Premium Base L3 Switch',
  'snFastIronSuperX1600' => 'FastIron SuperX 1600 ',
  'snFastIronSuperX1600Switch' => 'FastIron SuperX 1600 Switch',
  'snFastIronSuperX1600Router' => 'FastIron SuperX 1600 Router',
  'snFastIronSuperX1600BaseL3Switch' => 'FastIron SuperX 1600 Base L3 Switch',
  'snFastIronSuperX1600Prem' => 'FastIron SuperX 1600 Premium',
  'snFastIronSuperX1600PremSwitch' => 'FastIron SuperX 1600 Premium Switch',
  'snFastIronSuperX1600PremRouter' => 'FastIron SuperX 1600 Premium Router',
  'snFastIronSuperX1600PremBaseL3Switch' => 'FastIron SuperX 1600 Premium Base L3 Switch',
  'snFastIronSuperXV6' => 'FastIron SuperX V6 ',
  'snFastIronSuperXV6Switch' => 'FastIron SuperX V6 Switch',
  'snFastIronSuperXV6Router' => 'FastIron SuperX V6 Router',
  'snFastIronSuperXV6BaseL3Switch' => 'FastIron SuperX V6 Base L3 Switch',
  'snFastIronSuperXV6Prem' => 'FastIron SuperX V6 Premium',
  'snFastIronSuperXV6PremSwitch' => 'FastIron SuperX V6 Premium Switch',
  'snFastIronSuperXV6PremRouter' => 'FastIron SuperX V6 Premium Router',
  'snFastIronSuperXV6PremBaseL3Switch' => 'FastIron SuperX V6 Premium Base L3 Switch',
  'snFastIronSuperX800V6' => 'FastIron SuperX 800 V6 ',
  'snFastIronSuperX800V6Switch' => 'FastIron SuperX 800 V6 Switch',
  'snFastIronSuperX800V6Router' => 'FastIron SuperX 800 V6 Router',
  'snFastIronSuperX800V6BaseL3Switch' => 'FastIron SuperX 800 V6 Base L3 Switch',
  'snFastIronSuperX800V6Prem' => 'FastIron SuperX 800 V6 Premium',
  'snFastIronSuperX800V6PremSwitch' => 'FastIron SuperX 800 Premium V6 Switch',
  'snFastIronSuperX800V6PremRouter' => 'FastIron SuperX 800 Premium V6 Router',
  'snFastIronSuperX800V6PremBaseL3Switch' => 'FastIron SuperX 800 Premium V6 Base L3 Switch',
  'snFastIronSuperX1600V6' => 'FastIron SuperX 1600 V6 ',
  'snFastIronSuperX1600V6Switch' => 'FastIron SuperX 1600 V6 Switch',
  'snFastIronSuperX1600V6Router' => 'FastIron SuperX 1600 V6 Router',
  'snFastIronSuperX1600V6BaseL3Switch' => 'FastIron SuperX 1600 V6 Base L3 Switch',
  'snFastIronSuperX1600V6Prem' => 'FastIron SuperX 1600 Premium V6',
  'snFastIronSuperX1600V6PremSwitch' => 'FastIron SuperX 1600 Premium V6 Switch',
  'snFastIronSuperX1600V6PremRouter' => 'FastIron SuperX 1600 Premium V6 Router',
  'snFastIronSuperX1600V6PremBaseL3Switch' => 'FastIron SuperX 1600 Premium V6 Base L3 Switch',
  'snBigIronSuperXFamily' => 'BigIron SuperX Family',
  'snBigIronSuperX' => 'BigIron SuperX',
  'snBigIronSuperXSwitch' => 'BigIron SuperX Switch',
  'snBigIronSuperXRouter' => 'BigIron SuperX Router',
  'snBigIronSuperXBaseL3Switch' => 'BigIron SuperX Base L3 Switch',
  'snTurboIronSuperXFamily' => 'TurboIron SuperX Family',
  'snTurboIronSuperX' => 'TurboIron SuperX',
  'snTurboIronSuperXSwitch' => 'TurboIron SuperX Switch',
  'snTurboIronSuperXRouter' => 'TurboIron SuperX Router',
  'snTurboIronSuperXBaseL3Switch' => 'TurboIron SuperX Base L3 Switch',
  'snTurboIronSuperXPrem' => 'TurboIron SuperX Premium',
  'snTurboIronSuperXPremSwitch' => 'TurboIron SuperX Premium Switch',
  'snTurboIronSuperXPremRouter' => 'TurboIron SuperX Premium Router',
  'snTurboIronSuperXPremBaseL3Switch' => 'TurboIron SuperX Premium Base L3 Switch',
  'snNIIMRRouter' => 'NetIron IMR',
  'snBIRX16Switch' => 'BigIron RX16',
  'snBIRX16Router' => 'BigIron RX16',
  'snBIRX8Switch' => 'BigIron RX8',
  'snBIRX8Router' => 'BigIron RX8',
  'snBIRX4Switch' => 'BigIron RX4',
  'snBIRX4Router' => 'BigIron RX4',
  'snBIRX32Switch' => 'BigIron RX32',
  'snBIRX32Router' => 'BigIron RX32',
  'snNIXMR16000Router' => 'NetIron XMR16000',
  'snNIXMR8000Router' => 'NetIron XMR8000',
  'snNIXMR4000Router' => 'NetIron XMR4000',
  'snNIXMR32000Router' => 'NetIron XMR32000',
  'snSecureIronLS100' => 'SecureIronLS 100',
  'snSecureIronLS100Switch' => 'SecureIronLS 100 Switch',
  'snSecureIronLS100Router' => 'SecureIronLS 100 Router',
  'snSecureIronLS300' => 'SecureIronLS 300',
  'snSecureIronLS300Switch' => 'SecureIronLS 300 Switch',
  'snSecureIronLS300Router' => 'SecureIronLS 300 Router',
  'snSecureIronTM100' => 'SecureIronTM 100',
  'snSecureIronTM100Switch' => 'SecureIronTM 100 Switch',
  'snSecureIronTM100Router' => 'SecureIronTM 100 Router',
  'snSecureIronTM300' => 'SecureIronTM 300',
  'snSecureIronTM300Switch' => 'SecureIronTM 300 Switch',
  'snSecureIronTM300Router' => 'SecureIronTM 300 Router',
  'snNetIronMLX16Router' => 'NetIron MLX-16',
  'snNetIronMLX8Router' => 'NetIron MLX-8',
  'snNetIronMLX4Router' => 'NetIron MLX-4',
  'snNetIronMLX32Router' => 'NetIron MLX-32',
  'snFGS624P' => 'FastIron FGS624P',
  'snFGS624PSwitch' => 'FGS624P',
  'snFGS624PRouter' => 'FGS624P',
  'snFGS624XGP' => 'FastIron FGS624XGP',
  'snFGS624XGPSwitch' => 'FGS624XGP',
  'snFGS624XGPRouter' => 'FGS624XGP',
  'snFGS624PP' => 'FastIron FGS624XGP',
  'snFGS624XGPP' => 'FGS624XGP-POE',
  'snFGS648P' => 'FastIron GS FGS648P',
  'snFGS648PSwitch' => 'FastIron FGS648P',
  'snFGS648PRouter' => 'FastIron FGS648P',
  'snFGS648PP' => 'FastIron FGS648P-POE',
  'snFLS624' => 'FastIron FLS624',
  'snFLS624Switch' => 'FastIron FLS624',
  'snFLS624Router' => 'FastIron FLS624',
  'snFLS648' => 'FastIron FLS648',
  'snFLS648Switch' => 'FastIron FLS648',
  'snFLS648Router' => 'FastIron FLS648',
  'snSI100' => 'ServerIron SI100',
  'snSI100Switch' => 'ServerIron SI100',
  'snSI100Router' => 'ServerIron SI100',
  'snSI350' => 'ServerIron 350 series',
  'snSI350Switch' => 'SI350',
  'snSI350Router' => 'SI350',
  'snSI450' => 'ServerIron 450 series',
  'snSI450Switch' => 'SI450',
  'snSI450Router' => 'SI450',
  'snSI850' => 'ServerIron 850 series',
  'snSI850Switch' => 'SI850',
  'snSI850Router' => 'SI850',
  'snSI350Plus' => 'ServerIron 350 Plus series',
  'snSI350PlusSwitch' => 'SI350 Plus',
  'snSI350PlusRouter' => 'SI350 Plus',
  'snSI450Plus' => 'ServerIron 450 Plus series',
  'snSI450PlusSwitch' => 'SI450 Plus',
  'snSI450PlusRouter' => 'SI450 Plus',
  'snSI850Plus' => 'ServerIron 850 Plus series',
  'snSI850PlusSwitch' => 'SI850 Plus',
  'snSI850PlusRouter' => 'SI850 Plus',
  'snServerIronGTc' => 'ServerIronGT C series',
  'snServerIronGTcSwitch' => 'ServerIronGT C',
  'snServerIronGTcRouter' => 'ServerIronGT C',
  'snServerIronGTe' => 'ServerIronGT E series',
  'snServerIronGTeSwitch' => 'ServerIronGT E',
  'snServerIronGTeRouter' => 'ServerIronGT E',
  'snServerIronGTePlus' => 'ServerIronGT E Plus series',
  'snServerIronGTePlusSwitch' => 'ServerIronGT E Plus',
  'snServerIronGTePlusRouter' => 'ServerIronGT E Plus',
  'snServerIron4G' => 'ServerIron4G series',
  'snServerIron4GSwitch' => 'ServerIron4G',
  'snServerIron4GRouter' => 'ServerIron4G',
  'wirelessAp' => 'wireless access point',
  'wirelessProbe' => 'wireless probe',
  'ironPointMobility' => 'IronPoint Mobility Series',
  'ironPointMC' => 'IronPoint Mobility Controller',
  'dcrs7504Switch' => 'DCRS-7504',
  'dcrs7504Router' => 'DCRS-7504',
  'dcrs7508Switch' => 'DCRS-7508',
  'dcrs7508Router' => 'DCRS-7508',
  'dcrs7515Switch' => 'DCRS-7515',
  'dcrs7515Router' => 'DCRS-7515',
  'snCes2024F' => 'NetIron CES 2024F',
  'snCes2024C' => 'NetIron CES 2024C',
  'snCes2048F' => 'NetIron CES 2048F',
  'snCes2048C' => 'NetIron CES 2048C',
  'snCes2048FX' => 'NetIron CES 2048F + 2x10G',
  'snCes2048CX' => 'NetIron CES 2048C + 2x10G',
  'snCer2024F' => 'NetIron CER 2024F',
  'snCer2024C' => 'NetIron CER 2024C',
  'snCer2048F' => 'NetIron CER 2048F',
  'snCer2048C' => 'NetIron CER 2048C',
  'snCer2048FX' => 'NetIron CER 2048F + 2x10G',
  'snCer2048CX' => 'NetIron CER 2048C + 2x10G',
  'snTI2X24Family' => 'TurboIron 24X',
  'snTI2X24Switch' => 'TurboIron 24X switch',
  'snTI2X24Router' => 'TurboIron 24X router',
  'snTI2X48Family' => 'TurboIron 48X',
  'snTI2X48Switch' => 'TurboIron 48X switch',
  'snTI2X48Router' => 'TurboIron 48X router',
  'snFCX648SSwitch' => 'FCX648S switch',
  'snFCX648SBaseL3Router' => 'FCX648S Base L3 router',
  'snFCX648SRouter' => 'FCX648S Premium Router',
  'snFCX648SAdvRouter' => 'FCX648S Advanced Premium Router (BGP)',
  'snFCX648SHPOE' => 'FastIron CX Switch(FCX-S) 48-port 10/100/1000',
  'snFCX648SHPOESwitch' => 'FCX648S-HPOE switch',
  'snFCX648SHPOEBaseL3Router' => 'FCX648S-HPOE Base L3 router',
  'snFCX648SHPOERouter' => 'FCX648S-HPOE Premium Router',
  'snFCX648SHPOEAdvRouter' => 'FCX648S-HPOE Advanced Premium Router (BGP)',
  'snFCX648' => 'FastIron CX Switch(FCX) 48-port 10/100/1000',
  'snFCX648Switch' => 'FCX648 switch',
  'snFCX648BaseL3Router' => 'FCX648 Base L3 router',
  'snFCX648Router' => 'FCX648 Premium Router',
  'snFCX648AdvRouter' => 'FCX648 Advanced Premium Router (BGP)',
  'snFWS624POE' => 'FastIron WS Switch(FWS) 24-port 10/100 POE',
  'snFWS624POESwitch' => 'FWS624-POE switch',
  'snFWS624POEBaseL3Router' => 'FWS624-POE Base L3 router',
  'snFWS624POEEdgePremRouter' => 'FWS624-POE Edge Prem router',
  'snFWS624GPOE' => 'FastIron WS Switch(FWS) 24-port 10/100/1000 POE',
  'snFWS624GPOESwitch' => 'FWS624G-POE switch',
  'snFWS624GPOEBaseL3Router' => 'FWS624G-POE Base L3 router',
  'snFWS624GPOEEdgePremRouter' => 'FWS624G-POE Edge Prem router',
  'snFWS648POE' => 'FastIron WS Switch(FWS) 48-port 10/100 POE',
  'snFWS648POESwitch' => 'FWS648-POE switch',
  'snFWS648POEBaseL3Router' => 'FWS648-POE Base L3 router',
  'snFWS648POEEdgePremRouter' => 'FWS648-POE Edge Prem router',
  'snFWS648GPOE' => 'FastIron WS Switch(FWS) 48-port 10/100/1000 POE',
  'snFWS648GPOESwitch' => 'FWS648G-POE switch',
  'snFWS648GPOEBaseL3Router' => 'FWS648G-POE Base L3 router',
  'snFWS648GPOEEdgePremRouter' => 'FWS648G-POE Edge Prem router',
  'snFWS624' => 'FastIron WS Switch(FWS) 24-port 10/100',
  'snFWS624Switch' => 'FWS624 switch',
  'snFWS624BaseL3Router' => 'FWS624 Base L3 router',
  'snFWS624EdgePremRouter' => 'FWS624 Edge Prem router',
  'snFWS624G' => 'FastIron WS Switch(FWS) 24-port 10/100/1000',
  'snFWS624GSwitch' => 'FWS624G switch',
  'snFWS624GBaseL3Router' => 'FWS624G Base L3 router',
  'snFWS624GEdgePremRouter' => 'FWS624G Edge Prem router',
  'snFWS648' => 'FastIron WS Switch(FWS) 48-port 10/100 POE Ready',
  'snFWS648Switch' => 'FWS648 switch',
  'snFWS648BaseL3Router' => 'FWS648 Base L3 router',
  'snFWS648EdgePremRouter' => 'FWS648 Edge Prem router',
  'snFWS648G' => 'FastIron WS Switch(FWS) 48-port 10/100/1000 POE Ready',
  'snFWS648GSwitch' => 'FWS648G switch',
  'snFWS648GBaseL3Router' => 'FWS648G Base L3 router',
  'snFWS648GEdgePremRouter' => 'FWS648G Edge Prem router',
  'snFastIronStackFCXBaseL3Router' => 'FCX Base L3 router',
  'snFastIronStackFCXRouter' => 'FCX Premium Router',
  'snFastIronStackFCXAdvRouter' => 'FCX Advanced Premium Router (BGP)',
  'snFastIronStackICX6610Switch' => 'ICX6610 switch',
  'snFastIronStackICX6610BaseL3Router' => 'ICX6610 Base L3 router',
  'snFastIronStackICX6610Router' => 'ICX6610 Base Router',
  'snFastIronStackICX6610PRouter' => 'ICX6610 Premium Router',
  'snFastIronStackICX6610ARouter' => 'ICX6610 Advanced Router',
  'snFastIronStackICX6430Switch' => 'ICX6430 switch',
  'snFastIronStackICX6450Switch' => 'ICX6450 switch',
  'snFastIronStackICX6450BaseL3Router' => 'ICX6450 Base L3 router',
  'snFastIronStackICX6450Router' => 'ICX6450 Router',
  'snFastIronStackICX6450PRouter' => 'ICX6450 Premium Router',
  'snBrocadeMLXe16' => 'Brocade MLXe16',
  'snBrocadeMLXe16Router' => 'Brocade MLXe16',
  'snBrocadeMLXe8' => 'Brocade MLXe8',
  'snBrocadeMLXe8Router' => 'Brocade MLXe8',
  'snBrocadeMLXe4' => 'Brocade MLXe4',
  'snBrocadeMLXe4Router' => 'Brocade MLXe4',
  'snBrocadeMLXe32' => 'Brocade MLXe32',
  'snBrocadeMLXe32Router' => 'Brocade MLXe32',
  'snFastIronStackFCXSwitch' => 'FCX switch',
  'snFastIronStackFCXBaseL3Router' => 'FCX Base L3 router',
  'snFastIronStackFCXRouter' => 'FCX Premium Router',
  'snFastIronStackFCXAdvRouter' => 'FCX Advanced Premium Router (BGP)',
);

// rewrite oids used for snmp_translate()
$rewrite_oids = array(
  // JunOS/JunOSe BGP4 V2
  'BGP4-V2-MIB-JUNIPER' => array(
    'jnxBgpM2PeerTable'                 => '.1.3.6.1.4.1.2636.5.1.1.2.1.1',
    'jnxBgpM2PeerState'                 => '.1.3.6.1.4.1.2636.5.1.1.2.1.1.1.2',
    'jnxBgpM2PeerStatus'                => '.1.3.6.1.4.1.2636.5.1.1.2.1.1.1.3',
    'jnxBgpM2PeerInUpdates'             => '.1.3.6.1.4.1.2636.5.1.1.2.6.1.1.1',
    'jnxBgpM2PeerOutUpdates'            => '.1.3.6.1.4.1.2636.5.1.1.2.6.1.1.2',
    'jnxBgpM2PeerInTotalMessages'       => '.1.3.6.1.4.1.2636.5.1.1.2.6.1.1.3',
    'jnxBgpM2PeerOutTotalMessages'      => '.1.3.6.1.4.1.2636.5.1.1.2.6.1.1.4',
    'jnxBgpM2PeerFsmEstablishedTime'    => '.1.3.6.1.4.1.2636.5.1.1.2.4.1.1.1',
    'jnxBgpM2PeerInUpdatesElapsedTime'  => '.1.3.6.1.4.1.2636.5.1.1.2.4.1.1.2',
    'jnxBgpM2PeerLocalAddr'             => '.1.3.6.1.4.1.2636.5.1.1.2.1.1.1.7',
    'jnxBgpM2PeerIdentifier'            => '.1.3.6.1.4.1.2636.5.1.1.2.1.1.1.1',
    'jnxBgpM2PeerRemoteAs'              => '.1.3.6.1.4.1.2636.5.1.1.2.1.1.1.13',
    'jnxBgpM2PeerRemoteAddr'            => '.1.3.6.1.4.1.2636.5.1.1.2.1.1.1.11',
    'jnxBgpM2PeerRemoteAddrType'        => '.1.3.6.1.4.1.2636.5.1.1.2.1.1.1.10',
    'jnxBgpM2PeerIndex'                 => '.1.3.6.1.4.1.2636.5.1.1.2.1.1.1.14',
    'jnxBgpM2PrefixInPrefixesAccepted'  => '.1.3.6.1.4.1.2636.5.1.1.2.6.2.1.8',
    'jnxBgpM2PrefixInPrefixesRejected'  => '.1.3.6.1.4.1.2636.5.1.1.2.6.2.1.9',
    'jnxBgpM2PrefixOutPrefixes'         => '.1.3.6.1.4.1.2636.5.1.1.2.6.2.1.10',
    'jnxBgpM2PrefixCountersSafi'        => '.1.3.6.1.4.1.2636.5.1.1.2.6.2.1.2',
    'jnxBgpM2CfgPeerAdminStatus'        => '.1.3.6.1.4.1.2636.5.1.1.2.8.1.1.1',
  ),
  // Force10 BGP4 V2
  'FORCE10-BGP4-V2-MIB' => array(
    'f10BgpM2PeerTable'                 => '.1.3.6.1.4.1.6027.20.1.2.1.1',
    'f10BgpM2PeerState'                 => '.1.3.6.1.4.1.6027.20.1.2.1.1.1.3',
    'f10BgpM2PeerStatus'                => '.1.3.6.1.4.1.6027.20.1.2.1.1.1.4',
    'f10BgpM2PeerInUpdates'             => '.1.3.6.1.4.1.6027.20.1.2.6.1.1.1',
    'f10BgpM2PeerOutUpdates'            => '.1.3.6.1.4.1.6027.20.1.2.6.1.1.2',
    'f10BgpM2PeerInTotalMessages'       => '.1.3.6.1.4.1.6027.20.1.2.6.1.1.3',
    'f10BgpM2PeerOutTotalMessages'      => '.1.3.6.1.4.1.6027.20.1.2.6.1.1.4',
    'f10BgpM2PeerFsmEstablishedTime'    => '.1.3.6.1.4.1.6027.20.1.2.3.1.1.1',
    'f10BgpM2PeerInUpdatesElapsedTime'  => '.1.3.6.1.4.1.6027.20.1.2.3.1.1.2',
    'f10BgpM2PeerLocalAddr'             => '.1.3.6.1.4.1.6027.20.1.2.1.1.1.8',
    'f10BgpM2PeerIdentifier'            => '.1.3.6.1.4.1.6027.20.1.2.1.1.1.2',
    'f10BgpM2PeerRemoteAs'              => '.1.3.6.1.4.1.6027.20.1.2.1.1.1.14',
    'f10BgpM2PeerRemoteAddr'            => '.1.3.6.1.4.1.6027.20.1.2.1.1.1.12',
    'f10BgpM2PeerRemoteAddrType'        => '.1.3.6.1.4.1.6027.20.1.2.1.1.1.11',
    'f10BgpM2PeerIndex'                 => '.1.3.6.1.4.1.6027.20.1.2.1.1.1.15',
    'f10BgpM2PrefixInPrefixesAccepted'  => '.1.3.6.1.4.1.6027.20.1.2.6.2.1.8',
    'f10BgpM2PrefixInPrefixesRejected'  => '.1.3.6.1.4.1.6027.20.1.2.6.2.1.9',
    'f10BgpM2PrefixOutPrefixes'         => '.1.3.6.1.4.1.6027.20.1.2.6.2.1.10',
    'f10BgpM2PrefixCountersSafi'        => '.1.3.6.1.4.1.6027.20.1.2.6.2.1.2',
    'f10BgpM2CfgPeerAdminStatus'        => '.1.3.6.1.4.1.6027.20.1.2.8.1.1.1'
  )
);

$rewrite_ios_features = array(
  'PK9S' => 'IP w/SSH LAN Only',
  'LANBASEK9' => 'Lan Base Crypto',
  'LANBASE' => 'Lan Base',
  'ADVENTERPRISEK9_IVS' => 'Advanced Enterprise Crypto Voice',
  'ADVENTERPRISEK9' => 'Advanced Enterprise Crypto',
  'ADVSECURITYK9' => 'Advanced Security Crypto',
  'K91P' => 'Provider Crypto',
  'K4P' => 'Provider Crypto',
  'ADVIPSERVICESK9' => 'Adv IP Services Crypto',
  'ADVIPSERVICES' => 'Adv IP Services',
  'IK9P' => 'IP Plus Crypto',
  'K9O3SY7' => 'IP ADSL FW IDS Plus IPSEC 3DES',
  'SPSERVICESK9' => 'SP Services Crypto',
  'PK9SV' => 'IP MPLS/IPV6 W/SSH + BGP',
  'IS' => 'IP Plus',
  'IPSERVICESK9' => 'IP Services Crypto',
  'BROADBAND' => 'Broadband',
  'IPBASE' => 'IP Base',
  'IPSERVICE' => 'IP Services',
  'P' => 'Service Provider',
  'P11' => 'Broadband Router',
  'G4P5' => 'NRP',
  'JK9S' => 'Enterprise Plus Crypto',
  'IK9S' => 'IP Plus Crypto',
  'JK' => 'Enterprise Plus',
  'I6Q4L2' => 'Layer 2',
  'I6K2L2Q4' => 'Layer 2 Crypto',
  'C3H2S' => 'Layer 2 SI/EI',
  '_WAN' => ' + WAN',
);

$rewrite_shortif = array (
  'tengigabitethernet' => 'Te',
  'tengige' => 'Te',
  'gigabitethernet' => 'Gi',
  'fastethernet' => 'Fa',
  'ethernet' => 'Et',
  'serial' => 'Se',
  'pos' => 'Pos',
  'port-channel' => 'Po',
  'atm' => 'Atm',
  'null' => 'Null',
  'loopback' => 'Lo',
  'dialer' => 'Di',
  'vlan' => 'Vlan',
  'tunnel' => 'Tunnel',
  'serviceinstance' => 'SI',
  'dwdm' => 'DWDM',
);

$rewrite_iftype = array (
  'other' => 'Other',
  'regular1822',
  'hdh1822',
  'ddnX25',
  'rfc877x25',
  'ethernetCsmacd' => 'Ethernet',
  'iso88023Csmacd' => 'Ethernet',
  'iso88024TokenBus',
  'iso88025TokenRing',
  'iso88026Man',
  'starLan' => 'StarLAN',
  'proteon10Mbit',
  'proteon80Mbit',
  'hyperchannel',
  'fddi',
  'lapb',
  'sdlc',
  'ds1' => 'DS1',
  'e1' => 'E1',
  'basicISDN' => 'Basic Rate ISDN',
  'primaryISDN' => 'Primary Rate ISDN',
  'propPointToPointSerial' => 'PtP Serial',
  'ppp' => 'PPP',
  'softwareLoopback' => 'Loopback',
  'eon' => 'CLNP over IP',
  'ethernet3Mbit' => 'Ethernet',
  'nsip' => 'XNS over IP',
  'slip' => 'SLIP',
  'ultra' => 'ULTRA technologies',
  'ds3' => 'DS3',
  'sip' => 'SMDS',
  'frameRelay' => 'Frame Relay',
  'rs232' => 'RS232 Serial',
  'para' => 'Parallel',
  'arcnet' => 'Arcnet',
  'arcnetPlus' => 'Arcnet Plus',
  'atm' => 'ATM Cells',
  'miox25',
  'sonet' => 'SONET or SDH',
  'x25ple',
  'iso88022llc',
  'localTalk',
  'smdsDxi',
  'frameRelayService' => 'FRNETSERV-MIB',
  'v35',
  'hssi',
  'hippi',
  'modem' => 'Generic Modem',
  'aal5' => 'AAL5 over ATM',
  'sonetPath',
  'sonetVT',
  'smdsIcip' => 'SMDS InterCarrier Interface',
  'propVirtual' => 'Virtual/Internal',
  'propMultiplexor' => 'proprietary multiplexing',
  'ieee80212' => '100BaseVG',
  'fibreChannel' => 'Fibre Channel',
  'hippiInterface' => 'HIPPI',
  'frameRelayInterconnect' => 'Frame Relay',
  'aflane8023' => 'ATM Emulated LAN for 802.3',
  'aflane8025' => 'ATM Emulated LAN for 802.5',
  'cctEmul' => 'ATM Emulated circuit ',
  'fastEther' => 'Ethernet',
  'isdn' => 'ISDN and X.25',
  'v11' => 'CCITT V.11/X.21',
  'v36' => 'CCITT V.36 ',
  'g703at64k' => 'CCITT G703 at 64Kbps',
  'g703at2mb' => 'Obsolete see DS1-MIB',
  'qllc' => 'SNA QLLC',
  'fastEtherFX' => 'Ethernet',
  'channel' => 'Channel',
  'ieee80211' => 'IEEE802.11 Radio',
  'ibm370parChan' => 'IBM System 360/370 OEMI Channel',
  'escon' => 'IBM Enterprise Systems Connection',
  'dlsw' => 'Data Link Switching',
  'isdns' => 'ISDN S/T',
  'isdnu' => 'ISDN U',
  'lapd' => 'Link Access Protocol D',
  'ipSwitch' => 'IP Switching Objects',
  'rsrb' => 'Remote Source Route Bridging',
  'atmLogical' => 'ATM Logical Port',
  'ds0' => 'Digital Signal Level 0',
  'ds0Bundle' => 'Group of DS0s on the same DS1',
  'bsc' => 'Bisynchronous Protocol',
  'async' => 'Asynchronous Protocol',
  'cnr' => 'Combat Net Radio',
  'iso88025Dtr' => 'ISO 802.5r DTR',
  'eplrs' => 'Ext Pos Loc Report Sys',
  'arap' => 'Appletalk Remote Access Protocol',
  'propCnls' => 'Proprietary Connectionless Protocol',
  'hostPad' => 'CCITT-ITU X.29 PAD Protocol',
  'termPad' => 'CCITT-ITU X.3 PAD Facility',
  'frameRelayMPI' => 'Multiproto Interconnect over FR',
  'x213' => 'CCITT-ITU X213',
  'adsl' => 'ADSL',
  'radsl' => 'Rate-Adapt. DSL',
  'sdsl' => 'SDSL',
  'vdsl' => 'VDSL',
  'iso88025CRFPInt' => 'ISO 802.5 CRFP',
  'myrinet' => 'Myricom Myrinet',
  'voiceEM' => 'Voice recEive and transMit',
  'voiceFXO' => 'Voice FXO',
  'voiceFXS' => 'Voice FXS',
  'voiceEncap' => 'Voice Encapsulation',
  'voiceOverIp' => 'Voice over IP',
  'atmDxi' => 'ATM DXI',
  'atmFuni' => 'ATM FUNI',
  'atmIma' => 'ATM IMA',
  'pppMultilinkBundle' => 'PPP Multilink Bundle',
  'ipOverCdlc' => 'IBM ipOverCdlc',
  'ipOverClaw' => 'IBM Common Link Access to Workstn',
  'stackToStack' => 'IBM stackToStack',
  'virtualIpAddress' => 'IBM VIPA',
  'mpc' => 'IBM multi-protocol channel support',
  'ipOverAtm' => 'IBM ipOverAtm',
  'iso88025Fiber' => 'ISO 802.5j Fiber Token Ring',
  'tdlc  ' => 'IBM twinaxial data link control',
  'gigabitEthernet' => 'Ethernet',
  'hdlc' => 'HDLC',
  'lapf' => 'LAP F',
  'v37' => 'V.37',
  'x25mlp' => 'Multi-Link Protocol',
  'x25huntGroup' => 'X25 Hunt Group',
  'transpHdlc' => 'Transp HDLC',
  'interleave' => 'Interleave channel',
  'fast' => 'Fast channel',
  'ip' => 'IP',
  'docsCableMaclayer' => 'CATV Mac Layer',
  'docsCableDownstream' => 'CATV Downstream interface',
  'docsCableUpstream' => 'CATV Upstream interface',
  'a12MppSwitch' => 'Avalon Parallel Processor',
  'tunnel' => 'Tunnel',
  'coffee' => 'coffee pot',
  'ces' => 'Circuit Emulation Service',
  'atmSubInterface' => 'ATM Sub Interface',
  'l2vlan' => 'L2 VLAN (802.1Q)',
  'l3ipvlan' => 'L3 VLAN (IP)',
  'l3ipxvlan' => 'L3 VLAN (IPX)',
  'digitalPowerline' => 'IP over Power Lines',
  'mediaMailOverIp' => 'Multimedia Mail over IP',
  'dtm' => 'Dynamic Syncronous Transfer Mode',
  'dcn' => 'Data Communications Network',
  'ipForward' => 'IP Forwarding Interface',
  'msdsl' => 'Multi-rate Symmetric DSL',
  'ieee1394' => 'IEEE1394 High Performance Serial Bus',
  'if-gsn--HIPPI-6400 ',
  'dvbRccMacLayer' => 'DVB-RCC MAC Layer',
  'dvbRccDownstream' => 'DVB-RCC Downstream Channel',
  'dvbRccUpstream' => 'DVB-RCC Upstream Channel',
  'atmVirtual' => 'ATM Virtual Interface',
  'mplsTunnel' => 'MPLS Tunnel Virtual Interface',
  'srp' => 'Spatial Reuse Protocol       ',
  'voiceOverAtm' => 'Voice Over ATM',
  'voiceOverFrameRelay' => 'Voice Over FR',
  'idsl' => 'DSL over ISDN',
  'compositeLink' => 'Avici Composite Link Interface',
  'ss7SigLink' => 'SS7 Signaling Link ',
  'propWirelessP2P' => 'Prop. P2P wireless interface',
  'frForward' => 'Frame Forward Interface',
  'rfc1483       ' => 'Multiprotocol over ATM AAL5',
  'usb' => 'USB Interface',
  'ieee8023adLag' => 'IEEE 802.3ad Link Aggregate',
  'bgppolicyaccounting' => 'BGP Policy Accounting',
  'frf16MfrBundle' => 'FRF .16 Multilink Frame Relay ',
  'h323Gatekeeper' => 'H323 Gatekeeper',
  'h323Proxy' => 'H323 Proxy',
  'mpls' => 'MPLS ',
  'mfSigLink' => 'Multi-frequency signaling link',
  'hdsl2' => 'High Bit-Rate DSL - 2nd generation',
  'shdsl' => 'Multirate HDSL2',
  'ds1FDL' => 'Facility Data Link 4Kbps on a DS1',
  'pos' => 'Packet over SONET/SDH Interface',
  'dvbAsiIn' => 'DVB-ASI Input',
  'dvbAsiOut' => 'DVB-ASI Output ',
  'plc' => 'Power Line Communtications',
  'nfas' => 'Non Facility Associated Signaling',
  'tr008' => 'TR008',
  'gr303RDT' => 'Remote Digital Terminal',
  'gr303IDT' => 'Integrated Digital Terminal',
  'isup' => 'ISUP',
  'propDocsWirelessMaclayer' => 'Cisco proprietary Maclayer',
  'propDocsWirelessDownstream' => 'Cisco proprietary Downstream',
  'propDocsWirelessUpstream' => 'Cisco proprietary Upstream',
  'hiperlan2' => 'HIPERLAN Type 2 Radio Interface',
  'propBWAp2Mp' => 'PropBroadbandWirelessAccesspt2multipt',
  'sonetOverheadChannel' => 'SONET Overhead Channel',
  'digitalWrapperOverheadChannel' => 'Digital Wrapper',
  'aal2' => 'ATM adaptation layer 2',
  'radioMAC' => 'MAC layer over radio links',
  'atmRadio' => 'ATM over radio links',
  'imt' => 'Inter Machine Trunks',
  'mvl' => 'Multiple Virtual Lines DSL',
  'reachDSL' => 'Long Reach DSL',
  'frDlciEndPt' => 'Frame Relay DLCI End Point',
  'atmVciEndPt' => 'ATM VCI End Point',
  'opticalChannel' => 'Optical Channel',
  'opticalTransport' => 'Optical Transport',
  'propAtm' => 'Proprietary ATM',
  'voiceOverCable' => 'Voice Over Cable',
  'infiniband' => 'Infiniband',
  'teLink' => 'TE Link',
  'q2931' => 'Q.2931',
  'virtualTg' => 'Virtual Trunk Group',
  'sipTg' => 'SIP Trunk Group',
  'sipSig' => 'SIP Signaling',
  'docsCableUpstreamChannel' => 'CATV Upstream Channel',
  'econet' => 'Acorn Econet',
  'pon155' => 'FSAN 155Mb Symetrical PON interface',
  'pon622' => 'FSAN622Mb Symetrical PON interface',
  'bridge' => 'Transparent bridge interface',
  'linegroup' => 'Interface common to multiple lines',
  'voiceEMFGD' => 'voice E&M Feature Group D',
  'voiceFGDEANA' => 'voice FGD Exchange Access North American',
  'voiceDID' => 'voice Direct Inward Dialing',
  'mpegTransport' => 'MPEG transport interface',
  'sixToFour' => '6to4 interface (DEPRECATED)',
  'gtp' => 'GTP (GPRS Tunneling Protocol)',
  'pdnEtherLoop1' => 'Paradyne EtherLoop 1',
  'pdnEtherLoop2' => 'Paradyne EtherLoop 2',
  'opticalChannelGroup' => 'Optical Channel Group',
  'homepna' => 'HomePNA ITU-T G.989',
  'gfp' => 'Generic Framing Procedure (GFP)',
  'ciscoISLvlan' => 'Layer 2 Virtual LAN using Cisco ISL',
  'actelisMetaLOOP' => 'Acteleis proprietary MetaLOOP High Speed Link ',
  'fcipLink' => 'FCIP Link ',
  'rpr' => 'Resilient Packet Ring Interface Type',
  'qam' => 'RF Qam Interface',
  'lmp' => 'Link Management Protocol',
  'cblVectaStar' => 'Cambridge Broadband Networks Limited VectaStar',
  'docsCableMCmtsDownstream' => 'CATV Modular CMTS Downstream Interface',
  'adsl2' => 'Asymmetric Digital Subscriber Loop Version 2 ',
  'macSecControlledIF' => 'MACSecControlled ',
  'macSecUncontrolledIF' => 'MACSecUncontrolled',
  'aviciOpticalEther' => 'Avici Optical Ethernet Aggregate',
  'atmbond' => 'atmbond',
  'voiceFGDOS' => 'voice FGD Operator Services',
  'mocaVersion1' => 'MultiMedia over Coax Alliance (MoCA) Interface',
  'ieee80216WMAN' => 'IEEE 802.16 WMAN interface',
  'adsl2plus' => 'Asymmetric Digital Subscriber Loop Version 2, ',
  'dvbRcsMacLayer' => 'DVB-RCS MAC Layer',
  'dvbTdm' => 'DVB Satellite TDM',
  'dvbRcsTdma' => 'DVB-RCS TDMA',
  'x86Laps' => 'LAPS based on ITU-T X.86/Y.1323',
  'wwanPP' => '3GPP WWAN',
  'wwanPP2' => '3GPP2 WWAN',
  'voiceEBS' => 'voice P-phone EBS physical interface',
  'ifPwType' => 'Pseudowire interface type',
  'ilan' => 'Internal LAN on a bridge per IEEE 802.1ap',
  'pip' => 'Provider Instance Port on a bridge per IEEE 802.1ah PBB',
  'aluELP' => 'Alcatel-Lucent Ethernet Link Protection',
  'gpon' => 'Gigabit-capable passive optical networks (G-PON) as per ITU-T G.948',
  'vdsl2' => 'Very high speed digital subscriber line Version 2 (as per ITU-T Recommendation G.993.2)',
  'capwapDot11Profile' => 'WLAN Profile Interface',
  'capwapDot11Bss' => 'WLAN BSS Interface',
  'capwapWtpVirtualRadio' => 'WTP Virtual Radio Interface',
  'bits' => 'bitsport',
  'docsCableUpstreamRfPort' => 'DOCSIS CATV Upstream RF Port',
  'cableDownstreamRfPort' => 'CATV downstream RF port',
  'vmwareVirtualNic' => 'VMware Virtual Network Interface',
  'ieee802154' => 'IEEE 802.15.4 WPAN interface',
  'otnOdu' => 'OTN Optical Data Unit',
  'otnOtu' => 'OTN Optical channel Transport Unit',
  'ifVfiType' => 'VPLS Forwarding Instance Interface Type',
  'g9981' => 'G.998.1 bonded interface',
  'g9982' => 'G.998.2 bonded interface',
  'g9983' => 'G.998.3 bonded interface',
  'aluEpon' => 'Ethernet Passive Optical Networks (E-PON)',
  'aluEponOnu' => 'EPON Optical Network Unit',
  'aluEponPhysicalUni' => 'EPON physical User to Network interface',
  'aluEponLogicalLink' => 'The emulation of a point-to-point link over the EPON layer',
  'aluGponOnu' => 'GPON Optical Network Unit',
  'aluGponPhysicalUni' => 'GPON physical User to Network interface',
  'vmwareNicTeam' => 'VMware NIC Team',
);

$rewrite_ifname = array (
  'ether' => 'Ether',
  'gig' => 'Gig',
  'fast' => 'Fast',
  'ten' => 'Ten',
  '-802.1q vlan subif' => '',
  '-802.1q' => '',
  'bvi' => 'BVI',
  'vlan' => 'Vlan',
  'ether' => 'Ether',
  'tunnel' => 'Tunnel',
  'serial' => 'Serial',
  '-aal5 layer' => ' aal5',
  'null' => 'Null',
  'atm' => 'ATM',
  'port-channel' => 'Port-Channel',
  'dial' => 'Dial',
  'hp procurve switch software loopback interface' => 'Loopback Interface',
  'control plane interface' => 'Control Plane',
  'loop' => 'Loop',
  '802.1q encapsulation tag' => 'Vlan',
);

$rewrite_hrDevice = array (
  'GenuineIntel:' => '',
  'AuthenticAMD:' => '',
  'Intel(R)' => '',
  'CPU' => '',
  '(R)' => '',
  '  ' => ' ',
);

// Specific rewrite functions

function makeshortif($if)
{
  global $rewrite_shortif;

  $if = fixifName ($if);
  $if = strtolower($if);
  $if = array_str_replace($rewrite_shortif, $if);
  return $if;
}

function rewrite_ios_features ($features)
{
  global $rewrite_ios_features;

  $type = array_preg_replace($rewrite_ios_features, $features);

  return ($features);
}

function rewrite_fortinet_hardware ($hardware)
{
  global $rewrite_fortinet_hardware;

  $hardware = $rewrite_fortinet_hardware[$hardware];

  return ($hardware);
}

function rewrite_extreme_hardware ($hardware)
{
  global $rewrite_extreme_hardware;

  #$hardware = array_str_replace($rewrite_extreme_hardware, $hardware);
  $hardware = $rewrite_extreme_hardware[$hardware];

  return ($hardware);
}

function rewrite_ftos_hardware ($hardware)
{
  global $rewrite_ftos_hardware;

  $hardware = $rewrite_ftos_hardware[$hardware];

  return ($hardware);
}

function rewrite_ironware_hardware ($hardware)
{
  global $rewrite_ironware_hardware;

  $hardware = array_str_replace($rewrite_ironware_hardware, $hardware);

  return ($hardware);
}

function rewrite_junose_hardware ($hardware)
{
  global $rewrite_junos_hardware;

  $hardware = $rewrite_junos_hardware[$hardware];
  return ($hardware);
}

function rewrite_junos_hardware ($hardware)
{
  global $rewrite_junos_hardware;

  $hardware = $rewrite_junos_hardware[$hardware];
  return ($hardware);
}

function rewrite_unix_hardware($hardware)
{
  if (preg_match('/i[3456]86/i', $hardware)) { $hardware = 'Generic x86'; }
  elseif (preg_match('/x86_64|amd64/i', $hardware)) { $hardware = 'Generic x86 64-bit'; }
  elseif (strstr($hardware, 'sparc32')) { $hardware = 'Generic SPARC 32-bit'; }
  elseif (strstr($hardware, 'sparc64')) { $hardware = 'Generic SPARC 64-bit'; }
  elseif (strstr($hardware, 'mips')) { $hardware = 'Generic MIPS'; }
  elseif (strstr($hardware, 'armv5')) { $hardware = 'Generic ARMv5'; }
  elseif (strstr($hardware, 'armv6')) { $hardware = 'Generic ARMv6'; }
  elseif (strstr($hardware, 'armv7')) { $hardware = 'Generic ARMv7'; }
  elseif (strstr($hardware, 'armv')) { $hardware = 'Generic ARM'; }
  else { $hardware = 'Generic'; }

  return ($hardware);
}

function rewrite_ftos_vlanid($device, $ifindex)
{
  // damn DELL use them one known indexes
  //dot1qVlanStaticName.1107787777 = Vlan 1
  //dot1qVlanStaticName.1107787998 = mgmt
  $ftos_vlan = dbFetchCell('SELECT ifName FROM `ports` WHERE `device_id` = ? AND `ifIndex` = ?', array($device['device_id'], $ifindex));
  list(,$vlanid) = explode(' ', $ftos_vlan);
  return $vlanid;
}

function fixiftype ($type)
{
  global $rewrite_iftype;

  $type = replace_from_array($rewrite_iftype, $type);

  return ($type);
}

function fixifName ($inf)
{
  global $rewrite_ifname;

  //$inf = strtolower($inf); // ew. -tom
  $inf = array_str_replace($rewrite_ifname, $inf);

  return $inf;
}

function short_hrDeviceDescr($dev)
{
  global $rewrite_hrDevice;

  $dev = array_str_replace($rewrite_hrDevice, $dev);
  $dev = preg_replace("/\ +/"," ", $dev);
  $dev = trim($dev);

  return $dev;
}

function short_port_descr ($desc)
{
  list($desc) = explode("(", $desc);
  list($desc) = explode("[", $desc);
  list($desc) = explode("{", $desc);
  list($desc) = explode("|", $desc);
  list($desc) = explode("<", $desc);
  $desc = trim($desc);

  return $desc;
}

// Underlying rewrite functions

function replace_from_array($array, $string)
{
  if (array_key_exists($string, $array))
  {
    $string = $array[$string];
  }
  return $string;
}

function array_str_replace($array, $string)
{
  foreach ($array as $search => $replace)
  {
    $string = str_replace($search, $replace, $string);
  }

  return $string;
}

function array_preg_replace($array, $string)
{
  foreach ($array as $search => $replace)
  {
    $string = preg_replace($search, $replace, $string);
  }

  return $string;
}

function rewrite_adslLineType($adslLineType)
{
  $adslLineTypes = array ('noChannel'          => 'No Channel',
                          'fastOnly'           => 'Fastpath',
                          'interleavedOnly'    => 'Interleaved',
                          'fastOrInterleaved'  => 'Fast/Interleaved',
                          'fastAndInterleaved' => 'Fast+Interleaved');

  foreach ($adslLineTypes as $type => $text)
  {
    if ($adslLineType == $type)
    {
      $adslLineType = $text;
    }
  }

  return($adslLineType);
}

$countries = array (
    'AF' => 'Afghanistan',
    'AX' => 'Åland Islands',
    'AL' => 'Albania',
    'DZ' => 'Algeria',
    'AS' => 'American Samoa',
    'AD' => 'Andorra',
    'AO' => 'Angola',
    'AI' => 'Anguilla',
    'AQ' => 'Antarctica',
    'AG' => 'Antigua and Barbuda',
    'AR' => 'Argentina',
    'AM' => 'Armenia',
    'AW' => 'Aruba',
    'AU' => 'Australia',
    'AT' => 'Austria',
    'AZ' => 'Azerbaijan',
    'BS' => 'Bahamas',
    'BH' => 'Bahrain',
    'BD' => 'Bangladesh',
    'BB' => 'Barbados',
    'BY' => 'Belarus',
    'BE' => 'Belgium',
    'BZ' => 'Belize',
    'BJ' => 'Benin',
    'BM' => 'Bermuda',
    'BT' => 'Bhutan',
    'BO' => 'Bolivia, Plurinational State of',
    'BA' => 'Bosnia and Herzegovina',
    'BW' => 'Botswana',
    'BV' => 'Bouvet Island',
    'BR' => 'Brazil',
    'IO' => 'British Indian Ocean Territory',
    'BN' => 'Brunei Darussalam',
    'BG' => 'Bulgaria',
    'BF' => 'Burkina Faso',
    'BI' => 'Burundi',
    'KH' => 'Cambodia',
    'CM' => 'Cameroon',
    'CA' => 'Canada',
    'CV' => 'Cape Verde',
    'KY' => 'Cayman Islands',
    'CF' => 'Central African Republic',
    'TD' => 'Chad',
    'CL' => 'Chile',
    'CN' => 'China',
    'CX' => 'Christmas Island',
    'CC' => 'Cocos (Keeling) Islands',
    'CO' => 'Colombia',
    'KM' => 'Comoros',
    'CG' => 'Congo',
    'CD' => 'Congo, the Democratic Republic of the',
    'CK' => 'Cook Islands',
    'CR' => 'Costa Rica',
    'CI' => "Côte d'Ivoire",
    'HR' => 'Croatia',
    'CU' => 'Cuba',
    'CY' => 'Cyprus',
    'CZ' => 'Czech Republic',
    'DK' => 'Denmark',
    'DJ' => 'Djibouti',
    'DM' => 'Dominica',
    'DO' => 'Dominican Republic',
    'EC' => 'Ecuador',
    'EG' => 'Egypt',
    'SV' => 'El Salvador',
    'GQ' => 'Equatorial Guinea',
    'ER' => 'Eritrea',
    'EE' => 'Estonia',
    'ET' => 'Ethiopia',
    'FK' => 'Falkland Islands (Malvinas)',
    'FO' => 'Faroe Islands',
    'FJ' => 'Fiji',
    'FI' => 'Finland',
    'FR' => 'France',
    'GF' => 'French Guiana',
    'PF' => 'French Polynesia',
    'TF' => 'French Southern Territories',
    'GA' => 'Gabon',
    'GM' => 'Gambia',
    'GE' => 'Georgia',
    'DE' => 'Germany',
    'GH' => 'Ghana',
    'GI' => 'Gibraltar',
    'GR' => 'Greece',
    'GL' => 'Greenland',
    'GD' => 'Grenada',
    'GP' => 'Guadeloupe',
    'GU' => 'Guam',
    'GT' => 'Guatemala',
    'GG' => 'Guernsey',
    'GN' => 'Guinea',
    'GW' => 'Guinea-Bissau',
    'GY' => 'Guyana',
    'HT' => 'Haiti',
    'HM' => 'Heard Island and McDonald Islands',
    'VA' => 'Holy See (Vatican City State)',
    'HN' => 'Honduras',
    'HK' => 'Hong Kong',
    'HU' => 'Hungary',
    'IS' => 'Iceland',
    'IN' => 'India',
    'ID' => 'Indonesia',
    'IR' => 'Iran, Islamic Republic of',
    'IQ' => 'Iraq',
    'IE' => 'Ireland',
    'IM' => 'Isle of Man',
    'IL' => 'Israel',
    'IT' => 'Italy',
    'JM' => 'Jamaica',
    'JP' => 'Japan',
    'JE' => 'Jersey',
    'JO' => 'Jordan',
    'KZ' => 'Kazakhstan',
    'KE' => 'Kenya',
    'KI' => 'Kiribati',
    'KP' => "Korea, Democratic People's Republic of",
    'KR' => 'Korea, Republic of',
    'KW' => 'Kuwait',
    'KG' => 'Kyrgyzstan',
    'LA' => "Lao People's Democratic Republic",
    'LV' => 'Latvia',
    'LB' => 'Lebanon',
    'LS' => 'Lesotho',
    'LR' => 'Liberia',
    'LY' => 'Libyan Arab Jamahiriya',
    'LI' => 'Liechtenstein',
    'LT' => 'Lithuania',
    'LU' => 'Luxembourg',
    'MO' => 'Macao',
    'MK' => 'Macedonia, the former Yugoslav Republic of',
    'MG' => 'Madagascar',
    'MW' => 'Malawi',
    'MY' => 'Malaysia',
    'MV' => 'Maldives',
    'ML' => 'Mali',
    'MT' => 'Malta',
    'MH' => 'Marshall Islands',
    'MQ' => 'Martinique',
    'MR' => 'Mauritania',
    'MU' => 'Mauritius',
    'YT' => 'Mayotte',
    'MX' => 'Mexico',
    'FM' => 'Micronesia, Federated States of',
    'MD' => 'Moldova, Republic of',
    'MC' => 'Monaco',
    'MN' => 'Mongolia',
    'ME' => 'Montenegro',
    'MS' => 'Montserrat',
    'MA' => 'Morocco',
    'MZ' => 'Mozambique',
    'MM' => 'Myanmar',
    'NA' => 'Namibia',
    'NR' => 'Nauru',
    'NP' => 'Nepal',
    'NL' => 'Netherlands',
    'AN' => 'Netherlands Antilles',
    'NC' => 'New Caledonia',
    'NZ' => 'New Zealand',
    'NI' => 'Nicaragua',
    'NE' => 'Niger',
    'NG' => 'Nigeria',
    'NU' => 'Niue',
    'NF' => 'Norfolk Island',
    'MP' => 'Northern Mariana Islands',
    'NO' => 'Norway',
    'OM' => 'Oman',
    'PK' => 'Pakistan',
    'PW' => 'Palau',
    'PS' => 'Palestinian Territory, Occupied',
    'PA' => 'Panama',
    'PG' => 'Papua New Guinea',
    'PY' => 'Paraguay',
    'PE' => 'Peru',
    'PH' => 'Philippines',
    'PN' => 'Pitcairn',
    'PL' => 'Poland',
    'PT' => 'Portugal',
    'PR' => 'Puerto Rico',
    'QA' => 'Qatar',
    'RE' => 'Réunion',
    'RO' => 'Romania',
    'RU' => 'Russian Federation',
    'RW' => 'Rwanda',
    'BL' => 'Saint Barthélemy',
    'SH' => 'Saint Helena',
    'KN' => 'Saint Kitts and Nevis',
    'LC' => 'Saint Lucia',
    'MF' => 'Saint Martin (French part)',
    'PM' => 'Saint Pierre and Miquelon',
    'VC' => 'Saint Vincent and the Grenadines',
    'WS' => 'Samoa',
    'SM' => 'San Marino',
    'ST' => 'Sao Tome and Principe',
    'SA' => 'Saudi Arabia',
    'SN' => 'Senegal',
    'RS' => 'Serbia',
    'SC' => 'Seychelles',
    'SL' => 'Sierra Leone',
    'SG' => 'Singapore',
    'SK' => 'Slovakia',
    'SI' => 'Slovenia',
    'SB' => 'Solomon Islands',
    'SO' => 'Somalia',
    'ZA' => 'South Africa',
    'GS' => 'South Georgia and the South Sandwich Islands',
    'ES' => 'Spain',
    'LK' => 'Sri Lanka',
    'SD' => 'Sudan',
    'SR' => 'Suriname',
    'SJ' => 'Svalbard and Jan Mayen',
    'SZ' => 'Swaziland',
    'SE' => 'Sweden',
    'CH' => 'Switzerland',
    'SY' => 'Syrian Arab Republic',
    'TW' => 'Taiwan, Province of China',
    'TJ' => 'Tajikistan',
    'TZ' => 'Tanzania, United Republic of',
    'TH' => 'Thailand',
    'TL' => 'Timor-Leste',
    'TG' => 'Togo',
    'TK' => 'Tokelau',
    'TO' => 'Tonga',
    'TT' => 'Trinidad and Tobago',
    'TN' => 'Tunisia',
    'TR' => 'Turkey',
    'TM' => 'Turkmenistan',
    'TC' => 'Turks and Caicos Islands',
    'TV' => 'Tuvalu',
    'UG' => 'Uganda',
    'UA' => 'Ukraine',
    'AE' => 'United Arab Emirates',
    'GB' => 'United Kingdom',
    'US' => 'United States',
    'UM' => 'United States Minor Outlying Islands',
    'UY' => 'Uruguay',
    'UZ' => 'Uzbekistan',
    'VU' => 'Vanuatu',
    'VE' => 'Venezuela, Bolivarian Republic of',
    'VN' => 'Viet Nam',
    'VG' => 'Virgin Islands, British',
    'VI' => 'Virgin Islands, U.S.',
    'WF' => 'Wallis and Futuna',
    'EH' => 'Western Sahara',
    'YE' => 'Yemen',
    'ZM' => 'Zambia',
    'ZW' => 'Zimbabwe'
);


function country_from_code($code) {
    global $countries;
    $code = strtoupper($code);
    if (array_key_exists($code, $countries)) {
        return $countries[$code];
    }
    return "Unknown";
}

function flag_from_code($code)
{
  global $config;

  $code = strtolower($code);

  if ($device['icon'] && file_exists($config['html_dir'] . "/images/icons/flags/" . $code . ".png"))
  {
    $image = '<img src="' . $config['base_url'] . '/images/icons/flags/' . $code . '.png" />';
  } else {
    $image = '<img src="' . $config['base_url'] . '/images/icons/flags/europeanunion.png" />';
  }

  return $image;
}



?>
