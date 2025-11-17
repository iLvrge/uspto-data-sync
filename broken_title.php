<?php 
ignore_user_abort(true);

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbApplication);

$variables = $argv;
if(count($variables) == 3) {	
	$companyID = $variables[1];
	$organisationID = $variables[2];
	if($organisationID > 0 && $companyID > 0) { 
		if($con->query('CALL routine_transaction('.(int)$companyID.','.(int)$organisationID.')')){
			$result = $con->query('CALL GetAssetsTableC("'.$companyID.'", '.$organisationID.')');
			if($result && $result->num_rows > 0){
				$con->close();
				$con = new mysqli($host, $user, $password, $dbApplication);
				$brokenTitle = array();
				while($row = $result->fetch_object()){
					/*$query = "SELECT temp_document_transactions.rf_id, 
								temp_document_transactions.assignor_and_assignee_id as frm, 
								temp_document_transactions1.assignor_and_assignee_id as toA, temp_document_transactions.convey_name  FROM temp_document_transactions 
								INNER JOIN temp_document_transactions as temp_document_transactions1 
									ON temp_document_transactions1.rf_id = temp_document_transactions.rf_id 
									AND temp_document_transactions1.party_type > temp_document_transactions.party_type
									WHERE temp_document_transactions.appno_doc_num = '".$row->appno_doc_num."'
									GROUP BY temp_document_transactions.rf_id
								ORDER BY temp_document_transactions.transaction_date ASC, temp_document_transactions.party_type ASC, temp_document_transactions1.transaction_date ASC, temp_document_transactions1.party_type ASC 
								";*/
					$query = "SELECT temp_document_transactions.rf_id, temp_document_transactions.assignor_and_assignee_id as frm, temp_document_transactions1.assignor_and_assignee_id as toA, temp_document_transactions.convey_name  FROM temp_document_transactions 
					INNER JOIN temp_document_transactions as temp_document_transactions1 ON temp_document_transactions1.rf_id = temp_document_transactions.rf_id AND temp_document_transactions1.party_type > temp_document_transactions.party_type WHERE temp_document_transactions.appno_doc_num = '".$row->appno_doc_num."'	
					ORDER BY temp_document_transactions.transaction_date ASC, temp_document_transactions.party_type ASC, temp_document_transactions1.transaction_date ASC, temp_document_transactions1.party_type ASC ";
					
					$resultA = $con->query($query) or die($con->error);
					
					$breakLoop = false;
					if($resultA && $resultA->num_rows > 0) {
						$totalEmployees = 0;
						$totalEmployeesAssignees = 0;
						$previousAssignee = 0;
						$previousAssignor = 0;
						$previousRFID = 0;
						
						while($rowA = $resultA->fetch_object()){
							if($previousRFID != $rowA->rf_id && $previousAssignor != $rowA->frm && $previousAssignee != $rowA->toA ) {
								
								if($rowA->convey_name == 'employee') {
									if($rowA->frm != 0) {
										$totalEmployees++;
									} 
									if($rowA->toA != 0) {
										$totalEmployeesAssignees++;									
									}
								} else {
									if($previousAssignee != 0 && $previousAssignee != $rowA->frm) {
										$breakLoop = true;
										echo "broken";
										array_push($brokenTitle, '"'.$row->appno_doc_num.'"');
										break;
									}
								}
								$previousRFID = $rowA->rf_id;
								$previousAssignor = $rowA->frm;
								$previousAssignee = $rowA->toA;
							}								
						}
						if($breakLoop === false && $totalEmployees == 0 ) {
							echo "not broken";
							array_push($brokenTitle, '"'.$row->appno_doc_num.'"');
						}
					}
				}
				if(count($brokenTitle) > 0) {
					$con->query('DELETE table_d WHERE company_id = '.$companyID.' AND organisation_id ='.$organisationID);
					$con->query('INSERT IGNORE INTO table_d SELECT appno_doc_num, representative_id, company_id, organisation_id FROM 	table_c WHERE appno_doc_num IN ('.implode(',', $brokenTitle).') AND company_id = '.$companyID.' AND organisation_id ='.$organisationID);
				}
			}
		} else {
			echo "Error description: " . $con->error; 
		}
	}
}
	