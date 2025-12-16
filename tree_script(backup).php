<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$con = new mysqli("167.172.195.92","db_user_all","wDv%5tgn0O0kMkM","db_uspto");

$variables = $argv;
if(count($variables) == 2) {
	$companyName = $variables[1];
	 
	if($companyName != "") {
		 
		$representativeID = 0;
		/*Find Company in representative table*/
		
		$queryRepresentative = "SELECT representative_id FROM `db_uspto`.`representative` WHERE representative_name='".$con->real_escape_string($companyName)."'";
		
		$resultRepresentative = $con->query($queryRepresentative);
		if($resultRepresentative && $resultRepresentative->num_rows > 0){
			$rowRepresentative = $resultRepresentative->fetch_object();
			$representativeID = $rowRepresentative->representative_id;
		}
		
		
		
		if( $representativeID > 0 ) {
			
			$rfIDs = [];
			
			$queryCustomerRFIDs = "SELECT count(rf_id) as counter FROM `db_uspto`.`representative_transactions` WHERE representative_id = ".$representativeID;
			
			$resultRepresentativeRFIDs = $con->query($queryCustomerRFIDs);
			
			if( $resultRepresentativeRFIDs ) {
				$row = $resultRepresentativeRFIDs->fetch_object();
				
				if($row->counter > 0) {
					$queryFindCorrectRFIDs = 'SELECT rf_id FROM db_uspto.documentid WHERE appno_doc_num IN (SELECT appno_doc_num FROM db_uspto.documentid WHERE appno_doc_num <> "" AND  rf_id IN (SELECT rf_id FROM `db_uspto`.`representative_transactions` WHERE representative_id = '.$representativeID.')) GROUP BY rf_id';
					
					$resultIDs = $con->query($queryFindCorrectRFIDs);
					
					if($resultIDs->num_rows > 0) {
						while($row = $resultIDs->fetch_object()){
							array_push($rfIDs, $row->rf_id);
						}
					}
				}
			}
		}
		
		if(count($rfIDs) > 0) {
			$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
			$con->query("SET FOREIGN_KEY_CHECKS = 0");	
			
			//Flush All table;
			$deleteAssignorAssignee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE representative_id ='.$representativeID;
			
			$queryDeleteAssigneeAssignor = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
			
			$queryDeleteAssignorAssigneee = 'DELETE FROM `db_application`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
			
			$deleteRepresentiative = 'DELETE FROM `db_application`.`representative` WHERE representative_id ='.$representativeID;

			$deleteAssignee = 'DELETE FROM `db_application`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).')';

			$deleteAssignor = 'DELETE FROM `db_application`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')';

			$deleteAssignment = 'DELETE FROM `db_application`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).')';

			$deleteAssignmentConveyance = 'DELETE FROM `db_application`.`assignment_conveyance` WHERE rf_id IN ('.implode(',', $rfIDs).')';
			
			//$deleteLawFirm = 'DELETE FROM `db_application`.`law_firm` WHERE law_firm_id IN (SELECT law_firm_id FROM `db_application`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
			
			$con->query($deleteRepresentiative);
			$con->query($deleteAssignorAssignee);
			$con->query($queryDeleteAssigneeAssignor);
			$con->query($queryDeleteAssignorAssigneee);
			$con->query($deleteAssignee);
			$con->query($deleteAssignor);
			$con->query($deleteAssignment);
			$con->query($deleteAssignmentConveyance);
			//$con->query($deleteLawFirm);
			
			$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE representative_id ='.$representativeID;
			$con->query($queryRepresentiative);
			
			$queryAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE representative_id ='.$representativeID;

			$con->query($queryAssignorAssignee);
			
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
			
			$queryAssigneeAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_application`.`assignee` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
			
			$con->query($queryAssigneeAssignorAssignee);
			
			$queryAssignorAssignorAssignee = 'INSERT IGNORE INTO `db_application`.`assignor_and_assignee`(assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM `db_uspto`.`assignor_and_assignee` WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_application`.`assignor` WHERE rf_id IN ('.implode(',', $rfIDs).')) GROUP BY assignor_and_assignee_id';
			$con->query($queryAssignorAssignorAssignee);
			
			$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE  representative_id IN (SELECT representative_id FROM `db_application`.`assignor_and_assignee` WHERE  assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_application`.`assignor` WHERE representative_id IS NOT NULL AND rf_id IN ('.implode(',', $rfIDs).')))';
			$con->query($queryRepresentiative);
			
			$queryRepresentiative = 'INSERT IGNORE INTO `db_application`.`representative`(representative_id, representative_name, created_at, updated_at) SELECT representative_id, representative_name, created_at, updated_at FROM `db_uspto`.`representative` WHERE representative_id IN (SELECT representative_id FROM `db_application`.`assignor_and_assignee` WHERE  assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_application`.`assignee` WHERE representative_id IS NOT NULL AND rf_id IN ('.implode(',', $rfIDs).')))';
			$con->query($queryRepresentiative);
							
			/*$queryLawFirm = 'INSERT IGNORE INTO `db_application`.`law_firm`(law_firm_id, name, representative_id)SELECT law_firm_id, name, representative_id FROM `db_uspto`.`law_firm` WHERE law_firm_id IN (SELECT law_firm_id FROM `db_uspto`.`assignment` WHERE rf_id IN ('.implode(',', $rfIDs).'))';
			$con->query($queryLawFirm);*/

			
			
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
			
			$con->query("SET FOREIGN_KEY_CHECKS = 1");
			echo "Tree created";
		}		
	}
}
?>