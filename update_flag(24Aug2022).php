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


$variables = $argv;
//$variables = $_GET;
if(count($variables) == 3) {
//if(count($variables) > 0) {
	$organisationID = $variables[1];
	
	//$organisationID = $variables['o'];
	
	//echo $organisationID."<br/>";	
	if((int)$organisationID > 0) {
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				/*Check from client database */
				$rfIDs = [];		
				if($variables[2] == "") {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE type = 1 AND parent_id = 0";	
				} else {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE representative_id = ".$variables[2];	
				}
				
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
				$allRepresentative = array();
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {					
					while($getGroup = $resultRepresentativeParentCompany->fetch_object()) {
						
						array_push($allRepresentative, $getGroup->representative_id);
						$queryGroupRepresentative = "SELECT representative_id FROM representative WHERE type = 0 AND parent_id = ".$getGroup->representative_id;
						
						$resultRepresentativeGroupParentCompany = $orgConnect->query($queryGroupRepresentative);
						if($resultRepresentativeGroupParentCompany && $resultRepresentativeGroupParentCompany->num_rows > 0) {
							
							
							while($getCompanyRow = $resultRepresentativeGroupParentCompany->fetch_object()) {
								array_push($allRepresentative, $getCompanyRow->representative_id);
							}
						}
						
						if(count($allRepresentative) > 0) {
						
							$queryFindAllRFIDs = "SELECT dc.rf_id FROM documentid AS dc INNER JOIN assignment_conveyance AS ac ON ac.rf_id = dc.rf_id WHERE ac.convey_ty IN ('missing', 'other', 'assignment', 'employee') AND dc.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
							SELECT rf_id FROM db_uspto.list2 WHERE company_id IN (".implode(',', $allRepresentative).") AND organisation_id = ".$organisationID." ) GROUP BY appno_doc_num )GROUP BY dc.rf_id";
							
							$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
							if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
								while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
									array_push($rfIDs, $rowRepresentativeRF->rf_id);
								}
							}
						}						
					}
				}
				
				if($variables[2] == "") {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE type = 0 AND parent_id = 0";	
					$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
					
						
					if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
						while($getCompanyRow = $resultRepresentativeParentCompany->fetch_object()) {
							array_push($allRepresentative, $getCompanyRow->representative_id);
						}
					}
					if(count($allRepresentative) > 0) {
						echo $queryFindAllRFIDs = "SELECT dc.rf_id FROM documentid AS dc INNER JOIN assignment_conveyance AS ac ON ac.rf_id = dc.rf_id WHERE ac.convey_ty IN ('missing', 'other', 'assignment', 'employee') AND dc.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
						SELECT rf_id FROM db_uspto.list2 WHERE company_id IN (".implode(',', $allRepresentative).") AND organisation_id = ".$organisationID." ) GROUP BY appno_doc_num ) GROUP BY dc.rf_id";
						
						$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
						if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
							while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
								array_push($rfIDs, $rowRepresentativeRF->rf_id);
							}
						}
					}
				}
				echo "COUNT TRANSACTIONS: ".count($rfIDs)."<br/>";			
				
				
				if(count($rfIDs) > 0) {
					$rfIDs = array_values(array_unique($rfIDs));
					echo "UNIQUE TRANSACTIONS: ".count($rfIDs)."<br/>";			
					fixCompanyAssignorAndEmployee($rfIDs, $allRepresentative, $organisationID, $con);
				}
			}
		}
		
		sendNotifications("Employee flag script finished.");
		sendNotifications("Missing flag script is Inprocess.");
		if($variables[2] == "") {
			exec("php -f /var/www/html/trash/update_missing_type.php '".$variables[1]."' ''");
		} else {
			exec("php -f /var/www/html/trash/update_missing_type.php '".$variables[1]."' '".$variables[2]."'");
		}
		
		
		exec("php -f /var/www/html/trash/create_data_for_company_db_application.php ".$variables[1]." ''");
	}
}


