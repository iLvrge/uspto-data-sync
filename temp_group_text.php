<?php 
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$queryConTemp = "SELECT conveyance_text, convey_ty, counter FROM representative_assignment_conveyance_temp";

$resultTemp = $con->query($queryConTemp);

if($resultTemp) {
	while($row = $resultTemp->fetch_object()) {
		$queryFind  = 'SELECT a.rf_id as id, a.convey_text as text, null as reel_frame, count(a.convey_text) as counter, ac.convey_ty, rac.convey_ty as updated_convey_ty FROM assignment as a INNER JOIN assignment_conveyance as ac ON ac.rf_id = a.rf_id LEFT JOIN representative_assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE a.convey_text = "'.$con->real_escape_string($row->conveyance_text).'" GROUP BY a.convey_text';
		
		$resultCheck = $con->query($queryFind);
		
		if($resultCheck && $resultCheck->num_rows == 1) {
			$fetchRow = $resultCheck->fetch_object();
			
			if($fetchRow->updated_convey_ty == $row->convey_ty) {
				echo $row->conveyance_text."<br/>".$row->convey_ty."\n";
				
				
				
				$con->query('INSERT INTO representative_rf_id_temp (rf_id) SELECT rf_id FROM assignment WHERE convey_text = "'.$con->real_escape_string($row->conveyance_text).'"');
			}
		}
		
	}
}