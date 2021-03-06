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

$huawei['sensors_names'] = snmpwalk_cache_oid($device, 'hwEntityBomEnDesc',   array(), 'HUAWEI-ENTITY-EXTENT-MIB');
$huawei['temp']          = snmpwalk_cache_oid($device, 'hwEntityTemperature', array(), 'HUAWEI-ENTITY-EXTENT-MIB');
$huawei['fan']           = snmpwalk_cache_oid($device, 'HwFanStatusEntry',    array(), 'HUAWEI-ENTITY-EXTENT-MIB');

foreach ($huawei['temp'] as $index => $entry)
{
  $oid = ".1.3.6.1.4.1.2011.5.25.31.1.1.1.1.11.$index";
  $descr = explode(',', $huawei['sensors_names'][$index]['hwEntityBomEnDesc']);
  $value = $entry['hwEntityTemperature'];
  if ($entry['hwEntityTemperature'] > 0 && $value <= 1000)
  {
    $options = array('limit_high' => snmp_get($device, "hwEntityTemperatureThreshold.$index", '-Osqv', 'HUAWEI-ENTITY-EXTENT-MIB'));
    discover_sensor($valid['sensor'], 'temperature', $device, $oid, $index, 'huawei', $descr[0], 1, $value, $options);
  }
}
unset($options);

foreach ($huawei['fan'] as $index => $entry)
{
  $oid   = '.1.3.6.1.4.1.2011.5.25.31.1.1.10.1.5.'.$index;
  $fanstateoid = '.1.3.6.1.4.1.2011.5.25.31.1.1.10.1.7.'.$index;
  $value = $entry['hwEntityFanSpeed'];
  $descr = 'Slot '.$entry['hwEntityFanSlot'].' Fan '.$entry['hwEntityFanSn'];
  if ($entry['hwEntityFanSpeed'] > 0)
  {
    discover_sensor($valid['sensor'], 'load', $device, $oid, $index, 'huawei', $descr, 1, $value);
    discover_status($device, $fanstateoid, $index, 'huawei-entity-ext-mib-fan-state', $descr, $entry['hwEntityFanState'], array('entPhysicalClass' => 'fan'));
  }
}

// Optical sensors
$entity_array   = snmpwalk_cache_oid($device, 'HwOpticalModuleInfoEntry', array(), 'HUAWEI-ENTITY-EXTENT-MIB');

if (OBS_DEBUG > 1 && count($entity_array))
{
  print_vars($entity_array);
}

foreach ($entity_array as $index => $entry)
{
  $port    = get_port_by_ent_index($device, $index);
  $options = array('entPhysicalIndex' => $index);
  if (is_array($port))
  {
    $entry['ifDescr']            = $port['ifDescr'];
    $options['measured_class']   = 'port';
    $options['measured_entity']  = $port['port_id'];
    $options['entPhysicalIndex_measured'] = $port['ifIndex'];
  } else {
    // Skip?
    continue;
  }

  $temperatureoid = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.5.'.$index;
  $voltageoid     = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.6.'.$index;
  $biascurrentoid = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.7.'.$index;
  $rxpoweroid     = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.8.'.$index;
  $txpoweroid     = '.1.3.6.1.4.1.2011.5.25.31.1.1.3.1.9.'.$index;

  //Ignore optical sensors with temperature of zero or negative
  if ($entry['hwEntityOpticalTemperature'] > 1)
  {
    discover_sensor($valid['sensor'], 'temperature', $device, $temperatureoid, $index, 'huawei', $entry['ifDescr'] . ' Temperature',          1, $entry['hwEntityOpticalTemperature'], $options);
    discover_sensor($valid['sensor'], 'voltage',     $device, $voltageoid,     $index, 'huawei', $entry['ifDescr'] . ' Voltage',          0.001, $entry['hwEntityOpticalVoltage'],     $options);
    discover_sensor($valid['sensor'], 'current',     $device, $biascurrentoid, $index, 'huawei', $entry['ifDescr'] . ' Bias Current ', 0.000001, $entry['hwEntityOpticalBiasCurrent'], $options);
    // Huawei does not follow their own MIB for some devices and instead reports Rx/Tx Power as dBm converted to mW then multiplied by 1000
    if ($entry['hwEntityOpticalRxPower'] >= 0)
    {
      discover_sensor($valid['sensor'], 'power', $device, $rxpoweroid, 'hwEntityOpticalRxPower.' . $index, 'huawei', $entry['ifDescr'] . ' Rx Power', 0.000001, $entry['hwEntityOpticalRxPower'], $options);
      discover_sensor($valid['sensor'], 'power', $device, $txpoweroid, 'hwEntityOpticalTxPower.' . $index, 'huawei', $entry['ifDescr'] . ' Tx Power', 0.000001, $entry['hwEntityOpticalTxPower'], $options);
    } else {
      discover_sensor($valid['sensor'], 'dbm',   $device, $rxpoweroid, 'hwEntityOpticalRxPower.' . $index, 'huawei', $entry['ifDescr'] . ' Rx Power',     0.01, $entry['hwEntityOpticalRxPower'], $options);
      discover_sensor($valid['sensor'], 'dbm',   $device, $txpoweroid, 'hwEntityOpticalTxPower.' . $index, 'huawei', $entry['ifDescr'] . ' Tx Power',     0.01, $entry['hwEntityOpticalTxPower'], $options);
    }
  }

}

unset($entity_array, $huawei);

// EOF
