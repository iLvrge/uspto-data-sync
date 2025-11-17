<?php 
ignore_user_abort(true);
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$variables = $argv;
if(count($variables) == 3) {	
	$organisationID = $variables[1];
	$companyName = $variables[2];	
	if($organisationID > 0) { 
		/*echo $companyName."<br/>";*/
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				if($companyName != "") {
					$queryRepresentative = 'SELECT representative_id FROM representative WHERE (original_name = "'.$con->real_escape_string($companyName).'" OR representative_name = "'.$con->real_escape_string($companyName).'") AND parent_id = 0';
				} else {
					$queryRepresentative = 'SELECT representative_id FROM representative WHERE parent_id = 0';
				}
				
			
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
		
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
					$representativeIDs = array();
					
					while($representativeData = $resultRepresentativeParentCompany->fetch_object()) {
						array_push($representativeIDs, $representativeData->representative_id);
					}					
					
					if( count($representativeIDs) > 0 ) {
			
						$queryAllCompanyNames = "SELECT original_name FROM representative WHERE representative_id IN (".implode(',', $representativeIDs).") OR (parent_id IN (".implode(',', $representativeIDs).")) GROUP by original_name";
						
						$resultRepresentativeNames = $orgConnect->query($queryAllCompanyNames);
						
						$allCompanyNames = array();
						
						if($resultRepresentativeNames && $resultRepresentativeNames->num_rows > 0) {
							while($rowRepresentative = $resultRepresentativeNames->fetch_object()) {
								array_push($allCompanyNames, $rowRepresentative->original_name);
							}
						}
		
		
						$rfIDs = [];
						
						$queryFindAllRFIDs = "SELECT rf_id FROM db_uspto.representative_transactions WHERE organisation_id = ".$organisationID." AND representative_id IN (".implode(',', $representativeIDs).")";
						
						$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
						
						if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
							while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
								array_push($rfIDs, $rowRepresentativeRF->rf_id);
							}
						}		
						
						
						if(count($rfIDs) > 0) {
							/*RFIDs related with other Assets as well*/
							$queryFindCorrectRFIDs = 'SELECT rf_id FROM db_uspto.documentid WHERE appno_doc_num IN (SELECT appno_doc_num FROM db_uspto.documentid WHERE appno_doc_num <> "" AND  rf_id IN ('.implode(',', $rfIDs).') GROUP BY appno_doc_num) GROUP BY rf_id';
							
							//echo $queryFindCorrectRFIDs;
							
							$resultIDs = $con->query($queryFindCorrectRFIDs);
							
							if($resultIDs->num_rows > 0) {
								$rfIDs = array(); 
								while($row = $resultIDs->fetch_object()){
									array_push($rfIDs, $row->rf_id);
								}
							}
						}
						
						
						if(count($rfIDs) > 0) {
							/*$deleteAssignorAssignee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE representative_id IN (SELECT representative_id FROM `db_application`.`representative` WHERE representative_name="'.$con->real_escape_string($companyName).'")';

							$deleteRepresentiative = 'DELETE FROM `db_application`.`representative` WHERE representative_name="'.$con->real_escape_string($companyName).'"';*/
							$allNames = "";
							$allRepresentativeNames = "";
							
							$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
							$con->query("SET FOREIGN_KEY_CHECKS = 0");	
							
							foreach($allCompanyNames as $company) {
								$allNames .= 'name = "'.$con->real_escape_string($company).'" OR ';
								$allRepresentativeNames .= 'representative_name = "'.$con->real_escape_string($company).'" OR ';
							}
							
							$allNames = substr($allNames, 0, -3);
							$allRepresentativeNames = substr($allRepresentativeNames, 0, -3);
							
							$deleteAssignorAssignee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE ('.$allNames.')';
							
							$con->query($deleteAssignorAssignee);
							
							$deleteRepresentiative = 'DELETE FROM `db_application`.`representative` WHERE ('.$allRepresentativeNames.')';
							
							$con->query($deleteRepresentiative);
							
							
							echo $deleteAssignorAssignee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_application`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).') GROUP BY assignor_and_assignee_id)';
							
							$con->query($deleteAssignorAssignee);
							
							
							
							echo $deleteAssignorAssignee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_application`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).') GROUP BY assignor_and_assignee_id)';
							
							$con->query($deleteAssignorAssignee);
							

							//$deleteLawFirm = 'DELETE FROM `db_application`.`law_firm` WHERE law_firm_id IN (SELECT law_firm_id FROM `db_application`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).'))';

							$deleteAssignee = 'DELETE FROM `db_application`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).')';

							$deleteAssignor = 'DELETE FROM `db_application`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')';

							$deleteAssignment = 'DELETE FROM `db_application`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).')';

							$deleteAssignmentConveyance = 'DELETE FROM `db_application`.`assignment_conveyance` WHERE rf_id IN ('.implode(',', $rfIDs).')';

							

							
							
							
							$con->query($deleteAssignee);
							$con->query($deleteAssignor);
							$con->query($deleteAssignment);
							$con->query($deleteAssignmentConveyance);
							
							
							
							
							
							
							
							$queryAssigneeAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).') GROUP BY assignor_and_assignee_id)';
							//echo $queryAssigneeAssignorAssignee."<br/>";
							
							$con->query($queryAssigneeAssignorAssignee);
							
							$queryAssignorAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).') GROUP BY assignor_and_assignee_id)';
							//echo $queryAssignorAssignorAssignee."<br/>";
							$con->query($queryAssignorAssignorAssignee);
							
							
							$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE  representative_id IN (SELECT representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE  assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).') GROUP BY assignor_and_assignee_id) AND representative_id IS NOT NULL GROUP BY  representative_id, representative_name)';
							//echo $queryRepresentiative."<br/>";
							$con->query($queryRepresentiative);
							
							$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE representative_id IN (SELECT representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE  assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).') GROUP BY assignor_and_assignee_id) AND representative_id IS NOT NULL GROUP BY  representative_id, representative_name)';
							//echo $queryRepresentiative."<br/>";
							$con->query($queryRepresentiative);
							
							/*$queryLawFirm = 'INSERT IGNORE INTO `db_application`.`law_firm`(law_firm_id, name, representative_id)SELECT law_firm_id, name, representative_id FROM `db_uspto`.`law_firm` WHERE law_firm_id IN (SELECT law_firm_id FROM `db_uspto`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).'))';

							$con->query($queryLawFirm);*/

							$queryInsertAssignment = "INSERT IGNORE INTO `db_application`.`assignment`(rf_id, file_id, cname, caddress_1, caddress_2, caddress_3, caddress_4, reel_no, frame_no, convey_text, record_dt, last_update_dt, page_count, purge_in, law_firm_id) SELECT rf_id, file_id, cname, caddress_1, caddress_2, caddress_3, caddress_4, reel_no, frame_no, convey_text, record_dt, last_update_dt, page_count, purge_in, law_firm_id FROM `db_uspto`.`assignment` WHERE rf_id IN (".implode(',', $rfIDs).") GROUP BY rf_id";
							//echo $queryInsertAssignment."<br/>";
							$con->query($queryInsertAssignment);

							$queryAssignmentConveyance = 'INSERT IGNORE INTO `db_application`.`assignment_conveyance`(rf_id, convey_ty, employer_assign) SELECT rf_id, convey_ty, employer_assign FROM `db_uspto`.`assignment_conveyance` WHERE rf_id IN ('.implode(',', $rfIDs).') GROUP BY rf_id';
							//echo $queryAssignmentConveyance."<br/>";
							$con->query($queryAssignmentConveyance);
							$queryDocument = 'INSERT IGNORE INTO db_application.documentid(rf_id, title, lang, appno_doc_num, appno_date, appno_country, pgpub_doc_num, pgpub_date, pgpub_country, grant_doc_num, grant_date, grant_country) Select rf_id, title, lang, appno_doc_num, appno_date, appno_country, pgpub_doc_num, pgpub_date, pgpub_country, grant_doc_num, grant_date, grant_country FROM db_uspto.documentid WHERE appno_doc_num IN( SELECT appno_doc_num FROM db_uspto.documentid where rf_id IN ('.implode(',', $rfIDs).') GROUP BY rf_id, appno_doc_num)';
							
							//echo $queryDocument."<br/>";
							$con->query($queryDocument);
							

							$queryInsertAssignee = 'INSERT IGNORE INTO `db_application`.`assignee`(rf_id, ee_name, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country, assignor_and_assignee_id) SELECT rf_id, ee_name, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country, assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).') GROUP BY rf_id, ee_name';
							
							//echo $queryInsertAssignee."<br/>";
							$con->query($queryInsertAssignee);

							$queryInsertAssignor = 'INSERT IGNORE INTO `db_application`.`assignor`(rf_id, or_name, exec_dt, ack_dt, assignor_and_assignee_id) SELECT rf_id, or_name, exec_dt, ack_dt, assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')  GROUP BY rf_id, or_name'; 
							//echo $queryInsertAssignor."<br/>";
							$con->query($queryInsertAssignor);
							
							$queryFindRepresentativeConveyance = "SELECT rf_id FROM db_uspto.representative_assignment_conveyance WHERE rf_id IN (".implode(',', $rfIDs).")"; 
							
							$resultRepresentativeRFIDs = $con->query($queryFindRepresentativeConveyance);
							
							if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
								$allRFIDs = array();
								
								while($row = $resultRepresentativeRFIDs->fetch_object()) {
									array_push($allRFIDs, $row->rf_id);
								}
								
								$con->query("DELETE FROM `db_application`.`assignment_conveyance` WHERE rf_id IN (".implode(',', $allRFIDs).")");
								$con->query("INSERT IGNORE INTO `db_application`.`assignment_conveyance` SELECT rf_id, convey_ty, employer_assign FROM db_uspto.representative_assignment_conveyance WHERE rf_id IN (".implode(',', $allRFIDs).") GROUP BY rf_id");
							}
							
							
							$con->query("SET FOREIGN_KEY_CHECKS = 1");
							echo "Tree created";
						}		
					}		
				}		
			}		
		}		
	}
}
?>