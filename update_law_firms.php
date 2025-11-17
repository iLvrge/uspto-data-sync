<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);*/
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$query = "SELECT law_firm_id, name FROM db_uspto.law_firm";

$result = $con->query($query);

if($result) {	
	while($row = $result->fetch_object()){
		$queryUpdate = 'UPDATE db_uspto.assignment SET law_firm_id = "'.$row->law_firm_id.'" WHERE cname = "'.$con->real_escape_string($row->name).'"';
		echo $queryUpdate."<br/>";
		$con->query($queryUpdate);
	}
}
?>