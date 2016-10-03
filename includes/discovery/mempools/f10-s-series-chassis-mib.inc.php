<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage discovery
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

// Force10 S-Series

#F10-S-SERIES-CHASSIS-MIB::chStackUnitMemUsageUtil.1 = Gauge32: 86

$mempool_array = snmpwalk_cache_oid($device, 'chStackUnitMemUsageUtil', array(), $mib);
if (is_array($mempool_array))
{
  $mempool_array = snmpwalk_cache_oid($device, 'chStackUnitSysType', $mempool_array, $mib);
  $total_array   = snmpwalk_cache_oid($device, 'chSysProcessorMemSize', array(), $mib);
  if (OBS_DEBUG > 1 && count($total_array)) { print_vars($total_array); }
  foreach ($mempool_array as $index => $entry)
  {
    if (is_numeric($entry['chStackUnitMemUsageUtil']))
    {
      if (is_numeric($total_array[$index]['chSysProcessorMemSize']))
      {
        $precision = 1024 * 1024;
        $total     = $total_array[$index]['chSysProcessorMemSize']; // FTOS display memory in MB
        //$total    *= $precision;
      } else {
        $precision = 1;
        $total     = 1090519040;
      }
      $percent = $entry['chStackUnitMemUsageUtil'];
      $used    = $total * $percent / 100;
      $descr = 'Unit ' . strval($index - 1) . ' ' . $entry['chStackUnitSysType'];
      discover_mempool($valid['mempool'], $device, $index, 'F10-S-SERIES-CHASSIS-MIB', $descr, $precision, $total, $used);
    }
  }
}

unset ($mempool_array, $total_array, $index, $descr, $precision, $total, $used, $percent);

// EOF
