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


$mempool['used']  = snmp_get($device, '.1.3.6.1.4.1.2352.2.16.1.2.1.4.1', '-OvQ', 'RBN-MEMORY-MIB');
$mempool['free']  = snmp_get($device, '.1.3.6.1.4.1.2352.2.16.1.2.1.3.1', '-OvQ', 'RBN-MEMORY-MIB');
$mempool['total'] = $mempool['used'] + $mempool['free'];

// EOF
