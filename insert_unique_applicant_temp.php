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

$con->query("TRUNCATE db_patent_application_bibliographic.company_temp");
$queryGrant = "INSERT IGNORE INTO `db_patent_application_bibliographic`.`company_temp` (appno_doc_num, name) SELECT appno_doc_num, name  FROM db_patent_application_bibliographic.applicant  WHERE name <> '' GROUP BY appno_doc_num, name ";
$con->query($queryGrant);

echo "Applicant Done";

$queryGrant = "INSERT IGNORE INTO `db_patent_application_bibliographic`.`company_temp` (appno_doc_num, name) SELECT appno_doc_num, name  FROM db_patent_application_bibliographic.assignee  WHERE name <> '' GROUP BY appno_doc_num, name ";
$con->query($queryGrant);

echo "Assignee Done";

$queryGrantInventor = "INSERT IGNORE INTO `db_patent_application_bibliographic`.`company_temp` (appno_doc_num, name) SELECT appno_doc_num, name  FROM db_patent_application_bibliographic.inventor  WHERE name <> '' GROUP BY appno_doc_num, name ";
$con->query($queryGrantInventor);

echo "Inventor Done";

$queryAssets = "SELECT appno_doc_num FROM db_patent_application_bibliographic.applicant GROUP BY appno_doc_num";
$assets = array();
$resultApplicantAssetsList = $con->query($queryAssets);
if($resultApplicantAssetsList && $resultApplicantAssetsList->num_rows > 0) {
    while($rowAsset = $resultApplicantAssetsList->fetch_object()) {
        array_push($assets, '"'.$rowAsset->appno_doc_num.'"');
    }
}  

$queryAssets = "SELECT appno_doc_num FROM db_patent_application_bibliographic.assignee WHERE appno_doc_num NOT IN (".implode(',', $assets).") GROUP BY appno_doc_num";
 
$resultAssigneeAssetsList = $con->query($queryAssets);
if($resultAssigneeAssetsList && $resultAssigneeAssetsList->num_rows > 0) {
    while($rowAsset = $resultAssigneeAssetsList->fetch_object()) {
        array_push($assets, '"'.$rowAsset->appno_doc_num.'"');
    }
} 

$queryAssets = "SELECT appno_doc_num FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num NOT IN (".implode(',', $assets).") GROUP BY appno_doc_num";
$resultInventorAssetsList = $con->query($queryAssets);
if($resultInventorAssetsList && $resultInventorAssetsList->num_rows > 0) {
    while($rowAsset = $resultInventorAssetsList->fetch_object()) {
        array_push($assets, '"'.$rowAsset->appno_doc_num.'"');
    }
} 

$queryApplication = "INSERT IGNORE INTO `db_patent_application_bibliographic`.`company_temp` (appno_doc_num, name) SELECT appno_doc_num, name  FROM db_patent_grant_bibliographic.applicant  WHERE name <> '' AND appno_doc_num NOT IN (".implode(',', $assets).") GROUP BY appno_doc_num, name ";
$con->query($queryApplication);

echo "Grant Applicant Done";

$queryAssignee = "INSERT IGNORE INTO `db_patent_application_bibliographic`.`company_temp` (appno_doc_num, name) SELECT appno_doc_num, name  FROM db_patent_grant_bibliographic.assignee  WHERE name <> '' AND appno_doc_num NOT IN (".implode(',', $assets).") GROUP BY appno_doc_num, name ";
$con->query($queryAssignee);

echo "Grant Assignee Done";

$queryApplicationInventor = "INSERT IGNORE INTO `db_patent_application_bibliographic`.`company_temp` (appno_doc_num, name) SELECT appno_doc_num, name  FROM db_patent_grant_bibliographic.inventor  WHERE name <> '' AND appno_doc_num NOT IN (".implode(',', $assets).") GROUP BY appno_doc_num, name ";
$con->query($queryApplicationInventor);

echo "Grant Iventor Done";

$queryCompanyTemp = "SELECT name, COUNT(name) as instances FROM db_patent_application_bibliographic.company_temp GROUP BY name";

$resultCompanyTemp = $con->query($queryCompanyTemp);

echo "Start Counting";

if( $resultCompanyTemp && $resultCompanyTemp->num_rows > 0) {
	while($rowName = $resultCompanyTemp->fetch_object()) {
		$queryFind = "SELECT assignor_and_assignee_id FROM db_patent_application_bibliographic.assignor_and_assignee WHERE name = '".$con->real_escape_string($rowName->name)."' LIMIT 1";
		
		$resultFindName = $con->query($queryFind);
		$query = "";
		$assignorAndAssigneeID = 0;
		if( $resultFindName && $resultFindName->num_rows > 0) {
			echo "UPDATE assignor_and_assignee";
			$row = $resultFindName->fetch_object();
			$query = "UPDATE db_patent_application_bibliographic.assignor_and_assignee SET instances =  ".$rowName->instances." WHERE assignor_and_assignee_id = ".$row->assignor_and_assignee_id;
			$con->query($query);
			$assignorAndAssigneeID = $row->assignor_and_assignee_id;
			
		} else {			
			$query = "INSERT IGNORE INTO db_patent_application_bibliographic.assignor_and_assignee (name, instances) VALUES ('".$con->real_escape_string($rowName->name)."', '".$rowName->instances."')";
			$con->query($query);
			echo "INSERT assignor_and_assignee";
			if($con->insert_id > 0) {
				$assignorAndAssigneeID = $con->insert_id;
			}
		}
		
		if($assignorAndAssigneeID > 0) {
			$queryApplicant = "UPDATE db_patent_application_bibliographic.applicant SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE name = '".$con->real_escape_string($rowName->name)."'";
            $con->query($queryApplicant);

			echo "INSERT Grant applicant";

            $queryApplicant = "UPDATE db_patent_grant_bibliographic.applicant SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE name = '".$con->real_escape_string($rowName->name)."'";
            $con->query($queryApplicant);

			echo "INSERT Application applicant";

			$queryAssignee = "UPDATE db_patent_application_bibliographic.assignee SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE name = '".$con->real_escape_string($rowName->name)."'";
            $con->query($queryAssignee);

			echo "INSERT Grant assignee";

            $queryAssignee = "UPDATE db_patent_grant_bibliographic.assignee SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE name = '".$con->real_escape_string($rowName->name)."'";
            $con->query($queryAssignee);

			echo "INSERT Application assignee";

			$queryInventor = "UPDATE db_patent_application_bibliographic.inventor SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE name = '".$con->real_escape_string($rowName->name)."'";
            $con->query($queryInventor);

			echo "INSERT Grant iventor";

			$queryInventor = "UPDATE db_patent_grant_bibliographic.inventor SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE name = '".$con->real_escape_string($rowName->name)."'";
            $con->query($queryInventor);

			echo "INSERT Application inventor";


			$queryInventor = "UPDATE db_patent_grant_bibliographic.inventor_new SET assignor_and_assignee_id = ".$assignorAndAssigneeID." WHERE name = '".$con->real_escape_string($rowName->name)."'";
            $con->query($queryInventor);

			echo "INSERT Application inventor_new";
		}
	}
	$con->query("TRUNCATE db_patent_application_bibliographic.company_temp");
}