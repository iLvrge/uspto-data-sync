<?php 

ignore_user_abort(true);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000); 
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$con->query('SET GLOBAL range_optimizer_max_mem_size=0');

$queryDocument = "SELECT grant_doc_num FROM documentid WHERE date_format(grant_date, '%Y') >= 1997 AND grant_doc_num REGEXP '[a-zA-Z]'";
$resultDocument = $con->query($queryDocument);
if($resultDocument && $resultDocument->num_rows > 0) {
	$data = array();
	while($document = $resultDocument->fetch_object()) {
		$docNumber = $document->grant_doc_num;
		$substrString = substr($docNumber,2,1);
		//echo $docNumber.'@@'.$substrString."<br/>";
		if($substrString == '0' && substr($docNumber,0,2) == 'RE') {
			echo $substrString."<br/>";
			$rewriteNumber = substr($docNumber,0,2).substr($docNumber,3,strlen($docNumber));
			$queryUpdate = "UPDATE documentid SET grant_doc_num = '".$rewriteNumber."' WHERE grant_doc_num='".$document->grant_doc_num."'";
			
			echo $queryUpdate."<br/>";
			$con->query($queryUpdate);
		}
	}	
}