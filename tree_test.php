<?php 

$con = new mysqli("167.172.195.92","db_user_all","wDv%5tgn0O0kMkMM","db_uspto");

$variables = $argv;
if(count($variables) >1 ) {
	$companyName = $variables[1];
		
	if($companyName != "") { 
		/*echo $companyName."<br/>";*/
		
		$queryFindIDs = 'SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor_and_assignee` as aa LEFT JOIN `db_uspto`.`representative` as r1 ON r1.representative_id = aa.representative_id WHERE (aa.name = "'.$con->real_escape_string($companyName).'" OR r1.representative_name="'.$con->real_escape_string($companyName).'") GROUP BY assignor_and_assignee_id';
		
		
		$resultIDs = $con->query($queryFindIDs);
		
		$assignorAndAssigneeIDs = array();
		
		if($resultIDs->num_rows > 0) {	
			while($row = $resultIDs->fetch_object()){
				array_push($assignorAndAssigneeIDs, $row->assignor_and_assignee_id);
			}
		}
		$rfIDs = [];
		
		if(count($assignorAndAssigneeIDs) > 0 ) {
			$queryAssignee = 'SELECT rf_id FROM `db_uspto`.`assignee` as ac WHERE assignor_and_assignee_id IN ('.implode(',', $assignorAndAssigneeIDs).') GROUP BY rf_id';

			$result = $con->query($queryAssignee);
			
			/*echo $result->num_rows."<br/>";*/
			if($result->num_rows > 0) {	
				while($row = $result->fetch_object()){
					array_push($rfIDs, $row->rf_id);
				}
			}
			
			$queryAssignor = 'SELECT rf_id FROM `db_uspto`.`assignor` as ac WHERE assignor_and_assignee_id IN ('.implode(',', $assignorAndAssigneeIDs).') GROUP BY rf_id';

			$result = $con->query($queryAssignor);
			/*echo $result->num_rows."<br/>";*/
			if($result->num_rows > 0) {	
				while($row = $result->fetch_object()){
					if(!in_array($row->rf_id, $rfIDs)){
						array_push($rfIDs, $row->rf_id);
					}
				}
			}
		}
		echo implode(",", $rfIDs);
		die;
		if(count($rfIDs) > 0) {
			$queryFindCorrectRFIDs = 'SELECT rf_id FROM db_uspto.documentid WHERE appno_doc_num IN (SELECT appno_doc_num FROM db_uspto.documentid WHERE appno_doc_num <> "" AND  rf_id IN ('.implode(',', $rfIDs).')) GROUP BY rf_id';
			
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
			$deleteAssignorAssignee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE representative_id IN (SELECT representative_id FROM `db_application`.`representative` WHERE representative_name="'.$con->real_escape_string($companyName).'")';

			$deleteRepresentiative = 'DELETE FROM `db_application`.`representative` WHERE representative_name="'.$con->real_escape_string($companyName).'"';

			//$deleteLawFirm = 'DELETE FROM `db_application`.`law_firm` WHERE law_firm_id IN (SELECT law_firm_id FROM `db_application`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).'))';

			$deleteAssignee = 'DELETE FROM `db_application`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).')';

			$deleteAssignor = 'DELETE FROM `db_application`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')';

			$deleteAssignment = 'DELETE FROM `db_application`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).')';

			$deleteAssignmentConveyance = 'DELETE FROM `db_application`.`assignment_conveyance` WHERE rf_id IN ('.implode(',', $rfIDs).')';

			//$deleteDocument = 'DELETE FROM `db_application`.`documentid` WHERE rf_id IN ('.implode(',', $rfIDs).')';

			$con->query($deleteRepresentiative);
			$con->query($deleteAssignorAssignee);
			//$con->query($deleteLawFirm);
			$con->query($deleteAssignee);
			$con->query($deleteAssignor);
			$con->query($deleteAssignment);
			$con->query($deleteAssignmentConveyance);
			//$con->query($deleteDocument);
			
			$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
			$con->query("SET FOREIGN_KEY_CHECKS = 0");			
			
			/*$queryRepresentiative = 'INSERT INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE representative_name="'.$con->real_escape_string($companyName).'"';

			$con->query($queryRepresentiative);
			echo "REPRESENTATIVE: ".$con->insert_id."<br/>";*/

			/*$queryAssignorAssignee = 'INSERT INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT aaa.assignor_and_assignee_id, name, aaa.instances, aaa.representative_id FROM `db_uspto`.`assignor_and_assignee` as aaa INNER JOIN `db_uspto`.`representative` as r ON r.representative_id = aaa.representative_id WHERE representative_name="'.$con->real_escape_string($companyName).'"';

			$con->query($queryAssignorAssignee);*/
			
			$queryAssigneeAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
			/*echo $queryAssigneeAssignorAssignee."<br/>";*/
			$con->query($queryAssigneeAssignorAssignee);
			
			$queryAssignorAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')) GROUP BY assignor_and_assignee_id';
			
			$con->query($queryAssignorAssignorAssignee);
			
			
			$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE  representative_id IN (SELECT representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE  assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE representative_id IS NOT NULL AND rf_id IN ('.implode(',', $rfIDs).')))';
			
			$con->query($queryRepresentiative);
			
			$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE representative_id IN (SELECT representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE  assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE representative_id IS NOT NULL AND rf_id IN ('.implode(',', $rfIDs).')))';
			/*echo $queryRepresentiative."<br/>";*/
			$con->query($queryRepresentiative);
			
			/*$queryLawFirm = 'INSERT IGNORE INTO `db_application`.`law_firm`(law_firm_id, name, representative_id)SELECT law_firm_id, name, representative_id FROM `db_uspto`.`law_firm` WHERE law_firm_id IN (SELECT law_firm_id FROM `db_uspto`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).'))';

			$con->query($queryLawFirm);*/

			$queryInsertAssignment = "INSERT IGNORE INTO `db_application`.`assignment`(rf_id, file_id, cname, caddress_1, caddress_2, caddress_3, caddress_4, reel_no, frame_no, convey_text, record_dt, last_update_dt, page_count, purge_in, law_firm_id) SELECT rf_id, file_id, cname, caddress_1, caddress_2, caddress_3, caddress_4, reel_no, frame_no, convey_text, record_dt, last_update_dt, page_count, purge_in, law_firm_id FROM `db_uspto`.`assignment` WHERE rf_id IN (".implode(',', $rfIDs).")";

			$con->query($queryInsertAssignment);

			$queryAssignmentConveyance = 'INSERT IGNORE INTO `db_application`.`assignment_conveyance`(rf_id, convey_ty, employer_assign) SELECT rf_id, convey_ty, employer_assign FROM `db_uspto`.`assignment_conveyance` WHERE rf_id IN ('.implode(',', $rfIDs).')';

			$con->query($queryAssignmentConveyance);
			$queryDocument = 'INSERT IGNORE INTO db_application.documentid Select * FROM db_uspto.documentid WHERE appno_doc_num IN( SELECT appno_doc_num FROM db_uspto.documentid where rf_id IN ('.implode(',', $rfIDs).'))';
			//queryDocument1 = 'INSERT IGNORE INTO db_application.documentid Select * FROM db_uspto.documentid WHERE grant_doc_num IN( SELECT grant_doc_num FROM db_uspto.documentid where grant_doc_num <> '' AND  rf_id IN ('.implode(',', $rfIDs).'))';
			
			
			//$queryDocument = 'INSERT IGNORE INTO `db_application`.`documentid`(rf_id, title, lang, appno_doc_num, appno_date, appno_country, pgpub_doc_num, pgpub_date, pgpub_country, grant_date, grant_country) SELECT rf_id, title, lang, appno_doc_num, appno_date, appno_country, pgpub_doc_num, pgpub_date, pgpub_country, grant_date, grant_country FROM `db_uspto`.`documentid` WHERE rf_id IN ('.implode(',', $rfIDs).')';
			
			$con->query($queryDocument);
			//$con->query($queryDocument1);

			$queryInsertAssignee = 'INSERT IGNORE INTO `db_application`.`assignee`(rf_id, ee_name, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country, assignor_and_assignee_id) SELECT rf_id, ee_name, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country, assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).')';
			
			
			$con->query($queryInsertAssignee);

			$queryInsertAssignor = 'INSERT IGNORE INTO `db_application`.`assignor`(rf_id, or_name, exec_dt, ack_dt, assignor_and_assignee_id) SELECT rf_id, or_name, exec_dt, ack_dt, assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')'; 

			$con->query($queryInsertAssignor);
			
			$queryFindRepresentativeConveyance = "SELECT rf_id FROM db_uspto.representative_assignment_conveyance WHERE rf_id IN (".implode(',', $rfIDs).")"; 
			
			$resultRepresentativeRFIDs = $con->query($queryFindRepresentativeConveyance);
			
			if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
				$allRFIDs = array();
				
				while($row = $resultRepresentativeRFIDs->fetch_object()) {
					array_push($allRFIDs, $row->rf_id);
				}
				
				$con->query("DELETE FROM `db_application`.`assignment_conveyance` WHERE rf_id IN (".implode(',', $allRFIDs).")");
				$con->query("INSERT IGNORE INTO `db_application`.`assignment_conveyance` SELECT rf_id, convey_ty, employer_assign FROM db_uspto.representative_assignment_conveyance WHERE rf_id IN (".implode(',', $allRFIDs).")");
			}
			
			
			$con->query("SET FOREIGN_KEY_CHECKS = 0");
			echo "Tree created";
		}		
	}
}
?>