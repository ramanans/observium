<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage graphs
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

$total_units     = "B";
//$multiplier      = "8";

$graph_title     = "Tail Dropped Packets";
$colours         = 'reds';

$ds              = 'TailDropPkts';

include("jnx_cos_queues_common.inc.php");

