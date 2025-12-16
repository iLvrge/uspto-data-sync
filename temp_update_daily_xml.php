<?php 


ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000);
$fileName = './dds/ad20200803.xml';
	echo $fileName."<br/>";
	$getFileContent = file_get_contents($fileName);
	
	if($getFileContent) {
		try{
			$xml = simplexml_load_string($getFileContent);
			if ($xml !== false) {
				$host = getenv('DB_HOST');
				$user = getenv('DB_USER');
				$password = getenv('DB_PASSWORD');
				$dbUSPTO = getenv('DB_USPTO_DB');
				$dbBusiness = getenv('DB_BUSINESS');
				$dbApplication = getenv('DB_APPLICATION_DB');
				$con = new mysqli($host, $user, $password, $dbUSPTO);
				
				$queryConveyType = "Select a.convey_text as text, rac.convey_ty as convey_ty FROM db_uspto.assignment as a  INNER JOIN db_uspto.representative_assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE a.convey_text <> '' AND a.convey_text IS NOT NULL GROUP BY a.convey_text, convey_ty";
				
				
				$resultAllConveyType = $con->query($queryConveyType);
				$allConveyText = array();
				if($resultAllConveyType && $resultAllConveyType->num_rows > 0) {
					while($row = $resultAllConveyType->fetch_object()){
						array_push($allConveyText, array('text'=>$row->text, 'convey_ty'=>$row->convey_ty));
					}
				}
				
				$xmlObject = new SimpleXMLElement($getFileContent);	
				$pathAssignments = $xmlObject->xpath('patent-assignments');
				$assignmentList = $pathAssignments[0]->xpath('patent-assignment');
				$assignmentData = array();
				$assignmentConveyance = array();
				$assignorsList = array();
				$assigneesList = array();
				$documentList = array();
				$allRFIDs = array();
				if(count($assignmentList) > 0) {
					foreach( $assignmentList as $assignment) {
						$assignmentRecord = $assignment->xpath('assignment-record');
						
						$reelNo = (string)$assignmentRecord[0]->{'reel-no'};
						$frameNo = (string)$assignmentRecord[0]->{'frame-no'};
						$lastUpdateDate = (string)$assignmentRecord[0]->xpath('last-update-date')[0]->{'date'};
						$purgeN = (string)$assignmentRecord[0]->{'purge-indicator'};
						$recordDate = (string)$assignmentRecord[0]->xpath('recorded-date')[0]->{'date'};
						$pageCount = (string)$assignmentRecord[0]->{'page-count'};						
						$correspondence = $assignmentRecord[0]->xpath('correspondent');
						$cname = (string)$correspondence[0]->{'name'};
						$address1 = (string)$correspondence[0]->{'address-1'};
						$address2 = (string)$correspondence[0]->{'address-2'};
						$address3 = (string)$correspondence[0]->{'address-3'};
						$coveyanceText = (string)$assignmentRecord[0]->{'conveyance-text'};
						
						$rfID = $reelNo;
						//echo $reelNo.$frameNo."<br/>";
						if(strlen($frameNo) == 3){
							$rfID .= '0'.$frameNo;
						}else if(strlen($frameNo) == 2){
							$rfID .= '00'.$frameNo;
						}else if(strlen($frameNo) == 1){
							$rfID .= '000'.$frameNo;
						} else {
							$rfID .= $frameNo;
						}						
						
						array_push($allRFIDs, $rfID);
						array_push($assignmentData, array('rf_id' => $rfID, 'cname'=>$cname, 'caddress_1'=>$address1, 'caddress_2'=>$address2, 'caddress_3' =>$address3, 'reel_no'=>$reelNo, 'frame_no'=>$frameNo, 'convey_text'=>$coveyanceText, 'record_dt'=>$recordDate, 'last_update_dt'=>$lastUpdateDate, 'page_count'=>$pageCount, 'purge_in'=>$purgeN ));

						$convey_ty = '';
						$checkConveyanceType = strtolower($coveyanceText);
						
						if(count($allConveyText) > 0) {
							foreach($allConveyText as $ct) {
								if($ct['text'] == trim($checkConveyanceType)){
									$convey_ty = $ct['convey_ty'];
									break;
								}
							}
						}
						
						$employer_assign = 0;
						if($convey_ty == "") {
							if(strpos($checkConveyanceType, 'correct') || strpos($checkConveyanceType, 're-record')){
							$convey_ty = 'correct';
							} else if(strpos($checkConveyanceType, 'employee') || strpos($checkConveyanceType, 'employment')){
								$convey_ty = 'employee';
								$employer_assign = 1;
							} else if(strpos($checkConveyanceType, 'confirmator')){
								$convey_ty = 'govern';
							} else if(strpos($checkConveyanceType, 'merger')){
								$convey_ty = 'merger';
							} else if(strpos($checkConveyanceType, 'change of name') || strpos($checkConveyanceType, 'change of address')){
								$convey_ty = 'namechg';
							} else if(strpos($checkConveyanceType, 'license') || strpos($checkConveyanceType, 'letters of testamentary')){
								$convey_ty = 'license';
							} else if(strpos($checkConveyanceType, 'release')){
								$convey_ty = 'release';
							} else if(strpos($checkConveyanceType, 'security') || strpos($checkConveyanceType, 'mortgage')){
								$convey_ty = 'security';
							} else if(strpos($checkConveyanceType, 'assignment')){
								$convey_ty = 'assignment';
							} else {
								$convey_ty = 'missing';
							}
						}
						
						
						array_push($assignmentConveyance, array('rf_id'=> $rfID, 'convey_ty'=>$convey_ty, 'employer_assign'=>$employer_assign));
						
						/*missing*/
						
						
						$patentAssignors = $assignment->xpath('patent-assignors');
						$assignors = $patentAssignors[0]->xpath('patent-assignor');
						$assignorData = array();
						$assignorAndAssigneeData = array();
						foreach($assignors as $assignor) {
							$name = (string)$assignor->{'name'};
							$assignor_and_assigneeID = 0;
							
							$orName = $name;
							$orName = removeDoubleSpace( $orName );
							$orName = strReplace( $orName );
							$findString = 0;
							$stringC = remove_if_trailing($orName, "corporation");
							if($stringC[1] === 0) {
								$stringC = remove_if_trailing($orName, "incorporated");
								if($stringC[1] === 0) {
									$stringC = remove_if_trailing($orName, "limited");
									if($stringC[1] === 0) {
										$stringC = remove_if_trailing($orName, "company");
										if($stringC[1] === 1) {
											$findString = $stringC[1];
											$orName = removeDoubleSpace($stringC[0]);
										}
									} else {
										$findString = $stringC[1];
										$orName = removeDoubleSpace($stringC[0]);
									}	
								} else {
									$findString = $stringC[1];
									$orName = removeDoubleSpace($stringC[0]);
								}
							} else {
								$findString = $stringC[1];
								$orName = removeDoubleSpace($stringC[0]);
							}
							/*$checkName = 'SELECT assignor_and_assignee_id FROM assignor_and_assignee WHERE name ="'.$con->real_escape_string($orName).'"';
							$resultCheck = $con->query($checkName);
							if($resultCheck && $resultCheck->num_rows > 0) {
								$rowData = $resultCheck->fetch_object;
								$assignor_and_assigneeID = $rowData->assignor_and_assignee_id;
							}*/
							
							$executionDate = (string)$assignor->xpath('execution-date')[0]->{'date'};
							array_push($assignorData, array('rf_id'=> $rfID,'or_name'=>$name, 'exec_dt'=>$executionDate));
							
							/*if($assignor_and_assigneeID > 0) {
								$queryUpdateInstance = 'UPDATE assignor_and_assignee SET instances = instances + 1  WHERE assignor_and_assignee_id = '.$assignor_and_assigneeID;
								echo $queryUpdateInstance."<br/>";
								//$con->query($queryUpdateInstance);
							}*/							
						}
						array_push($assignorsList, $assignorData);
						
						$patentAssignees = $assignment->xpath('patent-assignees');
						$assignees = $patentAssignees[0]->xpath('patent-assignee');
						$assigneeData = array();
						foreach($assignees as $assignee) {
							$name = (string)$assignee->{'name'};
							$assignor_and_assigneeID = 0;
							$eeName = $name;
							$eeName = removeDoubleSpace( $eeName );
							$eeName = strReplace( $eeName );
							$findString = 0;
							$stringC = remove_if_trailing($eeName, "corporation");
							if($stringC[1] === 0) {
								$stringC = remove_if_trailing($eeName, "incorporated");
								if($stringC[1] === 0) {
									$stringC = remove_if_trailing($eeName, "limited");
									if($stringC[1] === 0) {
										$stringC = remove_if_trailing($eeName, "company");
										if($stringC[1] === 1) {
											$findString = $stringC[1];
											$eeName = removeDoubleSpace($stringC[0]);
										}
									} else {
										$findString = $stringC[1];
										$eeName = removeDoubleSpace($stringC[0]);
									}	
								} else {
									$findString = $stringC[1];
									$eeName = removeDoubleSpace($stringC[0]);
								}
							} else {
								$findString = $stringC[1];
								$eeName = removeDoubleSpace($stringC[0]);
							}
							
							/*$checkName = 'SELECT assignor_and_assignee_id FROM assignor_and_assignee WHERE name ="'.$con->real_escape_string($eeName).'"';
							$resultCheck = $con->query($checkName);
							if($resultCheck && $resultCheck->num_rows > 0) {
								$rowData = $resultCheck->fetch_object;
								$assignor_and_assigneeID = $rowData->assignor_and_assignee_id;
							}*/
							
							
							$address1 = (string)$assignee->{'address-1'};
							$city = (string)$assignee->{'city'};
							$state = (string)$assignee->{'state'};
							$postcode = (string)$assignee->{'postcode'};
							array_push($assigneeData, array('rf_id'=> $rfID,'ee_name'=>$name, 'ee_address_1'=>$address1, 'ee_city'=>$city, 'ee_state'=>$state, 'ee_postcode'=> $postcode, 'assignor_and_assignee_id' => $assignor_and_assigneeID));
							/*
							if($assignor_and_assigneeID > 0) {
								$queryUpdateInstance = 'UPDATE assignor_and_assignee SET instances = instances + 1  WHERE assignor_and_assignee_id = '.$assignor_and_assigneeID;
								echo $queryUpdateInstance."<br/>";
								//$con->query($queryUpdateInstance);
							}*/
						}
						array_push($assigneesList, $assigneeData);						
						
						$documentsIds = $assignment->xpath('patent-properties');					
						$document = $documentsIds[0]->xpath('patent-property');
						$documentData = array();
						foreach($document as $documents) {
							$documentIds = $documents->xpath('document-id');
							$title = (string)$documents->{'invention-title'};
							$application = ""; $applicationDate = ""; $applicationCountry = ""; $publication = ""; $publicationDate = ""; $publicationCountry = ""; $patent = ""; $patentDate = ""; $patentCountry = "";
							foreach($documentIds as $documentID) {
								if((string)$documentID->{'kind'} == "X0") {
									$application = (string)$documentID->{'doc-number'};
									$applicationDate = (string)$documentID->{'date'};
									$applicationCountry = (string)$documentID->{'country'};
								} else if(strpos((string)$documentID->{'kind'}, "A") !== false) {
									$publication = (string)$documentID->{'doc-number'};
									$publicationDate = (string)$documentID->{'date'};
									$publicationCountry = (string)$documentID->{'country'};
								} else {
									$patent = (string)$documentID->{'doc-number'};
									$patentDate = (string)$documentID->{'date'};
									$patentCountry = (string)$documentID->{'country'};
								}
							}
							array_push($documentData, array('rf_id'=> $rfID,'title'=>$title, 'appno_doc_num'=>$application, 'appno_date'=>$applicationDate, 'appno_country'=>$applicationCountry, 'pgpub_doc_num'=>$publication, 'pgpub_date'=>$publicationDate, 'pgpub_country'=>$publicationCountry, 'grant_doc_num'=>$patent, 'grant_date'=>$patentDate, 'grant_country'=>$patentCountry));
						}
						array_push($documentList, $documentData);
						
					}
				}
				
				if(count($assignmentData) > 0){
					
					$queryInsertAssignment = "INSERT IGNORE INTO db_uspto.assignment (rf_id, cname, caddress_1, caddress_2, caddress_3, reel_no, frame_no, convey_text, record_dt, last_update_dt, page_count, purge_in) VALUES ";
					
					foreach($assignmentData as $assignment){
						$recordDT  = substr($assignment['record_dt'], 0, 4).'-'.substr($assignment['record_dt'], 4, 2).'-'.substr($assignment['record_dt'], 6, 2);
						$lastUpdateDT  = substr($assignment['last_update_dt'], 0, 4).'-'.substr($assignment['last_update_dt'], 4, 2).'-'.substr($assignment['last_update_dt'], 6, 2);
						$cName = $assignment['cname'];						
						$cName = removeDoubleSpace( $cName );
						$cName = strReplace( $cName );
						$findString = 0;
						$stringC = remove_if_trailing($cName, "corporation");
						if($stringC[1] === 0) {
							$stringC = remove_if_trailing($cName, "incorporated");
							if($stringC[1] === 0) {
								$stringC = remove_if_trailing($cName, "limited");
								if($stringC[1] === 0) {
									$stringC = remove_if_trailing($cName, "company");
									if($stringC[1] === 1) {
										$findString = $stringC[1];
										$cName = removeDoubleSpace($stringC[0]);
									}
								} else {
									$findString = $stringC[1];
									$cName = removeDoubleSpace($stringC[0]);
								}	
							} else {
								$findString = $stringC[1];
								$cName = removeDoubleSpace($stringC[0]);
							}
						} else {
							$findString = $stringC[1];
							$cName = removeDoubleSpace($stringC[0]);
						}
						
						
						$queryInsertAssignment .= "(".$assignment['rf_id'].", '".$con->real_escape_string($cName)."', '".$con->real_escape_string($assignment['caddress_1'])."', '".$con->real_escape_string($assignment['caddress_2'])."', '".$con->real_escape_string($assignment['caddress_3'])."', '".$assignment['reel_no']."', '".$assignment['frame_no']."', '".$con->real_escape_string($assignment['convey_text'])."', '".$recordDT."', '".$lastUpdateDT."', '".$assignment['page_count']."', '".$assignment['purge_in']."'), ";
					}
					
					$queryInsertAssignment = substr($queryInsertAssignment, 0, -2);
					echo $queryInsertAssignment."<br/>";	
					$con->query($queryInsertAssignment);
					
					$queryInsertAssignmentConveyance = "INSERT IGNORE INTO db_uspto.assignment_conveyance (rf_id, convey_ty, employer_assign) VALUES ";
					$queryInsertRepresentativeAssignmentConveyance = "INSERT IGNORE INTO db_uspto.representative_assignment_conveyance (rf_id, convey_ty, employer_assign) VALUES ";
					
					foreach($assignmentConveyance as $conveyance){
						$queryInsertAssignmentConveyance .= "('".$conveyance['rf_id']."', '".$conveyance['convey_ty']."', '".$conveyance['employer_assign']."'), ";
						$queryInsertRepresentativeAssignmentConveyance .= "('".$conveyance['rf_id']."', '".$conveyance['convey_ty']."', '".$conveyance['employer_assign']."'), ";
					}
					$queryInsertAssignmentConveyance = substr($queryInsertAssignmentConveyance, 0, -2);
					$queryInsertRepresentativeAssignmentConveyance = substr($queryInsertRepresentativeAssignmentConveyance, 0, -2);
					echo $queryInsertAssignmentConveyance."<br/>";		
					echo $queryInsertRepresentativeAssignmentConveyance."<br/>";		
					$con->query($queryInsertAssignmentConveyance);
					$con->query($queryInsertRepresentativeAssignmentConveyance);
					
					$queryInsertAssignor = "INSERT IGNORE INTO db_uspto.assignor(rf_id, or_name, exec_dt) VALUES ";
					
					foreach($assignorsList as $assignors){
						if( count($assignors) > 0 ) {
							foreach($assignors as $assignor) {
								$exec_dt  = substr($assignor['exec_dt'], 0, 4).'-'.substr($assignor['exec_dt'], 4, 2).'-'.substr($assignor['exec_dt'], 6, 2);
								$orName = $assignor['or_name'];						
								$orName = removeDoubleSpace( $orName );
								$orName = strReplace( $orName );
								$findString = 0;
								$stringC = remove_if_trailing($orName, "corporation");
								if($stringC[1] === 0) {
									$stringC = remove_if_trailing($orName, "incorporated");
									if($stringC[1] === 0) {
										$stringC = remove_if_trailing($orName, "limited");
										if($stringC[1] === 0) {
											$stringC = remove_if_trailing($orName, "company");
											if($stringC[1] === 1) {
												$findString = $stringC[1];
												$orName = removeDoubleSpace($stringC[0]);
											}
										} else {
											$findString = $stringC[1];
											$orName = removeDoubleSpace($stringC[0]);
										}	
									} else {
										$findString = $stringC[1];
										$orName = removeDoubleSpace($stringC[0]);
									}
								} else {
									$findString = $stringC[1];
									$orName = removeDoubleSpace($stringC[0]);
								}
								
								$queryInsertAssignor .= "('".$assignor['rf_id']."', '".$con->real_escape_string($orName)."', '".$exec_dt."'), ";
							}
						}						
					}
					
					$queryInsertAssignor = substr($queryInsertAssignor, 0, -2);	
					echo $queryInsertAssignor."<br/>";							
					$con->query($queryInsertAssignor);
					
					
					$queryInsertAssignee = "INSERT IGNORE INTO db_uspto.assignee(rf_id, ee_name, ee_address_1, ee_city, ee_state, ee_postcode) VALUES ";
					
					foreach($assigneesList as $assignees){
						if( count($assignees) > 0 ) {
							foreach($assignees as $assignee) {								
								$eeName = $assignee['ee_name'];						
								$eeName = removeDoubleSpace( $eeName );
								$eeName = strReplace( $eeName );
								$findString = 0;
								$stringC = remove_if_trailing($eeName, "corporation");
								if($stringC[1] === 0) {
									$stringC = remove_if_trailing($eeName, "incorporated");
									if($stringC[1] === 0) {
										$stringC = remove_if_trailing($eeName, "limited");
										if($stringC[1] === 0) {
											$stringC = remove_if_trailing($eeName, "company");
											if($stringC[1] === 1) {
												$findString = $stringC[1];
												$eeName = removeDoubleSpace($stringC[0]);
											}
										} else {
											$findString = $stringC[1];
											$eeName = removeDoubleSpace($stringC[0]);
										}	
									} else {
										$findString = $stringC[1];
										$eeName = removeDoubleSpace($stringC[0]);
									}
								} else {
									$findString = $stringC[1];
									$eeName = removeDoubleSpace($stringC[0]);
								}
								
								$queryInsertAssignee .= "('".$assignee['rf_id']."', '".$con->real_escape_string($eeName)."', '".$con->real_escape_string($assignee['ee_address_1'])."',  '".$con->real_escape_string($assignee['ee_city'])."', '".$con->real_escape_string($assignee['ee_state'])."', '".$con->real_escape_string($assignee['ee_postcode'])."'), ";
								
								echo $queryInsertAssignee;
							}
						}						
					}
					
					$queryInsertAssignee = substr($queryInsertAssignee, 0, -2);	
					echo $queryInsertAssignee."<br/>";
					$con->query($queryInsertAssignee);
					
					$queryInsertDocument = "INSERT IGNORE INTO db_uspto.documentid(rf_id, title, appno_doc_num, appno_date, appno_country, pgpub_doc_num, pgpub_date, pgpub_country, grant_doc_num, grant_date, grant_country) VALUES ";
					
					foreach($documentList as $documents){
						if( count($documents) > 0 ) {
							foreach($documents as $document){
								$appno_date = null;
								if($document['appno_date'] != '') {
									$appno_date  = substr($document['appno_date'], 0, 4).'-'.substr($document['appno_date'], 4, 2).'-'.substr($document['appno_date'], 6, 2);
								}
								
								$pgpub_date = null;
								if($document['pgpub_date'] != '') {
									$pgpub_date  = substr($document['pgpub_date'], 0, 4).'-'.substr($document['pgpub_date'], 4, 2).'-'.substr($document['pgpub_date'], 6, 2);
								}
								
								$grant_date = null;
								if($document['grant_date'] != '') {
									$grant_date  = substr($document['grant_date'], 0, 4).'-'.substr($document['grant_date'], 4, 2).'-'.substr($document['grant_date'], 6, 2);
								}
								$queryInsertDocument .= "('".$document['rf_id']."', '".$con->real_escape_string($document['title'])."', '".$document['appno_doc_num']."','".$appno_date."', '".$document['appno_country']."', '".$document['pgpub_doc_num']."','".$pgpub_date."', '".$document['pgpub_country']."', '".$document['grant_doc_num']."','".$grant_date."', '".$document['grant_country']."'), ";
							}
						}
					}
					
					$queryInsertDocument = substr($queryInsertDocument, 0, -2);	
					echo $queryInsertDocument."<br/>";				
					$con->query($queryInsertDocument);
					
					echo "<pre>";
					print_r($allRFIDs);
					print_r($assignmentData);
					print_r($assignmentConveyance);
					print_r($assignorsList);
					print_r($assigneesList);
					print_r($documentList);
					
					$con->query("TRUNCATE db_uspto.company_temp");
										
					$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT or_name, count(or_name) as instances FROM db_uspto.assignor WHERE assignor_and_assignee_id IS NULL GROUP BY or_name";
					$con->query($query);
					
					$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT ee_name, count(ee_name) as instances FROM db_uspto.assignee WHERE assignor_and_assignee_id = 0 GROUP BY ee_name";
					
					$con->query($query);
					
					exec('php -f /var/www/html/trash/update_assignor_assignee_id.php');
				}
				
			}
		} catch(Exception $e ) {
		
		}
	}

function add($tableName,$postValues,$con){
		$stringName ="";
		$stringValue ="";
		foreach($postValues as $key=>$value){
			$stringName .= $key.",";
			$stringValue .="'".mysqli_real_escape_string($con,stripslashes($value))."'".",";
		}
		$stringName = substr($stringName,0,-1);
		$stringValue =substr($stringValue,0,-1);
		$sql = "INSERT IGNORE INTO ".$tableName."(".$stringName.") VALUES (".$stringValue.")";		
		
		$result = $con->query($sql);
		if($result){
			return mysqli_insert_id($con);
		} else {
			return 0;
		}
	}

function removeDoubleSpace($string) {
	return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return trim(ucwords(strtolower($string)));
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