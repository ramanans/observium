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

// Dell-Vendor-MIB::productIdentificationDisplayName.0 = STRING: PowerConnect 5324
// Dell-Vendor-MIB::productIdentificationDescription.0 = STRING: Neyland 24T
// Dell-Vendor-MIB::productIdentificationVersion.0 = STRING: 2.0.1.3
// Dell-Vendor-MIB::productIdentificationServiceTag.1 = STRING: 8D4XY51
if (strpos($hardware, 'Ethernet Switch') !== FALSE) // Sometimes DisplayName and Description is switched
{
  // Dell-Vendor-MIB::productIdentificationDisplayName.0 = STRING: Ethernet Switch
  // Dell-Vendor-MIB::productIdentificationDescription.0 = STRING: PowerConnect 5448
  $hardware = 'Dell '.snmp_get($device, 'productIdentificationDescription.0', '-Ovq', 'Dell-Vendor-MIB');
}

$serial   = implode(', ',explode("\n",snmp_walk($device, 'productIdentificationServiceTag', '-Ovq', 'Dell-Vendor-MIB')));
$icon     = 'dell';

// EOF
