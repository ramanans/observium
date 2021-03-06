<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage poller
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

// Include description parser (usually ifAlias) if config option set
$custom_port_parser = FALSE;
if (isset($config['port_descr_parser']) && $config['port_descr_parser'] != FALSE && is_file($config['install_dir'] . "/" . $config['port_descr_parser']))
{
  include_once($config['install_dir'] . "/" . $config['port_descr_parser']);

  if (function_exists('custom_port_parser'))
  {
    $custom_port_attribs = array('type', 'descr', 'circuit', 'speed', 'notes');
    $custom_port_parser = TRUE;
  } else {
    print_warning("WARNING: Rewrite your custom ports parser in file [".$config['install_dir'] . "/" . $config['port_descr_parser']."], using a function custom_port_parser().");
    $custom_port_parser = 'old';
  }
}

// Cache ports table from DB
$ports = array();

// FIXME -- this stuff is a little messy, looping the array to make an array just seems wrong. :>
//       -- i can make it a function, so that you don't know what it's doing.
//       -- $ports = adamasMagicFunction($ports_db); ?

$query  = 'SELECT * FROM `ports`';
//$query .= ' LEFT JOIN `ports-state` USING(`port_id`)';
$query .= ' WHERE `device_id` = ?';

$ports_db = dbFetchRows($query, array($device['device_id']));
$ports_attribs = get_device_entities_attribs($device['device_id'], 'port'); // Get all attribs
//print_vars($ports_attribs);
foreach ($ports_db as $port)
{
  if (isset($ports_attribs['port'][$port['port_id']]))
  {
    $port = array_merge($port, $ports_attribs['port'][$port['port_id']]);
  }
  $ports[$port['ifIndex']] = $port;
}
$ports_ignored_count_db = intval(get_entity_attrib('device', $device, 'ports_ignored_count')); // Cache last ports ignored count

// Ports module options
// FIXME - why are we defining an array here? Just use the config options.
foreach (array('etherlike', 'adsl', 'poe', 'docsis', 'junoseatmvp', 'sros_egress_qstat', 'sros_ingress_qstat', 'jnx_cos_qstat', 'separate_walk') as $ports_module)
{
  $ports_modules[$ports_module] = isset($attribs['enable_ports_' . $ports_module]) ? (bool)$attribs['enable_ports_' . $ports_module] : $config['enable_ports_' . $ports_module];
}
// Additionally force enable separate walk feature for some device oses, but only if ports total count >10
$ports_module = 'separate_walk';
if (!$ports_modules[$ports_module] && $config['os'][$device['os']]['ports_'.$ports_module])
{
  if (isset($attribs['enable_ports_' . $ports_module]) && !$attribs['enable_ports_' . $ports_module]) {} // forcing disabled in device config
  else
  {
    $ports_total_count = $ports_ignored_count_db + dbFetchCell('SELECT COUNT(*) FROM `ports` WHERE `device_id` = ? AND `deleted` = 0', array($device['device_id']));
    $ports_modules[$ports_module] = $ports_total_count > 10;
    if (OBS_DEBUG && $ports_modules[$ports_module])
    {
      print_debug('Forced ports separate walk feature!');
    }
  }
}
//print_vars($ports_modules);

$table_rows = array();

// Build SNMP Cache Array

// IF-MIB OIDs that go into the ports table

$data_oids_ifEntry = array(
  // ifEntry
  'ifDescr', 'ifType', 'ifMtu', 'ifSpeed', 'ifPhysAddress',
  'ifAdminStatus', 'ifOperStatus', 'ifLastChange',
);
$data_oids_ifXEntry = array(
  // ifXEntry
  'ifName', 'ifAlias', 'ifHighSpeed', 'ifPromiscuousMode', 'ifConnectorPresent',
);
$data_oids = array_merge($data_oids_ifEntry,
                         $data_oids_ifXEntry,
                         array('ifDuplex', 'ifTrunk', 'ifVlan')); // Add additional oids

// IF-MIB statistics OIDs that go into RRD

$stat_oids_ifEntry = array(
  // ifEntry
  'ifInOctets',        'ifOutOctets',
  'ifInUcastPkts',     'ifOutUcastPkts',
  'ifInNUcastPkts',    'ifOutNUcastPkts', // Note, (In|Out)NUcastPkts deprecated, for HC counters use Broadcast+Multicast instead
  'ifInDiscards',      'ifOutDiscards',
  'ifInErrors',        'ifOutErrors',
  'ifInUnknownProtos',
);

$stat_oids_ifXEntry = array(
  // ifXEntry
  'ifInMulticastPkts', 'ifOutMulticastPkts',
  'ifInBroadcastPkts', 'ifOutBroadcastPkts',
  // HC counters
  'ifHCInOctets',        'ifHCOutOctets',
  'ifHCInUcastPkts',     'ifHCOutUcastPkts',
  'ifHCInMulticastPkts', 'ifHCOutMulticastPkts',
  'ifHCInBroadcastPkts', 'ifHCOutBroadcastPkts',
);

// This oids definitions used only for Upstream/Downstream interface types
$upstream_oids = array(
  // ifEntry
  'ifInOctets', 'ifInUcastPkts', 'ifInNUcastPkts', 'ifInDiscards', 'ifInErrors',
  // ifXEntry
  'ifInMulticastPkts', 'ifInBroadcastPkts', 'ifHCInOctets', 'ifHCInUcastPkts', 'ifHCInMulticastPkts', 'ifHCInBroadcastPkts',
);
$downstream_oids = array(
  // ifEntry
  'ifOutOctets', 'ifOutUcastPkts', 'ifOutNUcastPkts', 'ifOutDiscards', 'ifOutErrors',
  // ifXEntry
  'ifOutMulticastPkts', 'ifOutBroadcastPkts', 'ifHCOutOctets', 'ifHCOutUcastPkts', 'ifHCOutMulticastPkts', 'ifHCOutBroadcastPkts',
);

// Remove HC oids from stat arrays for SNMP v1
if ($device['snmp_version'] == 'v1')
{
  $hc_oids = array(
    // HC counters
    'ifHCInOctets',        'ifHCOutOctets',
    'ifHCInUcastPkts',     'ifHCOutUcastPkts',
    'ifHCInMulticastPkts', 'ifHCOutMulticastPkts',
    'ifHCInBroadcastPkts', 'ifHCOutBroadcastPkts',
  );
  $stat_oids_ifXEntry = array_diff($stat_oids_ifXEntry, $hc_oids);
  $upstream_oids      = array_diff($upstream_oids,      $hc_oids);
  $downstream_oids    = array_diff($downstream_oids,    $hc_oids);
}

// Merge stat oids
$stat_oids = array_merge($stat_oids_ifEntry, $stat_oids_ifXEntry);

// Cisco old locIf OIDs. Currently unused.

