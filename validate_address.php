<?php
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);


$org = "SELECT * FROM db_business.organisation where organisation_id = 68";

$resultQuery = $con->query($org);
if($resultQuery && $resultQuery->num_rows > 0){
	$orgRow = $resultQuery->fetch_object();
	
	$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
	
	if($orgConnect) {
		print_r($orgRow);
		$query = "SELECT original_name FROM representative";
		
		$resultRepresentativeCompanies = $orgConnect->query($query);
		
		$assignorAndAssigneeIDs = array();
		
		if($resultRepresentativeCompanies && $resultRepresentativeCompanies->num_rows > 0){
		
			while($row = $resultRepresentativeCompanies->fetch_object()){
				$nameRepresentaitve = $row->original_name;
				
				$queryFind = 'SELECT assignor_and_assignee_id FROM db_uspto.assignor_and_assignee WHERE name ="'.$con->real_escape_string($nameRepresentaitve).'"';
				
				$resultFind = $con->query($queryFind);
				
				if($resultFind && $resultFind->num_rows > 0){
					$rowFind = $resultFind->fetch_object();
					if($rowFind->assignor_and_assignee_id > 0 ){
						array_push($assignorAndAssigneeIDs, $rowFind->assignor_and_assignee_id);
					}
				}			
			}
		}
		
		$query = "SELECT a.rf_id, a.law_firm_id, caddress_1, caddress_2 FROM assignment as a INNER JOIN assignee as aa ON aa.rf_id = a.rf_id INNER JOIN db_uspto.representative_transactions as rt ON rt.rf_id = aa.rf_id WHERE rt.organisation_id = 68 AND aa.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).") GROUP BY a.rf_id";
		
		$resultAssignment = $con->query($query);
		
		if($resultAssignment && $resultAssignment->num_rows > 0) {
			
			while($rowAssignment = $resultAssignment->fetch_object()){
				$caddress1 = $rowAssignment->caddress_1;
				$caddress2 = $rowAssignment->caddress_2;
				$value1 = "";$value2="";
				if($caddress1 != '') {
					$value1 = exec('python3 /var/www/html/python_script/validate_address.py "'.$con->real_escape_string($caddress1).'".');
				} 
				
				if($caddress2 != '') {
					$value2 = exec('python3 /var/www/html/python_script/validate_address.py "'.$con->real_escape_string($caddress2).'".');
				}
				$queryUpdate = "";
				echo "<pre>";
				echo $caddress1,$caddress2,$value1,$value2."<br/>";
				if($value1 == '3' && $value2 == '3') {
					$queryUpdate = 'UPDATE db_uspto.assignment SET caddress_1 = "", caddress_2 = "", caddress_5= "'.$con->real_escape_string($caddress1).'", caddress_6= "'.$con->real_escape_string($caddress2).'" WHERE rf_id = '.$rowAssignment->rf_id;
				} else if($value2 == '3') {
					$queryUpdate = 'UPDATE db_uspto.assignment SET caddress_2 = "", caddress_6= "'.$con->real_escape_string($caddress2).'" WHERE rf_id = '.$rowAssignment->rf_id;
				} else if($value1 == '3') {
					$queryUpdate = 'UPDATE db_uspto.assignment SET caddress_1 = "'.$con->real_escape_string($caddress2).'",  caddress_2 = "" WHERE rf_id = '.$rowAssignment->rf_id;
				}
				
				if($queryUpdate != "") {
					echo $queryUpdate."<br/>";
					$runQuery = $con->query($queryUpdate);
					
					if($runQuery) {
						if($value1 == '3' && $value2 == '3') {
							echo "1<br/>";
							$con->query('DELETE FROM db_uspto.lawyer WHERE law_firm_id = '.$rowAssignment->law_firm_id.' AND name = "'.$con->real_escape_string($caddress1).'"');
						} else if($value1 == '3') {
							echo "2<br/>";
							if($caddress2 != '') {
								$queryCheck = 'SELECT lawyer_id FROM db_uspto.lawyer WHERE law_firm_id = '.$rowAssignment->law_firm_id.' AND name = "'.$con->real_escape_string($caddress2).'"';
								echo $queryCheck ."<br/>";
								$resultCheck = $con->query($queryCheck);
								
								if($resultCheck && $resultCheck->num_rows > 0) {
									echo "3<br/>";
									$con->query('DELETE FROM db_uspto.lawyer WHERE law_firm_id = '.$rowAssignment->law_firm_id.' AND name = "'.$con->real_escape_string($caddress1).'"');
									
									$con->query('UPDATE db_uspto.lawyer SET instances = instances + 1 WHERE law_firm_id = '.$rowAssignment->law_firm_id.' AND name = "'.$con->real_escape_string($caddress2).'"');
									
								} else {
									echo "4<br/>";
									$con->query('UPDATE db_uspto.lawyer SET name = "'.$con->real_escape_string($caddress2).'" WHERE law_firm_id = '.$rowAssignment->law_firm_id.' AND name = "'.$con->real_escape_string($caddress1).'"');
								}
							} else {
								echo "5<br/>";
								$con->query('DELETE FROM db_uspto.lawyer WHERE law_firm_id = '.$rowAssignment->law_firm_id.' AND name = "'.$con->real_escape_string($caddress1).'"');
							}							
						}
					}
				}
			}
		}
		
	}	
}




