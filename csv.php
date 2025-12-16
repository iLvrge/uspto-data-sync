<?php
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$row = 1;
if (($handle = fopen("assignor.csv", "r")) !== FALSE) {
	$insertMain = 'INSERT INTO assignor_temp(rf_id,or_name,exec_dt,ack_dt) VALUES ';
	$queryInsert = "";
	//recordInsert = 0;
	$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
			$con->query("SET FOREIGN_KEY_CHECKS = 0");
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if($row > 1) {
			$num = count($data);
			$rfID = $data[0];
			$orName = $data[1];
			$date = $data[2];
			$ack = $data[3];
			
			if($num > 4){
				$orName = "";
				for($i = 1; $i < $num-2; $i++){
					$orName .=$data[$i];
				}
				$date = $data[count($data)-2];
				$ack = $data[count($data)-1];
			}
			$queryInsert = ' ("'.$rfID.'", "'.$con->real_escape_string($orName).'", "'.$date.'", "'.$ack.'") ';
			//$recordInsert++;
			echo $insertMain.$queryInsert."<br/>";
			$con->query($insertMain.$queryInsert);
			
		}
		$row++;		
    }
    fclose($handle);
}
?>