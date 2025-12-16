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

/* function insertMessageLog($message, $orgID, $con) { 
	$startDateTime = new DateTime();
	$log = $con->query("INSERT INTO db_new_application.log_messages(message, organisation_id) VALUES ('".$message."', '".$orgID."')");
	$logID = 0;
	if($log) {
		$logID = $con->insert_id;
	} 
	return $logID; 
} */ 
function updateLog($organisationID, $companyID, $message, $con) {
	if($companyID != '') {
		$con->query("UPDATE db_new_application.log_messages SET status = 1 WHERE organisation_id = ".$organisationID." AND company_id = '".$companyID."' AND message = '".$message."'");
	} else {
		$con->query("UPDATE db_new_application.log_messages SET status = 1 WHERE organisation_id = ".$organisationID." AND company_id = 0 AND message = '".$message."'");
	} 
}


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
				$allGroupRepresentative = array();
				$allRepresentative = array();
				/* if($variables[2] == "") {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE type = 1 AND parent_id = 0";	
				} else {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE representative_id = ".$variables[2];	
				}
				
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
				$allRepresentative = array();
				$allRepresentativeNames = array();
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {					
					while($getGroup = $resultRepresentativeParentCompany->fetch_object()) {
						
						array_push($allRepresentative, $getGroup->representative_id);
						$queryGroupRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 0 AND parent_id = ".$getGroup->representative_id;
						
						$resultRepresentativeGroupParentCompany = $orgConnect->query($queryGroupRepresentative);
						if($resultRepresentativeGroupParentCompany && $resultRepresentativeGroupParentCompany->num_rows > 0) {
							while($getCompanyRow = $resultRepresentativeGroupParentCompany->fetch_object()) {
								array_push($allRepresentative, $getCompanyRow->representative_id);
							}
						}					
					}
				}


				
				
				if($variables[2] == "") {
					$queryRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 0 AND parent_id = 0";	
					$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
					
						
					if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
						while($getCompanyRow = $resultRepresentativeParentCompany->fetch_object()) {
							array_push($allRepresentative, $getCompanyRow->representative_id);
						}
					}
				} */
				$company = $variables[2];
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
								}
							}
						}
					}
				} 

				
				/*CORRECT*/
				$rfIDs = array();
				
				if(count($allRepresentative) > 0) {
					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN('assignment', 'missing')  AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID;
					
					if( $variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}
					$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND (MATCH(a.convey_text) AGAINST('\"CORRECTIVE\" -SECURITY -RELEASE -DISCHARGE' IN BOOLEAN MODE) or MATCH(a.convey_text) AGAINST('\"CORRECT\" -SECURITY -RELEASE -DISCHARGE' IN BOOLEAN MODE)) GROUP BY documentid.rf_id";
					echo $queryFindAllRFIDs;
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}
				}
				
				if(count($rfIDs) > 0) {
					updateFlag(0, 'correct', $rfIDs, $con);
				}
				updateLog($organisationID, $variables[2], "Missing Assignment", $con);
				sendNotifications("RE-Classify flag.");

				/**
				 * ASSIGNMENT OF ASSIGNORS
				 */

				$rfIDs = array(); 
				/* $logID = insertMessageLog('Missing Assignment', $organisationID, $con); */
				if(count($allRepresentative) > 0 ) { 
					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id LEFT JOIN representative_assignment_conveyance AS rrac ON rrac.rf_id = rac.rf_id WHERE rac.convey_ty IN('missing', 'other', 'govern') AND rrac.convey_ty NOT IN ('employee', 'assignment', 'correct') AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
						SELECT rf_id FROM db_uspto.list2 WHERE  organisation_id = ".$organisationID;
						
					if($variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}
					
					$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND (
						MATCH(a.convey_text) AGAINST('\"ASSIGNMENT OF ASSIGNORS INTEREST\"' IN BOOLEAN MODE)
						OR 
						MATCH(a.convey_text) AGAINST('\"ACKNOWLEDGEMENT OF RIGHTS\"' IN BOOLEAN MODE)
						OR 
						MATCH(a.convey_text) AGAINST('\"ASSIGNMENT OF RIGHTS\"' IN BOOLEAN MODE)
						OR 
						MATCH(a.convey_text) AGAINST('\"CONVERSION\"' IN BOOLEAN MODE)
						OR 
						MATCH(a.convey_text) AGAINST('\"CONTINUANCE\"' IN BOOLEAN MODE)
						OR 
						MATCH(a.convey_text) AGAINST('\"NUNC\"' IN BOOLEAN MODE)
						OR 
						MATCH(a.convey_text) AGAINST('\"TUNC\"' IN BOOLEAN MODE)
						OR 
						MATCH(a.convey_text) AGAINST('\"ASSIGNMENT OF INTELLECTUAL PROPERTY\"' IN BOOLEAN MODE)
					) GROUP BY documentid.rf_id";

					echo $queryFindAllRFIDs;

					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					} 
				}

				echo "ASSIGNMENT OF ASSIGNORS: ".count($rfIDs);
				
				if(count($rfIDs) > 0) {
					$rfIDs = array_values(array_unique($rfIDs));
					echo "UNIQUE TRANSACTIONS: ".count($rfIDs)."<br/>";	
					updateFlag(0, 'assignment', $rfIDs, $con);
					//fixCompanyAssignorMissingTypes($rfIDs, $con); 
					
				}
				updateLog($organisationID, $variables[2], "Missing Assignment", $con);
				sendNotifications("RE-Classify flag.");
				/*Change of name*/
				$rfIDs = array();
				//$logID = insertMessageLog('Missing NameChange', $organisationID, $con);
				if(count($allRepresentative) > 0 ) {

					/*$queryUpdate =  "UPDATE representative_assignment_conveyance SET convey_ty = 'namechg' WHERE rf_id IN (SELECT documentid.rf_id FROM documentid 
					INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  
					INNER JOIN assignment_conveyance as ac ON ac.rf_id = a.rf_id 
					WHERE ac.convey_ty = 'namechg' AND documentid.appno_doc_num IN (
					   SELECT appno_doc_num FROM documentid WHERE rf_id IN (
						   SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID." 
					   ) GROUP BY appno_doc_num 
				   )GROUP BY documentid.rf_id )";

				   $con->query($queryUpdate);*/
						
					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN('missing', 'other', 'govern', 'correct')  AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE  organisation_id = ".$organisationID;
					
					if($variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}

					$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND ( MATCH(a.convey_text) AGAINST('\"CHANGE OF NAME\"' IN BOOLEAN MODE) 
					) GROUP BY documentid.rf_id";
							
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}/* 

					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN('missing', 'other')  AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE  organisation_id = ".$organisationID;
					
					if($variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}

					$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND ( MATCH(a.convey_text) AGAINST('\"CONVERSION\"' IN BOOLEAN MODE) ) GROUP BY documentid.rf_id";
							
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}  */
				} 
				echo "Change of name: ".count($rfIDs);
				
				if(count($rfIDs) > 0) {
					updateFlag(0, 'namechg', $rfIDs, $con);
				}
				
				updateLog($organisationID, $variables[2], "Missing NameChange", $con);
				sendNotifications("RE-Classify flag.");




				/*Change of Address*/
				$rfIDs = array();
				/* $logID = insertMessageLog('Missing Change address', $organisationID, $con); */
				if(count($allRepresentative) > 0  ) {
					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN('missing', 'namechg', 'other') AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID;
					
					if( $variables[2] != "") { 
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}
					/* $queryFindAllRFIDs .= "  ) GROUP BY appno_doc_num ) AND (
	MATCH(a.convey_text) AGAINST('\"CHANGE OF ADDRESS\"' IN BOOLEAN MODE) OR MATCH(a.convey_text) AGAINST('\"CHANGE OF BUSINESS ADDRESS\"' IN BOOLEAN MODE) OR MATCH(a.convey_text) AGAINST('\"ADDRESS\"' IN BOOLEAN MODE)
) GROUP BY documentid.rf_id"; */

					$queryFindAllRFIDs .= "  ) GROUP BY appno_doc_num ) AND (
						MATCH(a.convey_text) AGAINST('\"ADDRESS\"' IN BOOLEAN MODE)
					) GROUP BY documentid.rf_id";
							
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}
				}
				
				 
				echo "Change of address: ".count($rfIDs);
				if(count($rfIDs) > 0) {
					updateFlag(0, 'addresschg', $rfIDs, $con);
				} 
				updateLog($organisationID, $variables[2], "Missing Change address", $con);
				sendNotifications("RE-Classify flag.");

                /*License*/
				$rfIDs = array();
				/* $logID = insertMessageLog('Missing License', $organisationID, $con); */
				if(count($allRepresentative) > 0 ) {
					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN('missing', 'other', 'govern') AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE  organisation_id = ".$organisationID;
					
					if(  $variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}
					
					$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND a.convey_text LIKE '%LICENSE%' GROUP BY documentid.rf_id";

					
							
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}
				} 
				echo "License: ".count($rfIDs);
				if(count($rfIDs) > 0) {
					updateFlag(0, 'license', $rfIDs, $con);
				} 
				updateLog($organisationID, $variables[2], "Missing License", $con);
				sendNotifications("RE-Classify flag.");
				/*Security*/
				$rfIDs = array();
				/* $logID = insertMessageLog('Missing Security', $organisationID, $con); */
				if(count($allRepresentative) > 0 ) {
						
					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN('missing', 'other', 'govern')  AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID;
					
					if( $variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}

					$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND (
	MATCH(a.convey_text) AGAINST('\"SECURITY\" -RELEASE -DISCHARGE' IN BOOLEAN MODE) OR MATCH(a.convey_text) AGAINST('\"PLEDGE\" -RELEASE -DISCHARGE' IN BOOLEAN MODE) ) GROUP BY documentid.rf_id";

					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}

					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN('missing', 'other', 'govern')  AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
						SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID;
						
						if( $variables[2] != "") {
							$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
						}
	
						$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND (
		MATCH(a.convey_text) AGAINST('\"SECURITY\" -RELEASE -DISCHARGE' IN BOOLEAN MODE) OR MATCH(a.convey_text) AGAINST('\"SUCCESSION OF AGENCY\" -RELEASE -DISCHARGE' IN BOOLEAN MODE) ) GROUP BY documentid.rf_id";
	
						$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
						if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
							while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
								array_push($rfIDs, $rowRepresentativeRF->rf_id);
							}
						}


						$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN representative_assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN('assignment','merger')  AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
							SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID;
							
							if( $variables[2] != "") {
								$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
							}
		
							$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND (
			MATCH(a.convey_text) AGAINST('\"SECURITY\" -RELEASE -DISCHARGE' IN BOOLEAN MODE) OR MATCH(a.convey_text) AGAINST('\"SUCCESSION OF AGENCY\" -RELEASE -DISCHARGE' IN BOOLEAN MODE) ) GROUP BY documentid.rf_id";
		
							$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
							if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
								while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
									array_push($rfIDs, $rowRepresentativeRF->rf_id);
								}
							}
				}
				
				
				echo "Change of security: ".count($rfIDs);
				
				
				if(count($rfIDs) > 0) {
					updateFlag(0, 'security', $rfIDs, $con);
				}
				

				/*SECURITY, RATIFICATION, AMENDMENT,  In CORRECT*/
				$rfIDs = array();
				
				if(count($allRepresentative) > 0) {
					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN('correct') AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID;
					
					if( $variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}
					$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND MATCH(a.convey_text) AGAINST('\"+SECURITY\" (>RATIFICATION >AMENDMENT) -CORRECT -CORRECTIVE' IN BOOLEAN MODE)) GROUP BY documentid.rf_id";
					echo $queryFindAllRFIDs;
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}
				}
				
				if(count($rfIDs) > 0) {
					updateFlag(0, 'security', $rfIDs, $con);
				} 
				updateLog($organisationID, $variables[2], "Missing Security", $con);
				sendNotifications("RE-Classify flag.");


				/*Release*/
				$rfIDs = array();
				/* $logID = insertMessageLog('Missing Release', $organisationID, $con); */
				if(count($allRepresentative) > 0) {
						
					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN ('missing', 'security', 'other', 'govern') AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID;
					
					if( $variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}
					
					
					$queryFindAllRFIDs .= " )  GROUP BY appno_doc_num ) AND (
	MATCH(a.convey_text) AGAINST('\"RELEASE BY SECURED\" -PARTIAL' IN BOOLEAN MODE) OR MATCH(a.convey_text) AGAINST('\"RELEASE OF SECURITY\" -PARTIAL' IN BOOLEAN MODE) OR MATCH(a.convey_text) AGAINST('\"DISCHARGE OF SECURITY INTEREST\"  -PARTIAL' IN BOOLEAN MODE) OR MATCH(a.convey_text) AGAINST('\"RELEASE\" -PARTIAL' IN BOOLEAN MODE) OR MATCH(a.convey_text) AGAINST('\"BANKRUPTCY COURT ORDER RELEASING ALL LIENS INCLUDING THE SECURITY INTEREST\"' IN BOOLEAN MODE)) GROUP BY documentid.rf_id";
							
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}
				}
				 
				 
				
				echo "Change of release: ".count($rfIDs);
				
				
				
				if(count($rfIDs) > 0) {
					updateFlag(0, 'release', $rfIDs, $con);
				} 


				/*Partial Release*/
				$rfIDs = array();
				
				if(count($allRepresentative) > 0 ) {
						
					echo $queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE rac.convey_ty IN ('missing', 'security', 'other', 'govern', 'release') AND documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID;


					if( $variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}
					
					$queryFindAllRFIDs .= " )   GROUP BY appno_doc_num ) AND (
	MATCH(a.convey_text) AGAINST('\"PARTIAL RELEASE\"' IN BOOLEAN MODE)) GROUP BY documentid.rf_id";
							
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}
				} 


				echo "Change of partial release: ".count($rfIDs);
				
				
				
				if(count($rfIDs) > 0) {
					updateFlag(0, 'partialrelease', $rfIDs, $con);
				} 
				updateLog($organisationID, $variables[2], "Missing Release", $con);
				sendNotifications("RE-Classify flag.");
				
				/*MERGER*/
				$rfIDs = array();
				/* $logID = insertMessageLog('Missing Merger', $organisationID, $con); */
				if(count($allRepresentative) > 0) {
					$queryFindAllRFIDs = "SELECT documentid.rf_id FROM documentid INNER JOIN assignment as a ON a.rf_id = documentid.rf_id  INNER JOIN assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE documentid.appno_doc_num IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (
					SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID;
					
					if( $variables[2] != "") {
						$queryFindAllRFIDs .= " AND company_id IN (".implode(',', $allRepresentative).") ";
					}
					$queryFindAllRFIDs .= " ) GROUP BY appno_doc_num ) AND (a.convey_text LIKE '%MERGER%' OR a.convey_text LIKE 'MERGER%' OR a.convey_text LIKE '%MERGER') GROUP BY documentid.rf_id";
					echo $queryFindAllRFIDs;
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}
				}
				echo "MERGER: ".count($rfIDs);
				
				if(count($rfIDs) > 0) {
					updateFlag(0, 'merger', $rfIDs, $con);
                } 
				updateLog($organisationID, $variables[2], "Missing Merger", $con);
				sendNotifications("RE-Classify flag.");
			}
		} 
	}
}

