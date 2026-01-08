<?php 


ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000);
// Include database connection (provides $con, $host, $user, $password, $dbUSPTO and ensureConnection())
require_once __DIR__ . '/connection.php';


$queryConveyType = "Select a.convey_text as text, rac.convey_ty as convey_ty FROM db_uspto.assignment as a  INNER JOIN db_uspto.representative_assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE a.convey_text <> '' AND a.convey_text IS NOT NULL GROUP BY a.convey_text, convey_ty";
				
				
//$resultAllConveyType = $con->query($queryConveyType);
$allConveyText = array();
/*
if($resultAllConveyType && $resultAllConveyType->num_rows > 0) {
	while($row = $resultAllConveyType->fetch_object()){
		array_push($allConveyText, array('text'=>$row->text, 'convey_ty'=>$row->convey_ty));
	}
}*/
$enteredData = false;
foreach(glob('./dds/*.xml') as $fileName){
    // Ensure database connection is alive for each file
    ensureConnection($con, $host, $user, $password, $dbUSPTO);
    
    $fileName = realpath($fileName);
	echo $fileName."<br/>";
    try {
        $reader = new XMLReader();
        $reader->open($fileName);

        // Advancing to the first patent-assignment node
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'patent-assignment') {
                $doc = new DOMDocument();
                $assignment = simplexml_import_dom($reader->expand($doc));
                if ($assignment === false) continue;

                // Dummy arrays if needed for compatibility with internal code logic
                $allRFIDs = array();
                $documentList = array();

                if ($enteredData === false) {
                    $enteredData = true;
                }
                
                // The loop logic follows...
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
						$assignmentData = array('rf_id' => $rfID, 'cname'=>$cname, 'caddress_1'=>$address1, 'caddress_2'=>$address2, 'caddress_3' =>$address3, 'reel_no'=>$reelNo, 'frame_no'=>$frameNo, 'convey_text'=>$coveyanceText, 'record_dt'=>$recordDate, 'last_update_dt'=>$lastUpdateDate, 'page_count'=>$pageCount, 'purge_in'=>$purgeN );

						$convey_ty = '';
						$checkConveyanceType = strtolower($coveyanceText);
						
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
						
						if(count($allConveyText) > 0 && $convey_ty == 'missing') {
							/* foreach($allConveyText as $ct) {
								if($ct['text'] == trim($checkConveyanceType)){
									$convey_ty = $ct['convey_ty'];
									break;
								}
							} */
							$queryFindConveyance = "Select a.convey_text as text, rac.convey_ty as convey_ty FROM db_uspto.assignment as a  INNER JOIN db_uspto.representative_assignment_conveyance as rac ON rac.rf_id = a.rf_id WHERE a.convey_text <> '' AND a.convey_text = '".$con->real_escape_string($eeName)."' LIMIT 1 ";
							echo $queryFindConveyance."<br/>";
							$resultFindConveyance = $con->query($queryFindConveyance);

							if($resultFindConveyance && $resultFindConveyance->num_rows > 0) {
								$rowFindConveyance = $resultFindConveyance->fetch_object();
								print_r($rowFindConveyance);
								$convey_ty = $rowFindConveyance['convey_ty'];
							}
						}
						
						
						$assignmentConveyance = array('rf_id'=> $rfID, 'convey_ty'=>$convey_ty, 'employer_assign'=>$employer_assign);
						
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
						echo "<pre>";
						print_r($assignmentData);
						print_r($assignmentConveyance);
						print_r($assigneeData);
						print_r($assignorData);
						print_r($documentData);
						
						/*
						* Assignment
						*/
						
						echo $query = "SELECT rf_id FROM db_uspto.assignment WHERE reel_no = ".$assignmentData['reel_no']." AND frame_no = ".$assignmentData['frame_no'] ;
						$recordDT  = substr($assignmentData['record_dt'], 0, 4).'-'.substr($assignmentData['record_dt'], 4, 2).'-'.substr($assignmentData['record_dt'], 6, 2);
							$lastUpdateDT  = substr($assignmentData['last_update_dt'], 0, 4).'-'.substr($assignmentData['last_update_dt'], 4, 2).'-'.substr($assignmentData['last_update_dt'], 6, 2);
						$result = $con->query($query);						
						$rfID = $assignmentData['rf_id'];
						if($result && $result->num_rows > 0) {
							$assignmentRow = $result->fetch_object();
							$rfID = $assignmentRow->rf_id;
							update(array('cname'=> $assignmentData['cname'], 'caddress_1'=> $assignmentData['caddress_1'], 'caddress_2'=> $assignmentData['caddress_2'], 'caddress_3'=> $assignmentData['caddress_3'], 'reel_no'=> $assignmentData['reel_no'], 'frame_no'=> $assignmentData['frame_no'], 'convey_text'=> $assignmentData['convey_text'], 'record_dt'=> $recordDT, 'last_update_dt'=> $lastUpdateDT), array('rf_id'=> $rfID), 'assignment', $con);

							update(array('cname'=> $assignmentData['cname'], 'caddress_1'=> $assignmentData['caddress_1'], 'caddress_2'=> $assignmentData['caddress_2'], 'caddress_3'=> $assignmentData['caddress_3']), array('rf_id'=> $rfID), 'correspondent', $con);
						} else {							
							
							$cName = $assignmentData['cname'];						
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
							
							$queryInsertAssignment = "INSERT IGNORE INTO db_uspto.assignment (rf_id, cname, caddress_1, caddress_2, caddress_3, reel_no, frame_no, convey_text, record_dt, last_update_dt, page_count, purge_in) VALUES (".$assignmentData['rf_id'].", '".$con->real_escape_string($cName)."', '".$con->real_escape_string($assignmentData['caddress_1'])."', '".$con->real_escape_string($assignmentData['caddress_2'])."', '".$con->real_escape_string($assignmentData['caddress_3'])."', '".$assignmentData['reel_no']."', '".$assignmentData['frame_no']."', '".$con->real_escape_string($assignmentData['convey_text'])."', '".$recordDT."', '".$lastUpdateDT."', '".$assignmentData['page_count']."', '".$assignmentData['purge_in']."');";
							
							$con->query($queryInsertAssignment);

							$queryInsertCorrespondent = "INSERT IGNORE INTO db_uspto.correspondent (rf_id, cname, caddress_1, caddress_2, caddress_3) VALUES (".$assignmentData['rf_id'].", '".$con->real_escape_string($cName)."', '".$con->real_escape_string($assignmentData['caddress_1'])."', '".$con->real_escape_string($assignmentData['caddress_2'])."', '".$con->real_escape_string($assignmentData['caddress_3'])."');";
							
							$con->query($queryInsertCorrespondent);
						}
						
						/*
						* Assignment Conveyance
						*/
						$queryConveyance = "SELECT rf_id FROM db_uspto.assignment_conveyance WHERE rf_id = ".$rfID;
						$resultConveyance = $con->query($queryConveyance);
						$conveyanceInsert = true;
						if($resultConveyance && $resultConveyance->num_rows > 0) {
							$conveyanceInsert = false;
						}
						
						$queryRepresentativeConveyance = "SELECT rf_id FROM db_uspto.representative_assignment_conveyance WHERE rf_id = ".$rfID;
						$resultRepresentativeConveyance = $con->query($queryRepresentativeConveyance);
						$conveyanceRepresentativeInsert = true;
						if($resultRepresentativeConveyance && $resultRepresentativeConveyance->num_rows > 0) {
							$conveyanceRepresentativeInsert = false;
						}
						
						if($conveyanceInsert === true) {
							$queryInsertAssignmentConveyance = "INSERT IGNORE INTO db_uspto.assignment_conveyance (rf_id, convey_ty, employer_assign) VALUES ('".$rfID."', '".$assignmentConveyance['convey_ty']."', '".$assignmentConveyance['employer_assign']."')";
							
							$con->query($queryInsertAssignmentConveyance);

							
						}
						
						if($conveyanceRepresentativeInsert === true) {
							$queryInsertRepresentativeAssignmentConveyance = "INSERT IGNORE INTO db_uspto.representative_assignment_conveyance (rf_id, convey_ty, employer_assign) VALUES ('".$rfID."', '".$assignmentConveyance['convey_ty']."', '".$assignmentConveyance['employer_assign']."')";
							
							$con->query($queryInsertRepresentativeAssignmentConveyance);
						}
						
						/*
						* Assignee
						*/
						if(count($assigneeData) > 0) {
							/*$queryAssignee = "SELECT rf_id, original_name, ee_name FROM db_uspto.assignee WHERE rf_id = ".$rfID;
							$resultAssignee = $con->query($queryAssignee);
							if($resultAssignee && $resultAssignee->num_rows > 0) {
								$con->query("DELETE FROM db_uspto.assignee WHERE rf_id = ".$rfID);
							}*/
							$con->query("DELETE FROM db_uspto.assignee WHERE rf_id = ".$rfID);
							$queryInsertAssignee = "INSERT IGNORE INTO db_uspto.assignee(rf_id, original_name, ee_name, ee_address_1, ee_city, ee_state, ee_postcode) VALUES ";
							
							foreach($assigneeData as $assignees){							
								$eeName = $assignees['ee_name'];						
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
								/* $queryAssignee = "SELECT rf_id, original_name, ee_name FROM db_uspto.assignee WHERE rf_id = ".$rfID." AND (ee_name = '".$con->real_escape_string($eeName)."' OR original_name = '".$con->real_escape_string($assignees['ee_name'])."') LIMIT 1";
								echo $queryAssignee."<br/>";
								$resultAssignee = $con->query($queryAssignee);
								if($resultAssignee && $resultAssignee->num_rows > 0) {
									$assigneeRow = $resultAssignee->fetch_object();
									update(array('original_name'=>$assignees['ee_name'], 'ee_name'=>$eeName, 'ee_address_1'=>$assignees['ee_address_1'], 'ee_city'=> $assignees['ee_city'], 'ee_state'=> $assignees['ee_state'], 'ee_postcode'=> $assignees['ee_postcode']), array('rf_id'=>$rfID, 'original_name'=>$assigneeRow->original_name, 'ee_name'=>$assigneeRow->ee_name), 'assignee', $con);
								} else {
									$queryInsertAssignee = "INSERT IGNORE INTO db_uspto.assignee(rf_id, original_name, ee_name, ee_address_1, ee_city, ee_state, ee_postcode) VALUES ('".$assignees['rf_id']."', '".$con->real_escape_string($assignees['ee_name'])."', '".$con->real_escape_string($eeName)."', '".$con->real_escape_string($assignees['ee_address_1'])."',  '".$con->real_escape_string($assignees['ee_city'])."', '".$con->real_escape_string($assignees['ee_state'])."', '".$con->real_escape_string($assignees['ee_postcode'])."')";
									$con->query($queryInsertAssignee);
								} */
								
								$queryInsertAssignee .= " ('".$assignees['rf_id']."', '".$con->real_escape_string($assignees['ee_name'])."', '".$con->real_escape_string($eeName)."', '".$con->real_escape_string($assignees['ee_address_1'])."',  '".$con->real_escape_string($assignees['ee_city'])."', '".$con->real_escape_string($assignees['ee_state'])."', '".$con->real_escape_string($assignees['ee_postcode'])."'), ";
								
							}
							$queryInsertAssignee = substr($queryInsertAssignee, 0, -2);
							echo $queryInsertAssignee."<br/>";
								$con->query($queryInsertAssignee);
						}
						/*
						*Assignor
						*/
						if(count($assignorData) > 0) {
							/*$queryAssignor = "SELECT rf_id, original_name, or_name FROM db_uspto.assignor WHERE rf_id = ".$rfID;
							$resultAssignor = $con->query($queryAssignor);
							if($resultAssignor && $resultAssignor->num_rows > 0) {
								$con->query("DELETE FROM db_uspto.assignor WHERE rf_id = ".$rfID);
							}*/
							$con->query("DELETE FROM db_uspto.assignor WHERE rf_id = ".$rfID);
							$queryInsertAssignor = "INSERT IGNORE INTO db_uspto.assignor(rf_id, original_name, or_name, exec_dt) VALUES ";
							foreach($assignorData as $assignors){
								$exec_dt  = substr($assignors['exec_dt'], 0, 4).'-'.substr($assignors['exec_dt'], 4, 2).'-'.substr($assignors['exec_dt'], 6, 2);
								$orName = $assignors['or_name'];						
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
								/* $queryAssignor = "SELECT rf_id, original_name, or_name FROM db_uspto.assignor WHERE rf_id = ".$rfID." AND (or_name = '".$con->real_escape_string($orName)."' OR original_name = '".$con->real_escape_string($assignors['or_name'])."') LIMIT 1";
								echo $queryAssignor."<br/>";
								$resultAssignor = $con->query($queryAssignor);
								if($resultAssignor && $resultAssignor->num_rows > 0) {
									$assignorRow = $resultAssignor->fetch_object();
									update(array('original_name'=>$assignors['or_name'], 'or_name'=>$orName, 'exec_dt'=>$exec_dt), array('rf_id'=>$rfID, 'original_name'=>$assignorRow->original_name, 'or_name'=>$assignorRow->or_name), 'assignor', $con);
								} else {
									$queryInsertAssignor = "INSERT IGNORE INTO db_uspto.assignor(rf_id, original_name, or_name, exec_dt) VALUES ('".$assignors['rf_id']."', '".$con->real_escape_string($assignors['or_name'])."', '".$con->real_escape_string($orName)."', '".$exec_dt."')";
									$con->query($queryInsertAssignor);
								} */
								
								$queryInsertAssignor .= " ('".$assignors['rf_id']."', '".$con->real_escape_string($assignors['or_name'])."', '".$con->real_escape_string($orName)."', '".$exec_dt."'), ";
								
								
							}
							$queryInsertAssignor = substr($queryInsertAssignor, 0, -2);
							echo $queryInsertAssignor."<br/>";
							$con->query($queryInsertAssignor);
						}

						/**
						 * Assignment Arrow
						 */
						$con->query("DELETE FROM db_uspto.assignment_arrows WHERE rf_id = ".$rfID);
						$queryArrow = "INSERT IGNORE INTO assignment_arrows(rf_id, arrows) SELECT rf_id, ao * ae FROM (
							SELECT rf_id, (SELECT count(*) as countAssignor FROM assignor AS aor Where aor.rf_id = ass.rf_id) AS ao, 
							(SELECT count(*) as countAssignee FROM assignee AS aee Where aee.rf_id = ass.rf_id) AS ae 
							FROM assignment AS ass where rf_id = ".$rfID.") AS temp";
						
						$con->query($queryArrow);


						/*
						* Documentid
						*/
						if(count($documentData) > 0) {
							foreach($documentData as $documents){
								$queryDocument = "";
								if($documents['grant_doc_num'] != '' && $documents['grant_doc_num'] != null) {
									$queryDocument = "SELECT rf_id, grant_doc_num, appno_doc_num FROM db_uspto.documentid WHERE rf_id = ".$rfID." AND ";
									if($documents['appno_doc_num'] != '' && $documents['appno_doc_num'] != null) {
										$queryDocument .= " grant_doc_num = '".$con->real_escape_string($documents['grant_doc_num'])."' AND appno_doc_num = '".$con->real_escape_string($documents['appno_doc_num'])."' LIMIT 1";
									} else {
										$queryDocument .= " grant_doc_num = '".$con->real_escape_string($documents['grant_doc_num'])."' AND appno_doc_num = '' LIMIT 1";
									}
								} else if($documents['appno_doc_num'] != '' && $documents['appno_doc_num'] != null) {
									$queryDocument .= "SELECT rf_id, grant_doc_num, appno_doc_num FROM db_uspto.documentid WHERE rf_id = ".$rfID." AND  grant_doc_num = '' AND appno_doc_num = '".$con->real_escape_string($documents['appno_doc_num'])."' LIMIT 1";
								} 
								$appno_date = null;
								if($documents['appno_date'] != '') {
									$appno_date  = substr($documents['appno_date'], 0, 4).'-'.substr($documents['appno_date'], 4, 2).'-'.substr($documents['appno_date'], 6, 2);
								}
								
								$pgpub_date = null;
								if($documents['pgpub_date'] != '') {
									$pgpub_date  = substr($documents['pgpub_date'], 0, 4).'-'.substr($documents['pgpub_date'], 4, 2).'-'.substr($documents['pgpub_date'], 6, 2);
								}
								
								$grant_date = null;
								if($documents['grant_date'] != '') {
									$grant_date  = substr($documents['grant_date'], 0, 4).'-'.substr($documents['grant_date'], 4, 2).'-'.substr($documents['grant_date'], 6, 2);
								}
								
								if($queryDocument == '') {
									$queryInsertDocument = "INSERT IGNORE INTO db_uspto.documentid(rf_id, title, appno_doc_num, appno_date, appno_country, pgpub_doc_num, pgpub_date, pgpub_country, grant_doc_num, grant_date, grant_country) VALUES ('".$documents['rf_id']."', '".$con->real_escape_string($documents['title'])."', '".$documents['appno_doc_num']."','".$appno_date."', '".$documents['appno_country']."', '".$documents['pgpub_doc_num']."','".$pgpub_date."', '".$documents['pgpub_country']."', '".$documents['grant_doc_num']."','".$grant_date."', '".$documents['grant_country']."')";
									$con->query($queryInsertDocument);	
								} else { 
									$resultDocument = $con->query($queryDocument);
									if($resultDocument && $resultDocument->num_rows > 0) {
										$documentRow = $resultDocument->fetch_object();
										update(array('title'=>$con->real_escape_string($documents['title']), 'appno_doc_num'=>$documents['appno_doc_num'], 'appno_date'=>$appno_date, 'appno_country'=>$documents['appno_country'], 'pgpub_doc_num'=>$documents['pgpub_doc_num'], 'pgpub_date'=>$pgpub_date, 'pgpub_country'=>$documents['pgpub_country'], 'grant_doc_num'=>$documents['grant_doc_num'], 'grant_date'=>$grant_date, 'grant_country'=>$documents['grant_country']), array('rf_id'=>$rfID, 'grant_doc_num'=>$documentRow->grant_doc_num, 'appno_doc_num'=>$documentRow->appno_doc_num), 'documentid', $con);
									} else {									
										$queryInsertDocument = "INSERT IGNORE INTO db_uspto.documentid(rf_id, title, appno_doc_num, appno_date, appno_country, pgpub_doc_num, pgpub_date, pgpub_country, grant_doc_num, grant_date, grant_country) VALUES ('".$documents['rf_id']."', '".$con->real_escape_string($documents['title'])."', '".$documents['appno_doc_num']."','".$appno_date."', '".$documents['appno_country']."', '".$documents['pgpub_doc_num']."','".$pgpub_date."', '".$documents['pgpub_country']."', '".$documents['grant_doc_num']."','".$grant_date."', '".$documents['grant_country']."')";
										$con->query($queryInsertDocument);									
									}
								}
							}
						}	 
					}
            }

			$reader->close();
			unlink($fileName);
		} catch(Exception $e ) {
			error_log( "Errors in update record daily XML!" );
			error_log( $fileName );
			
			$f = fopen("/var/www/html/trash/daily_file.log", "w+");
			fwrite($f, $fileName);
		}

}
if($enteredData === true) {  
	$con->query("TRUNCATE db_uspto.company_temp");
	$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT or_name, count(or_name) as instances FROM db_uspto.assignor WHERE assignor_and_assignee_id IS NULL GROUP BY or_name";
	$con->query($query);
	
	$query = "INSERT INTO db_uspto.company_temp(name, instances) SELECT ee_name, count(ee_name) as instances FROM db_uspto.assignee WHERE assignor_and_assignee_id = 0 GROUP BY ee_name";
	
	$con->query($query);
	
	exec('php -f /var/www/html/trash/update_assignor_assignee_id.php');
	exec('php -f /var/www/html/trash/update_lawfirm.php'); 

	/*exec('php -f /var/www/html/trash/update_all_accounts.php');*/
	//exec('php -f /var/www/html/trash/download_pdf_files_yearly.php "'.date('Y').'"');
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

function update($postValues, $where, $tableName, $con){
	echo "=================TABLE==========================<br/>";
	//print_r($postValues);
	//print_r($where);
	print_r($tableName);
	$stringName ="";
	foreach($postValues as $key=>$value){
		$stringName .=$key."='".mysqli_real_escape_string($con,$value)."',";
	}
	$stringName = substr($stringName,0,-1);
	
	$condition = "";
	foreach($where as $key=>$value){
		$condition .=$key."='".mysqli_real_escape_string($con,$value)."' AND ";
	}
	$condition = substr($condition, 0, -4);
	
	$sql = "UPDATE ".$tableName." SET ".$stringName." WHERE ".$condition;	
	echo $sql."<br/>";
	$result = $con->query($sql);
	if($result){
		echo "AFFECTED ROWS : ".$con->affected_rows."<br/>";
	} else {
		echo "AFFECTED ROWS : 0<br/>";
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