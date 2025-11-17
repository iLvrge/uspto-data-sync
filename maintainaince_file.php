<?php 	
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, "db_patent_maintainence_fee");
$con->query("TRUNCATE TABLE db_patent_maintainence_fee.event_maintainence_fees;");
foreach(glob('/mnt/volume_sfo2_12/EVENTS/MaintFeeEvents_*.txt') as $fileName){
	$handle = fopen($fileName, "r");
	if ($handle) {
		$i = 0;
		$insertString = "";
	    while (($line = fgets($handle)) !== false) {        
			$parts = preg_split('/\s+/', $line);
			if(count($parts) > 0) {
				$insertString .='(';
				foreach($parts as $col){
					$insertString .='"'.$con->real_escape_string($col).'", ';
				}
				$insertString = substr($insertString, 0 , -2);
				$insertString .='), ';
			}
			if($i === 1000) {
				$insertString = substr($insertString, 0 , -2);  
				saveRecord($con, $insertString);
				$insertString = "";
				$i=0;
			}
			$i++;
	    }
		if($insertString != "") {
			$insertString = substr($insertString, 0 , -2);
			saveRecord($con, $insertString);
			$insertString = "";
		}

	    fclose($handle);
	} else {
	    // error opening the file.
	}
}

function saveRecord($con, $string) {
	$string = "INSERT IGNORE INTO db_patent_maintainence_fee.event_maintainence_fees(grant_doc_num,appno_doc_num,entity_status,filling_date,grant_date,event_date,event_code,event_icon) VALUES ".$string;
	echo $string;
	$con->query($string);	
}
?>
