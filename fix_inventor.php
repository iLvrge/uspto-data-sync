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
$con = new mysqli($host, $user, $password, $dbApplication);

$variables = $argv;
//$variables = $_GET;
if(count($variables) == 3) {
/*if(count($variables) > 0) {*/
	$organisationID = $variables[1];
	$representativeID = $variables[2];
	/*$organisationID = $variables['o'];
	$representativeID = $variables['r'];*/
	echo $organisationID.",".$representativeID."<br/>";
	if((int)$organisationID > 0) {
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		/*echo $queryOrganisation."<br/>";*/
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				/*Check from client database */
				if($representativeID != "" && $representativeID > 0) {
					$queryRepresentative = "SELECT representative_id, original_name, representative_name  FROM representative WHERE representative_id = '".$representativeID."' AND parent_id = 0";
				} else {
					$queryRepresentative = "SELECT representative_id, original_name, representative_name FROM representative WHERE (original_name = '".$con->real_escape_string($orgRow->name)."' OR representative_name = '".$con->real_escape_string($orgRow->name)."') AND parent_id = 0";
				}
				//echo $queryRepresentative."<br/>";
				
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
				
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
					$getCompanyRow = $resultRepresentativeParentCompany->fetch_object();
					$findRepresentativeName = "Select original_name, representative_name FROM representative WHERE representative_id = '".$getCompanyRow->representative_id."' OR parent_id = '".$getCompanyRow->representative_id."'";
					//echo $findRepresentativeName."<br/>";
					$resultRepresentativeCompanies = $orgConnect->query($findRepresentativeName);
					
					$allCompanies = array();
					
					while($row = $resultRepresentativeCompanies->fetch_object()){						
						$name = $row->original_name;
						array_push($allCompanies, $name);
					}
					/*echo "<pre>";
					print_r($allCompanies);
					*/
					
					if(count($allCompanies) > 0) {
						$queryAssignee = 'SELECT rf_id FROM `db_application`.`assignee` as ac WHERE assignor_and_assignee_id IN ( SELECT assignor_and_assignee_id FROM `db_application`.`assignor_and_assignee` as aa LEFT JOIN `db_application`.`representative` as r1 ON r1.representative_id = aa.representative_id WHERE ( ';
						
						foreach($allCompanies as $company) {
							$queryAssignee .= 'aa.name = "'.$con->real_escape_string($company).'" OR r1.representative_name="'.$con->real_escape_string($company).'" OR ';
						}
						
						$queryAssignee = substr($queryAssignee, 0, -3);
						
						$queryAssignee .= ' ) ) GROUP BY rf_id ';
						//echo $queryAssignee."<br/>";
						$result = $con->query($queryAssignee);
						$rfIDs = [];
						/*echo $result->num_rows."<br/>";*/
						if($result->num_rows > 0) {	
							while($row = $result->fetch_object()){
								array_push($rfIDs, $row->rf_id);
							}
						}
						
						$queryAssignor = 'SELECT rf_id FROM `db_application`.`assignor` as ac WHERE assignor_and_assignee_id IN ( SELECT assignor_and_assignee_id FROM `db_application`.`assignor_and_assignee` as aa LEFT JOIN `db_application`.`representative` as r1 ON r1.representative_id = aa.representative_id WHERE ( ';
						
						foreach($allCompanies as $company) {
							$queryAssignor .= ' aa.name = "'.$con->real_escape_string($company).'" OR r1.representative_name="'.$con->real_escape_string($company).'" OR ';
						}		

						$queryAssignor = substr($queryAssignor, 0, -3);
						
						$queryAssignor .= ') ) GROUP BY rf_id';
						//echo $queryAssignor."<br/>";
						$result = $con->query($queryAssignor);
						
						/*echo $result->num_rows."<br/>";*/
						if($result->num_rows > 0) {	
							while($row = $result->fetch_object()){
								array_push($rfIDs, $row->rf_id);
							}
						}
						/*AND `status` = 0*/
						$queryFindCorrectRFIDs = 'SELECT appno_doc_num FROM db_application.documentid WHERE appno_doc_num <> "" AND  rf_id IN ('.implode(',', $rfIDs).')  GROUP BY appno_doc_num';
						//echo $queryFindCorrectRFIDs."<br/>";
						$resultIDs = $con->query($queryFindCorrectRFIDs);
						$appNo = array(); 
						if($resultIDs && $resultIDs->num_rows > 0) {
							$appNo = array(); 
							while($row = $resultIDs->fetch_object()){
								array_push($appNo, $row->appno_doc_num);
							}
						}
						//echo "<pre>";
						//print_r($appNo);die;
						
						$otherApp = array();
						if(count($appNo) > 0) {
							foreach($appNo as $application) {
								
								$queryInventorCheck = "SELECT `or`.or_name, `or`.rf_id, ac.employer_assign, ac.convey_ty FROM db_application.assignor as `or` INNER JOIN db_application.assignment_conveyance as ac ON `or`.rf_id = ac.rf_id INNER JOIN db_application.documentid as d ON d.rf_id = ac.rf_id WHERE d.appno_doc_num = '".$application."'";
								
								$resultInventor = $con->query($queryInventorCheck);
								
								$queryInventorFromPatentApplication = "SELECT * FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num = '".$application."'";
								
								
								
								$resultPatentApplicationInventor = $con->query($queryInventorFromPatentApplication);
								//echo $resultPatentApplicationInventor->num_rows."<br/>";
								if($resultPatentApplicationInventor->num_rows > 0) {
									if($resultInventor->num_rows > 0) {
										//$allInventor = array();
										$inventorsData = array();
										while($rowUSPTOInventor = $resultInventor->fetch_object()) {
											array_push($inventorsData, $rowUSPTOInventor);
										}
										
										$biblioInventorData = array();
										
										while($rowBiblioInventor = $resultPatentApplicationInventor->fetch_object()) {
											/*echo "<pre>";
											echo "Biblio Inventor<br/>";
											print_r($rowBiblioInventor);
											print_r($inventorsData);*/
											$givenName = strtolower($rowBiblioInventor->given_name);
											$givenName = removeDoubleSpace( $givenName );
											$givenName = strReplace( $givenName );
											//$middleName = strtolower($rowBiblioInventor->middle_name);
											$familyName = strtolower($rowBiblioInventor->family_name);
											$familyName = removeDoubleSpace( $familyName );
											$familyName = strReplace( $familyName );
											foreach($inventorsData as $invent) {
												$name = strtolower($invent->or_name);
												
												//echo $familyName."@@".$name."<br/>";
												if(strpos($name, $familyName) !== false){
													if(strpos($name, $givenName) !== false){
														/*Inventor found in USPTO*/
														if($invent->employer_assign == 0) {
															/*Add in update table*/
															$con->query("UPDATE db_application.assignment_conveyance SET employer_assign = 1 WHERE rf_id = '".$invent->rf_id."'");
															/*$checkFlagUSPTO = "SELECT rf_id FROM db_uspto.representative_assignment_conveyance WHERE rf_id = '".$invent->rf_id."'";
															
															$resultFlagUSPTO = $con->query($checkFlagUSPTO);
															
															$queryInsertOrUpdate = "";
															if($resultFlagUSPTO && $resultFlagUSPTO->num_rows > 0) {
																$queryInsertOrUpdate = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = 1 WHERE rf_id = '".$invent->rf_id."'";
															} else {
																$queryInsertOrUpdate = "INSERT INTO db_uspto.representative_assignment_conveyance(rf_id, convey_ty, employer_assign) VALUES('".$invent->rf_id."', '".$invent->convey_ty."', '1')"; 
															}
															$con->query($queryInsertOrUpdate);*/
															//echo "EMPLOYER MISSING in USPTO2<br/>";
															//die;
														} else {
															//echo "EMPLOYER FIND in USPTO2<br/>";
															
														}
														//break;
													} else {
														/*Inventor not found in USPTO*/
													}
												} else {
													/*Inventor not found in USPTO*/
												}
												
												/*
												
												preg_match('/\b('.$familyName.'\w+)\b/', strtolower($invent->or_name), $matches); 
												
												if(count($matches) == 0) {
													echo "FamilyName Not Matched<br/>";
													// matches expression
													preg_match('/\b(\w*'.$givenName.'\w*)\b/', strtolower($invent->or_name), $matches);
													
													print_r($matches);
													
													if(count($matches) > 0 && $invent->employer_assign == 0){
														echo "Inventor missing in USPTO<br/>";
														die;
													} else if(count($matches) > 0 && $invent->employer_assign == 1) {
														echo "GivenName Matched<br/>";
													}
												} else {
													echo "FamilyName Matched<br/>";
													preg_match('/\b(\w*'.$givenName.'\w*)\b/', strtolower($invent->or_name), $matches);
													
													print_r($matches);
													
													if(count($matches) > 0 && $invent->employer_assign == 0){
														echo "Inventor missing in USPTO2<br/>";
														die;
													} else if(count($matches) > 0 && $invent->employer_assign == 1) {
														echo "GivenName Matched<br/>";
													}
												}*/												
											}
											
										}
									}
									$con->query("UPDATE documentid SET status = 1 WHERE appno_doc_num = '".$application."'");
								} else {
									array_push($otherApp, $application);
								}								
							}
						}
						
						$queryFindAllAssignor = "SELECT a.or_name, `a`.rf_id, ac.employer_assign, ac.convey_ty FROM db_application.assignor as a INNER JOIN db_application.assignment_conveyance as ac ON ac.rf_id = a.rf_id WHERE a.rf_id IN (SELECT rf_id FROM db_application.documentid WHERE ac.employer_assign = 0 AND appno_doc_num IN (".implode(',', $appNo).")) ";
						echo $queryFindAllAssignor."<br/>";
						//$rfIDs = array();
						$resultFindInventor = $con->query($queryFindAllAssignor);
						if($resultFindInventor && $resultFindInventor->num_rows > 0) {
							echo $resultFindInventor->num_rows."<br/>";
							while($rowUSPTOInventor = $resultFindInventor->fetch_object()) {
								//array_push($rfIDs, $rowUSPTOInventor->rf_id);
								$queryInventorCheck = 'SELECT `or`.rf_id FROM db_application.assignor as `or` INNER JOIN db_application.assignment_conveyance as ac ON `or`.rf_id = ac.rf_id INNER JOIN db_application.documentid as d ON d.rf_id = ac.rf_id WHERE d.appno_doc_num IN ('.implode(",", $appNo).') AND ac.employer_assign = 1 AND `or`.or_name = "'.$con->real_escape_string($rowUSPTOInventor->or_name).'" LIMIT 1';
								$resultInventor = $con->query($queryInventorCheck);
								if($resultInventor->num_rows > 0) {			
									echo "Fixed Inventor with previous Match";
									echo $queryInventorCheck."<br/>";
									//$con->query("UPDATE db_application.assignment_conveyance SET employer_assign = 1 WHERE rf_id = '".$rowUSPTOInventor->rf_id."'");
								} else {
									echo $rowUSPTOInventor->or_name."<br/>";
								}
							}
						}





						
						/*$con->query('TRUNCATE db_uspto.representative_assignment_conveyance ');*/
						if(count($rfIDs) > 0) {
							$queryFindConveyance = 'Select rf_id from db_uspto.assignment_conveyance WHERE rf_id IN (Select rf_id from db_application.assignment_conveyance where employer_assign = 1 AND rf_id IN ('.implode(',', $rfIDs).')) AND employer_assign = 0';
							$resultConveyance = $con->query($queryFindConveyance);
							if($resultConveyance && $resultConveyance->num_rows > 0) {
								$allRFIDs = array();
								while($rowConveyance = $resultConveyance->fetch_object()){
									array_push($allRFIDs,$rowConveyance->rf_id);
								}
								
								$uniqueRFIDs = array_unique($allRFIDs, SORT_NUMERIC );
								
								$queryRepresentativeFindRFIDS = "SELECT rf_id FROM db_uspto.representative_assignment_conveyance WHERE rf_id IN (".implode($uniqueRFIDs).") AND employer_assign = 0";
								
								$resultRFIDS = $con->query($queryRepresentativeFindRFIDS );
								
								$duplicate = array();
								
								if($resultRFIDS && $resultRFIDS->num_rows > 0){
									while($rowRF = $resultRFIDS->fetch_object()){
										array_push($duplicate, $rowRF->rf_id);
									}
									$con->query("UPDATE dbuspto.representative_assignment_conveyance SET employer_assign = 1 WHERE rf_id IN (".$duplicate.")");
								}
								$allRFIDs = array();
								if(count($duplicate) > 0) {
									$allRFIDs = array_diff($uniqueRFIDs, $duplicate);
								} else {
									$allRFIDs = $uniqueRFIDs;
								}
								
								
								if(count($allRFIDs) > 0) {
									$con->query('INSERT IGNORE db_uspto.representative_assignment_conveyance  Select rf_id, convey_ty, 1 as employer_assign from db_uspto.assignment_conveyance WHERE rf_id IN (Select rf_id from db_application.assignment_conveyance where employer_assign = 1 AND rf_id IN ('.implode(',', $allRFIDs).')) AND employer_assign = 0');
								}
								
							}
							/*$con->query('INSERT IGNORE db_uspto.representative_assignment_conveyance  Select rf_id, convey_ty, 1 as employer_assign from db_uspto.assignment_conveyance WHERE rf_id IN (Select rf_id from db_application.assignment_conveyance where employer_assign = 1 AND rf_id IN ('.implode(',', $rfIDs).')) AND employer_assign = 0');*/
						}
					}
				}
			}
		}
	}
}

/*INSERT IGNORE db_uspto.representative_assignment_conveyance  Select rf_id, convey_ty, 1 as employer_assign from db_uspto.assignment_conveyance WHERE rf_id IN (Select rf_id from db_application.assignment_conveyance where employer_assign = 1) AND employer_assign = 0;*/

function removeDoubleSpace($string) {
	return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return trim(strtolower($string));
}

/**
 * A slightly more readable, non-regex solution.
 */
function remove_if_trailing($haystack, $needle)
{
    // The length of the needle as a negative number is where it would appear in the haystack
    $needle_position = strlen($needle) * -1;  
	$lp = 0;
    // If the last N letters match $needle
    if (substr(strtolower($haystack), $needle_position) == strtolower($needle)) {
         // Then remove the last N letters from the string
        $haystack = substr($haystack, 0, $needle_position);
		if(strtolower($needle) == "company"){
			$haystack .= " co";
		} else if(strtolower($needle) == "incorporated"){
			$haystack .= " inc";
		} else if(strtolower($needle) == "limited"){
			$haystack .= " ltd";
		} else if(strtolower($needle) == "corporation"){
			$haystack .= " corp";
		}
		$lp = 1;
    }
    return array(trim(ucwords(strtolower($haystack))), $lp);
}