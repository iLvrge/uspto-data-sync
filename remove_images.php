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
$con = new mysqli($host, $user, $password, $dbUSPTO);

$query = "SELECT * FROM db_new_application.organisations";
$resultLogo = $con->query($query);	
/*if($resultLogo && $resultLogo->num_rows > 0) {
	while($row = $resultLogo->fetch_object()){
		$logo_optimize = $row->logo_optimize;
		$logo_optimize = 'https://s3-us-west-1.amazonaws.com/static.patentrack.com/images/'.$logo_optimize;
		$con->query("UPDATE db_new_application.organisations SET logo_optimize = '".$con->real_escape_string($logo_optimize)."' WHERE organisation_id = ".$row->organisation_id);
	}
}*/
foreach(glob('/mnt/volume_sfo2_12/DOWNLOAD/jpeg/*.jpg') as $fileName){
	$newFileName = str_replace('.jpg', '.png', $fileName);
	$newFileName = str_replace('/mnt/volume_sfo2_12/DOWNLOAD/jpeg/', '', $newFileName);
	exec(" aws s3 rm  --recursive --exclude '*' --include '".$newFileName."' s3://static.patentrack.com/images/");
}
/*
foreach(glob('/mnt/volume_sfo2_12/DOWNLOAD/jpeg/*.jpg') as $fileName){
	$newFileName = str_replace('.jpg', '.png', $fileName);
	$newFileName = str_replace('/mnt/volume_sfo2_12/DOWNLOAD/jpeg/', '', $newFileName);
	
	
	
	
	$query = "SELECT * FROM db_new_application.organisations where LOWER(logo_optimize) = '".$con->real_escape_string(strtolower($newFileName))."' LIMIT 1";
	$resultLogo = $con->query($query);	
	if($resultLogo && $resultLogo->num_rows > 0) {
		$row = $resultLogo->fetch_object();
		$updateName = str_replace('/mnt/volume_sfo2_12/DOWNLOAD/jpeg/', '', $fileName);
		echo "UPDATE db_new_application.organisations SET logo_optimize = '".$con->real_escape_string($updateName)."' WHERE organisation_id = ".$row->organisation_id;
		echo $newFileName;
		
		unlink('/mnt/volume_sfo2_12/DOWNLOAD/png/'.$newFileName);
		$con->query("UPDATE db_new_application.organisations SET logo_optimize = '".$con->real_escape_string($updateName)."' WHERE organisation_id = ".$row->organisation_id);
	}
}*/