function fixCompanyAssignorAndEmployee ($rfIDs, $allRepresentative, $organisationID, $con) {
	echo "CALL fixCompanyAssignorAndEmployee<br/>";
	
	if(count($rfIDs) > 0) {
		/*List of assignments in which company as a assignee*/						
		$queryAssignments = "SELECT a.rf_id FROM assignee as a WHERE a.rf_id IN (".implode(',', $rfIDs).") GROUP BY a.rf_id";						
		$resultAssignment = $con->query($queryAssignments);						
		$assignmentList = array();						
		if($resultAssignment && $resultAssignment->num_rows > 0) {
			while($rowAssignment = $resultAssignment->fetch_object()){
				array_push($assignmentList, $rowAssignment->rf_id);
			}
		}
		
		$originalList = $assignmentList;

/*
        $queryUpdateA = "SELECT ac.rf_id, ac.convey_ty AS aconveyTy, ac.employer_assign AS aEmployer, rac.convey_ty AS raconveyTy, rac.employer_assign AS raEmployer  FROM assignment_conveyance AS ac INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = ac.rf_id WHERE ac.rf_id IN (".implode(',', $originalList).")";
        echo $queryUpdateA;
        $resultUpdate = $con->query($queryUpdateA);	
        	echo $resultUpdate->num_rows;
		if($resultUpdate && $resultUpdate->num_rows > 0) {
			while($row = $resultUpdate->fetch_object()){
				if($row->aconveyTy != $row->raconveyTy) {
                    $con->query("UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$row->aEmployer. ", convey_ty = '".$row->aconveyTy."' WHERE rf_id = ".$row->rf_id);
                }
			}
		}


        die;*/
		
		/*Remove Corrective Assignments*/
		$queryFindAllRFIDs = "SELECT rf_id FROM assignment as a WHERE rf_id IN (".implode(',', $originalList).") AND 	MATCH(a.convey_text) AGAINST('\"CORRECTIVE\"' IN BOOLEAN MODE) GROUP BY a.rf_id";
		
		$resultCorrectiveRFIDs = $con->query($queryFindAllRFIDs);	

		$removeList = array();						
		if($resultCorrectiveRFIDs && $resultCorrectiveRFIDs->num_rows > 0) {
			while($row = $resultCorrectiveRFIDs->fetch_object()){
				array_push($removeList, $row->rf_id);
			}
		}	

		if(count($removeList) > 0 && count($originalList) > 0) {
			$originalList = array_diff($originalList, $removeList);
			
			//updateFlagCorrective(0, $removeList, $con);
		}
		
		/*find list from biblio database*/
		/* $queryAssetsList = "SELECT appno_doc_num FROM documentid AS d WHERE d.rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE company_id IN (".implode(',', $allRepresentative).") AND organisation_id = ".$organisationID." ) AND d.appno_doc_num <> '' GROUP BY d.appno_doc_num";*/
		$queryAssetsList = "SELECT appno_doc_num FROM documentid AS d WHERE d.rf_id IN (".implode(',', $rfIDs).") AND d.appno_doc_num <> '' GROUP BY d.appno_doc_num";
		//echo $queryAssetsList;
		$resultAssets  = $con->query($queryAssetsList);
		$biblioInventors = array();	
		$assetsList = array();	
		$allInventorName = array();		
		if($resultAssets && $resultAssets->num_rows > 0){
			while($rowAsset = $resultAssets->fetch_object()) {
				if(is_numeric($rowAsset->appno_doc_num)){
					$asset = (int)$rowAsset->appno_doc_num;
					array_push($assetsList, '"'.$asset.'"');
				}
			}
			
			if(count($assetsList) > 0) {
				
				/*$queryBiblioInventor = "SELECT name, given_name, family_name, middle_name  FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(",", $assetsList).") GROUP BY name 
							UNION 
				SELECT name, given_name, family_name, middle_name  FROM db_patent_application_bibliographic.inventor_temp WHERE appno_doc_num IN (".implode(",", $assetsList).")  GROUP BY name 
							UNION 
				SELECT name, given_name, family_name, middle_name  FROM db_patent_grant_bibliographic.inventor WHERE appno_doc_num IN (".implode(",", $assetsList).")  GROUP BY name 
						UNION 
				SELECT name, given_name, family_name, middle_name  FROM db_patent_grant_bibliographic.inventors WHERE appno_doc_num IN (".implode(",", $assetsList).")  GROUP BY name " ;*/
				
				$queryBiblioInventor = "SELECT name, given_name, family_name, middle_name  FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(",", $assetsList).") GROUP BY name 
							UNION 
				SELECT name, given_name, family_name, middle_name  FROM db_patent_grant_bibliographic.inventor WHERE appno_doc_num IN (".implode(",", $assetsList).")  GROUP BY name " ;
				//echo $queryBiblioInventor;
				$resultBiblioInventor = $con->query($queryBiblioInventor);
				if($resultBiblioInventor && $resultBiblioInventor->num_rows > 0 ) {
					while($rowInventor = $resultBiblioInventor->fetch_object()){
						if(!in_array($rowInventor->name,$allInventorName)){
							array_push($allInventorName, '"'.$con->real_escape_string($rowInventor->name).'"');
						}
						array_push($biblioInventors, $rowInventor);
					}
				}
			}									
		}
		echo "BIBLIOG DONE : ".count($biblioInventors)."<br/>";
		
		
		$inventorRFIDS = array();
		
		$queryInventorIDs = "SELECT aor.rf_id FROM assignor AS aor INNER JOIN assignment_conveyance AS ac ON ac.rf_id = aor.rf_id WHERE aor.or_name IN (".implode(',', $allInventorName).") AND aor.rf_id IN (SELECT rf_id FROM documentid WHERE appno_doc_num IN (".implode(",", $assetsList).")) AND ac.convey_ty IN ('missing', 'other', 'assignment') GROUP BY aor.rf_id";
		
		$resultInventorIDs = $con->query($queryInventorIDs);						

		if($resultInventorIDs && $resultInventorIDs->num_rows > 0) {
			while($rowInventor = $resultInventorIDs->fetch_object()){
				array_push($inventorRFIDS, $rowInventor->rf_id);
			}
		}
		
		
		echo "FOUND INVENTORS: ".count($inventorRFIDS)."<br/>"; 

		
		
		if(count($inventorRFIDS) > 0) {
			updateFlag(1, $inventorRFIDS, $con);
			$assignmentList = array_diff($assignmentList, $inventorRFIDS);
		}
		
		
		$queryUniqueAssignors = "SELECT aaa.assignor_and_assignee_id, aaa.name FROM assignor as a INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id  WHERE a.rf_id IN (".implode(",", $assignmentList).") GROUP BY aaa.name";	
		
		$resultAssignor = $con->query($queryUniqueAssignors);						
		$assignorList = array();
		$assignorListWithIDs = array();
		//$nameAll = array();
		if($resultAssignor && $resultAssignor->num_rows > 0) {
			while($rowAssignor = $resultAssignor->fetch_object()){
				$name = $rowAssignor->name;
				//array_push($nameAll, $name);
				array_push($assignorList, array('name'=>$name, 'assignor_and_assignee_id'=>$rowAssignor->assignor_and_assignee_id));
				array_push($assignorListWithIDs, $rowAssignor->assignor_and_assignee_id);
			}
		}	
		
		
		
		$patternMatch = '/\b(?:inc|llc|corporation|corp|systems|system|llp|industries|gmbh|lp|agent|sas|na|bank|co|states|ltd|kk|a\/s|aktiebolag|kigyo|kaisha|university|kabushiki|company|plc|gesellschaft|gesmbh|société|societe|mbh|aktiengesellschaft|haftung|vennootschap|bv|bvba|aktien|limitata|srl|sarl|kommanditgesellschaft|kg|gesellschaft|gbr|ohg|handelsgesellschaft|compagnie|privatstiftung|foundation|technologies|technology|solutions|solution|networks|network|holding|holdings|health|animal|scientific|chemical|chemicals|pharmaceutical|trust|the|resources|government|college|support|pharma|pharmalink|labs|lab|pyramid|analytics|analytic|therapeutics|tigenix|nexstim|voluntis|elobix|nxp|ab|sa|acies|wakefield|semiconductor|development|research|traingle|institute|advanced|interconnect|sensordynamics)\b/i'; 
		$removeAssignors = array();
		foreach($assignorList as $assignor) {
			$name = $assignor['name'];
			$name = preg_replace('/\'/', '', $name);
			
			$result = preg_match_all($patternMatch, strtolower($name), $matches, PREG_SET_ORDER, 0);
			
			$numberMatchPattern = '/([0-9])/';
			$resultNumberMatch = preg_match_all($numberMatchPattern, strtolower($name), $numberMatches, PREG_OFFSET_CAPTURE);
			
			if(($result !== false && isset($matches[0]) && count($matches[0]) > 0) || ($resultNumberMatch !== false && isset($numberMatches[0]) && count($numberMatches[0]) > 0)) {
				array_push($removeAssignors, $assignor['assignor_and_assignee_id']);
			}
		}
		
		echo "MATCHED COMPANIES: ".count($removeAssignors)."<br/>";

		
		$companiesRFIDS = array();	
		
		if(count($removeAssignors) > 0) {
			sendNotifications("Found companies by keyword search: ".count($removeAssignors));
			
			$assignorAssignmentList = findAssignmentsFromAssignorList($removeAssignors, $originalList, $con, $inventorRFIDS);
			


			if(count($assignorAssignmentList) > 0) {
				$assignorListWithIDs = array_diff($assignorListWithIDs, $removeAssignors);
				echo "COUNT:".count($assignorAssignmentList);
				$companiesRFIDS = $assignorAssignmentList;
				
				updateFlag(0, $assignorAssignmentList, $con);
				
				$assignmentList = array_diff($assignmentList, $assignorAssignmentList);
				
				foreach($removeAssignors as $a) {
					$i = 0;
					foreach($assignorList as $assignor){
						if($assignor['assignor_and_assignee_id'] == $a) {
							unset($assignorList[$i]);
							break;
						}
						$i++;
					}
					$assignorList = array_values($assignorList);
				}
			}								
		} else {
			sendNotifications("No companies found as Inventors.");
		}
		
		
			
		$assignorList = array_values($assignorList);
		$assignorListWithIDs = array_values($assignorListWithIDs);
		
		
		
		
		
		echo "REMAINING: ".count($assignorListWithIDs).'@@'.count($biblioInventors)."<br/>";
		
		$removeAssignors = array();
		$newInventorAccToAssignorFormat = array();
		
		if(count($biblioInventors) > 0) {
			foreach($biblioInventors as $inventor) {
				
				$familyName = strtolower($inventor->family_name);
				$familyName = strReplace($inventor->family_name);
				$familyName = removeDoubleSpace($inventor->family_name);
				
				$givenName = strtolower($inventor->given_name);
				$givenName = strReplace($inventor->given_name);
				$givenName = removeDoubleSpace($inventor->given_name);
				foreach($assignorList as $assignor) {
					$name = $assignor['name'];
                    $explodeName = explode(' ', trim($name));
                    /*if((($familyName != '' && $name != '' && strpos($name, $familyName) !== false) || ($givenName != '' && $name != '' && strpos($name, $givenName))) && (strpos($familyName, $explodeName[0]) || strpos($givenName, $explodeName[0]))){
						array_push($removeAssignors, $assignor['assignor_and_assignee_id']);
					}*/
                    if(($familyName != '' && $name != '' && strlen($familyName) > 2 && strlen($givenName) > 2 && strpos($name, $familyName) !== false) || ($givenName != '' && $name != '' && strpos($name, $givenName))){
                        array_push($removeAssignors, $assignor['assignor_and_assignee_id']);
                    }
				}
				
			}
		}
		
		
		if(count($removeAssignors) > 0) {
			$assignorAssignmentList = findAssignmentsFromAssignorList($removeAssignors, $assignmentList, $con, $inventorRFIDS);
			if(count($assignorAssignmentList) > 0) {
				$assignorListWithIDs = array_diff($assignorListWithIDs, $removeAssignors);
				
				updateFlag(1, $assignorAssignmentList, $con);
				
				$assignmentList = array_diff($assignmentList, $assignorAssignmentList);
				foreach($removeAssignors as $a) {
					$i = 0;
					foreach($assignorList as $assignor){
						if($assignor['assignor_and_assignee_id'] == $a) {
							unset($assignorList[$i]);
							break;
						}
						$i++;
					}
					$assignorList = array_values($assignorList);
				}
			}								
		}
		$assignorList = array_values($assignorList);
		$assignorListWithIDs = array_values($assignorListWithIDs);
		
		
		echo "CHECKING ONE BY ONE : ". count($assignorListWithIDs)."<br/><br/><br/><br/>";
		if(count($assignorListWithIDs) > 0) {
				
			foreach($assignorListWithIDs as $assignorID) {	
			
				$queryFlags = "SELECT (
					SELECT count(*) FROM (SELECT ac.rf_id FROM db_uspto.assignor as a 
					INNER JOIN db_uspto.representative_assignment_conveyance as ac ON ac.rf_id = a.rf_id 
					WHERE a.assignor_and_assignee_id = ". $assignorID." AND ac.employer_assign = 0 GROUP BY a.rf_id, a.assignor_and_assignee_id) as temp1
					) as N, (
						SELECT count(*) FROM (SELECT ac.rf_id FROM db_uspto.assignor as a 
						INNER JOIN db_uspto.representative_assignment_conveyance as ac ON ac.rf_id = a.rf_id 
						WHERE a.assignor_and_assignee_id = ". $assignorID." AND (ac.employer_assign = 1 OR ac.convey_ty='employee')  GROUP BY a.rf_id, a.assignor_and_assignee_id) as temp
					) as n  FROM assignor LIMIT 1";
				
				$resultFlag = $con->query($queryFlags);
				
				if($resultFlag) {
					$rowFlag = $resultFlag->fetch_object();
					
					if($rowFlag->N != null && $rowFlag->N > $rowFlag->n) {
						$flagCheck = false;
						$findAssignorName = "";
						foreach($assignorList as $assignor) {
							if($assignor['assignor_and_assignee_id'] == $assignorID) {
								$findAssignorName = $assignor['name'];
								break;
							}
						}												
						
						
						if($findAssignorName != "") {
							$findAssignorName = strtolower($findAssignorName);
							if(count($biblioInventors) > 0) {
								foreach($biblioInventors as $inventor) {
									$givenName = strtolower($inventor->given_name);
									$givenName = removeDoubleSpace( $givenName );
									$givenName = strReplace( $givenName );
									$familyName = strtolower($inventor->family_name);
									$familyName = removeDoubleSpace( $familyName );
									$familyName = strReplace( $familyName );
									if(($familyName != '' && $familyName != null && strpos($findAssignorName, $familyName) !== false) && ($givenName != '' && $givenName != null && strpos($findAssignorName, $givenName) !== false)){
										$flagCheck = true;
										break;
									}													
								}
							}
						}
						
						
						
						if($flagCheck == false) {
							$removeAssignors = array($assignorID);
							$assignorAssignmentList = findAssignmentsFromAssignorList($removeAssignors, $assignmentList, $con, $inventorRFIDS);
							
							
							if(count($assignorAssignmentList) > 0) {
								$assignorListWithIDs = array_diff($assignorListWithIDs, $removeAssignors);
								updateFlag(0, $assignorAssignmentList, $con);
								
								$assignmentList = array_diff($assignmentList, $assignorAssignmentList);
								
								foreach($removeAssignors as $a) {
									$i = 0;
									foreach($assignorList as $assignor){
										if($assignor['assignor_and_assignee_id'] == $a) {
											unset($assignorList[$i]);
											break;
										}
										$i++;
									}
									$assignorList = array_values($assignorList);
								}
							}
						}										
					}
				}
			}								
		}
		$assignorList = array_values($assignorList);
		$assignorListWithIDs = array_values($assignorListWithIDs);	
		/*Check from entire biblio database*/
		if(count($assignorListWithIDs) > 0) {
			$removeAssignors = array();
			foreach($assignorList as $assignor) {
				$name = $assignor['name'];
				
				/*$queryBiblioInventor = "SELECT SELECT name, given_name, family_name, middle_name FROM (SELECT name, given_name, family_name, middle_name  FROM db_patent_application_bibliographic.inventor WHERE MATCH(name) AGAINST('".$con->real_escape_string($name)."' IN BOOLEAN MODE) GROUP BY name 
							UNION 
				SELECT name, given_name, family_name, middle_name  FROM db_patent_application_bibliographic.inventor_temp WHERE MATCH(name) AGAINST('".$con->real_escape_string($name)."' IN BOOLEAN MODE)  GROUP BY name 
							UNION 
				SELECT name, given_name, family_name, middle_name  FROM db_patent_grant_bibliographic.inventor WHERE MATCH(name) AGAINST('".$con->real_escape_string($name)."' IN BOOLEAN MODE)  GROUP BY name 
						UNION 
				SELECT name, given_name, family_name, middle_name  FROM db_patent_grant_bibliographic.inventors WHERE MATCH(name) AGAINST('".$con->real_escape_string($name)."' IN BOOLEAN MODE)  GROUP BY name ) AS temp GROUP BY name" ;*/
				
				$queryBiblioInventor = "SELECT SELECT name, given_name, family_name, middle_name FROM (SELECT name, given_name, family_name, middle_name  FROM db_patent_application_bibliographic.inventor WHERE MATCH(name) AGAINST('".$con->real_escape_string($name)."' IN BOOLEAN MODE) GROUP BY name 
							UNION 
				SELECT name, given_name, family_name, middle_name  FROM db_patent_grant_bibliographic.inventor WHERE MATCH(name) AGAINST('".$con->real_escape_string($name)."' IN BOOLEAN MODE)  GROUP BY name ) AS temp GROUP BY name" ;
				
				echo $queryBiblioInventor;
				
				$resultBiblioInventor = $con->query($queryBiblioInventor);
				if($resultBiblioInventor && $resultBiblioInventor->num_rows > 0) {
					while($inventor = $resultAssignor->fetch_object()){
						$familyName = strtolower($inventor->family_name);
						$familyName = strReplace($inventor->family_name);
						$familyName = removeDoubleSpace($inventor->family_name);
						
						$givenName = strtolower($inventor->given_name);
						$givenName = strReplace($inventor->given_name);
						$givenName = removeDoubleSpace($inventor->given_name);
                        $explodeName = explode(' ', trim($name));
						/*if((($familyName != '' && $name != '' && strpos($name, $familyName) !== false) || ($givenName != '' && $name != '' && strpos($name, $givenName))) && (strpos($familyName, $explodeName[0]) || strpos($givenName, $explodeName[0]))){
							array_push($removeAssignors, $assignor['assignor_and_assignee_id']);
						}*/
                        if(($familyName != '' && $name != '' && strpos($name, $familyName) !== false) || ($givenName != '' && $name != '' && strpos($name, $givenName))){
							array_push($removeAssignors, $assignor['assignor_and_assignee_id']);
						}
					}
				}
			}
			if(count($removeAssignors) > 0) {
				
				$assignorAssignmentList = findAssignmentsFromAssignorList($removeAssignors, $assignmentList, $con, $inventorRFIDS);
				if(count($assignorAssignmentList) > 0) {
					$assignorListWithIDs = array_diff($assignorListWithIDs, $removeAssignors);
					
					updateFlag(1, $assignorAssignmentList, $con);
					$assignmentList = array_diff($assignmentList, $assignorAssignmentList);
					
					foreach($removeAssignors as $a) {
						$i = 0;
						foreach($assignorList as $assignor){
							if($assignor['assignor_and_assignee_id'] == $a) {
								unset($assignorList[$i]);
								break;
							}
							$i++;
						}
						$assignorList = array_values($assignorList);
					}
				}
			}
		}
		echo "REMAINING: ".count($assignorListWithIDs);	
		echo implode(',', $assignorListWithIDs);
	}
}

