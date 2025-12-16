<?php 
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$queryCompanyTemp = "Select * from db_application.assignor where assignor_and_assignee_id IS NULL GROUP BY or_name";

$resultCompanyTemp = $con->query($queryCompanyTemp);

if( $resultCompanyTemp && $resultCompanyTemp->num_rows > 0) {
	while($rowName = $resultCompanyTemp->fetch_object()) {
		$queryFind = "SELECT * FROM db_uspto.assignor WHERE or_name = '".$con->real_escape_string($rowName->or_name)."' LIMIT 1";
		
		$resultFindName = $con->query($queryFind);
		$query = "";
		$assignorAndAssigneeID = 0;
		if( $resultFindName && $resultFindName->num_rows > 0) {
			$row = $resultFindName->fetch_object();
			$assignorAndAssigneeID = $row->assignor_and_assignee_id;
			$con->query("UPDATE db_application.assignor SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE or_name = '".$con->real_escape_string($rowName->or_name)."'");
			
			 
			$queryFind = "SELECT assignor_and_assignee_id FROM db_uspto.assignor_and_assignee WHERE assignor_and_assignee_id = '".$assignorAndAssigneeID."' LIMIT 1";
			$resultFindName = $con->query($queryFind);
			
			if( $resultFindName && $resultFindName->num_rows == 0) {
				$query = "INSERT INTO db_application.assignor_and_assignee (name, instances) SELECT name instances FROM db_uspto.assignor_and_assignee WHERE assignor_and_assignee_id = '".$assignorAndAssigneeID."' LIMIT 1";
			}
		}
	}
}

$queryCompanyTemp = "Select * from db_application.assignee where assignor_and_assignee_id = 0 GROUP BY ee_name";

$resultCompanyTemp = $con->query($queryCompanyTemp);

if( $resultCompanyTemp && $resultCompanyTemp->num_rows > 0) {
	while($rowName = $resultCompanyTemp->fetch_object()) {
		$queryFind = "SELECT * FROM db_uspto.assignee WHERE ee_name = '".$con->real_escape_string($rowName->ee_name)."' LIMIT 1";
		
		$resultFindName = $con->query($queryFind);
		$query = "";
		$assignorAndAssigneeID = 0;
		if( $resultFindName && $resultFindName->num_rows > 0) {
			$row = $resultFindName->fetch_object();
			$assignorAndAssigneeID = $row->assignor_and_assignee_id;
			$con->query("UPDATE db_application.assignee SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE ee_name = '".$con->real_escape_string($rowName->ee_name)."'");
			
			
			$queryFind = "SELECT assignor_and_assignee_id FROM db_uspto.assignor_and_assignee WHERE assignor_and_assignee_id = '".$assignorAndAssigneeID."' LIMIT 1";
			$resultFindName = $con->query($queryFind);
			
			if( $resultFindName && $resultFindName->num_rows == 0) {
				$query = "INSERT INTO db_application.assignor_and_assignee (name, instances) SELECT name instances FROM db_uspto.assignor_and_assignee WHERE assignor_and_assignee_id = '".$assignorAndAssigneeID."' LIMIT 1";
			}
		}
	}
}
?>
