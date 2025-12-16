<?php 
ignore_user_abort(true);
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);


$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$variables = $argv;
print_r($variables);
if(count($variables) == 3) {
	$organisationID = $variables[1];
	$representativeName = $variables[2];
	if((int)$organisationID > 0) {		
		$query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID;	
		$result = $con->query($query);
		if($result && $result->num_rows > 0) {  
			while($row = $result->fetch_object()) {
				
				try {
					$orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
					if($orgConnect) {
						$queryRepresentative = "SELECT representative_id, original_name, representative_name FROM representative WHERE type = 0 AND parent_id = 0 ";
						if($representativeName != '') {
							$queryRepresentative .= " AND (representative_name = '".$orgConnect->real_escape_string($representativeName)."' OR original_name = '".$orgConnect->real_escape_string($representativeName)."')";
						}				
						$queryRepresentative .= " GROUP BY representative_name";		
echo $queryRepresentative;
						$resultRepresentative = $orgConnect->query($queryRepresentative);	
						$allRepresentativeNames = [];
						if($resultRepresentative && $resultRepresentative->num_rows > 0) {
							echo "REPORT";
							while($row = $resultRepresentative->fetch_object()){
								
								$representativeName = $row->representative_name;
								$queryCheck = "SELECT assignor_and_assignee.representative_id, representative.representative_name, assignor_and_assignee.name FROM ".$dbUSPTO.".assignor_and_assignee INNER JOIN ".$dbUSPTO.".representative ON assignor_and_assignee.representative_id = representative.representative_id WHERE name = '".$con->real_escape_string($row->representative_name)."'";
								$resultCheck = $con->query($queryCheck);				
								if($resultCheck && $resultCheck->num_rows > 0) {
									$getRepresentativeRow = $resultCheck->fetch_object();
									if($getRepresentativeRow->representative_name != $row->representative_name) {
										//Update name 
										$representativeName = $getRepresentativeRow->representative_name;
										
										$updateQuery = "UPDATE representative SET representative_name = '".$con->real_escape_string($representativeName)."', original_name = '".$con->real_escape_string($getRepresentativeRow->name)."' WHERE representative_id = ".$row->representative_id;
										$orgConnect->query($updateQuery);	
									}
								}
								$allAssignorAssigneeIDs = [];
								echo $representativeName;
								//get All AssignorAndAssigneeIDs
								if( $representativeName != '' ) {						
									$queryFindAssignorAndAssigneeIDs = "SELECT assignor_and_assignee.assignor_and_assignee_id FROM representative 
									INNER JOIN assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
									WHERE representative.representative_name = '".$con->real_escape_string($representativeName)."'";
									$resultAssignorAndAssignee = $con->query($queryFindAssignorAndAssigneeIDs);				
									if($resultAssignorAndAssignee && $resultAssignorAndAssignee->num_rows == 0) {
										$queryFindAssignorAndAssigneeIDs = "Select assignor_and_assignee_id, name FROM assignor_and_assignee 
										WHERE representative_id IN (
											SELECT representative.representative_id FROM representative 
											INNER JOIN assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
											WHERE assignor_and_assignee.name = '".$con->real_escape_string($representativeName)."'
										)";
										$resultAssignorAndAssignee = $con->query($queryFindAssignorAndAssigneeIDs);	
										if($resultAssignorAndAssignee && $resultAssignorAndAssignee->num_rows == 0) {
											$queryFindAssignorAndAssigneeIDs = "Select assignor_and_assignee_id, name FROM assignor_and_assignee 
											WHERE name = '".$con->real_escape_string($representativeName)."'";
											$resultAssignorAndAssignee = $con->query($queryFindAssignorAndAssigneeIDs);
										}
									}
									if($resultAssignorAndAssignee && $resultAssignorAndAssignee->num_rows == 0) {
										$queryFindAssignorAndAssigneeIDs = "SELECT assignor_and_assignee.assignor_and_assignee_id FROM assignor_and_assignee WHERE assignor_and_assignee.name = '".$con->real_escape_string($representativeName)."'";
										$resultAssignorAndAssignee = $con->query($queryFindAssignorAndAssigneeIDs);
									}
									if($resultAssignorAndAssignee && $resultAssignorAndAssignee->num_rows > 0) {								
										while($rowAssignorAssignee = $resultAssignorAndAssignee->fetch_object()) {
											array_push($allAssignorAssigneeIDs,  $rowAssignorAssignee->assignor_and_assignee_id);
										}
									}
								}
								echo $representativeName."<br/>";
								echo "<pre>";
								print_r($allAssignorAssigneeIDs);
								if(count($allAssignorAssigneeIDs) > 0) {
									/*$con->query('DELETE FROM report_representative_assets_transactions_parties WHERE rf_id IN (SELECT rf_id FROM report_representative_assets_transactions WHERE representative_name = "'.$con->real_escape_string($representativeName).'")');
									$con->query('DELETE FROM report_representative_assets_transactions WHERE representative_name = "'.$con->real_escape_string($representativeName).'"');*/
									
									$queryAssetsRFIDs = "INSERT IGNORE INTO report_representative_assets_transactions(representative_name, appno_doc_num, grant_doc_num, rf_id, assignee, assignor, convey_ty) SELECT temp.representative_name, documentid.appno_doc_num, documentid.grant_doc_num , documentid.rf_id, (SELECT ee_name FROM assignee WHERE assignee.rf_id = documentid.rf_id LIMIT 1), (SELECT or_name FROM assignor WHERE assignor.rf_id = documentid.rf_id LIMIT 1), representative_assignment_conveyance.convey_ty
									FROM(
										SELECT temp1.representative_name, appno_doc_num, grant_doc_num FROM documentid
										INNER JOIN (
											SELECT '".$con->real_escape_string($representativeName)."' AS representative_name, rf_id FROM assignee
											WHERE assignee.assignor_and_assignee_id IN (".implode(',', $allAssignorAssigneeIDs).")
											UNION
											SELECT '".$con->real_escape_string($representativeName)."' AS representative_name, rf_id FROM  assignor 
											WHERE assignor.assignor_and_assignee_id IN (".implode(',', $allAssignorAssigneeIDs).")
										) as temp1 ON temp1.rf_id = documentid.rf_id
										GROUP BY temp1.representative_name, appno_doc_num, grant_doc_num
									) as temp
									INNER JOIN documentid ON documentid.appno_doc_num = temp.appno_doc_num AND documentid.grant_doc_num = temp.grant_doc_num
									INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = documentid.rf_id
									GROUP BY temp.representative_name, documentid.appno_doc_num, documentid.grant_doc_num , documentid.rf_id
									";
									$con->query("DELETE FROM report_representative_assets_transactions WHERE representative_name = '".$con->real_escape_string($representativeName)."'");
									$con->query($queryAssetsRFIDs);
									
									$queryAssetsRFIDsParties = "INSERT IGNORE INTO report_representative_assets_transactions_parties (rf_id, no_of_parties, party_type)	
									SELECT assignee.rf_id, count( distinct assignee.assignor_and_assignee_id) as no_of_parties, 0 as party_type  FROM db_uspto.assignee 
										INNER JOIN db_uspto.report_representative_assets_transactions ON report_representative_assets_transactions.rf_id = assignee.rf_id
										WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."'
										GROUP BY assignee.rf_id
										UNION ALL
										SELECT assignor.rf_id, count( distinct assignor.assignor_and_assignee_id) as no_of_parties, 1 as party_type FROM db_uspto.assignor 
										INNER JOIN db_uspto.report_representative_assets_transactions ON report_representative_assets_transactions.rf_id = assignor.rf_id
										INNER JOIN db_uspto.representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = assignor.rf_id AND representative_assignment_conveyance.convey_ty <> 'employee'
										WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."'
										GROUP BY assignor.rf_id
										UNION ALL
										SELECT assignor.rf_id, count( distinct assignor.assignor_and_assignee_id) as no_of_parties, 2 as party_type FROM db_uspto.assignor 
										INNER JOIN db_uspto.report_representative_assets_transactions ON report_representative_assets_transactions.rf_id = assignor.rf_id
										INNER JOIN db_uspto.representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = assignor.rf_id AND representative_assignment_conveyance.convey_ty = 'employee'
										WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."'
										GROUP BY assignor.rf_id";
									$con->query($queryAssetsRFIDsParties);
									
									/*Report*/
									$queryReports = "INSERT IGNORE INTO representative_reports (representative_name, no_of_assets, no_of_transactions, no_of_parties, no_of_inventor, no_of_activities, no_of_arrows) SELECT representative_name, (SELECT count(asset) FROM (Select CASE 
											WHEN report_representative_assets_transactions.grant_doc_num <> '' 
												THEN report_representative_assets_transactions.grant_doc_num 
											ELSE report_representative_assets_transactions.appno_doc_num
										END as asset from report_representative_assets_transactions WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."'
										GROUP BY asset) as temp_asset) as noOfAssets, (SELECT count(transactions) FROM (SELECT report_representative_assets_transactions.rf_id as transactions
										FROM report_representative_assets_transactions
										WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."'
										GROUP BY report_representative_assets_transactions.appno_doc_num, report_representative_assets_transactions.grant_doc_num,
										report_representative_assets_transactions.rf_id) as temp) as noOfTransactions, (SELECT sum(no_of_parties) FROM (SELECT report_representative_assets_transactions.grant_doc_num, 
										report_representative_assets_transactions.appno_doc_num, 
										report_representative_assets_transactions_parties.rf_id, 
										sum(report_representative_assets_transactions_parties.no_of_parties) as no_of_parties
										FROM report_representative_assets_transactions
										INNER JOIN report_representative_assets_transactions_parties ON report_representative_assets_transactions_parties.rf_id = report_representative_assets_transactions.rf_id AND party_type <> 2
										WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."'
										GROUP BY report_representative_assets_transactions.grant_doc_num, 
										report_representative_assets_transactions.appno_doc_num, 
										report_representative_assets_transactions_parties.rf_id) as temp) as noOfParties, (SELECT sum(no_of_parties) FROM (SELECT report_representative_assets_transactions.grant_doc_num, 
										report_representative_assets_transactions.appno_doc_num, 
										report_representative_assets_transactions_parties.rf_id, 
										sum(report_representative_assets_transactions_parties.no_of_parties) as no_of_parties
										FROM report_representative_assets_transactions
										INNER JOIN report_representative_assets_transactions_parties ON report_representative_assets_transactions_parties.rf_id = report_representative_assets_transactions.rf_id AND party_type = 2
										WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."'
										GROUP BY report_representative_assets_transactions.grant_doc_num, 
										report_representative_assets_transactions.appno_doc_num, 
										report_representative_assets_transactions_parties.rf_id) as temp) as noOfInventors, (SELECT count(*) FROM ( SELECT convey_ty FROM report_representative_assets_transactions WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."' GROUP BY convey_ty) as temp) as noOfActivity, (SELECT SUM(arrows) FROM assignment_arrows where rf_id IN (SELECT rf_id FROM (SELECT rf_id FROM assignor WHERE rf_id IN (SELECT rf_id FROM report_representative_assets_transactions WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."') GROUP BY rf_id UNION SELECT rf_id FROM assignee WHERE rf_id IN (SELECT rf_id FROM report_representative_assets_transactions WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."') GROUP BY rf_id) AS temp GROUP BY rf_id)) AS arrow  FROM report_representative_assets_transactions WHERE report_representative_assets_transactions.representative_name = '".$con->real_escape_string($representativeName)."' LIMIT 1";
									$con->query("DELETE FROM representative_reports WHERE representative_name = '".$con->real_escape_string($representativeName)."'");
									echo $queryReports;
									$con->query($queryReports);
									
								}
							}
						}						
					}
				} catch (Exception $e){
					echo "<pre>";
					print_r($e);
				}
			}
		}
	}
}
?>