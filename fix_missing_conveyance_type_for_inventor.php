<?php 
require_once('/var/www/html/trash/vendor/autoload.php');
require_once('/var/www/html/trash/noti_config.php');
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
$variables = $argv;
//$variables = $_GET;
if(count($variables) == 2) {
	$organisationID = $variables[1];
	if((int)$organisationID > 0) {
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				/*Check from client database */
				$rfIDs = [];				
				$queryRepresentative = "SELECT representative_id FROM representative WHERE parent_id = 0";	
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
					$allRepresentative = array();
					while($getCompanyRow = $resultRepresentativeParentCompany->fetch_object()) {
						array_push($allRepresentative, $getCompanyRow->representative_id);
					}
					if(count($allRepresentative) > 0) {
						$queryFindAllRFIDs = "SELECT representative_assignment_conveyance.rf_id FROM documentid INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = documentid.rf_id WHERE appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
						SELECT rf_id FROM db_uspto.representative_transactions WHERE organisation_id = ".$organisationID." AND representative_id IN (".implode(',', $allRepresentative)."))) AND representative_assignment_conveyance.convey_ty = 'missing' GROUP BY representative_assignment_conveyance.rf_id";
						
						
						
						$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
						if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
							while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
								array_push($rfIDs, $rowRepresentativeRF->rf_id);
							}
						}
					}
					if(count($rfIDs) > 0) {
						$queryUniqueAssignors = "SELECT aaa.assignor_and_assignee_id, aaa.name FROM assignor as a INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id  WHERE a.rf_id IN (".implode(",", $rfIDs).") GROUP BY aaa.name";	
						
						$resultAssignor = $con->query($queryUniqueAssignors);						
						$assignorList = array();
						$assignorListWithIDs = array();
						
						if($resultAssignor && $resultAssignor->num_rows > 0) {
							while($rowAssignor = $resultAssignor->fetch_object()){
								$name = $rowAssignor->name;
								array_push($assignorList, array('name'=>$name, 'assignor_and_assignee_id'=>$rowAssignor->assignor_and_assignee_id));
								array_push($assignorListWithIDs, $rowAssignor->assignor_and_assignee_id);
							}
						}
					}
					
					
					$patternMatch = '/\b(?:inc|llc|corporation|corp|systems|system|llp|industries|gmbh|lp|agent|sas|na|bank|co|states|ltd|kk|a\/s)\b/i';
					$removeAssignors = array();
					foreach($assignorList as $assignor) {
						$name = $assignor['name'];
						$name = preg_replace('/\'/', '', $name);
						$result = preg_match_all($patternMatch, strtolower($name), $matches, PREG_SET_ORDER, 0);
						if($result !== false && isset($matches[0]) && count($matches[0]) > 0) {
							array_push($removeAssignors, $assignor['assignor_and_assignee_id']);
						}
					}
					if(count($removeAssignors) > 0){
						$assignorListWithIDs = array_diff($assignorListWithIDs, $removeAssignors);
						foreach($removeAssignors as $a) {
							$i = 0;
							foreach($assignorList as $assignor){
								if($assignor['assignor_and_assignee_id'] == $a) {
									unset($assignorList[$i]);
									break;
								}
								$i++;
							}
							$assignorList = array_values($assignorList);
						}
					}
					
					$assignorList = array_values($assignorList);
					$assignorListWithIDs = array_values($assignorListWithIDs);
					
					if(count($assignorList) > 0) {
						foreach($assignorList as $assignor) {
							$findInventorQuery = "SELECT COUNT(representative_assignment_conveyance.rf_id) AS countRFIDs FROM assignor INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = assignor.rf_id WHERE assignor.assignor_and_assignee_id = ".$assignor['assignor_and_assignee_id']. " AND (representative_assignment_conveyance.convey_ty = 'employee' OR (representative_assignment_conveyance.convey_ty = 'assignment' AND representative_assignment_conveyance.employer_assign = 1))";
							
							$resultInventor = $con->query($findInventorQuery);	
							if($resultInventor && $resultInventor->num_rows > 0) {
								$row = $resultInventor->fetch_object();
								
								if($row->countRFIDs > 0) {
									$assignorAssignmentList = findAssignmentsFromAssignorList(array($assignor['assignor_and_assignee_id']), $rfIDs, $con);
									if(count($assignorAssignmentList) > 0) {
										updateFlag(1, $assignorAssignmentList, $con);
										$rfIDs = array_diff($rfIDs, $assignorAssignmentList);
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


function findAssignmentsFromAssignorList($removeAssignors, $assignmentList, $con) {
	$queryAssignorAssignments = "SELECT a.rf_id  FROM assignor as a WHERE a.rf_id IN (".implode(",", $assignmentList).") AND a.assignor_and_assignee_id IN (".implode(",", $removeAssignors).") GROUP BY a.rf_id";
	$queryAssignorAssignments."<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>";
	$resultAssignorAssignment = $con->query($queryAssignorAssignments);
	
	$assignorAssignmentList = array();
	
	if($resultAssignorAssignment && $resultAssignorAssignment->num_rows > 0) {
		while($rowAssignorAssignment = $resultAssignorAssignment->fetch_object()){
			array_push($assignorAssignmentList, $rowAssignorAssignment->rf_id);
		}
	}
	
	return $assignorAssignmentList;
}

function updateFlag($flag, $rfIDs, $con) {
	/*if($flag == 1){
		$updateQuery = "UPDATE db_application.assignment_conveyance SET employer_assign = " .$flag. ", convey_ty = 'employee' WHERE rf_id IN (".implode(',', $rfIDs).")";
	} else {
		$updateQuery = "UPDATE db_application.assignment_conveyance SET employer_assign = " .$flag. " WHERE rf_id IN (".implode(',', $rfIDs).")";
	}
	
	echo $updateQuery."<br/><br/><br/><br/><br/><br/><br/><br/><br/>";
	$con->query($updateQuery);*/
	if($flag == 1){
		$updateQuery = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$flag. ", convey_ty = 'employee' WHERE rf_id IN (".implode(',', $rfIDs).")";
	} else {
		$updateQuery = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$flag. " WHERE rf_id IN (".implode(',', $rfIDs).")";
	}
	
	echo $updateQuery."<br/><br/><br/><br/><br/><br/><br/><br/><br/>";
	$con->query($updateQuery);
}	