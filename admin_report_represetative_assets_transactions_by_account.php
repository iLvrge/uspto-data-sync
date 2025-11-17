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
//echo count($variables);
if(count($variables) == 3) {
	$organisationID = $variables[1];
	$representativeName = $variables[2];
	if((int)$organisationID > 0) {	
		echo "Script start";	
		$query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID;	
		$result = $con->query($query);
		if($result && $result->num_rows > 0) {  
			echo "Connecting to DB";
			while($row = $result->fetch_object()) {
				try {
					$orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
					if($orgConnect) {
						$queryAccountRepresentative = "SELECT representative_id, original_name, representative_name FROM representative WHERE type = 0 AND parent_id = 0 ";
						if($representativeName != '') {
							$queryAccountRepresentative .= " AND representative_name = '".$orgConnect->real_escape_string($representativeName)."' OR original_name = '".$orgConnect->real_escape_string($representativeName)."'";
						}						
						$resultAccountRepresentative = $orgConnect->query($queryAccountRepresentative);
				
						if($resultAccountRepresentative && $resultAccountRepresentative->num_rows > 0) {
							while($companyRow = $resultAccountRepresentative->fetch_object()){
								$representativeName = $companyRow->representative_name;
								//echo $representativeName.'<br/>';
								$queryRepresentative = "SELECT representative_id, representative_name FROM representative WHERE representative_name = '".$con->real_escape_string($representativeName)."'";
								$resultRepresentative = $con->query($queryRepresentative) or die("ERROR");
								echo $queryRepresentative;

								echo "NUM ROWS".$resultRepresentative->num_rows;
								if($resultRepresentative && $resultRepresentative->num_rows > 0){
									echo "INSERTING Data to admin";
									while($row = $resultRepresentative->fetch_object()){
										
										$queryAssetsRFIDs = "INSERT IGNORE INTO admin_report_representative_assets_transactions(representative_id, representative_name, appno_doc_num, grant_doc_num, rf_id, assignee, assignor) SELECT temp.representative_id, temp.representative_name, documentid.appno_doc_num, documentid.grant_doc_num , documentid.rf_id, (SELECT ee_name FROM assignee WHERE assignee.rf_id = documentid.rf_id LIMIT 1), (SELECT or_name FROM assignor WHERE assignor.rf_id = documentid.rf_id LIMIT 1)
														FROM(
															SELECT temp1.representative_id, temp1.representative_name, appno_doc_num, MAX(grant_doc_num) AS grant_doc_num FROM documentid
															INNER JOIN (
																Select representative.representative_id, representative_name, rf_id FROM db_uspto.representative 
																INNER JOIN db_uspto.assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
																INNER JOIN db_uspto.assignee ON assignee.assignor_and_assignee_id = assignor_and_assignee.assignor_and_assignee_id
																WHERE representative.representative_id = ".$row->representative_id."
																UNION
																Select representative.representative_id, representative_name, rf_id FROM db_uspto.representative 
																INNER JOIN db_uspto.assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
																INNER JOIN db_uspto.assignor ON assignor.assignor_and_assignee_id = assignor_and_assignee.assignor_and_assignee_id
																WHERE representative.representative_id  = ".$row->representative_id."
															) as temp1 ON temp1.rf_id = documentid.rf_id
															GROUP BY temp1.representative_id, appno_doc_num, grant_doc_num
														) as temp
														INNER JOIN documentid ON documentid.appno_doc_num = temp.appno_doc_num AND documentid.grant_doc_num = temp.grant_doc_num
														GROUP BY temp.representative_id, documentid.appno_doc_num, documentid.grant_doc_num , documentid.rf_id
														"; 
														echo $queryAssetsRFIDs;
										$con->query("DELETE FROM admin_report_representative_assets_transactions WHERE representative_id = ".$row->representative_id);
										
										$con->query($queryAssetsRFIDs);
										$queryAssetsRFIDsParties = "INSERT IGNORE INTO admin_report_representative_assets_transactions_parties (rf_id, no_of_parties, party_type)	
									SELECT assignee.rf_id, count( distinct assignee.assignor_and_assignee_id) as no_of_parties, 0 as party_type  FROM db_uspto.assignee 
										INNER JOIN db_uspto.admin_report_representative_assets_transactions ON admin_report_representative_assets_transactions.rf_id = assignee.rf_id
										WHERE admin_report_representative_assets_transactions.representative_id = ".$row->representative_id."
										GROUP BY assignee.rf_id
										UNION ALL
										SELECT assignor.rf_id, count( distinct assignor.assignor_and_assignee_id) as no_of_parties, 1 as party_type FROM db_uspto.assignor 
										INNER JOIN db_uspto.admin_report_representative_assets_transactions ON admin_report_representative_assets_transactions.rf_id = assignor.rf_id
										WHERE admin_report_representative_assets_transactions.representative_id = ".$row->representative_id."
										GROUP BY assignor.rf_id";
										
										$con->query($queryAssetsRFIDsParties);
									
										/*Report*/
										$queryReports = "INSERT IGNORE INTO admin_representative_reports (representative_id, representative_name, no_of_assets, no_of_transactions, no_of_parties, no_of_loans, no_of_banks, no_of_arrows) SELECT representative_id, representative_name, (SELECT count(asset) FROM (Select CASE 
												WHEN admin_report_representative_assets_transactions.grant_doc_num <> '' 
													THEN admin_report_representative_assets_transactions.grant_doc_num 
												ELSE admin_report_representative_assets_transactions.appno_doc_num
											END as asset from admin_report_representative_assets_transactions where representative_id = ".$row->representative_id."
											GROUP BY asset) as temp_asset) as noOfAssets, (SELECT count(transactions) FROM (SELECT admin_report_representative_assets_transactions.rf_id as transactions
											FROM admin_report_representative_assets_transactions
											WHERE admin_report_representative_assets_transactions.representative_id = ".$row->representative_id."
											GROUP BY admin_report_representative_assets_transactions.appno_doc_num, admin_report_representative_assets_transactions.grant_doc_num,
											admin_report_representative_assets_transactions.rf_id) as temp) as noOfTransactions, (SELECT sum(no_of_parties) FROM (SELECT admin_report_representative_assets_transactions.grant_doc_num, 
											admin_report_representative_assets_transactions.appno_doc_num, 
											admin_report_representative_assets_transactions_parties.rf_id, 
											sum(admin_report_representative_assets_transactions_parties.no_of_parties) as no_of_parties
											FROM admin_report_representative_assets_transactions
											INNER JOIN admin_report_representative_assets_transactions_parties ON admin_report_representative_assets_transactions_parties.rf_id = admin_report_representative_assets_transactions.rf_id
											WHERE admin_report_representative_assets_transactions.representative_id = ".$row->representative_id."
											GROUP BY admin_report_representative_assets_transactions.grant_doc_num, 
											admin_report_representative_assets_transactions.appno_doc_num, 
											admin_report_representative_assets_transactions_parties.rf_id) as temp) as noOfParties, (SELECT count(*)FROM(SELECT assignor.rf_id FROM assignor INNER JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id INNER JOIN representative ON representative.representative_id = assignor_and_assignee.representative_id INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = assignor.rf_id WHERE representative.representative_id = ".$row->representative_id." AND representative_assignment_conveyance.convey_ty IN ('security', 'restatedsecurity') GROUP BY assignor.rf_id) as temp1) as loans, (SELECT COUNT(*) FROM(SELECT assignee.assignor_and_assignee_id FROM assignee WHERE assignee.rf_id IN(SELECT assignor.rf_id FROM assignor INNER JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id INNER JOIN representative ON representative.representative_id = assignor_and_assignee.representative_id INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = assignor.rf_id WHERE representative.representative_id = ".$row->representative_id." AND representative_assignment_conveyance.convey_ty IN ('security', 'restatedsecurity') GROUP BY assignor.rf_id) GROUP BY assignee.assignor_and_assignee_id) as temp) as banks, (SELECT SUM(arrows) FROM assignment_arrows where rf_id IN (SELECT rf_id FROM (SELECT rf_id FROM assignor INNER JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id INNER JOIN representative ON representative.representative_id = assignor_and_assignee.representative_id WHERE representative.representative_id = ".$row->representative_id." UNION SELECT rf_id FROM assignee INNER JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id INNER JOIN representative ON representative.representative_id = assignor_and_assignee.representative_id WHERE representative.representative_id = ".$row->representative_id.") AS temp GROUP BY rf_id)) AS arrow  FROM admin_report_representative_assets_transactions WHERE admin_report_representative_assets_transactions.representative_id = ".$row->representative_id." LIMIT 1";
										$con->query("DELETE FROM admin_representative_reports WHERE representative_id = ".$row->representative_id);
										
										echo $queryReports;
										$con->query($queryReports); 
									}
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