<?php 

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbAppBiblio = getenv('DB_APPLICATION_BIBLIO');
$dbGrantBiblio = getenv('DB_GRANT_BIBLIO');

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000);

$con = new mysqli($host,$user,$password,$dbAppBiblio);

$query = "SELECT appno_doc_num, grant_doc_num, grant_date FROM application_grant";

$queryResult = $con->query($query);

if($queryResult) {
	while($row = $queryResult->fetch_object()) {
		$queryUpdateDocumentID = "UPDATE db_uspto.documentid SET grant_doc_num = '".$row->grant_doc_num."', grant_date = '".$row->grant_date."' WHERE appno_doc_num = '".$row->appno_doc_num."'";
		echo $queryUpdateDocumentID. "<br/>";
		$con->query($queryUpdateDocumentID);
	}
}
?>