<?php
/**
 * ioc2rpz.gui - RpiDNS Configuration Download Endpoint
 * 
 * Generates and serves installation scripts for RpiDNS devices.
 * This endpoint is intentionally unauthenticated because it is called
 * via CLI (curl/wget) on target servers during provisioning.
 * Access is protected by the UUID being a cryptographically random secret.
 * 
 * @package ioc2rpz.gui
 * @author Vadim Pavlov
 * @copyright 2019-2026
 * @license MIT
 */
#(c) Vadim Pavlov 2019-2026

require_once 'io2vars.php';
require_once 'io2fun.php';

$REQUEST=getRequest();

switch ($REQUEST['method'].' '.$REQUEST["req"]):
	case "GET rpidns_config":
    // Validate UUID format before using in queries
    if (empty($REQUEST['uuid']) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $REQUEST['uuid'])) {
      header('Content-Type: application/json');
      echo '{"status":"failed","reason":"Invalid UUID format"}';
      break;
    }
    $db=DB_open();
    // Determine deployment type from device configuration
    $safe_uuid = DB_escape($db, $REQUEST['uuid']);
    $model_row = DB_selectArray($db, "SELECT json_extract(configuration,'$.model') as model FROM rpidns WHERE rpidns_uuid='$safe_uuid' LIMIT 1");
    $deployment_type = 'docker'; // default
    if (!empty($model_row)) {
      $model = $model_row[0]['model'] ?? '';
      // Models starting with 'pi' or 'rpi' are bare-metal Raspberry Pi deployments
      if (preg_match('/^(pi|rpi)/i', $model)) {
        $deployment_type = 'rpidns';
      }
    }
    $result=generate_install_script($db,$REQUEST['uuid'],$deployment_type);
    $script=$result['script'];
    $device_name=$result['name'];

		if (strlen($script)>0){
			header('Content-Type: application/x-sh');
			header("Content-Transfer-Encoding: Binary"); 
			header('Content-Disposition: attachment; filename="'.$device_name.'_install.sh"');
			header('Expires: 0');
			echo trim($script);
		};
    DB_close($db);			
	break;
endswitch;
?>