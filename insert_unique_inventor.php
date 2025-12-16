<?php 

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set("log_errors", 1);
ini_set("error_log", "/var/www/html/trash/daily_file.log");

ini_set('xdebug.max_nesting_level', 1000);
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, 'db_uspto');


$con->query("TRUNCATE db_uspto.company_temp");

$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT name, count(name) as instances FROM db_patent_application_bibliographic.inventor GROUP BY name";
$con->query($query);	

$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT name, count(name) as instances FROM db_patent_grant_bibliographic.inventor_new GROUP BY name";
$con->query($query);	

$queryCompanyTemp = "SELECT name, SUM(instances) as instances  FROM company_temp GROUP BY name";

$resultCompanyTemp = $con->query($queryCompanyTemp);


if( $resultCompanyTemp && $resultCompanyTemp->num_rows > 0) {
    while($rowName = $resultCompanyTemp->fetch_object()) {
        $queryFind = "SELECT assignor_and_assignee_id, instances FROM db_patent_application_bibliographic.assignor_and_assignee WHERE name = '".$con->real_escape_string($rowName->name)."' LIMIT 1";
        
        $resultFindName = $con->query($queryFind);
        $query = "";
        $assignorAndAssigneeID = 0;
        if( $resultFindName && $resultFindName->num_rows > 0) {
            $row = $resultFindName->fetch_object();
            $instances = $row->instances + $rowName->instances;
            $query = "UPDATE assignor_and_assignee SET instances =  ".$instances." WHERE assignor_and_assignee_id = ".$row->assignor_and_assignee_id;
            $con->query($query);
            $assignorAndAssigneeID = $row->assignor_and_assignee_id;
            
        } else {			
            $query = "INSERT IGNORE INTO db_patent_application_bibliographic.assignor_and_assignee (name, instances) VALUES ('".$con->real_escape_string($rowName->name)."', '".$rowName->instances."')";
            $con->query($query);
            if($con->insert_id > 0) {
                $assignorAndAssigneeID = $con->insert_id;
            }
        }
        
        if($assignorAndAssigneeID > 0) {
            echo $assignorAndAssigneeID."<br/>";
            $con->query("UPDATE db_patent_application_bibliographic.inventor SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE  name = '".$con->real_escape_string($rowName->name)."'");
            
            $con->query("UPDATE db_patent_grant_bibliographic.inventor_new SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE  name = '".$con->real_escape_string($rowName->name)."'");
        }
    }
    $con->query("TRUNCATE db_uspto.company_temp");
}