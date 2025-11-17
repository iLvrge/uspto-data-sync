<?php 

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set("log_errors", 1);
ini_set("error_log", "/var/www/html/trash/daily_file.log");

ini_set('xdebug.max_nesting_level', 1000);
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);



$fileConveyance = fopen("/var/www/html/trash/dds/assignment_conveyance.csv","r");
$i = 0;
while(! feof($fileConveyance)){
	$csvData = fgetcsv($fileConveyance);
	if($i > 0) {
		if(is_array($csvData) && count($csvData) == 3 ) {
			$rfID = $csvData[0];
			$queryConveyance = "SELECT rf_id, convey_ty, employer_assign FROM db_uspto.assignment_conveyance WHERE rf_id = ".$rfID;
			$resultConveyance = $con->query($queryConveyance);
			$conveyanceInsert = true;
			if($resultConveyance && $resultConveyance->num_rows > 0) {
				$con->query("UPDATE db_uspto.assignment_conveyance SET employer_assign = ".$csvData[2]. ", convey_ty = '".$csvData[1]."' WHERE rf_id = ".$rfID);
			} else {
				$queryInsertAssignmentConveyance = "INSERT IGNORE INTO db_uspto.assignment_conveyance (rf_id, convey_ty, employer_assign) VALUES ('".$rfID."', '".$csvData[1]."', '".$csvData[2]."')";
				$con->query($queryInsertAssignmentConveyance);
			}		
			$queryRepresentativeConveyance = "SELECT rf_id, convey_ty, employer_assign FROM db_uspto.representative_assignment_conveyance WHERE rf_id = ".$rfID;
			$resultRepresentativeConveyance = $con->query($queryRepresentativeConveyance);
			if($resultRepresentativeConveyance && $resultRepresentativeConveyance->num_rows > 0) {
				$queryData = $resultRepresentativeConveyance->fetch_object();
				if(($queryData->convey_ty != 'assignment' && $queryData->convey_ty != 'employee')) {
					$con->query("UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = ".$csvData[2]. ", convey_ty = '".$csvData[1]."' WHERE rf_id = ".$rfID);
				}
			} else {
				$queryInsertAssignmentConveyance = "INSERT IGNORE INTO db_uspto.representative_assignment_conveyance (rf_id, convey_ty, employer_assign) VALUES ('".$rfID."', '".$csvData[1]."', '".$csvData[2]."')";
				$con->query($queryInsertAssignmentConveyance);
			}
		}		
	}  
	$i++;
}
fclose($fileConveyance);
unset("/var/www/html/trash/dds/assignment_conveyance.csv");
