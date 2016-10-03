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

include_once($config['html_dir']."/includes/graphs/common.inc.php");

$colours      = "mixed";
$nototal      = 1;
$unit_text    = "Connections";
$rrd_filename = get_rrd_path($device, "wmi-app-mssql_".$app['app_instance']."-stats.rrd");

if (is_file($rrd_filename))
{
  $rrd_list[0]['filename'] = $rrd_filename;
  $rrd_list[0]['descr']    = "User Connections";
  $rrd_list[0]['ds']       = "userconnections";

} else {
  echo("file missing: $file");
}

include($config['html_dir']."/includes/graphs/generic_multi_line.inc.php");

// EOF
