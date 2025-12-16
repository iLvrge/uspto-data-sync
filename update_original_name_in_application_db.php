<?php 
ini_set('memory_limit', '90000M');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
$con->query("SET FOREIGN_KEY_CHECKS = 0");

$query = "SELECT * FROM ".$dbUSPTO.".assignee WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".assignee GROUP BY rf_id)";

$result = $con->query($query);

if($result->num_rows > 0) {
	echo $result->num_rows."<br/>";
	while($row = $result->fetch_object()){
		$orName = $row->ee_name;
		$assignorAndAssigneeID = $row->assignor_and_assignee_id;
		if($assignorAndAssigneeID  > 0) {
			update("assignee", array('original_name'=>$row->ee_name), $row->rf_id, $assignorAndAssigneeID, $con,  $dbApplication);
		}
	}
}

$query = "SELECT * FROM ".$dbUSPTO.".assignor WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".assignor GROUP BY rf_id)";

$result = $con->query($query);

if($result->num_rows > 0) {
	echo $result->num_rows."<br/>";
	while($row = $result->fetch_object()){
		$orName = $row->or_name;
		$assignorAndAssigneeID = $row->assignor_and_assignee_id;
		if($assignorAndAssigneeID  > 0) {
			update("assignor", array('original_name'=>$row->or_name), $row->rf_id, $assignorAndAssigneeID, $con,  $dbApplication);
		}
	}
}

function update($tableName, $postValues, $rfID, $assignorAndAssigneeID ,$con, $dbName){
	$stringName ="";
	foreach($postValues as $key=>$value){
		$stringName .=$key."='".$con->real_escape_string($value)."',";
	}
	$stringName = substr($stringName,0,-1);
	$sql = "UPDATE ".$dbName.".".$tableName." SET ".$stringName." WHERE rf_id= ".$rfID." AND assignor_and_assignee_id = ".$assignorAndAssigneeID;
	echo $sql."<br/>";
	
	$con->query($sql);
}