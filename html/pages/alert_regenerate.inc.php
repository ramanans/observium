<?php

/**
 * Observium Network Management and Monitoring System
 * Copyright (C) 2006-2015, Adam Armstrong - http://www.observium.org
 *
 * @package    observium
 * @subpackage webui
 * @author     Adam Armstrong <adama@observium.org>
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

// Global write permissions required.
if ($_SESSION['userlevel'] < 10)
{
  print_error_permission();
  return;
}

include($config['html_dir']."/includes/alerting-navbar.inc.php");

  // Regenerate alerts

  echo generate_box_open();

  foreach (dbFetchRows("SELECT * FROM `devices`") as $device)
  {
    $result = update_device_alert_table($device);
    print_message($result['message'], $result['class']);
    del_obs_attrib('alerts_require_rebuild');
  }

  echo generate_box_close();

unset($vars['action']);

// EOF
