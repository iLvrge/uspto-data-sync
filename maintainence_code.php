<?php 
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, "db_patent_maintainence_fee");	


$handle = fopen("/mnt/volume_sfo2_12/MaintFeeEventsDesc_20230501.txt", "r");
if ($handle) {
	$i = 0;
	$insertString = "";
    while (($line = fgets($handle)) !== false) {    
		
		$parts = preg_split('/\s+/', $line);
		
		if(count($parts) > 0) {
			$insertString .='(';
			$code = $parts[0];
			unset($parts[0]);
			$description = implode(" ",$parts);
			$insertString .='"'.$con->real_escape_string($code).'", "'.$con->real_escape_string($description).'"';
			
			$insertString .='), ';
		}		
    }
	//echo $insertString;
	if($insertString != "") {
		$insertString = substr($insertString, 0 , -2);
		saveRecord($con, $insertString);
		$insertString = "";
	}
    fclose($handle);
} else {
    // error opening the file.
} 

function saveRecord($con, $string) {
	$string = "INSERT INTO db_patent_maintainence_fee.event_maintainence_code(event_code,event_description) VALUES ".$string;
	
	$con->query($string);	
}
?>