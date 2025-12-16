<?php

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$row = 1;
if (($handle = fopen("assignee.csv", "r")) !== FALSE) {
	$insertMain = 'INSERT INTO assignee_temp(rf_id,ee_name,ee_address_1,ee_address_2,ee_city,ee_state,ee_postcode,ee_country) VALUES ';
	$queryInsert = "";
	$recordInsert = 0;
	$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
			$con->query("SET FOREIGN_KEY_CHECKS = 0");
    while (($data = fgetcsv($handle, 8000,",",'"')) !== FALSE) {
		$num = count($data);
		$rfID = $data[0];
		$eeName = $data[1];
		$eeAddress = $data[2];
		$eeAddress2 = $data[3];
		if($num>8){			
			$e = 0;
			$m = 0;
			$a = 0;
			$a1 = 0;
			if(strpos($data[1],'"') !== false){
				$eeName = "";
				$eeAddress = "";
				$eeAddress2 = "";
				for($i = 1; $i<=count($data)-4; $i++ ) {
					if($m == 0 && ($e == 0 || $e == 1 )) {
						$eeName .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($e == 0){
								$e = 1;
							}else if($e == 1) {
								$e = 2;$m++;
							}
						}
					}
					
					if(($a == 0 || $a ==1) && $e == 2 && $m ==0) {
						$eeAddress .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a == 0){
								$a = 1;
							}else if($a == 1) {
								$a = 2;$m++;
							}
						} 
					}
					
					if(($a1 == 0 || $a1 ==1) && $a == 2 && $m ==0) {
						$eeAddress2 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a1 == 0){
								$a1 = 1;
							}else if($a1 == 1) {
								$a1 = 2;$m++;
							}
						} 
					}
					if($a == 2 && $a1 == 0) {
						$m = 0;
					}
					if($e == 2 && $a == 0) {
						$m = 0;
					}
				}
			} else {
				$eeAddress = "";
				$eeAddress2 = "";
				for($i = 2; $i<=count($data)-4; $i++ ) {					
					if(($a == 0 || $a ==1) && $m ==0) {
						$eeAddress .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a == 0){
								$a = 1;
							}else if($a == 1) {
								$a = 2;$m++;
							}
						} 
					}
					
					if(($a1 == 0 || $a1 ==1) && $a == 2 && $m ==0) {
						$eeAddress2 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a1 == 0){
								$a1 = 1;
							}else if($a1 == 1) {
								$a1 = 2;$m++;
							}
						} 
					}
					if($a == 2 && $a1 == 0) {
						$m = 0;
					}
				}
			}
			
			/*echo "<pre>";
			print_r($data);
			print_r($asignee);
			echo "<pre>";
			$recordInsert++;
			if($recordInsert == 50) {
				die;
			}*/
			
		}
		$eeCity = $data[count($data)-4];
		$eeState = $data[count($data)-3];
		$eePostCode = $data[count($data)-2];
		$eeCountry = $data[count($data)-1];
		$asignee = array('rf_id'=>$rfID,'ee_name'=>$eeName,'ee_address_1'=>$eeAddress,'ee_address_2'=>$eeAddress2,'ee_city'=>$eeCity,'ee_state'=>$eeState,'ee_postcode'=>$eePostCode,'ee_country'=>$eeCountry);	
		$queryInsert = ' ("'.$rfID.'", "'.$con->real_escape_string($eeName).'", "'.$con->real_escape_string($eeAddress).'", "'.$con->real_escape_string($eeAddress2).'", "'.$con->real_escape_string($eeCity).'", "'.$con->real_escape_string($eeState).'", "'.$con->real_escape_string($eePostCode).'", "'.$con->real_escape_string($eeCountry).'") ';
			echo $insertMain.$queryInsert."<br/>";
			$con->query($insertMain.$queryInsert);
    }
    fclose($handle);
}
?>