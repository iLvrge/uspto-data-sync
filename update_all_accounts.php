<?php 

ini_set('max_execution_time', '0');
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbApplication);

$mainOrg = array(176, 175, 174, 173, 161, 160, 159, 155, 154, 152);
$mainOrg1 = array(151, 149, 145, 130, 129, 127, 126, 125, 124, 123);
$mainOrg2 = array(122, 120, 117, 116, 114, 113, 107, 106, 104, 101);
$mainOrg3 = array(100, 97, 96, 95, 94, 93, 92, 91, 90, 89);
$mainOrg4 = array(87, 86, 85, 84, 81, 68);

$orgList = array();
$variables = $argv;
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
	}
}

$queryOrganisation = "SELECT organisation_id FROM `".$dbBusiness."`.`organisation` WHERE org_pass <> '' AND org_db <> '' AND organisation_type = 1 AND organisation_id IN (".implode(',', $orgList).")";
		
//echo $queryOrganisation."<br/>";
$resultOrg = $con->query($queryOrganisation);
//echo $resultOrg->num_rows."<br/>";

if($resultOrg && $resultOrg->num_rows > 0) {	
	while($row = $resultOrg->fetch_object()) {

		//exec('php -f /var/www/html/trash/create_data_for_company_db_application.php "'.$row->organisation_id.'" ""');
		exec('php -f /var/www/html/trash/dashboard_with_company.php  "" "'.$row->organisation_id.'"');
	}
}