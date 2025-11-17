<?php 

require_once('/var/www/html/trash/vendor/autoload.php');
require_once('/var/www/html/trash/noti_config.php');
ignore_user_abort(true);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000); 
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$con->query('SET GLOBAL range_optimizer_max_mem_size=0');


function removeDoubleSpace($string) {
	return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return trim(ucwords(strtolower($string)));
}

function getCombinationNamesList($resultUniqueInventors, $con) {
	$Array4 = array();
	while($row = $resultUniqueInventors->fetch_object()) { 
		$name1 = $row->family_name.$row->middle_name.$row->given_name;
		$Name = removeDoubleSpace( $name1 );
		$Name = strReplace( $Name );
		array_push($Array4, strtolower('"'.$con->real_escape_string($Name).'"'));

		$name2 = $row->given_name.$row->middle_name.$row->family_name;
		$Name = removeDoubleSpace( $name2 );
		$Name = strReplace( $Name );
		array_push($Array4, strtolower('"'.$con->real_escape_string($Name).'"'));

		$name3 = $row->family_name.$row->given_name.$row->middle_name;
		$Name = removeDoubleSpace( $name3 );
		$Name = strReplace( $Name );
		array_push($Array4, strtolower('"'.$con->real_escape_string($Name).'"'));


		$name4 = $row->given_name.$row->family_name.$row->middle_name;
		$Name = removeDoubleSpace( $name4 );
		$Name = strReplace( $Name );
		array_push($Array4, strtolower('"'.$con->real_escape_string($Name).'"'));


		$name5 = $row->family_name.$row->given_name;
		$Name = removeDoubleSpace( $name5 );
		$Name = strReplace( $Name );
		array_push($Array4, strtolower('"'.$con->real_escape_string($Name).'"'));

		$name6 = $row->given_name.$row->family_name;
		$Name = removeDoubleSpace( $name6 );
		$Name = strReplace( $Name );
		array_push($Array4, strtolower('"'.$con->real_escape_string($Name).'"'));

		$name7 = $row->family_name;
		$Name = removeDoubleSpace( $name7 );
		$Name = strReplace( $Name );
		array_push($Array4, strtolower('"'.$con->real_escape_string($Name).'"'));

		$name8 = $row->given_name;
		$Name = removeDoubleSpace( $name8 );
		$Name = strReplace( $Name );
		array_push($Array4, strtolower('"'.$con->real_escape_string($Name).'"')); 
	}
	return $Array4;
}


