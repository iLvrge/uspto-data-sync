<?php

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$row = 1;
if (($handle = fopen("assignment.csv", "r")) !== FALSE) {
	$insertMain = 'INSERT INTO assignment_temp(rf_id,file_id,cname,caddress_1,caddress_2,caddress_3,caddress_4,reel_no,frame_no,convey_text,record_dt,last_update_dt,page_count,purge_in) VALUES ';
	$queryInsert = "";
	$recordInsert = 0;
	$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
			$con->query("SET FOREIGN_KEY_CHECKS = 0");
    while (($data = fgetcsv($handle, 8000,",",'"')) !== FALSE) {
		$num = count($data);
		$rfID = $data[0];
		$file_id = $data[1];
		$cname = $data[2];
		$caddress_1 = $data[3];
		$caddress_2 = $data[4];
		$caddress_3 = $data[5];
		$caddress_4 = $data[6];
		$reel_no = $data[count($data)-7];
		$frame_no = $data[count($data)-6];
		$convey_text = $data[count($data)-5];
		$record_dt = $data[count($data)-4];
		$last_update_dt = $data[count($data)-3];
		$page_count = $data[count($data)-2];
		$purge_in = $data[count($data)-1];
		
		if($num>14){			
			$e = 0;
			$m = 0;
			$a = 0;
			$a1 = 0;
			$a2 = 0;
			$a3 = 0;
			if(strpos($cname,'"') !== false){
				$cname = "";
				$caddress_1 = "";
				$caddress_2 = "";
				$caddress_3 = "";
				$caddress_4 = "";
				for($i = 2; $i<=count($data)-8; $i++ ) {
					if($m == 0 && ($e == 0 || $e == 1 )) {
						$cname .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($e == 0){
								$e = 1;
							}else if($e == 1) {
								$e = 2;$m++;
							}
						}
					}
					
					if(($a == 0 || $a ==1) && $e == 2 && $m ==0) {
						$caddress_1 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a == 0){
								$a = 1;
							}else if($a == 1) {
								$a = 2;$m++;
							}
						} 
					}
					
					if(($a1 == 0 || $a1 ==1) && $a == 2 && $m ==0) {
						$caddress_2 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a1 == 0){
								$a1 = 1;
							}else if($a1 == 1) {
								$a1 = 2;$m++;
							}
						} 
					}
					
					if(($a2 == 0 || $a2 ==1) && $a1 == 2 && $m ==0) {
						$caddress_3 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a2 == 0){
								$a2 = 1;
							}else if($a2 == 1) {
								$a2 = 2;$m++;
							}
						} 
					}
					
					if(($a3 == 0 || $a3 ==1) && $a2 == 2 && $m ==0) {
						$caddress_4 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a3 == 0){
								$a3 = 1;
							}else if($a3 == 1) {
								$a3 = 2;$m++;
							}
						} 
					}
					
					if($a == 2 && $a1 == 0) {
						$m = 0;
					}
					if($a1 == 2 && $a2 == 0) {
						$m = 0;
					}
					if($a2 == 2 && $a3 == 0) {
						$m = 0;
					}
					if($e == 2 && $a == 0) {
						$m = 0;
					}
				}
			} else {
				$e = 2;
				$caddress_1 = "";
				$caddress_2 = "";
				$caddress_3 = "";
				$caddress_4 = "";
				for($i = 3; $i<=count($data)-8; $i++ ) {
										
					if(($a == 0 || $a ==1) && $e == 2 && $m ==0) {
						$caddress_1 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a == 0){
								$a = 1;
							}else if($a == 1) {
								$a = 2;$m++;
							}
						} 
					}
					
					if(($a1 == 0 || $a1 ==1) && $a == 2 && $m ==0) {
						$caddress_2 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a1 == 0){
								$a1 = 1;
							}else if($a1 == 1) {
								$a1 = 2;$m++;
							}
						} 
					}
					
					if(($a2 == 0 || $a2 ==1) && $a1 == 2 && $m ==0) {
						$caddress_3 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a2 == 0){
								$a2 = 1;
							}else if($a2 == 1) {
								$a2 = 2;$m++;
							}
						} 
					}
					
					if(($a3 == 0 || $a3 ==1) && $a2 == 2 && $m ==0) {
						$caddress_4 .= $data[$i];
						if(strpos($data[$i],'"') !== false){
							if($a3 == 0){
								$a3 = 1;
							}else if($a3 == 1) {
								$a3 = 2;$m++;
							}
						} 
					}
					
					if($a == 2 && $a1 == 0) {
						$m = 0;
					}
					if($a1 == 2 && $a2 == 0) {
						$m = 0;
					}
					if($a2 == 2 && $a3 == 0) {
						$m = 0;
					}
					if($e == 2 && $a == 0) {
						$m = 0;
					}
				}
			}
			$assignment = array($rfID,$file_id,$cname,$caddress_1,$caddress_2,$caddress_3,$caddress_4,$reel_no,$frame_no,$convey_text,$record_dt,$last_update_dt,$page_count,$purge_in);
			echo "<pre>";
			print_r($data);
			print_r($assignment);
			echo "<pre>";
			$recordInsert++;
			if($recordInsert == 50) {
				die;
			}
			
		}
		
		/*$queryInsert = ' ("'.$rfID.'", "'.$con->real_escape_string($eeName).'", "'.$con->real_escape_string($eeAddress).'", "'.$con->real_escape_string($eeAddress2).'", "'.$con->real_escape_string($eeCity).'", "'.$con->real_escape_string($eeState).'", "'.$con->real_escape_string($eePostCode).'", "'.$con->real_escape_string($eeCountry).'") ';
			echo $insertMain.$queryInsert."<br/>";
			$con->query($insertMain.$queryInsert);*/
    }
    fclose($handle);
}
?>