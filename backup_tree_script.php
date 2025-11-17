<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$variables = $argv;
if(count($variables) == 2) {
	$companyName = $variables[1];
	 
	if($companyName != "") {
		/*echo $companyName."<br/>";*/
		$queryAssignee = 'SELECT d.rf_id FROM `db_uspto`.`assignee` as ac INNER JOIN db_uspto.documentid as d ON d.rf_id = ac.rf_id WHERE assignor_and_assignee_id IN ( SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor_and_assignee` as aa LEFT JOIN `db_uspto`.`representative` as r1 ON r1.representative_id = aa.representative_id WHERE (aa.name = "'.$con->real_escape_string($companyName).'" OR r1.representative_name="'.$con->real_escape_string($companyName).'")) GROUP BY d.rf_id';
		
		$result = $con->query($queryAssignee);
		$rfIDs = []; $assignorRFIDs = []; $assigneeRFIDs = [];
		/*echo $result->num_rows."<br/>";*/
		if($result->num_rows > 0) {	
			while($row = $result->fetch_object()){
				array_push($rfIDs, $row->rf_id);
				array_push($assigneeRFIDs, $row->rf_id);
			}
		}
		
		//print_r($assigneeRFIDs);
		
		$queryAssignor = 'SELECT d.rf_id FROM `db_uspto`.`assignor` as ac INNER JOIN db_uspto.documentid as d ON d.rf_id = ac.rf_id WHERE assignor_and_assignee_id IN ( SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor_and_assignee` as aa LEFT JOIN `db_uspto`.`representative` as r1 ON r1.representative_id = aa.representative_id where (aa.name = "'.$con->real_escape_string($companyName).'" OR r1.representative_name="'.$con->real_escape_string($companyName).'")) GROUP BY d.rf_id';
		
		

		$result = $con->query($queryAssignor);
		/*echo $result->num_rows."<br/>";*/
		if($result->num_rows > 0) {	
			while($row = $result->fetch_object()){
				if(!in_array($row->rf_id, $rfIDs)){
					array_push($rfIDs, $row->rf_id);
					array_push($assignorRFIDs, $row->rf_id);
				}
			}
		}
		
		$queryFindCorrectRFIDs = 'SELECT rf_id FROM db_uspto.documentid WHERE appno_doc_num IN (SELECT appno_doc_num FROM db_uspto.documentid WHERE appno_doc_num <> "" AND  rf_id IN ('.implode(',', $rfIDs).')) GROUP BY rf_id';
		
		//echo $queryFindCorrectRFIDs;
		
		$resultIDs = $con->query($queryFindCorrectRFIDs);
		
		if($resultIDs->num_rows > 0) {
			$rfIDs = array(); 
			while($row = $resultIDs->fetch_object()){
				array_push($rfIDs, $row->rf_id);
			}
		}
		
		//echo count($rfIDs)."<br>";
		
		$rfIDs = array_unique($rfIDs);
		/*print_r($rfIDs);
		echo "</pre>";
		die;*/
		/*var_dump($rfIDs);*/
		if(count($rfIDs) > 0) {
			$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
			$con->query("SET FOREIGN_KEY_CHECKS = 0");	
			
			$insertNew = true;
			
			$queryCheckRFIDs = "SELECT count(rf_id) as counter FROM `db_application`.`assignment` WHERE rf_id IN (".implode(',', $rfIDs).") ";
			
			$resultCheckAssignment = $con->query($queryCheckRFIDs);
			
			if($resultCheckAssignment) {
				$countCheck = $resultCheckAssignment->fetch_object();
				//echo $countCheck->counter ."@@". count($rfIDs)."<br/>";
				if((int)$countCheck->counter == count($rfIDs)) {
					//echo "IN TEST ALL5<br/>";
					$queryCheckConveyance = "SELECT count(rf_id) as counter FROM `db_application`.`assignment_conveyance` WHERE rf_id IN (".implode(',', $rfIDs).")";
					$resultCheckConveyance = $con->query($queryCheckConveyance);
					
					if($resultCheckConveyance) {
						$countCheck = $resultCheckConveyance->fetch_object();
						//echo $countCheck->counter ."@@". count($rfIDs)."<br/>";
						if((int)$countCheck->counter == count($rfIDs)) {
							//echo "IN TEST ALL4<br/>";
							$queryCheckAssignors = "SELECT rf_id FROM `db_application`.`assignor` WHERE rf_id IN (".implode(',', $assignorRFIDs).") GROUP BY  rf_id";
							$resultCheckAssignors = $con->query($queryCheckAssignors);
							
							if($resultCheckAssignors) {
								//echo $resultCheckAssignors->num_rows ."@@". count($assignorRFIDs)."<br/>";
								if($resultCheckAssignors->num_rows == count($assignorRFIDs)) {
									//echo "IN TEST ALL3<br/>";
									$queryCheckAssignees = "SELECT rf_id FROM `db_application`.`assignee` WHERE rf_id IN (".implode(',', $assigneeRFIDs).") GROUP BY  rf_id";
									$resultCheckAssignees = $con->query($queryCheckAssignees);
									
									if($resultCheckAssignees) {
										//echo "IN TEST ALL2<br/>";
										//echo $resultCheckAssignees->num_rows ."@@". count($assigneeRFIDs)."<br/>";
										if($resultCheckAssignees->num_rows == count($assigneeRFIDs)) {
											//echo "IN TEST ALL1<br/>";
											$queryDeleteAssignorAssignor = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $assignorRFIDs).'))';
			
											$con->query($queryDeleteAssignorAssignor);
											
											$queryDeleteAssignorAssignee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $assigneeRFIDs).'))';
			
											$con->query($queryDeleteAssignorAssignee);
											
											
											$queryAssigneeAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $assigneeRFIDs).'))';
											
											
											$con->query($queryAssigneeAssignorAssignee);
											
											$queryAssignorAssignorAssignor = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $assignorRFIDs).')) GROUP BY assignor_and_assignee_id';
											
											$con->query($queryAssignorAssignorAssignor);
											
											$findRepresentativesIDs = "SELECT aaa.representative_id FROM `db_application`.`assignor_and_assignee` as aaa INNER JOIN `db_application`.`assignor` as a ON a.assignor_and_assignee_id = aaa.assignor_and_assignee_id WHERE a.rf_id IN (".implode(',', $assignorRFIDs).") AND aaa.rf_id > 0 GROUP BY aaa.assignor_and_assignee_id";
											
											$resultRepresentativeIDs = $con->query($findRepresentativesIDs);
											
											$representativeIDs = array();
											
											if($resultRepresentativeIDs && $resultRepresentativeIDs->num_rows > 0) {
												while($row = $resultRepresentativeIDs->fetch_object()){
													array_push($representativeIDs, $row->representative_id);
												}
											}
											
											
											$findRepresentativesIDs = "SELECT aaa.representative_id FROM `db_application`.`assignor_and_assignee` as aaa INNER JOIN `db_application`.`assignee` as a ON a.assignor_and_assignee_id = aaa.assignor_and_assignee_id WHERE a.rf_id IN (".implode(',', $assigneeRFIDs).") AND aaa.rf_id > 0 GROUP BY aaa.assignor_and_assignee_id";
											
											$resultRepresentativeIDs = $con->query($findRepresentativesIDs);
											
											if($resultRepresentativeIDs && $resultRepresentativeIDs->num_rows > 0) {
												while($row = $resultRepresentativeIDs->fetch_object()){
													array_push($representativeIDs, $row->representative_id);
												}
											}
											
											$queryDeleteRepresentative = "DELETE FROM db_application.representative WHERE representative_id IN (".implode(',', $representativeIDs).")";
											
											$con->query($queryDeleteRepresentative);
											
											$queryInsertRepresentative = "INSERT IGNORE db_application.representative(representative_id,representative_name) SELECT representative_id,representative_name FROM db_uspto.representative WHERE representative_id IN (".implode(',', $representativeIDs).")";
											
											$con->query($queryInsertRepresentative);
											//echo "IN TEST ALL";
											$insertNew = false;
										}
									}
								}
							}							
						}
					}
				}
			}
			
			if($insertNew  === true) {
				//echo "IN DELETE ALL";
				$deleteAssignorAssignee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE representative_id IN (SELECT representative_id FROM `db_uspto`.`representative` WHERE representative_name="'.$con->real_escape_string($companyName).'")';
				
				$queryDeleteAssigneeAssignor = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
				
				$queryDeleteAssignorAssigneee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
				
				$deleteRepresentiative = 'DELETE FROM `db_application`.`representative` WHERE representative_name="'.$con->real_escape_string($companyName).'"';

				$deleteLawFirm = 'DELETE FROM `db_application`.`law_firm` WHERE law_firm_id IN (SELECT law_firm_id FROM `db_application`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).'))';

				$deleteAssignee = 'DELETE FROM `db_application`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).')';

				$deleteAssignor = 'DELETE FROM `db_application`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')';

				$deleteAssignment = 'DELETE FROM `db_application`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).')';

				$deleteAssignmentConveyance = 'DELETE FROM `db_application`.`assignment_conveyance` WHERE rf_id IN ('.implode(',', $rfIDs).')';
				
				$con->query($deleteRepresentiative);
				$con->query($deleteAssignorAssignee);
				$con->query($queryDeleteAssigneeAssignor);
				$con->query($queryDeleteAssignorAssigneee);
				$con->query($deleteLawFirm);
				$con->query($deleteAssignee);
				$con->query($deleteAssignor);
				$con->query($deleteAssignment);
				$con->query($deleteAssignmentConveyance);
				
				$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE representative_name="'.$con->real_escape_string($companyName).'"';
				$con->query($queryRepresentiative);
				
				$queryAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE representative_id IN (SELECT representative_id FROM `db_uspto`.`representative` WHERE representative_name="'.$con->real_escape_string($companyName).'")';

				$con->query($queryAssignorAssignee);
				
				$queryAssigneeAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
				
				$con->query($queryAssigneeAssignorAssignee);
				
				$queryAssignorAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')) GROUP BY assignor_and_assignee_id';
				$con->query($queryAssignorAssignorAssignee);
				
				$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE  representative_id IN (SELECT representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE  assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE representative_id IS NOT NULL AND rf_id IN ('.implode(',', $rfIDs).')))';
				$con->query($queryRepresentiative);
				
				$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE representative_id IN (SELECT representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE  assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE representative_id IS NOT NULL AND rf_id IN ('.implode(',', $rfIDs).')))';
				$con->query($queryRepresentiative);
								
				$queryLawFirm = 'INSERT IGNORE INTO `db_application`.`law_firm`(law_firm_id, name, representative_id)SELECT law_firm_id, name, representative_id FROM `db_uspto`.`law_firm` WHERE law_firm_id IN (SELECT law_firm_id FROM `db_uspto`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
				$con->query($queryLawFirm);

				$queryInsertAssignment = "INSERT IGNORE INTO `db_application`.`assignment`(rf_id, file_id, cname, caddress_1, caddress_2, caddress_3, caddress_4, reel_no, frame_no, convey_text, record_dt, last_update_dt, page_count, purge_in, law_firm_id) SELECT rf_id, file_id, cname, caddress_1, caddress_2, caddress_3, caddress_4, reel_no, frame_no, convey_text, record_dt, last_update_dt, page_count, purge_in, law_firm_id FROM `db_uspto`.`assignment` WHERE rf_id IN (".implode(',', $rfIDs).")";
				$con->query($queryInsertAssignment);

				$queryAssignmentConveyance = 'INSERT IGNORE INTO `db_application`.`assignment_conveyance`(rf_id, convey_ty, employer_assign) SELECT rf_id, convey_ty, employer_assign FROM `db_uspto`.`assignment_conveyance` WHERE rf_id IN ('.implode(',', $rfIDs).')';
				$con->query($queryAssignmentConveyance);
				
				$queryDocument = 'INSERT IGNORE INTO db_application.documentid (rf_id, title, lang, appno_doc_num, appno_date, appno_country, pgpub_doc_num, pgpub_date, pgpub_country, grant_doc_num, grant_date, grant_country) Select rf_id, title, lang, appno_doc_num, appno_date, appno_country, pgpub_doc_num, pgpub_date, pgpub_country, grant_doc_num, grant_date, grant_country FROM db_uspto.documentid WHERE appno_doc_num IN( SELECT appno_doc_num FROM db_uspto.documentid where rf_id IN ('.implode(',', $rfIDs).'))';
				$con->query($queryDocument);				

				$queryInsertAssignee = 'INSERT IGNORE INTO `db_application`.`assignee`(rf_id, ee_name, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country, assignor_and_assignee_id) SELECT rf_id, ee_name, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country, assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).')';
				$con->query($queryInsertAssignee);

				$queryInsertAssignor = 'INSERT IGNORE INTO `db_application`.`assignor`(rf_id, or_name, exec_dt, ack_dt, assignor_and_assignee_id) SELECT rf_id, or_name, exec_dt, ack_dt, assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')'; 
				$con->query($queryInsertAssignor);				
			}
			
			$queryFindRepresentativeConveyance = "SELECT rf_id FROM db_uspto.representative_assignment_conveyance WHERE rf_id IN (".implode(',', $rfIDs).")"; 
			
			$resultRepresentativeRFIDs = $con->query($queryFindRepresentativeConveyance);
			
			if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
				$allRFIDs = array();
				
				while($row = $resultRepresentativeRFIDs->fetch_object()) {
					array_push($allRFIDs, $row->rf_id);
				}
				
				$con->query("DELETE FROM db_application.assignment_conveyance WHERE rf_id IN (".implode(',', $allRFIDs).")");
				$con->query("INSERT IGNORE INTO db_application.assignment_conveyance SELECT rf_id, convey_ty, employer_assign FROM db_uspto.representative_assignment_conveyance WHERE rf_id IN (".implode(',', $allRFIDs).")");
			}
			
			$con->query("SET FOREIGN_KEY_CHECKS = 1");
			echo "Tree created";
		}		
	}
}
?>