$variables = $argv;
//$variables = $_GET;
if(count($variables) == 3) {
//if(count($variables) > 0) {
	$organisationID = $variables[1];
	$company = $variables[2];
	
	//$organisationID = $variables['o'];
	
	//echo $organisationID."<br/>";	
	if((int)$organisationID > 0) { 
		$logID = 0;
		$logResult = '';
		if($variables[2] != '') {
			$con->query("DELETE FROM db_new_application.log_messages WHERE organisation_id = ".$organisationID." AND company_id = '".$variables[2]."'");
			$logResult = $con->query("INSERT INTO db_new_application.log_messages(message, organisation_id, company_id) VALUES ('Employee flag',  '".$organisationID."', '".$variables[2]."'), ('Missing Assignment',  '".$organisationID."', '".$variables[2]."'), ('Missing NameChange',  '".$organisationID."', '".$variables[2]."'), ('Missing Change address',  '".$organisationID."', '".$variables[2]."'), ('Missing License',  '".$organisationID."', '".$variables[2]."'), ('Missing Security',  '".$organisationID."', '".$variables[2]."'), ('Missing Release',  '".$organisationID."', '".$variables[2]."'), ('Missing Merger',  '".$organisationID."', '".$variables[2]."')"); 
		} else {
			$con->query("DELETE FROM db_new_application.log_messages WHERE organisation_id = ".$organisationID." AND company_id = 0");
			$logResult = $con->query("INSERT INTO db_new_application.log_messages(message, organisation_id, company_id) VALUES ('Employee flag',  '".$organisationID."', '0'), ('Missing Assignment',  '".$organisationID."', '0'), ('Missing NameChange',  '".$organisationID."', '0'), ('Missing Change address',  '".$organisationID."', '0'), ('Missing License',  '".$organisationID."', '0'), ('Missing Security',  '".$organisationID."', '0'), ('Missing Release',  '".$organisationID."', '0'), ('Missing Merger',  '".$organisationID."', '0')"); 
		} 
		if($logResult) {
			$logID = $con->insert_id;
		}
		
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				/*Check from client database */
				$rfIDs = [];	
				$allRepresentative = array();
				$allRepresentativeNames = array();	 

				if($company != "") {
					$company = json_decode($company);
				} else {
					$company = array();
				}
				
				$queryRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 0"; 
				
				if(count($company) > 0) {
					$queryRepresentative .= " AND representative_id IN (".implode(',', $company).")";
				} else {
					$queryRepresentative .= " AND parent_id = 0 ";
				}
				$resultRepresentative = $orgConnect->query($queryRepresentative);	
				if($resultRepresentative && $resultRepresentative->num_rows > 0) {
					while($representative = $resultRepresentative->fetch_object()){
						array_push($allRepresentative, $representative->representative_id);
						array_push($allRepresentativeNames, '"'.$representative->representative_name.'"'); 
					}
				}
				$queryRepresentativeGroup = "SELECT representative_id, representative_name FROM representative WHERE type = 1 ";	
				if(count($company) == 0) {
					$queryRepresentativeGroup .= " AND parent_id = 0";
				} else {
					$queryRepresentativeGroup .= " AND representative_id IN (".implode(',', $company).")";
				}
					
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentativeGroup);
		
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {					
					while($getGroup = $resultRepresentativeParentCompany->fetch_object()) {
						$queryGroupRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 0 AND parent_id = ".$getGroup->representative_id;
						
						$resultRepresentativeGroupParentCompany = $orgConnect->query($queryGroupRepresentative);
						if($resultRepresentativeGroupParentCompany && $resultRepresentativeGroupParentCompany->num_rows > 0) {
							
							while($getCompanyRow = $resultRepresentativeGroupParentCompany->fetch_object()) {
								if(!in_array($getCompanyRow->representative_id, $allRepresentative)) { 
									array_push($allRepresentative, $getCompanyRow->representative_id);
									array_push($allRepresentativeNames, '"'.$getCompanyRow->representative_name.'"'); 
								}
							}
						}
					}
				} 

				$assignorAndAssigneeIDs = array();

				if(count($allRepresentativeNames) > 0) {

					$queryRepresentativeIDs = "SELECT representative_id FROM representative WHERE representative_name IN (".implode(',', $allRepresentativeNames).") GROUP BY representative_id";
					

					$allRepresentativeIDs = array();
					$resultRepresentativeIDs= $con->query($queryRepresentativeIDs);
					if($resultRepresentativeIDs && $resultRepresentativeIDs->num_rows > 0) {
						while($getRow = $resultRepresentativeIDs->fetch_object()) {
							array_push($allRepresentativeIDs, $getRow->representative_id);
						}
					}

					$queryAssignorAndAssigneeIDs = "SELECT assignor_and_assignee_id FROM assignor_and_assignee WHERE name IN (".implode(',', $allRepresentativeNames).") ";

					if(count($allRepresentativeIDs) > 0) {
						$queryAssignorAndAssigneeIDs .= " OR representative_id IN (".implode(',', $allRepresentativeIDs).") ";
					}

					$queryAssignorAndAssigneeIDs .= " GROUP BY assignor_and_assignee_id";

					

					$resultAssignorAndAssigneeIDs = $con->query($queryAssignorAndAssigneeIDs);
					if($resultAssignorAndAssigneeIDs && $resultAssignorAndAssigneeIDs->num_rows > 0) {
						while($getRow = $resultAssignorAndAssigneeIDs->fetch_object()) {
							array_push($assignorAndAssigneeIDs, $getRow->assignor_and_assignee_id);
						}
					}
				} 
				/**
				 * List of RFID where company is Assignee (List1)  
				 * List of Assets from List1 call it (List2)
				 * List of RFID from List2 call it (Array1)
				 * List of Unique Assignors from List3 call it (Array2)
				 * List of Inventors from List2 call it (Array4)
				 * List of RFID by Find fullmatch name from with Assignor and Inventors call it temp1
				 * Get the List of the Assignor Name from temp1 call it temp2
				 * List of RFID from temp2 call it Temp3
				 * Get the List of the Assignor Name from temp1 call it temp4
				 * List of RFID from temp4 call it Temp5
				 * Set Flag = 1 of all Temp5 and remove it from List 3 and Remove temp4 from unique Assignors
				 */
				/* print_r($allRepresentative);
				print_r($allRepresentativeNames);
				print_r($assignorAndAssigneeIDs); */
				
				//sendNotifications("Employee Flag in process: ".count($assignorAndAssigneeIDs));
				
				$filterYear = 1999;
				if(count($assignorAndAssigneeIDs) > 0) {
					$list1 = array();
					 /*$queryList1 = "SELECT ass.rf_id FROM db_uspto.assignee AS ass INNER JOIN db_uspto.assignment_conveyance AS ac ON ac.rf_id = ass.rf_id  AND  ac.convey_ty IN ('missing', 'other', 'govern', 'assignment', 'employee', 'correct') INNER JOIN db_uspto.list2 AS li On li.rf_id = ass.rf_id AND organisation_id = ".$organisationID." AND company_id IN (".implode(',', $allRepresentative).") INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = ass.assignor_and_assignee_id WHERE ass.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).") GROUP BY ass.rf_id";*/

					$queryList1 = "SELECT ass.rf_id FROM db_uspto.assignee AS ass INNER JOIN db_uspto.assignment_conveyance AS ac ON ac.rf_id = ass.rf_id  AND  ac.convey_ty IN ('missing', 'other', 'govern', 'assignment', 'employee', 'correct') INNER JOIN db_uspto.list2 AS li On li.rf_id = ass.rf_id AND organisation_id = ".$organisationID." AND company_id IN (".implode(',', $allRepresentative).") GROUP BY ass.rf_id";

					
					
					$resultRepresentativeRFIDs = $con->query($queryList1);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($row = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($list1, $row->rf_id);
						}
					}

					
					
					$list2 = array();
					$list21 = array();
					if(count($list1) > 0) {
						$queryList2 = "SELECT MAX(doc.appno_doc_num) AS appno_doc_num FROM documentid AS doc WHERE appno_doc_num <> '' AND rf_id IN (".implode(',', $list1).") GROUP BY appno_doc_num"; 
						
						$resultList2 = $con->query($queryList2);
						if($resultList2 && $resultList2->num_rows > 0) {
							while($row = $resultList2->fetch_object()) {
								$appNo = $row->appno_doc_num;
								array_push($list2, '"'.$appNo.'"');
								array_push($list21, $appNo);

								if(substr($appNo, 0, 1) == '0') {
									array_push($list2, '"'.substr($appNo, 1).'"');
									array_push($list21, substr($appNo, 1));
								}
							}
						}
					} 



					

					$Array1 = array();

					if(count($list2) > 0) {
						$queryList3 = "SELECT doc.rf_id AS rf_id FROM documentid AS doc INNER JOIN assignor AS aor ON aor.rf_id = doc.rf_id AND date_format(exec_dt, '%Y') > ".$filterYear."  WHERE appno_doc_num IN (".implode(',', $list2).") GROUP BY rf_id"; 
						$resultList3 = $con->query($queryList3);
						if($resultList3 && $resultList3->num_rows > 0) {
							while($row = $resultList3->fetch_object()) {
								array_push($Array1, $row->rf_id);
							}
						}
					}

					 

					$queryNameChange = "SELECT ass.rf_id FROM db_uspto.assignee AS ass INNER JOIN db_uspto.assignment_conveyance AS ac ON ac.rf_id = ass.rf_id  AND  ac.convey_ty IN ('namechg') INNER JOIN db_uspto.list2 AS li On li.rf_id = ass.rf_id AND organisation_id = ".$organisationID." AND company_id IN (".implode(',', $allRepresentative).") GROUP BY ass.rf_id";
					$nameChgList1 = array();
					$resultRepresentativeRFIDs = $con->query($queryNameChange);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($row = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($nameChgList1, $row->rf_id);
						}
					}

					
					if(count($nameChgList1) > 0) {
						$queryNameChgList2 = "SELECT MAX(doc.appno_doc_num) AS appno_doc_num FROM documentid AS doc WHERE rf_id IN (".implode(',', $nameChgList1).") GROUP BY appno_doc_num";
						$nameChgList2 = array();
						$resultList2 = $con->query($queryNameChgList2);
						if($resultList2 && $resultList2->num_rows > 0) {
							while($row = $resultList2->fetch_object()) {
								$appNo = $row->appno_doc_num;
								array_push($nameChgList2, '"'.$appNo.'"'); 
								array_push($list2, '"'.$appNo.'"'); 

								if(substr($appNo, 0, 1) == '0') {
									array_push($nameChgList2, '"'.substr($appNo, 1).'"'); 
									array_push($list2, '"'.substr($appNo, 1).'"'); 
								}
							}
						}
						
						if(count($nameChgList2) > 0) {
							echo $queryNameChgList3 = "SELECT doc.rf_id AS rf_id FROM documentid AS doc INNER JOIN assignor AS aor ON aor.rf_id = doc.rf_id AND date_format(exec_dt, '%Y') > ".$filterYear."  WHERE appno_doc_num IN (".implode(',', $nameChgList2).") GROUP BY rf_id";
	
							$resultList3 = $con->query($queryNameChgList3);
							if($resultList3 && $resultList3->num_rows > 0) {
								while($row = $resultList3->fetch_object()) {
									array_push($Array1, $row->rf_id);
								}
							}
						}
					}

					
					
					$Array2 = array();
					$uniqueAssignorsRFID = array();
					$uniqueAssignorsIDs = array();
					$assignorData = array();
					$assignorName = array();

					
					
					if(count($Array1) > 0) {
						$queryUniqueAssignors = "SELECT aor.rf_id, aaa.assignor_and_assignee_id, IF( r.representative_name <> '' , r.representative_name, aaa.name) AS assignorName  FROM assignor AS aor INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id LEFT JOIN representative AS r ON r.representative_id = aaa.representative_id WHERE aor.rf_id IN (".implode(',', $Array1).") ";

						$resultUniqueAssignors = $con->query($queryUniqueAssignors);
						if($resultUniqueAssignors && $resultUniqueAssignors->num_rows > 0) {
							while($row = $resultUniqueAssignors->fetch_object()) {
								if(!in_array($row->assignorName, $assignorName)){
									array_push($assignorName, $row->assignorName); 
									array_push($Array2, '"'.$con->real_escape_string($row->assignorName).'"');
									array_push($uniqueAssignorsIDs, $row->assignor_and_assignee_id	);
								}
								array_push($assignorData, $row);
								array_push($uniqueAssignorsRFID, $row->rf_id);
							}
							$uniqueAssignorsRFID = array_unique($uniqueAssignorsRFID, SORT_NUMERIC);
							$Array2 = array_unique($Array2, SORT_STRING);
							$uniqueAssignorsIDs = array_unique($uniqueAssignorsIDs, SORT_NUMERIC);
						}
					}

					
					$uniqueInventors = array();
					$Array4 = array();
					$inventorsMayComeAsCorpAssignors = array();
					if(count($Array2) > 0) {
						/**
						 * Step 1
						 * Remove coportate companies
						 */
							//echo "Start Count: ".count($Array1); 
							$patternMatch = '/\b(?:inc|llc|corporation|corp|systems|system|llp|industries|gmbh|lp|agent|sas|na|bank|co|states|ltd|kk|a\/s|aktiebolag|kigyo|kaisha|university|kabushiki|company|plc|gesellschaft|gesmbh|société|societe|mbh|aktiengesellschaft|haftung|vennootschap|bv|bvba|aktien|limitata|srl|sarl|kommanditgesellschaft|kg|gesellschaft|gbr|ohg|handelsgesellschaft|compagnie|privatstiftung|foundation|technologies|technology|solutions|solution|networks|network|holding|holdings|health|animal|scientific|chemical|chemicals|pharmaceutical|trust|the|resources|government|college|support|pharma|pharmalink|labs|lab|pyramid|analytics|analytic|therapeutics|tigenix|nexstim|voluntis|elobix|nxp|ab|sa|acies|wakefield|semiconductor|development|research|traingle|institute|advanced|interconnect|sensordynamics|product|products|international|biotech|investment|partner|capital|royalty|parallel|laboratories|spa|city|studios|universal|lllp|partners|national|wrestling|international|licensing|demografx|island|ag|credit|suisse|properties)\b/i'; 
							
							$removeAssignors = array();
							$removeAssignorsNames = array();
							foreach($assignorData as $assignor) {
								if(!in_array($assignor->assignor_and_assignee_id, $removeAssignors)) { 
									$name = $assignor->assignorName;
									$name = preg_replace('/\'/', '', $name);
									$result = preg_match_all($patternMatch, strtolower($name), $matches, PREG_SET_ORDER, 0);
									
									$numberMatchPattern = '/([0-9])/';
									$resultNumberMatch = preg_match_all($numberMatchPattern, strtolower($name), $numberMatches, PREG_OFFSET_CAPTURE);
									
									if(($result !== false && isset($matches[0]) && count($matches[0]) > 0) || ($resultNumberMatch !== false && isset($numberMatches[0]) && count($numberMatches[0]) > 0)) {
										array_push($removeAssignors, $assignor->assignor_and_assignee_id);
										array_push($removeAssignorsNames, '"'.strtolower($con->real_escape_string($name)).'"' );
										if(isset($matches[0]) && count($matches[0]) > 0){
											foreach($matches as $match) {
												if($match[0] == 'na' || $match[0] == 'a/s' || $match[0] == 'sa' || $match[0] == 'ab' || $match[0] == 'kg' || $match[0] == 'bv' || $match[0] == 'kk') {
													array_push($inventorsMayComeAsCorpAssignors, '"'.$assignor->assignorName.'"');
												}
											}
										}
									}
								}
							}
							
							echo "Corporate Names";
							//print_r($removeAssignorsNames); 
							 

							// implode(',', $removeAssignors);


							
							$organisationRFIDs = array();
							if(count($removeAssignors) > 0) {
								foreach($assignorData  as $assignor) {  
									if(in_array($assignor->assignor_and_assignee_id, $removeAssignors)){
										array_push($organisationRFIDs, $assignor->rf_id); 
									} 
								}
							}
							
							
							$organisationRFIDs = array_unique($organisationRFIDs);
							//echo implode(',', $uniqueAssignorsIDs);
							//print_r($organisationRFIDs);
							
							//print_r($assignorData);
							
							if(count($organisationRFIDs) > 0){
								/**
								 * Before set to flag check those coprorate RFID assignor name in the inventor table of an account
								 */
								//$inventorsMayComeAsCorpAssignors = array_unique($inventorsMayComeAsCorpAssignors);

								if(count($removeAssignorsNames) > 0) {

									/*$corporateList1 = array();
									 $queryCorporateList1 = "SELECT ass.rf_id FROM db_uspto.assignee AS ass INNER JOIN db_uspto.assignment_conveyance AS ac ON ac.rf_id = ass.rf_id   INNER JOIN db_uspto.list2 AS li On li.rf_id = ass.rf_id AND organisation_id = ".$organisationID."  INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = ass.assignor_and_assignee_id WHERE ass.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).") GROUP BY ass.rf_id";
									
									$resultRepresentativeRFIDs = $con->query($queryCorporateList1);
									if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
										while($row = $resultRepresentativeRFIDs->fetch_object()) {
											array_push($corporateList1, $row->rf_id);
										}
									} */
									
									
									
									/* $corporateList2 = array();
									if(count($list2) > 0) {
										$queryCorporateList2 = "SELECT MAX(doc.appno_doc_num) AS appno_doc_num FROM documentid AS doc WHERE rf_id IN (".implode(',', $list2).") GROUP BY appno_doc_num";
	
										$resultList2 = $con->query($queryCorporateList2);
										if($resultList2 && $resultList2->num_rows > 0) {
											while($row = $resultList2->fetch_object()) {
												array_push($corporateList2, '"'.$row->appno_doc_num.'"');
											}
										}
									} */
	
									if(count($list2) > 0) { 
	
										$queryInventorAccount = " SELECT given_name, middle_name, family_name FROM (
											SELECT IF(given_name <> '', CONCAT(' ', given_name), '') AS given_name, IF(middle_name <> '', CONCAT(' ', middle_name), '') AS middle_name, IF(family_name <> '', CONCAT(' ', family_name), '') AS family_name FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $list2).") GROUP BY given_name, middle_name, family_name UNION SELECT  IF(given_name <> '', CONCAT(' ', given_name), '') AS given_name, IF(middle_name <> '', CONCAT(' ', middle_name), '') AS middle_name, IF(family_name <> '', CONCAT(' ', family_name), '') AS family_name  FROM db_patent_grant_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $list2).") GROUP BY given_name, middle_name, family_name  ) AS temp 
											WHERE 
											LOWER(trim(CONCAT(family_name, middle_name, given_name))) IN (".implode(',', $removeAssignorsNames).")
												OR
												LOWER(trim(CONCAT(given_name, middle_name, family_name))) IN (".implode(',', $removeAssignorsNames).")
												OR
												LOWER(trim(CONCAT(family_name, given_name, middle_name))) IN (".implode(',', $removeAssignorsNames).")
												OR
												LOWER(trim(CONCAT(given_name, family_name, middle_name))) IN (".implode(',', $removeAssignorsNames).")
												OR
												LOWER(trim(CONCAT(family_name, given_name))) IN (".implode(',', $removeAssignorsNames).")
												OR
												LOWER(trim(CONCAT(given_name, family_name))) IN (".implode(',', $removeAssignorsNames).")
												OR
												LOWER(trim(CONCAT(family_name))) IN (".implode(',', $removeAssignorsNames).")
												OR
												LOWER(trim(CONCAT(given_name))) IN (".implode(',', $removeAssignorsNames).")
											GROUP BY given_name, middle_name, family_name";
										
										$resultCorpUniqueInventors = $con->query($queryInventorAccount);
										if($resultCorpUniqueInventors && $resultCorpUniqueInventors->num_rows > 0) { 
											$corporateArray = getCombinationNamesList($resultCorpUniqueInventors, $con);
											echo "ALL CORPORATE ARRAY";
											//print_r($corporateArray);
											
											if(count($corporateArray) > 0) {
												$queryCorpTempIDs = "SELECT aor.rf_id FROM assignor AS aor INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id LEFT JOIN representative AS r ON r.representative_id = aaa.representative_id WHERE aor.rf_id IN (".implode(',', $Array1).") AND (LOWER(aaa.name) IN (".implode(',', $corporateArray).") OR LOWER(r.representative_name) IN (".implode(',', $corporateArray)."))  GROUP BY aor.rf_id";
												//echo $queryCorpTempIDs;
												$resultTempIDs = $con->query($queryCorpTempIDs);						
												$corpRFID = array();
												if($resultTempIDs && $resultTempIDs->num_rows > 0) {
													while($rowInventor = $resultTempIDs->fetch_object()){
														array_push($corpRFID, $rowInventor->rf_id);
													}
												} 

												if(count($corpRFID) > 0) {
													//echo "Before removed corporate Inventor count :".count($organisationRFIDs);
													//echo "COUNT: ".count($corpRFID);
													$organisationRFIDs = array_diff($organisationRFIDs, $corpRFID);

													//echo "After removed corporate Inventor count :".count($organisationRFIDs);
												}
											}
										} 
									}
								} 
								/**
								 * Set Flag = 0
								 */
								echo "CORPORATE END";
								
								//print_r($organisationRFIDs); 
								//echo implode(',', $organisationRFIDs); 
								
								$nonCorporateRFIDs = array();
								
								
								$nonCorporateAssignors = array();
								foreach($assignorData as $assignor) {
									if(!in_array($assignor->assignor_and_assignee_id, $removeAssignors)) {  
										array_push($nonCorporateAssignors, $assignor->assignor_and_assignee_id); 
									}
								}

								if(count($nonCorporateAssignors) > 0) {
									foreach($assignorData  as $assignor) {
										if(in_array($assignor->assignor_and_assignee_id, $nonCorporateAssignors)){
											array_push($nonCorporateRFIDs, $assignor->rf_id);
										}
									}
								}

								if(count($nonCorporateRFIDs) > 0) { 
									$organisationRFIDs = array_diff($organisationRFIDs, $nonCorporateRFIDs);
								}  


								echo "REAL CORPORRRATE";

								//echo implode(',', $organisationRFIDs);  
								$organisationRFIDs = array_unique($organisationRFIDs);   
								$con->query("set innodb_lock_wait_timeout=500");
								updateFlag(0, $organisationRFIDs, $con, array());
							} 
							//echo implode(',', $organisationRFIDs);
							$Array1 = array_diff($Array1, $organisationRFIDs);


							
							
							/**
							 * Step 2
							 * Remove Corp Assignor from the Array2
							 */
							$removeCorpAssignors = array();
							foreach($assignorData as $aor) {
								if(in_array($aor->rf_id, $organisationRFIDs)) {
									array_push($removeCorpAssignors, '"'.$con->real_escape_string($aor->assignorName).'"');
								}
							}
							$Array2 = array_diff($Array2, $removeCorpAssignors); 
							$assignorName = array_diff($assignorName, $removeCorpAssignors);

							echo "<pre>";
							//print_r($assignorName);
							

							/**
							 * Remove corporate assignors from Array2
							 */
							//echo implode(',', $Array1);
							//echo implode(',', $Array1);

							
						echo "After removed corporate count :".count($Array1);
						

						$con->query("TRUNCATE db_patent_application_bibliographic.inventory_search_temp");
						$RFID0 = array();
						$queryInventorAccount = "INSERT IGNORE INTO db_patent_application_bibliographic.inventory_search_temp SELECT CONCAT(gName, mName, fName) AS name FROM ( SELECT gName, mName, fName FROM ( SELECT IF(given_name <> '', CONCAT(' ', given_name), '') AS gName, IF(middle_name <> '', CONCAT(' ', middle_name), '') AS mName, IF(family_name <> '', CONCAT(' ', family_name), '') AS fName FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $list2).") ) AS temp GROUP BY gName, mName, fName) AS temppp";
						$con->query($queryInventorAccount);
						$queryInventorAccount = "INSERT IGNORE INTO db_patent_application_bibliographic.inventory_search_temp SELECT CONCAT(gName, mName, fName) AS name FROM ( SELECT gName, mName, fName FROM ( SELECT IF(given_name <> '', CONCAT(' ', given_name), '') AS gName, IF(middle_name <> '', CONCAT(' ', middle_name), '') AS mName, IF(family_name <> '', CONCAT(' ', family_name), '') AS fName FROM db_patent_grant_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $list2).") ) AS temp GROUP BY gName, mName, fName) AS temppp";

						$con->query($queryInventorAccount);
						
						foreach($assignorName as $inventor) {
							//echo $inventor."<br/>";
							$explodeName = explode(' ', trim($inventor));
							$allExpression = array();
							$allExpression[] = '('.implode('.*', $explodeName).')';
							$reverseExplode = array_reverse($explodeName);
							$allExpression[] = '('.implode('.*', $reverseExplode).')';;
							if(count($explodeName) > 1) {
								$allExpression[] = '('.$explodeName[0].'.*'.$explodeName[1].')';
								$allExpression[] = '('.$explodeName[1].'.*'.$explodeName[0].')';
							}

							if(count($explodeName)> 2) {
								$allExpression[] = '('.$explodeName[0].'.*'.$explodeName[1].'.*'.$explodeName[2].')';
								$allExpression[] = '('.$explodeName[0].'.*'.$explodeName[2].'.*'.$explodeName[1].')';
								$allExpression[] = '('.$explodeName[1].'.*'.$explodeName[0].'.*'.$explodeName[2].')';
								$allExpression[] = '('.$explodeName[1].'.*'.$explodeName[2].'.*'.$explodeName[0].')';
								$allExpression[] = '('.$explodeName[2].'.*'.$explodeName[1].'.*'.$explodeName[0].')';
								$allExpression[] = '('.$explodeName[2].'.*'.$explodeName[0].'.*'.$explodeName[1].')';
							}

							$implodeAllExpressions = implode('|', $allExpression);

							$queryFindInventor = 'SELECT count(*) AS numOfInventor FROM  db_patent_application_bibliographic.inventory_search_temp WHERE name REGEXP "'.$implodeAllExpressions.'"';

							print_r($queryFindInventor);
							$resultUniqueInventors = $con->query($queryFindInventor) ;
							if($resultUniqueInventors) { 
								$rowInventor = $resultUniqueInventors->fetch_object();
								
								if($rowInventor->numOfInventor > 0) {
									array_push($Array4,  strtolower('"'.trim($inventor).'"'));
									$otherArray = array(); 
									$currentAllRFIDS = array(); 
									foreach($assignorData as $assignor) {
										if(trim($assignor->assignorName) == trim($inventor)) {
											array_push($RFID0, $assignor->rf_id);
											array_push($currentAllRFIDS, $assignor->rf_id);
										}
									}
									foreach($assignorData as $assignor) {
										if(in_array($assignor->rf_id, $currentAllRFIDS)) {
											if(!in_array($assignor->assignorName, $otherArray)) {
												array_push($otherArray, $assignor->assignorName);
											}
										}
									}

									foreach($otherArray as $name) {
										foreach($assignorData as $assignor) {
											if(trim($assignor->assignorName) == trim($name)) {
												if(!in_array($assignor->rf_id, $currentAllRFIDS)) {
													array_push($RFID0, $assignor->rf_id);
													array_push($currentAllRFIDS, $assignor->rf_id);
												}
											} 
										} 
									}
									//print_r($currentAllRFIDS);
									if(count($otherArray) > 0) {
										$Array2 = array_diff($Array2, $otherArray);
										$Array2 = array_values($Array2);
									}
								}
							} 
						}

						
						$RFID0 = array_unique($RFID0);  
					}   
					
					echo "Find Employees<br/>";

					//echo implode(',', $RFID0);
					
					
					
					$Array4 = array_unique($Array4); 
					if(count($RFID0) > 0) {
						
						
						
						$queryTempIDs = "SELECT aor.rf_id FROM assignor AS aor INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id LEFT JOIN representative AS r ON r.representative_id = aaa.representative_id WHERE aor.rf_id IN (".implode(',', $Array1).") AND (LOWER(aaa.name) IN (".implode(',', $Array4).") OR LOWER(r.representative_name) IN (".implode(',', $Array4)."))  GROUP BY aor.rf_id";
						//echo $queryTempIDs;
						$resultTempIDs = $con->query($queryTempIDs);						
				
						if($resultTempIDs && $resultTempIDs->num_rows > 0) {
							while($rowInventor = $resultTempIDs->fetch_object()){
								array_push($RFID0, $rowInventor->rf_id);
							}
						} 
						//echo "TOTAL RFIDS:".count($Array1)."<br/>";
						//echo "INVENTOR COUNT: ".count($Array4);
						//echo "RFIDs Temp1: ".count($RFID0);
						

						//echo implode(',', $RFID0); 
						

						$previousCounter = 0;
						$previousRFIDs = $RFID0;
						$allEmployee  = array();
						
						$previousRFIDs = array_diff($previousRFIDs, $organisationRFIDs);
						
						//echo implode(",", $previousRFIDs);

						print_r($previousRFIDs); 
						if(count($previousRFIDs) > 0){
							/**
							 * Set Flag = 1
							 */
							$previousRFIDs = array_unique($previousRFIDs);  
							$con->query("set innodb_lock_wait_timeout=500");
							updateFlag(1, $previousRFIDs, $con);
						} 
 

						$remainingRFIDs = array_diff($Array1, $previousRFIDs);


						$requestRemaingRFID = array();

						foreach($remainingRFIDs as $rf) {
							array_push($requestRemaingRFID, $rf);
						}

						echo "REMAINING RFIDs: ".count($requestRemaingRFID);



						
						print_r($remainingRFIDs);  

						if(count($remainingRFIDs) > 0) {
							echo "node /var/www/html/script/inventor_levenshtein.js '".json_encode($requestRemaingRFID)."' '".json_encode($list21)."'";

							
							$output = shell_exec("node /var/www/html/script/inventor_levenshtein.js '".json_encode($requestRemaingRFID)."' '".json_encode($list21)."'");
							
							echo "OUTPUT";
							print_r($output); 
						} 
					}
				}   
				if($variables[2] != '') {
					$con->query("UPDATE db_new_application.log_messages SET status = 1 WHERE organisation_id = ".$organisationID." AND message='Employee flag' AND company_id = '".$variables[2]."'");
				} else { 
					$con->query("UPDATE db_new_application.log_messages SET status = 1 WHERE organisation_id = ".$organisationID." AND message='Employee flag'  AND company_id = 0");
				}  
				sendNotifications("RE-Classify flag.");
				sendNotifications("Employee flag script finished.");

				

				if($variables[2] == "") {
					exec("php -f /var/www/html/trash/update_missing_type.php '".$variables[1]."' ''");
				} else {
					exec("php -f /var/www/html/trash/update_missing_type.php '".$variables[1]."' '".$variables[2]."'");
				}  
				sendNotifications("RE-Classify flag.");
				sendNotifications('Classification Complete.'); 
				//exec("php -f /var/www/html/trash/inventors_data.php"); 
				exec("php -f /var/www/html/trash/create_data_for_company_db_application.php ".$variables[1]." '' 1"); 
			}
		}
	}
}

