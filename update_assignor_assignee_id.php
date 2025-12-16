<?php 
/* $host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO); */
/*
$con->query("TRUNCATE db_uspto.company_temp");

$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT or_name, count(or_name) as instances FROM db_uspto.assignor GROUP BY or_name";
$con->query($query);
	
$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT ee_name, count(ee_name) as instances FROM db_uspto.assignee GROUP BY ee_name";

$con->query($query);*/

/*
$queryCompanyTemp = "SELECT name, SUM(instances) as instances FROM company_temp GROUP BY name";

$resultCompanyTemp = $con->query($queryCompanyTemp);*/

/* $queryCompanyTemp = "SELECT name FROM company_temp GROUP BY name";

$resultCompanyTemp = $con->query($queryCompanyTemp);

$allNames = array();

while($rowName = $resultCompanyTemp->fetch_object()) {
	array_push($allNames, '"'.$con->real_escape_string($rowName->name).'"');
}


if(count($allNames) > 0) {
	$con->query("TRUNCATE db_uspto.company_temp");

	$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT or_name, count(or_name) as instances FROM db_uspto.assignor WHERE or_name IN (".implode(',', $allNames).") GROUP BY or_name";
	$con->query($query);	

	$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT ee_name, count(ee_name) as instances FROM db_uspto.assignee WHERE ee_name IN (".implode(',', $allNames).") GROUP BY ee_name";

	$con->query($query);

	$queryCompanyTemp = "SELECT name, SUM(instances) as instances  FROM company_temp GROUP BY name";

	$resultCompanyTemp = $con->query($queryCompanyTemp);

	

	if( $resultCompanyTemp && $resultCompanyTemp->num_rows > 0) {
		while($rowName = $resultCompanyTemp->fetch_object()) {
			$queryFind = "SELECT assignor_and_assignee_id FROM assignor_and_assignee WHERE name = '".$con->real_escape_string($rowName->name)."' LIMIT 1";
			
			$resultFindName = $con->query($queryFind);
			$query = "";
			$assignorAndAssigneeID = 0;
			if( $resultFindName && $resultFindName->num_rows > 0) {
				$row = $resultFindName->fetch_object();
				$query = "UPDATE assignor_and_assignee SET instances =  ".$rowName->instances." WHERE assignor_and_assignee_id = ".$row->assignor_and_assignee_id;
				$con->query($query);
				$assignorAndAssigneeID = $row->assignor_and_assignee_id;
				
			} else {			
				$query = "INSERT IGNORE INTO assignor_and_assignee (name, instances) VALUES ('".$con->real_escape_string($rowName->name)."', '".$rowName->instances."')";
				$con->query($query);
				if($con->insert_id > 0) {
					$assignorAndAssigneeID = $con->insert_id;
				}
			}
			
			if($assignorAndAssigneeID > 0) {
				echo $assignorAndAssigneeID."<br/>";
				$con->query("UPDATE assignor SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE (assignor_and_assignee_id = 0 OR  assignor_and_assignee_id IS NULL) AND or_name = '".$con->real_escape_string($rowName->name)."'");
				
				$con->query("UPDATE assignee SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE (assignor_and_assignee_id = 0 OR assignor_and_assignee_id = '') AND ee_name = '".$con->real_escape_string($rowName->name)."'");
			}
		}
		$con->query("TRUNCATE db_uspto.company_temp");
	}
}
 */


?>



 <?php 
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$con->query("TRUNCATE db_uspto.company_temp");



$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT or_name, count(or_name) as instances FROM db_uspto.assignor GROUP BY or_name";
$con->query($query);
	
$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT ee_name, count(ee_name) as instances FROM db_uspto.assignee GROUP BY ee_name";

$con->query($query);

if(1==1) {

	$queryCompanyTemp = "SELECT name, SUM(instances) as instances  FROM company_temp GROUP BY name";

	$resultCompanyTemp = $con->query($queryCompanyTemp);

	

	if( $resultCompanyTemp && $resultCompanyTemp->num_rows > 0) {
		while($rowName = $resultCompanyTemp->fetch_object()) {
			$queryFind = "SELECT assignor_and_assignee_id FROM assignor_and_assignee WHERE name = '".$con->real_escape_string($rowName->name)."' LIMIT 1";
			
			$resultFindName = $con->query($queryFind);
			$query = "";
			$assignorAndAssigneeID = 0;
			if( $resultFindName && $resultFindName->num_rows > 0) {
				$row = $resultFindName->fetch_object();
				$query = "UPDATE assignor_and_assignee SET instances =  ".$rowName->instances." WHERE assignor_and_assignee_id = ".$row->assignor_and_assignee_id;
				$con->query($query);
				$assignorAndAssigneeID = $row->assignor_and_assignee_id;
				
			} else {			
				$query = "INSERT IGNORE INTO assignor_and_assignee (name, instances) VALUES ('".$con->real_escape_string($rowName->name)."', '".$rowName->instances."')";
				$con->query($query);
				if($con->insert_id > 0) {
					$assignorAndAssigneeID = $con->insert_id;
				}
			}
			
			if($assignorAndAssigneeID > 0) {
				echo $assignorAndAssigneeID."<br/>";
				$con->query("UPDATE assignor SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE (assignor_and_assignee_id = 0 OR  assignor_and_assignee_id IS NULL) AND or_name = '".$con->real_escape_string($rowName->name)."'");
				
				$con->query("UPDATE assignee SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE (assignor_and_assignee_id = 0 OR assignor_and_assignee_id = '') AND ee_name = '".$con->real_escape_string($rowName->name)."'");
			}
		}
		$con->query("TRUNCATE db_uspto.company_temp");
	}
}



?>