<?php 
ignore_user_abort(true);
ini_set('max_execution_time', '0');

$host = getenv('DB_HOST'); 
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$YEAR = 2000;
$variables = $argv;
$query = "";
if(count($variables) == 3) {
	$organisationID = $variables[1];
	$representativeID = $variables[2];
	if((int)$organisationID > 0) {
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				/*Check from client database */
				if($representativeID != "" && $representativeID > 0) {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE representative_id = '".$representativeID."' AND parent_id = 0";
				} else {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE parent_id = 0";
				}				
				$allRepresentatives = array();
				$rfIDUpdated = array();
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
				//echo $resultRepresentativeParentCompany->num_rows."<br/>";
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
					while($getCompanyRow = $resultRepresentativeParentCompany->fetch_object()) {						
						array_push($allRepresentatives, $getCompanyRow->representative_id);
					}
				}
				if(count($allRepresentatives) > 0) {
					/*$queryFindAllAssets = "SELECT appno_doc_num, grant_doc_num FROM ".$dbApplication.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbUSPTO.".representative_transactions WHERE organisation_id = ".$organisationID." AND representative_id IN (" .implode(',',$allRepresentatives).")) AND grant_doc_num='6084944' AND rfid_status = 0 AND date_format(appno_date, '%Y') >= ".$YEAR." GROUP BY appno_doc_num, grant_doc_num";*/
					$queryFindAllAssets = "SELECT appno_doc_num, grant_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbUSPTO.".representative_transactions WHERE organisation_id = ".$organisationID." AND representative_id IN (" .implode(',',$allRepresentatives).")) AND grant_doc_num='6231743' GROUP BY appno_doc_num, grant_doc_num";
					//echo $queryFindAllAssets;
					$resultAssets = $con->query($queryFindAllAssets);
						
					if($resultAssets && $resultAssets->num_rows > 0) {
						while($rowAsset = $resultAssets->fetch_object()) {
							$URL = ""; $type = 0;
							if($rowAsset->grant_doc_num != "") {
								$URL = "https://assignment.uspto.gov/solr/aotw/select?fl=id,displayId,reelNo,frameNo,pctNum,applNum,patNum,publNum,issueDate,publDate,filingDate,conveyanceText,patAssigneeName,patAssignorName,inventors,inventionTitle,inventionTitleFirst,applNumFirst,publNumFirst,patNumFirst,intlRegNum,intlRegNumFirst,corrName,corrAddress1,corrAddress2,corrAddress3,corrAddress4,patAssignorEarliestExDate,recordedDate,filingDateFirst,publDateFirst,issueDateFirst,intlPublDateFirst,patNumSize,applNumSize,pageCount,patAssigneeAddress1,patAssigneeAddress2,patAssigneeCity,patAssigneeState,patAssigneePostcode,patAssigneeCountryName,assignmentRecordHasImages&fq=patNum:".$rowAsset->grant_doc_num."&hl=true&lowercaseOperators=true&q=*:*&rows=500&wt=json";
								/*&sort=patAssignorEarliestExDate+desc,+recordedDate+desc*/
							} else if($rowAsset->appno_doc_num != "") {
								$type = 1;
								$URL = "https://assignment.uspto.gov/solr/aotw/select?fl=id,displayId,reelNo,frameNo,pctNum,applNum,patNum,publNum,issueDate,publDate,filingDate,conveyanceText,patAssigneeName,patAssignorName,inventors,inventionTitle,inventionTitleFirst,applNumFirst,publNumFirst,patNumFirst,intlRegNum,intlRegNumFirst,corrName,corrAddress1,corrAddress2,corrAddress3,corrAddress4,patAssignorEarliestExDate,recordedDate,filingDateFirst,publDateFirst,issueDateFirst,intlPublDateFirst,patNumSize,applNumSize,pageCount,patAssigneeAddress1,patAssigneeAddress2,patAssigneeCity,patAssigneeState,patAssigneePostcode,patAssigneeCountryName,assignmentRecordHasImages&fq=applNum:".$rowAsset->appno_doc_num."&hl=true&lowercaseOperators=true&q=*:*&rows=500&wt=json";
							} 
							echo $URL."<br/>";
							$dataUSPTO = curl($URL);
							try{
								$updated = false;
								if($dataUSPTO != "" && $dataUSPTO != null) {
									$assignmentList = json_decode($dataUSPTO,true);
									if(isset($assignmentList['response']) && isset($assignmentList['response']['docs'])) {
										if(count($assignmentList['response']['docs']) > 0) {
											/*Query to find rfIDs in database*/
											$query = "SELECT rf_id FROM ".$dbUSPTO.".documentid WHERE ";
											if($type === 1) {
												$query .= " appno_doc_num = '".$rowAsset->appno_doc_num."'";
											} else {
												$query .= " grant_doc_num = '".$rowAsset->grant_doc_num."'";
											}
											
											$query .= " GROUP BY rf_id";
											echo $query."<br/>";
											$resultQuery = $con->query($query);
											
											if( $resultQuery ) {
												
												if(count($assignmentList['response']['docs']) != $resultQuery->num_rows ) {
													$assigneesList = array();
													$assignorsList = array();
													$documentList = array();
													$assignmentData = array();
													$assignmentConveyance = array();
													foreach( $assignmentList['response']['docs'] as $doc ) {
														$reelNo = $doc['reelNo'];
														$frameNo = $doc['frameNo'];
														$queryAssignment = "SELECT rf_id FROM ".$dbUSPTO.".assignment WHERE reel_no = '".$reelNo."' AND frame_no = '".$frameNo."'";
														$resultAssignment = $con->query($queryAssignment);
														$rfID = $reelNo;
														if(strlen($frameNo) == 3){
															$rfID .= '0'.$frameNo;
														}else if(strlen($frameNo) == 2){
															$rfID .= '00'.$frameNo;
														}else if(strlen($frameNo) == 1){
															$rfID .= '000'.$frameNo;
														} else {
															$rfID .= $frameNo; 
														}
														if($resultAssignment && $resultAssignment->num_rows == 0) {
															//assignment
															
															$conveyanceText = $doc['conveyanceText'];
															$recordedDate = $doc['recordedDate'];
															$pageCount = $doc['pageCount'];
															$corrName = $doc['corrName'];
															$assignmentRecordHasImages = $doc['assignmentRecordHasImages'];
															$corrAddress1 = $doc['corrAddress1'];
															$corrAddress2 = $doc['corrAddress2'];
															$corrAddress3 = "";
															
															if(isset($doc['corrAddress3'])) {
																$corrAddress3 = $doc['corrAddress3'];
															}
															$date = new DateTime($recordedDate);
															
															//echo $reelNo.$frameNo."<br/>";
															
															array_push($assignmentData, array('rf_id' => $rfID, 'cname'=>$corrName, 'caddress_1'=>$corrAddress1, 'caddress_2'=>$corrAddress2, 'caddress_3' =>$corrAddress3, 'reel_no'=>$reelNo, 'frame_no'=>$frameNo, 'convey_text'=>$conveyanceText, 'record_dt'=>$date->format('Y-m-d'), 'last_update_dt'=>$date->format('Y-m-d'), 'page_count'=>$pageCount, 'purge_in'=>$assignmentRecordHasImages ));
														} else {
															$assignmentRow = $resultAssignment->fetch_object();
															$rfID = $assignmentRow->rf_id;
														}
														
														$queryAssignee = "SELECT * FROM ".$dbUSPTO.".assignee WHERE rf_id = ".$rfID;
														$resultAssignee = $con->query($queryAssignee);
														echo $queryAssignee."<br/>";
														if($resultAssignee && $resultAssignee->num_rows == 0) {
															$patAssigneeName = $doc['patAssigneeName'];
															$patAssigneeAddress1 = $doc['patAssigneeAddress1'];
															$patAssigneeAddress2 = $doc['patAssigneeAddress2'];
															$patAssigneeCity = $doc['patAssigneeCity'];
															$patAssigneeState = $doc['patAssigneeState'];
															$patAssigneeCountryName = $doc['patAssigneeCountryName'];
															$patAssigneePostcode = $doc['patAssigneePostcode'];
															if(count($patAssigneeName) > 0) {
																$aIncrement = 0;
																$assigneeData = array();
																foreach( $patAssigneeName as $assignee ) {
																	$assignor_and_assigneeID = 0;
																	
																	array_push($assigneeData, array('rf_id'=> $rfID,'original_name'=>$assignee, 'ee_name'=>$assignee, 'ee_address_1'=>$patAssigneeAddress1[$aIncrement], 'ee_address_2'=>$patAssigneeAddress2[$aIncrement],'ee_city'=>$patAssigneeCity[$aIncrement], 'ee_state'=>$patAssigneeState[$aIncrement], 'ee_postcode'=> $patAssigneePostcode[$aIncrement], 'ee_country'=> $patAssigneeCountryName[$aIncrement], 'assignor_and_assignee_id' => $assignor_and_assigneeID));
																	
																	$aIncrement++;
																}
																array_push($assigneesList, $assigneeData);
															}
														} else {
															$rfID = $resultAssignee->fetch_object()->rf_id;
														}
														
														$queryAssignor = "SELECT * FROM ".$dbUSPTO.".assignor WHERE rf_id = ".$rfID;
														echo $queryAssignor."<br/>";
														$resultAssignor = $con->query($queryAssignor);
														if($resultAssignor && $resultAssignor->num_rows == 0) {
															$patAssignorName = $doc['patAssignorName'];
															$exDate = $doc['patAssignorEarliestExDate'];
															if(count($patAssignorName) > 0) {
																$aIncrement = 0;
																$assignorData = array();
																foreach( $patAssignorName as $assignor ) {
																	$date = new DateTime($exDate[$aIncrement]);
																	array_push($assignorData, array('rf_id'=> $rfID, 'original_name'=>$assignor, 'or_name'=>$assignor, 'exec_dt'=>$date));
																	$aIncrement++;
																}																
																array_push($assignorsList, $assignorData);
															}
														}
														
														$queryAssignmentConveyance = "SELECT * FROM ".$dbUSPTO.".assignment_conveyance WHERE rf_id = ".$rfID;
														echo $queryAssignmentConveyance."<br/>";
														$resultAssignmentConveyance = $con->query($queryAssignmentConveyance);
														if($resultAssignmentConveyance && $resultAssignmentConveyance->num_rows == 0) {
															$conveyanceText = strtolower($doc['conveyanceText']);
															$conveyTy = "other";
															
															if(strpos($conveyanceText, "assignment")) {
																$conveyTy = "assignment";
															} else if(strpos($conveyanceText, "change of name")) {
																$conveyTy = "namechg";
															} else if(strpos($conveyanceText, "merger")) {
																$conveyTy = "merger";
															} else if(strpos($conveyanceText, "security") && !strpos($conveyanceText, "release")) {
																$conveyTy = "security";
															} else if(strpos($conveyanceText, "correct")) {
																$conveyTy = "correct";
															} else if(strpos($conveyanceText, "missing")) {
																$conveyTy = "missing";
															} else if(strpos($conveyanceText, "release")) {
																$conveyTy = "release";
															} else if(strpos($conveyanceText, "govern")) {
																$conveyTy = "govern";
															} else if(strpos($conveyanceText, "license")) {
																$conveyTy = "license";
															}
															
															array_push($assignmentConveyance, array('rf_id'=> $rfID, 'convey_ty'=>$conveyTy, 'employer_assign'=>0));
														}
														
														$queryDocumentID = "SELECT * FROM ".$dbUSPTO.".documentid WHERE rf_id = ".$rfID;
														echo $queryDocumentID."<br/>";
														$resultDocumentID = $con->query($queryDocumentID);
														if($resultDocumentID && $resultDocumentID->num_rows == 0) {
															$patNum = $doc['patNum'];
															$applNum = $doc['applNum'];
															$inventionTitle = $doc['inventionTitle'];
															$filingDate = $doc['filingDate'];
															$issueDate = $doc['issueDate'];
															$publNum = $doc['publNum'];
															$publDate = $doc['publDate'];
															$patIndex = 0;
															$documentData = array();
															foreach($patNum as $pat) {
																$application = $applNum[$patIndex];
																$applicationDate = $filingDate[$patIndex];
																$applicationCountry = 'US';
																
																$publication = $publNum[$patIndex];
																$publicationDate = $publDate[$patIndex];
																$publicationCountry = 'US';
																
																$patent = $patNum[$patIndex];
																$patentDate = $issueDate[$patIndex];
																$patentCountry = 'US';
																
																$filingdate = new DateTime($applicationDate);
																$issuedate = new DateTime($patentDate);
																
																array_push($documentData, array('rf_id'=> $rfID,'title'=>$inventionTitle[$patIndex], 'appno_doc_num'=>$application, 'appno_date'=>$filingdate->format('Y-m-d'), 'appno_country'=>$applicationCountry, 'pgpub_doc_num'=>$publication, 'pgpub_date'=>$publicationDate, 'pgpub_country'=>$publicationCountry, 'grant_doc_num'=>$patent, 'grant_date'=>$issuedate->format('Y-m-d'), 'grant_country'=>$patentCountry));
																$patIndex++;
															}
															array_push($documentList, $documentData);
														}
													}
													echo "<pre>";
													//print_r($assignmentData);
													
													//print_r($assignmentConveyance);
													//print_r($assigneesList);
													
													//print_r($assignorsList);
													
													print_r($documentList);
													
													
													die;
												} 
											}
										}
									}
								}
								/*if($updated === true) {
									$con->query("TRUNCATE ".$dbUSPTO.".company_temp");
										
									$query = "INSERT INTO ".$dbUSPTO.".company_temp(name, instances) SELECT or_name, count(or_name) as instances FROM ".$dbUSPTO.".assignor WHERE assignor_and_assignee_id IS NULL GROUP BY or_name";
									$con->query($query);
									
									$query = "INSERT INTO ".$dbUSPTO.".company_temp(name, instances) SELECT ee_name, count(ee_name) as instances FROM ".$dbUSPTO.".assignee WHERE assignor_and_assignee_id = 0 GROUP BY ee_name";
									
									$con->query($query);
									
									exec('php -f /var/www/html/trash/update_assignor_assignee_id.php');
								}*/
							} catch (Exception $e) {
								
							}
							sleep(30); //30 seconds sleep
						}
					}
				}
			}
		}
	}
}

function curl($url) {
	echo $url."<br/>";
	$ch = curl_init ();
	curl_setopt ( $ch, CURLOPT_URL, $url );
	/*curl_setopt ( $ch, CURLOPT_HEADER, array('Accept:application/xml') );*/
	curl_setopt ( $ch, CURLOPT_HEADER,false );
	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
	$dataUSPTO = curl_exec ( $ch );
	if (curl_errno ( $ch )) {	
		//echo curl_errno ( $ch );die;
		curl_close ( $ch );			
	} else {
		curl_close ( $ch );
	}
	return $dataUSPTO;
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
			$haystack .= " Co";
		} else if(strtolower($needle) == "incorporated"){
			$haystack .= " Inc";
		} else if(strtolower($needle) == "limited"){
			$haystack .= " Ltd";
		} else if(strtolower($needle) == "corporation"){
			$haystack .= " Corp";
		}
		$lp = 1;
    }
    return array(trim(ucwords(strtolower($haystack))), $lp);
}