$cisco_oids = array('locIfHardType', 'locIfInRunts', 'locIfInGiants', 'locIfInCRC', 'locIfInFrame', 'locIfInOverrun', 'locIfInIgnored', 'locIfInAbort',
                    'locIfCollisions', 'locIfInputQueueDrops', 'locIfOutputQueueDrops');

// PAgP OIDs

//$pagp_oids = array('pagpOperationMode', 'pagpPortState', 'pagpPartnerDeviceId', 'pagpPartnerLearnMethod', 'pagpPartnerIfIndex', 'pagpPartnerGroupIfIndex',
//                   'pagpPartnerDeviceName', 'pagpEthcOperationMode', 'pagpDeviceId', 'pagpGroupIfIndex');
$pagp_oids = array(); // PAgP disabled since r7987, while not moved to new polling style

// PoE OIDs

$cpe_oids = array('cpeExtPsePortEnable', 'cpeExtPsePortDiscoverMode', 'cpeExtPsePortDeviceDetected', 'cpeExtPsePortIeeePd',
  'cpeExtPsePortAdditionalStatus', 'cpeExtPsePortPwrMax', 'cpeExtPsePortPwrAllocated', 'cpeExtPsePortPwrAvailable', 'cpeExtPsePortPwrConsumption',
  'cpeExtPsePortMaxPwrDrawn', 'cpeExtPsePortEntPhyIndex', 'cpeExtPsePortEntPhyIndex', 'cpeExtPsePortPolicingCapable', 'cpeExtPsePortPolicingEnable',
  'cpeExtPsePortPolicingAction', 'cpeExtPsePortPwrManAlloc');

$peth_oids = array('pethPsePortAdminEnable', 'pethPsePortPowerPairsControlAbility', 'pethPsePortPowerPairs', 'pethPsePortDetectionStatus',
  'pethPsePortPowerPriority', 'pethPsePortMPSAbsentCounter', 'pethPsePortType', 'pethPsePortPowerClassifications', 'pethPsePortInvalidSignatureCounter',
  'pethPsePortPowerDeniedCounter', 'pethPsePortOverLoadCounter', 'pethPsePortShortCounter', 'pethMainPseConsumptionPower');

//$ifmib_oids = array_merge($data_oids, $stat_oids);

print_cli_data_field("Caching Oids");
$port_stats = array();
if (!$ports_modules['separate_walk'])
{
  print_debug("Used full table ifEntry/ifXEntry snmpwalk.");
  $ifmib_oids = array('ifEntry', 'ifXEntry');
  foreach ($ifmib_oids as $oid)
  {
    $has_name = 'has_' . $oid;
    echo("$oid ");
    $port_stats = snmpwalk_cache_oid($device, $oid, $port_stats, "IF-MIB");
    $$has_name = $GLOBALS['snmp_status'] || $GLOBALS['snmp_error_code'] === 2; // $has_ifEntry, $has_ifXEntry
    //print_vars($$has_name);
    if ($oid == 'ifEntry')
    {
      // Store error_code, 1000 == not exist table, 2 and 3 - not complete request
      $has_ifEntry_error_code = $GLOBALS['snmp_error_code'];
    }
  }

} else {

  print_debug("Used separate data tables snmpwalk and per port snmpget.");

  $has_ifEntry = FALSE;
  // Data fields
  // ifDescr, ifAlias, ifName, ifType, ifOperStatus
  foreach (array('ifDescr', 'ifType', 'ifOperStatus') as $oid)
  {
    echo("$oid ");
    $port_stats = snmpwalk_cache_oid($device, $oid, $port_stats, "IF-MIB");
    $has_ifEntry = $has_ifEntry || $GLOBALS['snmp_status'];
    $has_ifEntry_error_code = $GLOBALS['snmp_error_code'];
  }
  $has_ifXEntry = FALSE;
  foreach (array('ifAlias', 'ifName', 'ifHighSpeed') as $oid)
  {
    echo("$oid ");
    $port_stats = snmpwalk_cache_oid($device, $oid, $port_stats, "IF-MIB");
    $has_ifXEntry = $has_ifXEntry || $GLOBALS['snmp_status'];
  }

  // Per port snmpget
  if ($port_stats)
  {
    // Collect oids for per port snmpget
    if ($has_ifXEntry)
    {
      $port_oids = array_merge($stat_oids_ifXEntry, $stat_oids_ifEntry);
      $port_oids = array_merge($port_oids, array_diff($data_oids_ifEntry,  array('ifDescr', 'ifType', 'ifOperStatus')));
      $port_oids = array_merge($port_oids, array_diff($data_oids_ifXEntry, array('ifAlias', 'ifName', 'ifHighSpeed')));
    } else {
      $port_oids = array_merge($stat_oids_ifEntry, array_diff($data_oids_ifEntry,  array('ifDescr', 'ifType', 'ifOperStatus')));
    }

    // Use snmpget for each (not ignored) port
    // NOTE. This method reduce polling time when too many ports (>100)
    echo(implode(' ', $port_oids) . ", ifIndex: ");
    foreach ($port_stats as $ifIndex => $port)
    {
      $port_disabled = isset($ports[$ifIndex]['disabled']) && $ports[$ifIndex]['disabled']; // Port polling disabled from WUI
      if (!$port_disabled && is_port_valid($port, $device))
      {
        echo("$ifIndex ");
        $port_oid = implode(".$ifIndex ", $port_oids) . ".$ifIndex";
        $port_stats = snmp_get_multi_oid($device, $port_oid, $port_stats, "IF-MIB");
      }
    }
  }
}

// Prevent mark ports as DELETED when ifEntry snmpwalk return not complete data!
$allow_delete_ports = $has_ifEntry_error_code !== 2 && $has_ifEntry_error_code !== 3;

// Store polled time exactly after walk IF-MIB, for more correct port speed calculate!
$polled = time();

// Device uptime and polled time (required for ifLastChange)
if (isset($cache['devices']['uptime'][$device['device_id']]))
{
  $device_uptime = &$cache['devices']['uptime'][$device['device_id']];
} else {
  print_error("Device uptime not cached, ifLastChange will incorrect. Check polling system module.");
}

// Subset of IF-MIB OIDs that we put into the state table
$stat_oids_db = array('ifInOctets', 'ifOutOctets', 'ifInErrors', 'ifOutErrors', 'ifInUcastPkts', 'ifOutUcastPkts', 'ifInNUcastPkts', 'ifOutNUcastPkts',
                      'ifInBroadcastPkts', 'ifOutBroadcastPkts', 'ifInMulticastPkts', 'ifOutMulticastPkts', 'ifInDiscards', 'ifOutDiscards');

// Subset of IF-MIB OIDs that we put into the ports table
$data_oids_db = array_diff($data_oids, array('ifLastChange')); // remove ifLastChange, because it added separate
$data_oids_db = array_merge($data_oids_db, array('port_label', 'port_label_short', 'port_label_base', 'port_label_num'));