function removeDoubleSpace($string) {
	return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return trim(strtolower($string));
}

function findAssignmentsFromAssignorList($removeAssignors, $assignmentList, $con, $notInclude = array()) {
	$assignorAssignmentList = array();
	if(count($assignmentList) > 30000) {
		$totalPages = 10;
		echo "TOTAL: ".count($assignmentList)."<br/><br/><br/><br/><br/>   TOTAL PAGES: ".$totalPages."<br/><br/><br/><br/><br/>";
		$perPage = ceil(count($assignmentList) / $totalPages); 
		echo "PER PAGE: ".$perPage."<br/><br/><br/><br/><br/><br/><br/>";
		for($p = 0; $p < $totalPages; $p++) {
			echo "INDEX: ".$p ."@@@@@@@@@@@@@@@@@@@@@@@@";
			$sendList = array_slice($assignmentList,$p * $perPage, $perPage);
			echo "BEFORE COUNT: ".count($sendList)."<br/><br/><br/><br/><br/>";
			
			if($p == $totalPages - 1) {
				$sendList = array_slice($assignmentList,$p * $perPage, count($assignmentList) - 1);
			}
			
			echo "AFTER Counter: ".count($sendList)."<br/>";
			
			if(count($sendList) > 0) {
				$innerArrayList = array();
				$queryAssignorAssignments = "SELECT a.rf_id  FROM assignor as a INNER JOIN assignment_conveyance AS ac ON ac.rf_id = a.rf_id WHERE ac.convey_ty IN ('missing', 'other', 'assignment') AND a.rf_id IN (".implode(",", $sendList).") AND a.assignor_and_assignee_id IN (".implode(",", $removeAssignors).") ";
				
				if(count($notInclude) > 0) {
					$queryAssignorAssignments .= " AND a.rf_id NOT IN (".implode(',', $notInclude).") ";
				}
				
				$queryAssignorAssignments .= " GROUP BY a.rf_id ";
				//echo $queryAssignorAssignments."<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>";
				$resultAssignorAssignment = $con->query($queryAssignorAssignments);
				
				if($resultAssignorAssignment && $resultAssignorAssignment->num_rows > 0) {
					while($rowAssignorAssignment = $resultAssignorAssignment->fetch_object()){
						array_push($innerArrayList, $rowAssignorAssignment->rf_id);
					}
				}
				$assignorAssignmentList = array_merge($assignorAssignmentList,$innerArrayList);
			}
		}
		
		$assignorAssignmentList = array_column($assignorAssignmentList, NULL, 'rf_id');
		ksort($assignorAssignmentList);
		$assignorAssignmentList = array_values($assignorAssignmentList);
	} else {
		$queryAssignorAssignments = "SELECT a.rf_id  FROM assignor as a INNER JOIN assignment_conveyance AS ac ON ac.rf_id = a.rf_id WHERE ac.convey_ty IN ('missing', 'other', 'assignment') AND a.rf_id IN (".implode(",", $assignmentList).") AND a.assignor_and_assignee_id IN (".implode(",", $removeAssignors).") ";
		
		if(count($notInclude) > 0) {
			$queryAssignorAssignments .= " AND a.rf_id NOT IN (".implode(',', $notInclude).") ";
		}
		$queryAssignorAssignments .= " GROUP BY a.rf_id ";
		
		
		echo "SINGLE COUNTER: ".count($assignmentList)."@@@@@@@@Current Counter: ".count($removeAssignors)."<br/>";
		echo $queryAssignorAssignments."<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>";
		$resultAssignorAssignment = $con->query($queryAssignorAssignments);
		
		
		if($resultAssignorAssignment && $resultAssignorAssignment->num_rows > 0) {
			while($rowAssignorAssignment = $resultAssignorAssignment->fetch_object()){
				array_push($assignorAssignmentList, $rowAssignorAssignment->rf_id);
			}
		}
	}
	
	return $assignorAssignmentList;
}

