<?php 

ini_set('max_execution_time', '0');
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbUSPTO);


$variables = $argv;
//$variables = $_GET;
if(count($variables) == 3) {
//if(count($variables) > 0) {
	$organisationID = $variables[1];

    $accounts = $variables[2];
	
	//$organisationID = $variables['o'];
	
	//echo $organisationID."<br/>";	
	if((int)$organisationID > 0) {
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$mainAccountConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db); 
            
            if($mainAccountConnect) { 
                $dataEntered = false;
                $allAddedRepresentatives = [];
                if($accounts != '') {
                    $accountList = explode(',', $accounts);

                    if(count($accountList) > 0) {
                        foreach( $accountList as $account) {

                            $queryAccount = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$account.'"';

                            $resultAccount = $con->query($queryAccount);

                            if($resultAccount && $resultAccount->num_rows > 0) {

                                $accountOrgRow = $resultAccount->fetch_object();
                                $accountOrgConnect = new mysqli($accountOrgRow->org_host,$accountOrgRow->org_usr,$accountOrgRow->org_pass,$accountOrgRow->org_db);
                                
                                if($accountOrgConnect) {

                                    $groupData = array('original_name' => $accountOrgRow->name, 'representative_name' => $accountOrgRow->representative_name, 'instances' => 0, 'parent_id' => 0, 'child' => 0, 'type' => 1, 'status' => 1);

                                    $groupdID = insertData($groupData, $con);

                                    if($groupdID > 0) {
                                        array_push($allAddedRepresentatives, $groupdID);
                                        $queryRepresentative = "SELECT * FROM representative WHERE parent_id = 0 AND type = 0";

                                        $resultRepresentative = $con->query($queryRepresentative);

                                        if($resultRepresentative && $resultRepresentative->num_rows > 0) {

                                            while( $rowParent = $resultRepresentative->fetch_object()) {

                                                $parentData = array('original_name' => $rowParent->original_name, 'representative_name' => $rowParent->representative_name, 'instances' => $rowParent->instances, 'parent_id' => $groupdID, 'child' => 1, 'type' => 0, 'status' => 1);

                                                $representativeID = insertData($parentData, $con);

                                                if($representativeID > 0) {
                                                    $dataEntered = true;
                                                    $queryChild = "SELECT * FROM representative WHERE parent_id = $representativeID AND type = 0";

                                                    $resultChild = $con->query($queryChild);

                                                    if($resultChild && $resultChild->num_rows > 0) {
                                                        while( $rowchild = $resultRepresentative->fetch_object()) {
                                                            $childData = array('original_name' => $rowchild->original_name, 'representative_name' => $rowchild->representative_name, 'instances' => $rowchild->instances, 'parent_id' => $representativeID, 'child' => 0, 'type' => 0, 'status' => 1);

                                                            $childID = insertData($childData, $con);
                                                        }
                                                    } 
                                                } 
                                            }
                                        }
                                    }  
                                }
                            }
                        }
                    }
                }

                if($dataEntered == true) {
                    if(count($allAddedRepresentatives) > 0) {
                        foreach( $allAddedRepresentatives as $representativeID) {
                            exec('php -f /var/www/html/trash/add_representative_rfids.php '.$organisationID.' '.$representativeID);
                            exec('php -f /var/www/html/trash/create_data_for_company_db_application.php '.$organisationID.' '.$representativeID);
                        }
                    } 
                }
            }
        }
    }
}

function insertData($data, $con) {
    $columnNames = "";
    $columnValues = array(); 
    foreach($data as $key => $value) {
        $columnNames .= $key .", ";
        array_push($columnValues, "'".$con->real_escape_string($value)."'");
    }

    $columnNames = substr($columnNames, -2);
    $query = "INSERT IGNORE INTO db_patent_application_bibliographic.assignor_and_assignee ($columnNames) VALUES (".implode(',', $columnValues).")";
    $con->query($query);
    return $con->insert_id;
}
?>