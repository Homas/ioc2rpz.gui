<?php
#(c) Vadim Pavlov 2019-2020

require_once 'io2vars.php';
require_once 'io2fun.php';

$REQUEST=getRequest();

switch ($REQUEST['method'].' '.$REQUEST["req"]):
	case "GET rpidns_config":
    $db=DB_open();
    $script=generate_install_script($db,$REQUEST['uuid']);

		if (strlen($script)>0){
			header('Content-Type: application/x-sh');
			header("Content-Transfer-Encoding: Binary"); 
			header('Content-Disposition: attachment; filename="rpidns_install.sh"');
			header('Expires: 0');
			echo trim($script);
		};
    DB_close($db);			
	break;
endswitch;
?>