// Additional MIBS and modules
$process_port_functions = array('label' => TRUE); // collect processing functions
$process_port_db        = array();                // collect processing db fields

// Additionaly include per MIB functions and snmpwalks (uses include_once)
$port_stats_count = count($port_stats);
$include_lib = TRUE;
$include_dir = "includes/polling/ports/";
include("includes/include-dir-mib.inc.php");

if (count($port_stats))
{
  // Fetch POWER-ETHERNET-MIB and CISCO-POWER-ETHERNET-EXT-MIB if enable_ports_poe is enabled.
  // This data is used in the per-port poe include.
  if ($ports_modules['poe'])
  {
    $port_stats = snmpwalk_cache_oid($device, "pethPsePortEntry", $port_stats, "POWER-ETHERNET-MIB");
    if ($device['os_group'] == 'cisco')
    {
      $port_stats = snmpwalk_cache_oid($device, "cpeExtPsePortEntry", $port_stats, "CISCO-POWER-ETHERNET-EXT-MIB");
    }
  }

  // FIXME This probably needs re-enabled. We need to clear these things when they get unset, too.
  #foreach ($cisco_oids as $oid)     { $port_stats = snmpwalk_cache_oid($device, $oid, $port_stats, "OLD-CISCO-INTERFACES-MIB"); }

  // If the device is cisco, pull a few cisco-specific MIBs and try to get vlan data from CISCO-VTP-MIB
  if ($device['os_group'] == "cisco")
  {
    //FIXME. All PAGP operations should be moved to separate "stacks" module and separate table (not in ports table)
    /* PAgP disabled since r7987, while not moved to new polling style
    foreach ($pagp_oids as $oid)
    {
      $port_stats = snmpwalk_cache_oid($device, $oid, $port_stats, "CISCO-PAGP-MIB");
      // Break if no PAGP tables on device
      if ($oid == 'pagpOperationMode' && $GLOBALS['snmp_status'] === FALSE) { break; }
    }
    */

    // Grab data to put ports into vlans or make them trunks
    // FIXME we probably shouldn't be doing this from the VTP MIB, right?
    $port_stats = snmpwalk_cache_oid($device, "vmVlan", $port_stats, "CISCO-VLAN-MEMBERSHIP-MIB");
    if ($GLOBALS['snmp_status'] === TRUE)
    {
      $port_stats = snmpwalk_cache_oid($device, "vlanTrunkPortEncapsulationOperType", $port_stats, "CISCO-VTP-MIB");
      $port_stats = snmpwalk_cache_oid($device, "vlanTrunkPortNativeVlan", $port_stats, "CISCO-VTP-MIB");
    }
  } else {

    // The port is not Cisco. Try to get VLAN data from Q-BRIDGE-MIB.

    $port_stats = snmpwalk_cache_oid($device, "dot1qPortVlanTable", $port_stats, "Q-BRIDGE-MIB");

    $vlan_ports = snmpwalk_cache_oid($device, "dot1qVlanStaticUntaggedPorts", $vlan_stats, "Q-BRIDGE-MIB");
    if ($GLOBALS['snmp_status'] === TRUE)
    {
      $vlan_ifindex_map = snmpwalk_cache_oid($device, "dot1dBasePortIfIndex", $vlan_stats, "Q-BRIDGE-MIB");
      $vlan_ifindex_min = $vlan_ifindex_map[key($vlan_ifindex_map)]['dot1dBasePortIfIndex'];

      foreach ($vlan_ports as $vlan_id => $instance)
      {
        $parts = explode(' ',$instance['dot1qVlanStaticUntaggedPorts']);
        $binary = '';
        foreach ($parts as $part)
        {
          $binary .= zeropad(base_convert($part, 16, 2),8);
        }
        $length = strlen($binary);
        for ($i = 0; $i < $length; $i++)
        {
          if ($binary[$i])
          {
            $ifindex = $i + $vlan_ifindex_min;
            $q_bridge_untagged[$ifindex] = $vlan_id;
          }
        }
      }
    }
  }

  // End Building SNMP Cache Array

  if (OBS_DEBUG > 1) { print_vars($port_stats); }

  $graphs['bits'] = TRUE;
}

// New interface detection
$ports_ignored_count = 0; // Counting ignored ports
foreach ($port_stats as $ifIndex => $port)
{
  if (!isset($port['ifIndex']))
  {
    // Some ports have only ifXEntry without ifIndex inside
    //$port_stats[$ifIndex]['ifIndex'] = $ifIndex;
    $port['ifIndex'] = $ifIndex;
  }

  if (is_port_valid($port, $device))
  {
    if (!is_array($ports[$port['ifIndex']]))
    {
      $port_id = dbInsert(array('device_id' => $device['device_id'], 'ifIndex' => $ifIndex), 'ports');
      $ports[$port['ifIndex']] = dbFetchRow("SELECT * FROM `ports` WHERE `port_id` = ?", array($port_id));
      print_message("Adding: ".$port['ifName']."(".$ifIndex.")(".$ports[$port['ifIndex']]['port_id'].")");
    }
    else if ($ports[$ifIndex]['deleted'] == "1")
    {
      dbUpdate(array('deleted' => '0'), 'ports', '`port_id` = ?', array($ports[$ifIndex]['port_id']));
      log_event("Interface DELETED mark removed", $device, 'port', $ports[$ifIndex]);
      $ports[$ifIndex]['deleted'] = "0";
    }
  } else {
    if (isset($ports[$port['ifIndex']]) && $ports[$port['ifIndex']]['deleted'] != '1' && $allow_delete_ports)
    {
      dbUpdate(array('deleted' => '1', 'ifLastChange' => date('Y-m-d H:i:s', $polled)), 'ports', '`port_id` = ?', array($ports[$ifIndex]['port_id']));
      log_event("Interface was marked as DELETED", $device, 'port', $ports[$ifIndex]);
      $ports[$ifIndex]['deleted'] = "1";
    }
    $ports_ignored_count++; // Counting ignored ports
  }
}
if (!$allow_delete_ports)
{
  log_event("WARNING! Ports snmpwalk did not complete. Try to increase SNMP timeout on the device properties page.", $device, 'device', $device['device_id'], 7);
}
if ($ports_ignored_count !== $ports_ignored_count_db)
{
  set_entity_attrib('device', $device, 'ports_ignored_count', $ports_ignored_count);
}
// End New interface detection

echo(PHP_EOL . PHP_EOL);

