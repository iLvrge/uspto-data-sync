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
		$startTime = microtime(true);
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
								if( $representativeName != '' ) {
									$escapedRepresentativeName = $con->real_escape_string($representativeName);
									$queryCheck = "
									    SELECT aa.representative_id, r.representative_name, aa.name
									    FROM {$dbUSPTO}.assignor_and_assignee AS aa
									    INNER JOIN {$dbUSPTO}.representative AS r ON aa.representative_id = r.representative_id
									    WHERE aa.name = '{$escapedRepresentativeName}'
									    LIMIT 1
									";
									$resultCheck = $con->query($queryCheck);

									if ($resultCheck && ($getRepresentativeRow = $resultCheck->fetch_object())) {
									    if ($getRepresentativeRow->representative_name !== $row->representative_name) {
									        $representativeName = $getRepresentativeRow->representative_name;
									        $escapedRepresentativeName = $con->real_escape_string($representativeName);
									        $updateQuery = "
									            UPDATE representative 
									            SET representative_name = '{$con->real_escape_string($representativeName)}',
									                original_name = '{$con->real_escape_string($getRepresentativeRow->name)}'
									            WHERE representative_id = {$row->representative_id}
									        ";
									        $orgConnect->query($updateQuery);
									    }
									}
									$allAssignorAssigneeIDs = [];

									$queryFindAssignorAndAssigneeIDs = "
								        SELECT aa.assignor_and_assignee_id FROM assignor_and_assignee AS aa
								        WHERE aa.name = '{$escapedRepresentativeName}'

								        UNION

								        SELECT aa.assignor_and_assignee_id FROM assignor_and_assignee AS aa
								        INNER JOIN representative AS r ON aa.representative_id = r.representative_id
								        WHERE r.representative_name = '{$escapedRepresentativeName}'
								    ";
								    $resultAssignorAndAssignee = $con->query($queryFindAssignorAndAssigneeIDs);
								    if ($resultAssignorAndAssignee && $resultAssignorAndAssignee->num_rows > 0) {
								        while ($rowAssignorAssignee = $resultAssignorAndAssignee->fetch_object()) {
								            $allAssignorAssigneeIDs[] = $rowAssignorAssignee->assignor_and_assignee_id;
								        }
								    }
								}
								echo $representativeName."<br/>";
								echo "<pre>";
								print_r($allAssignorAssigneeIDs);
								if(count($allAssignorAssigneeIDs) > 0) {
									$assigneeIDs = implode(',', array_map('intval', $allAssignorAssigneeIDs));
									$queryAssetsRFIDs = "
									INSERT IGNORE INTO report_representative_assets_transactions
									(representative_name, appno_doc_num, grant_doc_num, rf_id, assignee, assignor, convey_ty)
									SELECT 
									    ?, 
									    d.appno_doc_num, 
									    d.grant_doc_num, 
									    d.rf_id,
									    COALESCE(c.ee_name, NULL) as assignee,
									    COALESCE(c.or_name, NULL) as assignor,
									    r.convey_ty
									FROM
									    documentid d
									JOIN (
									    SELECT 
									        rf_id, 
									        ee_name, 
									        NULL as or_name
									    FROM assignee 
									    WHERE assignor_and_assignee_id IN ($assigneeIDs)
									    UNION
									    SELECT 
									        rf_id, 
									        NULL as ee_name, 
									        or_name
									    FROM assignor 
									    WHERE assignor_and_assignee_id IN ($assigneeIDs)
									) c ON c.rf_id = d.rf_id
									JOIN representative_assignment_conveyance r ON r.rf_id = d.rf_id
									GROUP BY d.appno_doc_num, d.grant_doc_num, d.rf_id
									";

									$stmt = $con->prepare("DELETE FROM report_representative_assets_transactions WHERE representative_name = ?");
									$stmt->bind_param('s', $representativeName);
									$stmt->execute();
									$stmt->close();

									$stmt = $con->prepare($queryAssetsRFIDs);
									$stmt->bind_param('s', $representativeName);
									$stmt->execute();
									$stmt->close();

									$queryAssetsRFIDsParties = "
										INSERT IGNORE INTO report_representative_assets_transactions_parties (rf_id, no_of_parties, party_type)
										SELECT rf_id, no_of_parties, party_type FROM (
										    -- Assignee count (party_type 0)
										    SELECT a.rf_id, COUNT(DISTINCT a.assignor_and_assignee_id) AS no_of_parties, 0 AS party_type
										    FROM db_uspto.assignee a
										    INNER JOIN db_uspto.report_representative_assets_transactions r ON r.rf_id = a.rf_id
										    WHERE r.representative_name = '$escapedRepresentativeName'
										    GROUP BY a.rf_id

										    UNION ALL

										    -- Assignor count (non-employee, party_type 1)
										    SELECT s.rf_id, COUNT(DISTINCT s.assignor_and_assignee_id) AS no_of_parties, 1 AS party_type
										    FROM db_uspto.assignor s
										    INNER JOIN db_uspto.report_representative_assets_transactions r ON r.rf_id = s.rf_id
										    INNER JOIN db_uspto.representative_assignment_conveyance c ON c.rf_id = s.rf_id AND c.convey_ty <> 'employee'
										    WHERE r.representative_name = '$escapedRepresentativeName'
										    GROUP BY s.rf_id

										    UNION ALL

										    -- Assignor count (employee, party_type 2)
										    SELECT s.rf_id, COUNT(DISTINCT s.assignor_and_assignee_id) AS no_of_parties, 2 AS party_type
										    FROM db_uspto.assignor s
										    INNER JOIN db_uspto.report_representative_assets_transactions r ON r.rf_id = s.rf_id
										    INNER JOIN db_uspto.representative_assignment_conveyance c ON c.rf_id = s.rf_id AND c.convey_ty = 'employee'
										    WHERE r.representative_name = '$escapedRepresentativeName'
										    GROUP BY s.rf_id
										) AS combined
										";

										$con->query($queryAssetsRFIDsParties);

										/*Report*/
										$queryReports = "
											INSERT INTO representative_reports 
											(representative_name, no_of_assets, no_of_transactions, no_of_parties, no_of_inventor, no_of_activities, no_of_arrows)
											SELECT
											    '$escapedRepresentativeName' AS representative_name,

											    -- Assets Count
											    COUNT(DISTINCT CASE WHEN grant_doc_num <> '' THEN grant_doc_num ELSE appno_doc_num END) AS no_of_assets,

											    -- Transactions Count
											    COUNT(DISTINCT CONCAT(appno_doc_num, '-', grant_doc_num, '-', rf_id)) AS no_of_transactions,

											    -- Parties Count (excluding inventors)
											    COALESCE((
											        SELECT SUM(rp.no_of_parties)
											        FROM report_representative_assets_transactions_parties rp
											        INNER JOIN report_representative_assets_transactions ra ON ra.rf_id = rp.rf_id
											        WHERE ra.representative_name = '$escapedRepresentativeName' AND rp.party_type <> 2
											    ), 0) AS no_of_parties,

											    -- Inventors Count (party_type = 2)
											    COALESCE((
											        SELECT SUM(rp.no_of_parties)
											        FROM report_representative_assets_transactions_parties rp
											        INNER JOIN report_representative_assets_transactions ra ON ra.rf_id = rp.rf_id
											        WHERE ra.representative_name = '$escapedRepresentativeName' AND rp.party_type = 2
											    ), 0) AS no_of_inventor,

											    -- Activity Count (distinct convey_ty)
											    COUNT(DISTINCT convey_ty) AS no_of_activities,

											    -- Arrows Count (SUM arrows from assignment_arrows)
											    COALESCE((
											        SELECT SUM(arrows)
											        FROM assignment_arrows
											        WHERE rf_id IN (
											            SELECT DISTINCT rf_id FROM report_representative_assets_transactions WHERE representative_name = '$escapedRepresentativeName'
											        )
											    ), 0) AS no_of_arrows

											FROM report_representative_assets_transactions
											WHERE representative_name = '$escapedRepresentativeName'
											";

										$con->query("DELETE FROM representative_reports WHERE representative_name = '$escapedRepresentativeName'");
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
		$endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // milliseconds
        echo "⏱️ Query execution time: " . round($executionTime, 3) . " ms\n";
	}
}
?>
								