function fixCompanyAssignorMissingTypes ($assignmentRFIDs, $con) {
	
	if(count($assignmentRFIDs) > 0) {	
		/*List of assignments in which company as a assignee*/						
		$queryAssignments = "SELECT a.rf_id FROM assignee as a WHERE a.rf_id IN (".implode(',', $assignmentRFIDs).") GROUP BY a.rf_id";
		$resultAssignment = $con->query($queryAssignments);						
		$assignmentList = array();						
		if($resultAssignment && $resultAssignment->num_rows > 0) {
			while($rowAssignment = $resultAssignment->fetch_object()){
				array_push($assignmentList, $rowAssignment->rf_id);
			}
		}
		
		$originalList = $assignmentList;
		print_r(implode(',', $originalList));
		
		$queryUniqueAssignors = "SELECT aaa.assignor_and_assignee_id, aaa.name FROM assignor as a INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id  WHERE a.rf_id IN (".implode(",", $assignmentList).") GROUP BY aaa.name";	
		//echo $queryUniqueAssignors."<br/>";
		
		$resultAssignor = $con->query($queryUniqueAssignors);						
		$assignorList = array();
		$assignorListWithIDs = array();
		
		if($resultAssignor && $resultAssignor->num_rows > 0) {
			while($rowAssignor = $resultAssignor->fetch_object()){
				$name = $rowAssignor->name;
				array_push($assignorList, array('name'=>$name, 'assignor_and_assignee_id'=>$rowAssignor->assignor_and_assignee_id));
				array_push($assignorListWithIDs, $rowAssignor->assignor_and_assignee_id);
			}
		}
		
		$patternMatch = '/\b(?:inc|llc|corporation|corp|systems|system|llp|industries|gmbh|lp|agent|sas|na|bank|co|states|ltd|kk|a\/s|aktiebolag|kigyo|kaisha|university|kabushiki|company|plc|gesellschaft|gesmbh|société|societe|mbh|aktiengesellschaft|haftung|vennootschap|bv|bvba|aktien|limitata|srl|sarl|kommanditgesellschaft|kg|gesellschaft|gbr|ohg|handelsgesellschaft|compagnie|privatstiftung|foundation|technologies|technology|solutions|solution|networks|network|holding|holdings|health|animal|scientific|chemical|chemicals|pharmaceutical|trust|the|resources|government|college|support|pharma|pharmalink|labs|lab|pyramid|analytics|analytic|therapeutics|tigenix|nexstim|voluntis|elobix|nxp|ab|sa|acies|wakefield|semiconductor|development|research|traingle|institute|advanced|interconnect|sensordynamics|product|products|international|biotech|investment|partner|capital|royalty|parallel|laboratories|spa|city|studios|universal|lllp|partners|national|wrestling|international|licensing|demografx|island|ag|credit|suisse)\b/i'; 
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
		echo implode(',', $removeAssignors);
		
		
		if(count($removeAssignors) > 0) {
			//sendNotifications("Total companies found by keyword search: ".count($removeAssignors));
			
			$assignorAssignmentList = findAssignmentsFromAssignorList($removeAssignors, $originalList, $con);							
				
			if(count($assignorAssignmentList) > 0) {
				//print_r($assignorListWithIDs);
				//echo implode(',',$removeAssignors);
				//echo implode(',',$assignorAssignmentList);
				$assignorListWithIDs = array_diff($assignorListWithIDs, $removeAssignors);
				echo "COUNT:".count($assignorAssignmentList);
				$companiesRFIDS = $assignorAssignmentList;
				updateFlag(0, 'assignment', $assignorAssignmentList, $con);
			}								
		}
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

function findAssignmentsFromAssignorList($removeAssignors, $assignmentList, $con) {
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
				$queryAssignorAssignments = "SELECT a.rf_id  FROM assignor as a WHERE a.rf_id IN (".implode(",", $sendList).") AND a.assignor_and_assignee_id IN (".implode(",", $removeAssignors).") GROUP BY a.rf_id";
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
		$queryAssignorAssignments = "SELECT a.rf_id  FROM assignor as a WHERE a.rf_id IN (".implode(",", $assignmentList).") AND a.assignor_and_assignee_id IN (".implode(",", $removeAssignors).") GROUP BY a.rf_id";
		echo "SINGLE COUNTER: ".count($assignmentList)."@@@@@@@@Current Counter: ".count($removeAssignors)."<br/>";
		//echo $queryAssignorAssignments."<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>";
		$resultAssignorAssignment = $con->query($queryAssignorAssignments);
		
		
		if($resultAssignorAssignment && $resultAssignorAssignment->num_rows > 0) {
			while($rowAssignorAssignment = $resultAssignorAssignment->fetch_object()){
				array_push($assignorAssignmentList, $rowAssignorAssignment->rf_id);
			}
		}
	}
	
	return $assignorAssignmentList;
}


function updateFlag($flag, $conveyanceType, $rfIDs, $con) {
	if($flag == 1){
		$updateQuery = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$flag. ", convey_ty = 'employee' WHERE rf_id IN (".implode(',', $rfIDs).")";
	} else {
		$updateQuery = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$flag. ", convey_ty = '".$conveyanceType."' WHERE rf_id IN (".implode(',', $rfIDs).")";
	}
	echo "UPDATING QUERY<br/>";
	echo $updateQuery."<br/><br/><br/><br/><br/><br/><br/><br/><br/>";
	$con->query($updateQuery);
}


function sendNotifications($data) {	
	$pusher = new Pusher\Pusher(CONSTANT_PUSHER_KEY, CONSTANT_PUSHER_SECRET, CONSTANT_PUSHER_APPID, array( 'cluster' => CONSTANT_PUSHER_CLUSTER, 'useTLS' => CONSTANT_PUSHER_ENCRYPTED ) );
	$pusher->trigger( CONSTANT_PUSHER_CHANNEL, CONSTANT_PUSHER_EVENT, $data ); 
}