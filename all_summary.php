<?php 

ini_set('max_execution_time', '0');
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbApplication);

$mainOrg = array(183, 182, 177, 176, 175, 174, 173, 161, 160, 159, 155, 154, 152);
$mainOrg1 = array(151, 149, 145, 130, 129, 127, 126, 125, 124, 123);
$mainOrg2 = array(122, 120, 117, 116, 114, 113, 107, 106, 104, 101);
$mainOrg3 = array(100, 97, 96, 95, 94, 93, 92, 91, 90, 89);
$mainOrg4 = array(87, 86, 85, 84, 81, 68);
$mainOrg5 = array(98, 105, 121, 144, 146, 147, 150, 153, 156, 142);
$mainOrg6 = array(88, 99, 108, 103);


$orgList = array();
$variables = $argv;
$companyID = [];
if(count($variables) == 2) {
	switch($variables[1]){
		case 1:
			$orgList = $mainOrg;
			break;
		case 2:
			$orgList = $mainOrg1;
			break;
		case 3:
			$orgList = $mainOrg2;
			break;
		case 4:
			$orgList = $mainOrg3;
			break;
		case 5:
			$orgList = $mainOrg4;
			break;
        case 6:
            $orgList = $mainOrg5;
            break;
        case 7:
            $orgList = $mainOrg6;
            break;
	}
} else if (count($variables) == 3){
	$orgList = array($variables[1]);
	$companyID = array($variables[2]);
}

if(count($orgList) > 0) {
    foreach($orgList as $org) {
        /*./node_modules/.bin/env-cmd node name_to_domain_api.js*/
        exec('php -f /var/www/html/trash/summary.php  '.$org.' "" "1" ');
    }

    
}