function updateFlag($flag, $rfIDs, $con) {
	/*if($flag == 1){
		$updateQuery = "UPDATE db_application.assignment_conveyance SET employer_assign = " .$flag. ", convey_ty = 'employee' WHERE rf_id IN (".implode(',', $rfIDs).")";
	} else {
		$updateQuery = "UPDATE db_application.assignment_conveyance SET employer_assign = " .$flag. " WHERE rf_id IN (".implode(',', $rfIDs).")";
	}
    INNER JOIN assignment_conveyance AS ac ON ac.rf_id = dc.rf_id WHERE ac.convey_ty IN ('missing', 'other', 'assignment') AND
	
	echo $updateQuery."<br/><br/><br/><br/><br/><br/><br/><br/><br/>";
	$con->query($updateQuery);*/
	if($flag == 1){
		$updateQuery = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$flag. ", convey_ty = 'employee' WHERE rf_id IN (".implode(',', $rfIDs).") AND convey_ty IN ('missing', 'other', 'assignment') ";
	} else {
		$updateQuery = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$flag. " WHERE rf_id IN (".implode(',', $rfIDs).") AND convey_ty <> 'correct' AND convey_ty <> 'govern'";
	}
	
	
		
	
	echo "UPDATING QUERY<br/>";
	//echo $updateQuery."<br/><br/><br/><br/><br/><br/><br/><br/><br/>";
	$con->query($updateQuery);
	
	if($flag == 0){
		$queryUPDATEASSIGNMENT = "SELECT rf_id FROM db_uspto.representative_assignment_conveyance WHERE rf_id IN (".implode(',', $rfIDs).") AND convey_ty = 'employee'";
		
		
		$resultUPDATEASSIGNMENT = $con->query($queryUPDATEASSIGNMENT);
		echo $queryUPDATEASSIGNMENT."@@@@".$resultUPDATEASSIGNMENT->num_rows."@@@@<br/>";
		if($resultUPDATEASSIGNMENT && $resultUPDATEASSIGNMENT->num_rows > 0) {
			$listIDS = array();
			while($row = $resultUPDATEASSIGNMENT->fetch_object()){
				array_push($listIDS, $row->rf_id);
			}
			if(count($listIDS) > 0) {
				$updateQueryExtra = "UPDATE db_uspto.representative_assignment_conveyance SET convey_ty = 'assignment' WHERE rf_id IN (".implode(',', $listIDS).")";
				echo "UPDATING updateQueryExtra<br/>";
				$con->query($updateQueryExtra);
			}
		}

        $queryUPDATEASSIGNMENT = "SELECT rf_id FROM db_uspto.assignment_conveyance WHERE rf_id IN (".implode(',', $rfIDs).") AND convey_ty = 'security'";
		
		
		$resultUPDATEASSIGNMENT = $con->query($queryUPDATEASSIGNMENT);
		echo $queryUPDATEASSIGNMENT."@@@@".$resultUPDATEASSIGNMENT->num_rows."@@@@<br/>";
		if($resultUPDATEASSIGNMENT && $resultUPDATEASSIGNMENT->num_rows > 0) {
			$listIDS = array();
			while($row = $resultUPDATEASSIGNMENT->fetch_object()){
				array_push($listIDS, $row->rf_id);
			}
			if(count($listIDS) > 0) {
				$updateQueryExtra = "UPDATE db_uspto.representative_assignment_conveyance SET convey_ty = 'security' WHERE rf_id IN (".implode(',', $listIDS).")";
				echo "UPDATING updateQueryExtra<br/>";
				$con->query($updateQueryExtra);
			}
		}
	}
}

function updateFlagCorrective($flag, $rfIDs, $con) {
	$updateQueryExtra = "UPDATE db_uspto.representative_assignment_conveyance SET convey_ty = 'correct' WHERE rf_id IN (".implode(',', $rfIDs).")";
	echo "UPDATING updateQueryExtra<br/>";
	$con->query($updateQueryExtra);
}


function sendNotifications($data) {	
	$pusher = new Pusher\Pusher(CONSTANT_PUSHER_KEY, CONSTANT_PUSHER_SECRET, CONSTANT_PUSHER_APPID, array( 'cluster' => CONSTANT_PUSHER_CLUSTER, 'useTLS' => CONSTANT_PUSHER_ENCRYPTED ) );
	$pusher->trigger( CONSTANT_PUSHER_CHANNEL, CONSTANT_PUSHER_EVENT, $data );
}