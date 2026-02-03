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
require_once __DIR__ . '/connection.php';

if ($con->connect_errno) {
    throw new Exception('MySQL Connection failed: ' . $con->connect_error);
}

if (!$con->set_charset("utf8mb4")) {
    throw new Exception("Error loading character set utf8mb4: " . $con->error);
}

// Empty target table
//$con->query("TRUNCATE TABLE db_uspto.company_temp");

// Single insert query
/*
$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT or_name, count(or_name) as instances FROM db_uspto.assignor GROUP BY or_name";
$con->query($query);
        
$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT ee_name, count(ee_name) as instances FROM db_uspto.assignee GROUP BY ee_name";

$con->query($query);

if (!$con->query($sql)) {
    throw new Exception('Insert failed: ' . $con->error);
}
*/
// Reliable row count
/*
$res = $con->query("SELECT ROW_COUNT() AS cnt");
$inserted = (int) $res->fetch_assoc()['cnt'];

echo "Total rows inserted: {$inserted}\n";
*/

$logFile = __DIR__ . '/update_assignor_assignee_id.log';

function logMessage($msg) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $msg" . PHP_EOL, FILE_APPEND);
}

/**
 * Helper to run INSERT...SELECT and get inserted row count reliably
 */
function runInsertAndCount(mysqli $con, string $sql): int {
    if (!$con->query($sql)) {
        throw new Exception('Query failed: ' . $con->error);
    }

    $res = $con->query("SELECT ROW_COUNT() AS cnt");
    return (int) $res->fetch_assoc()['cnt'];
}

/* ---------- Start ---------- */
try {
    logMessage("Script started.");

$con->query("TRUNCATE TABLE db_uspto.company_temp");

/* Insert from assignor */
$sqlAssignor = "
INSERT INTO db_uspto.company_temp (name, instances)
SELECT or_name, COUNT(*) AS instances
FROM db_uspto.assignor
GROUP BY or_name
";
$rows1 = runInsertAndCount($con, $sqlAssignor);

/* Insert from assignee */
$sqlAssignee = "
INSERT INTO db_uspto.company_temp (name, instances)
SELECT ee_name, COUNT(*) AS instances
FROM db_uspto.assignee
GROUP BY ee_name
";
$rows2 = runInsertAndCount($con, $sqlAssignee);

$totalInserted = $rows1 + $rows2;

logMessage("Rows inserted from assignor: {$rows1}");
logMessage("Rows inserted from assignee: {$rows2}");
logMessage("Total rows inserted: {$totalInserted}");



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
				logMessage("Updated ID: " . $assignorAndAssigneeID);
				$con->query("UPDATE assignor SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE (assignor_and_assignee_id = 0 OR  assignor_and_assignee_id IS NULL) AND or_name = '".$con->real_escape_string($rowName->name)."'");
				
				$con->query("UPDATE assignee SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE (assignor_and_assignee_id = 0 OR assignor_and_assignee_id = '') AND ee_name = '".$con->real_escape_string($rowName->name)."'");
			}
		}
		$con->query("TRUNCATE db_uspto.company_temp");
	}
}

} catch (Exception $e) {
    logMessage("CRITICAL ERROR: " . $e->getMessage());
}



?>