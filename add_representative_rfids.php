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


if(count($variables) == 3) {
	$organisationID = $variables[1];
	$companyName = $variables[2];
	 
	if($companyName != "") {
		$representativeID = 0;
		
		/*Find Company in representative table*/
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				$queryRepresentative = 'SELECT representative_id FROM representative WHERE (original_name = "'.$con->real_escape_string($companyName).'" OR representative_name = "'.$con->real_escape_string($companyName).'")';
			
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
		
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
					$representativeData = $resultRepresentativeParentCompany->fetch_object();
					$representativeID = $representativeData->representative_id;
					if( $representativeID > 0 ) {
						$con->query('DELETE `db_uspto`.`representative_transactions` WHERE organisation_id = '.$organisationID.' AND representative_id='.$representativeID);
						$queryRepresentativeCompanies = 'SELECT original_name, representative_name FROM representative WHERE representative_id = '.$representativeID.' OR parent_id = '.$representativeID;
						
						$resultRepresentativeCompanies = $orgConnect->query($queryRepresentativeCompanies);
						
						if($resultRepresentativeCompanies && $resultRepresentativeCompanies->num_rows > 0) {
							$allCompanies = [];
							while($rowCompany = $resultRepresentativeCompanies->fetch_object()) {
								$name = $rowCompany->original_name;
								
								if(!in_array($name, $allCompanies)){
									array_push($allCompanies, $name);
								}
							}
							
							$allNames = "";
							foreach($allCompanies as $company) {
								$allNames .= ' aa.name = "'.$con->real_escape_string($company).'" OR r.representative_name="'.$con->real_escape_string($company).'" OR ';
							}
							
							$allNames = substr($allNames, 0, -3);
							
							$queryAssignorAssignees = 'SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor_and_assignee` as aa LEFT JOIN `db_uspto`.`representative` as r  ON r.representative_id = aa.representative_id WHERE ('.$allNames.') GROUP BY assignor_and_assignee_id';
							
							$assignorAssigneeIDs = array();
							$result = $con->query($queryAssignorAssignees);
							if($result->num_rows > 0) {	
								while($row = $result->fetch_object()){
									array_push($assignorAssigneeIDs, $row->assignor_and_assignee_id);
								}
							}
							
							if(count($assignorAssigneeIDs) > 0) {
								$rfIDs = []; 
					
								$queryAssignee = 'SELECT rf_id FROM `db_uspto`.`assignee` as ac WHERE assignor_and_assignee_id IN ( '.implode(",", $assignorAssigneeIDs).') GROUP BY rf_id';
								
								$result = $con->query($queryAssignee);
								if($result->num_rows > 0) {	
									while($row = $result->fetch_object()){
										array_push($rfIDs, $row->rf_id);
									}
								}
								
								$queryAssignor = 'SELECT rf_id FROM `db_uspto`.`assignor` as ac WHERE assignor_and_assignee_id IN ( '.implode(",", $assignorAssigneeIDs).') GROUP BY rf_id';

								$result = $con->query($queryAssignor);
								if($result->num_rows > 0) {	
									while($row = $result->fetch_object()){
										array_push($rfIDs, $row->rf_id);
									}
								}								
								if(count($rfIDs) > 0) {								
									$queryDocumentRF = "SELECT rf_id FROM documentid WHERE rf_id IN (".implode(',', $rfIDs).") GROUP BY rf_id";
								
									$result = $con->query($queryDocumentRF);
									$rfIDs = array();
									if($result && $result->num_rows > 0) {	
										while($row = $result->fetch_object()){
											array_push($rfIDs, $row->rf_id);
										}
									}									
									if(count($rfIDs) > 0) {
										$string = "";
									
										foreach($rfIDs as $r){
											$string .= '('.$organisationID.', '.$representativeID.', '.$r.'), '; 
										}
										
										$string = substr($string, 0, -2);
										
										$con->query('INSERT IGNORE INTO `db_uspto`.`representative_transactions`(organisation_id, representative_id, rf_id) VALUES '.$string)	;
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