// Loop ports in the DB and update where necessary
foreach ($ports as $port)
{
  // Notes:
  // $port_stats - array of ports from snmpwalks
  // $this_port  - link to port array from snmpwalk
  // $ports      - array of ports based on current db entries
  // $port       - current port array from db
  if ($port['deleted']) { continue; } // Skip updating RRDs and DB if interface marked as DELETED (also skipped bad_if's)

  if ($port_stats[$port['ifIndex']] && $port['disabled'] != "1")
  { // Check to make sure Port data is cached.
    $this_port = &$port_stats[$port['ifIndex']];

    $polled_period = $polled - $port['poll_time'];

    $port['update'] = array();
    $port['state'] = array();

    $port['state']['poll_time'] = $polled;
    $port['state']['poll_period'] = $polled_period;

    // Store original port walked OIDs for debugging later
    if ($config['debug_port']['spikes'] || $config['debug_port'][$port['port_id']])
    {
      $debug_port = $this_port; // DEBUG
    }

    //print_vars($process_port_functions);
    foreach ($process_port_functions as $func => $ok)
    {
      if ($ok && function_exists('process_port_' . $func))
      {
        if (OBS_DEBUG > 1)
        {
          print_debug("Processing port ifIndex ".$this_port['ifIndex']." with function process_port_{$func}() ");
        }
        // Note, used call by array, because parameters for call_user_func() are not passed by reference
        call_user_func_array('process_port_' . $func, array(&$this_port, $device, $port));
      }
    }
    //print_vars($this_port);

#    // Copy ifHC[In|Out] values to non-HC if they exist
#    // Check if they're greater than zero to work around stupid devices which expose HC counters, but don't populate them. HERPDERP. - adama
#    if ($device['os'] == "netapp") { $hc_prefixes = array('HC', '64'); } else { $hc_prefixes = array('HC'); }
#    foreach ($hc_prefixes as $hc_prefix)
#    {
#      foreach (array('Octets', 'UcastPkts', 'BroadcastPkts', 'MulticastPkts') as $hc)
#      {
#        $hcin = 'if'.$hc_prefix.'In'.$hc;
#        $hcout = 'if'.$hc_prefix.'Out'.$hc;
#        if (is_numeric($this_port[$hcin]) && $this_port[$hcin] > 0 && is_numeric($this_port[$hcout]) && $this_port[$hcout] > 0)
#        {
#          // echo(" ".$hc_prefix." $hc, ");
#          $this_port['ifIn'.$hc]  = $this_port[$hcin];
#          $this_port['ifOut'.$hc] = $this_port[$hcout];
#        }
#      }
#    }

    // Here special checks for Upstream/Downstream ports, because it have only In or only Out counters
    if (strpos($this_port['ifType'], 'Upstream') !== FALSE)
    {
      // Upstream has only In counters
      foreach ($upstream_oids as $oid_in)
      {
        $oid_out = str_replace('In', 'Out', $oid_in);
        if (is_numeric($this_port[$oid_in]) && !is_numeric($this_port[$oid_out]))
        {
          $this_port[$oid_out] = 0; // Set it all to zero
        }
      }
    }
    else if (strpos($this_port['ifType'], 'Downstream') !== FALSE)
    {
      // Downstream has only Out counters
      foreach ($downstream_oids as $oid_out)
      {
        $oid_in = str_replace('Out', 'In', $oid_out);
        if (is_numeric($this_port[$oid_out]) && !is_numeric($this_port[$oid_in]))
        {
          $this_port[$oid_in] = 0; // Set it all to zero
        }
      }
    }

    // If we're not using SNMPv1, assumt there are 64-bit values and overwrite the 32-bit OIDs.
    if ($device['snmp_version'] != 'v1')
    {
      $hc_prefix = 'HC';
      $port_has_64bit = is_numeric($this_port['if'.$hc_prefix.'InOctets']) && is_numeric($this_port['if'.$hc_prefix.'OutOctets']);

      // We've never tested for 64bit. Lets do it now. Lots of devices seem to not support 64bit counters for all ports.
      if ($port['port_64bit'] == NULL)
      {
        // We have 64-bit traffic counters. Lets set port_64bit
        if ($port_has_64bit)
        {
          $port['port_64bit'] = 1;
          $port['update']['port_64bit'] = 1;
        } else {
          $port['port_64bit'] = 0;
          $port['update']['port_64bit'] = 0;
        }
      }
      else if ($has_ifXEntry && $port_has_64bit && !$port['port_64bit'])
      {
        // Port changed to 64-bit
        $port['port_64bit'] = 1;
        $port['update']['port_64bit'] = 1;
        log_event('Interface changed: [HC] -> Counter64 (may cause disposable spike)', $device, 'port', $port);
      }

      $port_has_mcbc = is_numeric($this_port['ifInBroadcastPkts']) && is_numeric($this_port['ifOutBroadcastPkts']) &&
                       is_numeric($this_port['ifInMulticastPkts']) && is_numeric($this_port['ifOutMulticastPkts']);

      if ($port['port_mcbc'] == NULL)
      {
        // We have Broadcast/Multicast traffic counters. Lets set port_mcbc
        if ($port_has_mcbc)
        {
          $port['port_mcbc'] = 1;
          $port['update']['port_mcbc'] = 1;
        } else {
          $port['port_mcbc'] = 0;
          $port['update']['port_mcbc'] = 0;
        }
      }
      else if ($has_ifXEntry && $port_has_mcbc && !$port['port_mcbc'])
      {
        // Port acquired multicast/broadcast!
        $port['port_mcbc'] = 1;
        $port['update']['port_mcbc'] = 1;
        log_event('Interface changed: Separated Multicast/Broadcast statistics appeared.', $device, 'port', $port);
      }

      //else if (!$port_has_64bit && $port['port_64bit'])
      //{
      //  // Port changed to 32-bit
      //  $port['port_64bit'] = 0;
      //  $port['update']['port_64bit'] = 0;
      //  log_event('Interface changed: [HC] -> Counter32', $device, 'port', $port);
      //}

      if ($port['port_64bit'])
      {
        print_debug("64-bit, ");
        foreach (array('Octets', 'UcastPkts', 'BroadcastPkts', 'MulticastPkts') as $hc)
        {
          $hcin  = 'if'.$hc_prefix.'In'.$hc;
          $hcout = 'if'.$hc_prefix.'Out'.$hc;
          $this_port['ifIn'.$hc]  = $this_port[$hcin];
          $this_port['ifOut'.$hc] = $this_port[$hcout];
        }
        // Additionally override (In|Out)NUcastPkts
        if (defined('GMP_VERSION'))
        {
          // Better to use GMP extension, for more correct operations with big numbers
          // $a = "18446742978492891134"; $b = "0"; $sum = gmp_add($a, $b); echo gmp_strval($sum) . "\n"; // Result: 18446742978492891134
          // $a = "18446742978492891134"; $b = "0"; $sum = $a + $b; printf("%.0f\n", $sum);               // Result: 18446742978492891136
          $sum                          = gmp_add($this_port['ifInBroadcastPkts'], $this_port['ifInMulticastPkts']);
          $this_port['ifInNUcastPkts']  = gmp_strval($sum);
          $sum                          = gmp_add($this_port['ifOutBroadcastPkts'], $this_port['ifOutMulticastPkts']);
          $this_port['ifOutNUcastPkts'] = gmp_strval($sum);
        } else {
          $this_port['ifInNUcastPkts']  = $this_port['ifInBroadcastPkts']  + $this_port['ifInMulticastPkts'];
          $this_port['ifOutNUcastPkts'] = $this_port['ifOutBroadcastPkts'] + $this_port['ifOutMulticastPkts'];
          // Convert this values to int string, for prevent rrd update error with big Counter64 numbers,
          // see: http://jira.observium.org/browse/OBSERVIUM-1749
          $this_port['ifInNUcastPkts']  = sprintf("%.0f", $this_port['ifInNUcastPkts']);
          $this_port['ifOutNUcastPkts'] = sprintf("%.0f", $this_port['ifOutNUcastPkts']);
        }
      }
    }

    // rewrite the ifPhysAddress
    // IF-MIB::ifPhysAddress.2 = STRING: 66:c:9b:1b:62:7e
    // IF-MIB::ifPhysAddress.2 = Hex-STRING: 00 02 99 09 E9 84
    $this_port['ifPhysAddress'] = mac_zeropad($this_port['ifPhysAddress']);

    // ifSpeed processing
    if (isset($port['ifSpeed_custom']) && $port['ifSpeed_custom'] > 0)
    {
      // Custom ifSpeed from WebUI
      $this_port['ifSpeed'] = intval($port['ifSpeed_custom']);
      print_debug('Port ifSpeed manually set.');
    } else {
      if (is_numeric($this_port['ifHighSpeed']))
      {
        if ($this_port['ifHighSpeed'] == '0' && $port['ifHighSpeed'] > '0')
        {
          // Use old ifHighSpeed if current speed '0', seems as some error on device
          $this_port['ifHighSpeed'] = $port['ifHighSpeed'];
          print_debug('Port ifHighSpeed fixed from zero.');
        }

        // Maximum possible ifSpeed value is 4294967295
        // Overwrite ifSpeed with ifHighSpeed if it's over 4G or ifSpeed equals to zero
        // ifSpeed is more accurate for low speeds (ie: ifSpeed.60 = 1536000, ifHighSpeed.60 = 2)
        $ifSpeed_max = max($this_port['ifHighSpeed'] * 1000000, $this_port['ifSpeed']);
        if ($this_port['ifHighSpeed'] > 0 && ($ifSpeed_max > 4000000000 || $this_port['ifSpeed'] == 0))
        {
          // echo("HighSpeed, ");
          $this_port['ifSpeed'] = $ifSpeed_max;
        }
      }
      if ($this_port['ifSpeed'] == '0' && $port['ifSpeed'] > '0')
      {
        // Use old ifSpeed if current speed '0', seems as some error on device
        $this_port['ifSpeed'] = $port['ifSpeed'];
        print_debug('Port ifSpeed fixed from zero.');
      }
    }

    // Set VLAN and Trunk from Cisco
    if (isset($this_port['vlanTrunkPortEncapsulationOperType']) && $this_port['vlanTrunkPortEncapsulationOperType'] != "notApplicable")
    {
      $this_port['ifTrunk'] = $this_port['vlanTrunkPortEncapsulationOperType'];
      if (isset($this_port['vlanTrunkPortNativeVlan'])) { $this_port['ifVlan'] = $this_port['vlanTrunkPortNativeVlan']; }
    }

    if (isset($this_port['vmVlan']))
    {
      $this_port['ifVlan']  = $this_port['vmVlan'];
    }

    // Set VLAN and Trunk from Q-BRIDGE-MIB
    if (!isset($this_port['ifVlan']) && $q_bridge_untagged[$this_port['ifIndex']])
    {
      $this_port['ifVlan'] = $q_bridge_untagged[$this_port['ifIndex']];
    }
    if (!isset($this_port['ifVlan']) && isset($this_port['dot1qPvid']))
    {
      $this_port['ifVlan'] = $this_port['dot1qPvid'];
      if ((isset($this_port['dot1qPortIngressFiltering']) && $this_port['dot1qPortIngressFiltering'] == 'true') ||
          (isset($this_port['dot1qPortAcceptableFrameTypes']) && $this_port['dot1qPortAcceptableFrameTypes'] == 'admitOnlyVlanTagged'))
      {
        $this_port['ifTrunk'] = 'dot1Q';
      }
    }

    if ($this_port['ifVlan'])
    {
      //print_cli_data("VLAN", $this_port['ifVlan'], $cli_level);
      $this_port['vlan_id'] = $this_port['ifVlan'];
    }

    if ($this_port['ifTrunk'])
    {
      //print_cli_data("Trunk", $this_port['ifTrunk'], $cli_level);
      $this_port['vlan_id'] .= " ".$this_port['ifTrunk'];
    }

    // Update TrustSec
    if ($this_port['encrypted'])
    {
      if ($port['encrypted'] === '0')
      {
        log_event("Interface is now encrypted", $device, 'port', $port);
        $port['update']['encrypted'] = '1';
      }
    }
    else if ($port['encrypted'] === '1')
    {
        log_event("Interface is no longer encrypted", $device, 'port', $port);
        $port['update']['encrypted'] = '0';
    }

    // Make sure ifOperStatus is valid (FIXME. not exist statuses already "filtered" in is_port_valid())
    if (isset($this_port['ifOperStatus']) &&
        !in_array($this_port['ifOperStatus'], array('testing', 'notPresent', 'dormant', 'down', 'lowerLayerDown', 'unknown', 'up', 'monitoring')))
    {
      $this_port['ifOperStatus'] = 'unknown';
    }

    if (isset($this_port['ifAdminStatus']) &&
       !in_array($this_port['ifAdminStatus'], array('up', 'down', 'testing')))
    {
      $this_port['ifAdminStatus'] = ''; // or NULL?
    }

    if (isset($this_port['ifConnectorPresent']) &&
       !in_array($this_port['ifConnectorPresent'], array('true', 'false')))
    {
      $this_port['ifConnectorPresent'] = NULL;
    }

    // Update IF-MIB data

    $log_event = array();
    foreach ($data_oids_db as $oid)
    {
      if ($port[$oid] != $this_port[$oid])
      {
        if (isset($this_port[$oid]))
        {
          $port['update'][$oid] = $this_port[$oid];
          $msg = "[$oid] '" . $port[$oid] . "' -> '" . $this_port[$oid] . "'";
        } else {
          $port['update'][$oid] = array('NULL');
          $msg = "[$oid] '" . $port[$oid] . "' -> NULL";
        }
        if ($oid == 'ifOperStatus' && ($port[$oid] == 'up' || $port[$oid] == 'down') && isset($this_port[$oid]))
        {
          // Specific log_event for port Up/Down
          log_event('Interface '.ucfirst($this_port[$oid]) . ": [$oid] '" . $port[$oid] . "' -> '" . $this_port[$oid] . "'", $device, 'port', $port, 'warning');
        } else {
          $log_event[] = $msg;
        }
        if (OBS_DEBUG) { echo($msg." "); } // else { echo($oid . " "); }
      }
    }

    // ifLastChange
    if (isset($this_port['ifLastChange']) && $this_port['ifLastChange'] != '')
    {
      // Convert ifLastChange from timetick to timestamp
      /**
       * The value of sysUpTime at the time the interface entered
       * its current operational state. If the current state was
       * entered prior to the last re-initialization of the local
       * network management subsystem, then this object contains a
       * zero value.
       *
       * NOTE, observium uses last change timestamp.
       */
      $if_lastchange_uptime = timeticks_to_sec($this_port['ifLastChange']);
      if (($device_uptime['sysUpTime'] - $if_lastchange_uptime) > 90)
      {
        $if_lastchange = $device_uptime['polled'] - $device_uptime['sysUpTime'] + $if_lastchange_uptime;
        print_debug('IFLASTCHANGE = '.$device_uptime['polled'].'s - '.$device_uptime['sysUpTime'].'s + '.$if_lastchange_uptime.'s');
        if (abs($if_lastchange - strtotime($port['ifLastChange'])) > 90)
        {
          // Compare lastchange with previous, update only if more than 60 sec (for exclude random dispersion)
          $port['update']['ifLastChange'] = date('Y-m-d H:i:s', $if_lastchange); // Convert to timestamp
        }
      } else {
        // Device sysUpTime more than if uptime or too small difference.. impossible, seems as bug on device
        $if_lastchange_uptime = FALSE;
      }
    } else {
      // ifLastChange not exist
      $if_lastchange_uptime = FALSE;
    }

    if ($if_lastchange_uptime === FALSE)
    {
      if (empty($port['ifLastChange']) || $port['ifLastChange'] == '0000-00-00 00:00:00' || // Newer set (first time)
          isset($port['update']['ifOperStatus']) || isset($port['update']['ifAdminStatus']) || isset($port['update']['ifSpeed']) || isset($port['update']['ifDuplex']))
      {
        $port['update']['ifLastChange'] = date('Y-m-d H:i:s', $polled);
      }
      print_debug("IFLASTCHANGE unknown/false, used system times.");
    }
    if (isset($port['update']['ifLastChange']))
    {
      print_debug("IFLASTCHANGE (" . $port['ifIndex'] . "): " . $port['update']['ifLastChange']);
      if ($port['ifLastChange'] && $port['ifLastChange'] != '0000-00-00 00:00:00' && count($log_event))
      {
        $log_event[] = "[ifLastChange] '" . $port['ifLastChange'] . "' -> '" . $port['update']['ifLastChange'] . "'";
      }
    }
    if ((bool)$log_event) { log_event('Interface changed: ' . implode('; ', $log_event), $device, 'port', $port); }

    // Parse description (usually ifAlias) if config option set
    if ($custom_port_parser)
    {
      $log_event = array();
      if ($custom_port_parser !== 'old')
      {
        $port_ifAlias = custom_port_parser($this_port);
      } else {
        $custom_port_attribs = array('type', 'descr', 'circuit', 'speed', 'notes');

        include($config['install_dir'] . "/" . $config['port_descr_parser']);
      }

      foreach ($custom_port_attribs as $attrib)
      {
        $attrib_key = "port_descr_".$attrib;
        if ($port_ifAlias[$attrib] != $port[$attrib_key])
        {
          if (isset($port_ifAlias[$attrib]))
          {
            $port['update'][$attrib_key] = $port_ifAlias[$attrib];
            $msg = "[$attrib] " . $port[$attrib_key] . " -> " . $port_ifAlias[$attrib];
          } else {
            $port['update'][$attrib_key] = array('NULL');
            $msg = "[$attrib] " . $port[$attrib_key] . " -> NULL";
          }
          $log_event[] = $msg;
        }
      }
      if ((bool)$log_event) { log_event('Interface changed (attrib): ' . implode('; ', $log_event), $device, 'port', $port); }
    }
    // End parse ifAlias

    // Update IF-MIB metrics
    foreach ($stat_oids_db as $oid)
    {
      $port['state'][$oid] = $this_port[$oid];
      if (isset($port[$oid]) && is_numeric($port[$oid]))
      {
        $oid_diff = $this_port[$oid] - $port[$oid];
        $oid_rate  = $oid_diff / $polled_period;
        if ($oid_rate < 0)
        {
          print_warning("Negative $oid. Possible spike on next poll!");
          $port['stats'][$oid.'_negative_rate'] = $oid_rate;
          $oid_rate = "0";
        }
        $port['stats'][$oid.'_rate'] = $oid_rate;


          // Perhaps need to protect these from false polls.
          $port['alert_array'][$oid.'_rate']  = $oid_rate;
          $port['alert_array'][$oid.'_delta'] = $oid_diff;

          $port['stats'][$oid.'_diff'] = $oid_diff;
          $port['state'][$oid.'_rate'] = $oid_rate;

          // Record delta in database only for In/Out errors.
          if($oid == "ifInErrors" || $oid == "ifOutErrors")
          {
            $port['state'][$oid.'_delta'] = $oid_diff;
          }


        print_debug("\n $oid ($oid_diff B) $oid_rate Bps $polled_period secs");
      }

    }

    foreach ($stat_oids as $oid)
    {
      // Update StatsD/Carbon
      if ($config['statsd']['enable'] == TRUE && !strpos($oid, "HC") && is_numeric($this_port[$oid]))
      {
        StatsD::gauge(str_replace(".", "_", $device['hostname']).'.'.'port'.'.'.$port['ifIndex'].'.'.$oid, $this_port[$oid]);
      }
    }

    $port['stats']['ifInBits_rate']  = round($port['stats']['ifInOctets_rate']  * 8);
    $port['stats']['ifOutBits_rate'] = round($port['stats']['ifOutOctets_rate'] * 8);

    $port['alert_array']['ifInBits_rate'] =  $port['stats']['ifInBits_rate'];
    $port['alert_array']['ifOutBits_rate'] =  $port['stats']['ifOutBits_rate'];

    // If we have been told to debug this port, output the counters we collected earlier, with the rates stuck on the end.
    if ($config['debug_port'][$port['port_id']])
    {
      print_debug("Wrote port debugging data");
      $debug_file   = "/tmp/port_debug_".$port['port_id'].".txt";
      //FIXME. I think formatted debug out (as for spikes) more informative, but output here more parsable as CSV
      $port_msg  = $port['port_id']."|".$polled."|".$polled_period."|".$debug_port['ifInOctets']."|".$debug_port['ifOutOctets']."|".$debug_port['ifHCInOctets']."|".$debug_port['ifHCOutOctets'];
      $port_msg .= "|".formatRates($port['stats']['ifInOctets_rate'])."|".formatRates($port['stats']['ifOutOctets_rate'])."|".$device['snmp_version']."\n";
      file_put_contents($debug_file, $port_msg, FILE_APPEND);
    }

    // If we see a spike above ifSpeed or negative rate, output it to /tmp/port_debug_spikes.txt
    // Example how to read usefull info from this debug by grep:
    // grep -B 1 -A 6 'ID:\ 520' /tmp/port_debug_spikes.txt
    if ($config['debug_port']['spikes'] && $this_port['ifSpeed'] > "0" &&
        ($port['stats']['ifInBits_rate'] > $this_port['ifSpeed'] || $port['stats']['ifOutBits_rate'] > $this_port['ifSpeed'] ||
         isset($port['stats']['ifInOctets_negative_rate'])       || isset($port['stats']['ifOutOctets_negative_rate'])))
    {
      if (!$port['port_64bit']) { $hc_prefix = ''; }
      print_warning("Spike above ifSpeed or negative rate detected! See debug info here: ");
      $debug_file   = "/tmp/port_debug_spikes.txt";
      $debug_format = "| %20s | %20s | %20s |\n";
      $debug_msg  = sprintf("+%'-68s+\n", '');
      $debug_msg .= sprintf("|%67s |\n", $device['hostname']." ".$debug_port['ifDescr']." (ID: ".$port['port_id'].") ".formatRates($debug_port['ifSpeed'])." ".($port['port_64bit'] ? 'Counter64' : 'Counter32'));
      $debug_msg .= sprintf("+%'-68s+\n", '');
      $debug_msg .= sprintf("| %-20s | %-20s | %-20s |\n", 'Polled time', 'if'.$hc_prefix.'OutOctets', 'if'.$hc_prefix.'InOctets');
      $debug_msg .= sprintf($debug_format, '(prev) '.$port['poll_time'], $port['ifOutOctets'], $port['ifInOctets']);
      $debug_msg .= sprintf($debug_format, '(now)  '.$polled, $this_port['ifOutOctets'], $this_port['ifInOctets']);
      $debug_msg .= sprintf($debug_format, format_unixtime($polled), formatRates($port['stats']['ifOutBits_rate']*8), formatRates($port['stats']['ifInBits_rate']));
      $debug_msg .= sprintf("%'+70s\n", '');
      $debug_msg .= sprintf("| %-67s|\n", 'Port dump:');
      // Added full original port variable dump
      foreach ($debug_port as $debug_key => $debug_var)
      {
        $debug_msg .= sprintf("|  %-66s|\n", "'$debug_key' => '$debug_var',");
      }
      $debug_msg .= sprintf("+%'-68s+\n\n", '');
      file_put_contents($debug_file, $debug_msg, FILE_APPEND);
    }

    // Put States into alert array
    foreach (array('ifOperStatus', 'ifAdminStatus', 'ifMtu', 'ifDuplex', 'ifVlan') as $oid)
    {
      if (isset($this_port[$oid]))
      {
        $port['alert_array'][$oid] = $this_port[$oid];
      }
    }

    // If we have a valid ifSpeed we should populate the percentage stats for checking.
    if (is_numeric($this_port['ifSpeed']))
    {
      $port['stats']['ifInBits_perc'] = round($port['stats']['ifInBits_rate'] / $this_port['ifSpeed'] * 100);
      $port['stats']['ifOutBits_perc'] = round($port['stats']['ifOutBits_rate'] / $this_port['ifSpeed'] * 100);
      $port['alert_array']['ifSpeed'] = $this_port['ifSpeed'];
    }

    if (is_numeric($this_port['ifHighSpeed']))
    {
      $port['alert_array']['ifHighSpeed'] = $this_port['ifHighSpeed'];
    }

    $port['state']['ifInOctets_perc'] = $port['stats']['ifInBits_perc'];
    $port['state']['ifOutOctets_perc'] = $port['stats']['ifOutBits_perc'];

    $port['alert_array']['ifInOctets_perc'] = $port['stats']['ifInBits_perc'];
    $port['alert_array']['ifOutOctets_perc'] = $port['stats']['ifOutBits_perc'];

    $port['alert_array']['rx_ave_pktsize']   = $port['state']['ifInOctets_delta'] / ($port['state']['ifInUcastPkts_delta'] + $port['state']['ifInNUcastPkts_delta']);
    $port['alert_array']['tx_ave_pktsize']   = $port['state']['ifOutOctets_delta'] / ($port['state']['ifOutUcastPkts_delta'] + $port['state']['ifOutNUcastPkts_delta']);

    // Store aggregate in/out state
    $port['state']['ifOctets_rate']    = $port['stats']['ifOutOctets_rate'] + $port['stats']['ifInOctets_rate'];
    $port['state']['ifUcastPkts_rate'] = $port['stats']['ifOutUcastPkts_rate'] + $port['stats']['ifInUcastPkts_rate'];
    $port['state']['ifErrors_rate'] = $port['stats']['ifOutErrors_rate'] + $port['stats']['ifInErrors_rate'];
    $port['state']['ifDiscards_rate'] = $port['stats']['ifOutDiscards_rate'] + $port['stats']['ifInDiscards_rate'];

    // Send aggregate data to alerter too
    $port['alert_array']['ifOctets_rate']        = $port['state']['ifOctets_rate'];
    $port['alert_array']['ifUcastPkts_rate']     = $port['state']['ifUcastPkts_rate'];
    $port['alert_array']['ifNUcastPkts_rate']    = $port['stats']['ifOutNUcastPkts_rate'] + $port['stats']['ifInNUcastPkts_rate'];
    $port['alert_array']['ifErrors_rate']        = $port['state']['ifErrors_rate'];
    $port['alert_array']['ifBroadcastPkts_rate'] = $port['stats']['ifOutBroadcastPkts_rate'] + $port['stats']['ifInBroadcastPkts_rate'];
    $port['alert_array']['ifMulticastPkts_rate'] = $port['stats']['ifOutMulticastPkts_rate'] + $port['stats']['ifInMulticastPkts_rate'];
    $port['alert_array']['ifDiscards_rate']      = $port['stats']['ifOutDiscards_rate'] + $port['stats']['ifInDiscards_rate'];

    // Update RRDs
    rrdtool_update_ng($device, 'port', array(
      'INOCTETS'         => $this_port['ifInOctets'],
      'OUTOCTETS'        => $this_port['ifOutOctets'],
      'INERRORS'         => $this_port['ifInErrors'],
      'OUTERRORS'        => $this_port['ifOutErrors'],
      'INUCASTPKTS'      => $this_port['ifInUcastPkts'],
      'OUTUCASTPKTS'     => $this_port['ifOutUcastPkts'],
      'INNUCASTPKTS'     => $this_port['ifInNUcastPkts'],
      'OUTNUCASTPKTS'    => $this_port['ifOutNUcastPkts'],
      'INDISCARDS'       => $this_port['ifInDiscards'],
      'OUTDISCARDS'      => $this_port['ifOutDiscards'],
      'INUNKNOWNPROTOS'  => $this_port['ifInUnknownProtos'],
      'INBROADCASTPKTS'  => $this_port['ifInBroadcastPkts'],
      'OUTBROADCASTPKTS' => $this_port['ifOutBroadcastPkts'],
      'INMULTICASTPKTS'  => $this_port['ifInMulticastPkts'],
      'OUTMULTICASTPKTS' => $this_port['ifOutMulticastPkts'],
    ), get_port_rrdindex($port));

    // End Update IF-MIB

    // Update additional MIBS and modules
    foreach ($process_port_db as $port_module => $oids)
    {
      $log_event = array();
      foreach ($oids as $oid)
      {
        if ($port[$oid] != $this_port[$oid])
        {
          if (isset($this_port[$oid]))
          {
            $port['update'][$oid] = $this_port[$oid];
            $msg = "[$oid] '" . $port[$oid] . "' -> '" . $this_port[$oid] . "'";
          } else {
            $port['update'][$oid] = array('NULL');
            $msg = "[$oid] '" . $port[$oid] . "' -> NULL";
          }
          $log_event[] = $msg;
          if (OBS_DEBUG) { echo($msg." "); }
        }
      }
      if ((bool)$log_event) { log_event('Interface changed ('.$port_module.'): ' . implode('; ', $log_event), $device, 'port', $port); }
    }
    // End update additional MIBS

    /* PAgP disabled since r7987, while not moved to new polling style
    // Update PAgP
    if ($this_port['pagpOperationMode'] || $port['pagpOperationMode'])
    {
      $log_event = array();
      foreach ($pagp_oids as $oid)
      { // Loop the OIDs
        if ($this_port[$oid] != $port[$oid])
        { // If data has changed, build a query
          $port['update'][$oid] = $this_port[$oid];
          $log_event[] = "[$oid] " . $port[$oid] . " -> " . $this_port[$oid];
        }
      }
      if ((bool)$log_event) { log_event('Interface changed (pagp): ' . implode('; ', $log_event), $device, 'port', $port); }
    }
    // End Update PAgP
    */

    // Do PoE MIBs
    if ($ports_modules['poe']) { include("port-poe.inc.php"); }

#    if (OBS_DEBUG > 1) { print_vars($port['alert_array']); echo(PHP_EOL); print_vars($this_port);}

    check_entity('port', $port, $port['alert_array']);

    // Send statistics array via AMQP/JSON if AMQP is enabled globally and for the ports module
    if ($config['amqp']['enable'] == TRUE && $config['amqp']['modules']['ports'])
    {
      $json_data = array_merge($this_port, $port['state']) ;
      unset($json_data['rrd_update']); // FIXME unset no longer needed when switched to rrdtool_update_ng() !
      messagebus_send(array('attribs' => array('t' => $polled, 'device' => $device['hostname'], 'device_id' => $device['device_id'], 'e_type' => 'port', 'e_index' => $port['ifIndex']), 'data' => $json_data));
      unset($json_data);
    }

#    // Do Alcatel Detailed Stats
#    if ($device['os'] == "aos") { include("port-alcatel.inc.php"); }

    // Unified state update

    $port['update'] = array_merge($port['state'], $port['update']);
    $updated = dbUpdate($port['update'], 'ports', '`port_id` = ?', array($port['port_id']));

    // Update Database
    if (count($port['update']))
    {
      //$updated = dbUpdate($port['update'], 'ports', '`port_id` = ?', array($port['port_id']));
      //print_debug("PORT updated rows=$updated");
    }

    // Update State
    if (count($port['state']))
    {
      if (empty($port['poll_time']))
      {
        // Initial state insert
        //$state_insert = $port['state'];
        //$state_insert['port_id'] = $port['port_id'];
        //$insert = dbInsert($state_insert, 'ports-state');
        //if ($insert === FALSE) { print_error("Certain MEMORY DB error for table 'ports-state'."); }
        //else { print_debug("STATE inserted port_id=".$port['port_id']); }
        //unset($state_insert);
      } else {
        //$updated = dbUpdate($port['state'], 'ports-state', '`port_id` = ?', array($port['port_id']));
        //print_debug("STATE updated rows=$updated");
      }
    }

    // Add table row

    $table_row = array();
    $table_row[] = $port['ifIndex'];
    $table_row[] = $port['port_label_short'];
    $table_row[] = rewrite_iftype($port['ifType']);
    $table_row[] = formatRates($port['ifSpeed']);
    $table_row[] = formatRates($port['stats']['ifInBits_rate']);
    $table_row[] = formatRates($port['stats']['ifOutBits_rate']);
    $table_row[] = formatStorage($port['stats']['ifInOctets_diff']);
    $table_row[] = formatStorage($port['stats']['ifOutOctets_diff']);
    $table_row[] = format_si($port['stats']['ifInUcastPkts_rate']);
    $table_row[] = format_si($port['stats']['ifOutUcastPkts_rate']);
    $table_row[] = ($port['port_64bit'] ? "%gY%w" : "%rN%w");
    $table_rows[] = $table_row;
    unset($table_row);

    // End Update Database
  }
  elseif ($port['disabled'] != "1")
  {
    print_message("Port Deleted."); // Port missing from SNMP cache.
    if (isset($port['ifIndex']) && $port['deleted'] != "1")
    {
      dbUpdate(array('deleted' => '1', 'ifLastChange' => date('Y-m-d H:i:s', $polled)), 'ports',  '`device_id` = ? AND `ifIndex` = ?', array($device['device_id'], $port['ifIndex']));
      log_event("Interface was marked as DELETED", $device, 'port', $port);
    }
  } else {
    print_message("Port Disabled.");
  }

  //echo("\n");

  // Clear Per-Port Variables Here
  unset($this_port);
}

$headers = array('%WifIndex%n', '%WLabel%n', '%WType%n', '%WSpeed%n', '%WBPS In%n', '%WBPS Out%n', '%WData In%n', '%WData Out%n', '%WPPS In%n', '%WPPS Out%n', '%WHC%n');
print_cli_table($table_rows, $headers);

echo(PHP_EOL);

// Clear Variables Here
unset($port_stats, $process_port_functions, $process_port_db, $has_ifEntry, $has_ifXEntry, $has_ifEntry_error_code, $ports_ignored_count, $ports_ignored_count_db);

// EOF
