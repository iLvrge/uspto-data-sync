<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
//$dbApplication = getenv('DB_APPLICATION_DB');
//$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbUSPTO);

$query = 'SELECT representative_id, representative_name FROM representative';
$result = $con->query($query);
if($result && $result->num_rows > 0) {
	while($representative = $result->fetch_object()) {
		$queryAssignorAndAssignee = 'SELECT representative_id, assignor_and_assignee_id FROM '.$dbUSPTO.'.assignor_and_assignee WHERE name="'.$con->real_escape_string($representative->representative_name).'"';
		$resultAssignorAndAssignee = $con->query($queryAssignorAndAssignee);
		if($resultAssignorAndAssignee && $resultAssignorAndAssignee->num_rows == 1) {
			$rowAssignorAndAssignee = $resultAssignorAndAssignee->fetch_object();
			if($rowAssignorAndAssignee->representative_id == 0) {
				echo 'UPDATE '.$dbUSPTO.'.assignor_and_assignee SET representative_id='.$representative->representative_id.' WHERE assignor_and_assignee_id='.$rowAssignorAndAssignee->assignor_and_assignee_id."<br/>";
				$con->query('UPDATE '.$dbUSPTO.'.assignor_and_assignee SET representative_id='.$representative->representative_id.' WHERE assignor_and_assignee_id='.$rowAssignorAndAssignee->assignor_and_assignee_id);				
			}
		}		
	}
}
	?>
	