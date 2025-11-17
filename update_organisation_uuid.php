<?php 
ignore_user_abort(true);
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);


$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbBusiness);

$query = "SELECT * FROM organisation";
$resultOrganisation = $con->query($query);
if($resultOrganisation && $resultOrganisation->num_rows > 0){
	while($row = $resultOrganisation->fetch_object()){
		$con->query("UPDATE organisation SET uuid=UUID_TO_BIN(UUID()) WHERE organisation_id = ". $row->organisation_id);
	}
}