function updateFlag($flag, $rfIDs, $con, $previousIDs = array()) {
	
	if($flag == 1){
		$updateQuery = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$flag. ", convey_ty = 'employee' WHERE rf_id IN (".implode(',', $rfIDs).") ";
	} else {
		$updateQuery = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$flag. " WHERE rf_id IN (".implode(',', $rfIDs).") AND convey_ty <> 'correct' AND convey_ty <> 'govern' ";

		if($previousIDs != null && count($previousIDs) > 0 && is_array($previousIDs)) {
			$updateQuery .= " AND rf_id NOT IN (".implode(',', $previousIDs).")";
		}
	}
	
	
	echo "UPDATING QUERY<br/>";
	echo $updateQuery;
	$con->query($updateQuery);
	
	if($flag == 0){
		$queryUPDATEASSIGNMENT = "SELECT rf_id FROM db_uspto.representative_assignment_conveyance WHERE rf_id IN (".implode(',', $rfIDs).") AND convey_ty = 'employee' ";

		if($previousIDs != null && count($previousIDs) > 0 && is_array($previousIDs)) {
			$queryUPDATEASSIGNMENT .= " AND rf_id NOT IN (".implode(',', $previousIDs).")";
		}
		
		$resultUPDATEASSIGNMENT = $con->query($queryUPDATEASSIGNMENT);
		echo $queryUPDATEASSIGNMENT."@@@@".$resultUPDATEASSIGNMENT->num_rows."@@@@<br/>";
		if($resultUPDATEASSIGNMENT && $resultUPDATEASSIGNMENT->num_rows > 0) {
			$listIDS = array();
			while($row = $resultUPDATEASSIGNMENT->fetch_object()){
				array_push($listIDS, $row->rf_id);
			}
			if(count($listIDS) > 0) {
				$updateQueryExtra = "UPDATE db_uspto.representative_assignment_conveyance SET convey_ty = 'assignment' WHERE rf_id IN (".implode(',', $listIDS).")";
				//echo "UPDATING updateQueryExtra<br/>";
				$con->query($updateQueryExtra);
			}
		}
	}
}

function sendNotifications($data) {	
	$pusher = new Pusher\Pusher(CONSTANT_PUSHER_KEY, CONSTANT_PUSHER_SECRET, CONSTANT_PUSHER_APPID, array( 'cluster' => CONSTANT_PUSHER_CLUSTER, 'useTLS' => CONSTANT_PUSHER_ENCRYPTED ) );
	$pusher->trigger( CONSTANT_PUSHER_CHANNEL, CONSTANT_PUSHER_EVENT, $data );
}