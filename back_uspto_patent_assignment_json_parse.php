<?php
$overAllArray = array();
$db = array();
ini_set('max_execution_time', 0);
$db['default']['hostname'] = '167.172.195.92';
$db['default']['username'] = 'patent_user';
$db['default']['password'] = 'P@t3nt@u5r';
$db['default']['database'] = 'db_patentrack';
ini_set('max_execution_time', 0);
ignore_user_abort(true);
ini_set('xdebug.max_nesting_level', 1000);
ini_set("memory_limit","256M");
$patentData = "";
$con = mysqli_connect($db['default']['hostname'],$db['default']['username'],$db['default']['password'],$db['default']['database']);
$fromJOBID = 0;
if((int)mysqli_errno($con)==0){
	mysqli_set_charset($con, 'utf8'); // <- add this too
	mysqli_query($con, "SET NAMES 'utf8';");
	mysqli_query($con, "SET CHARACTER SET 'utf8';");
	mysqli_query($con, "SET COLLATION_CONNECTION = 'utf8_unicode_ci';");
	if(isset($_REQUEST['p']) && $_REQUEST['p'] != ''){
		$patentData = $_REQUEST['p'];
	} else {
		$query = "SELECT j.patent_id, p.number,j.id FROM jobs as j INNER JOIN patents as p ON p.id = j.patent_id WHERE j.status = 0 ORDER BY j.id DESC limit 1";
		$resultQuery = $con->query($query);
		if($resultQuery && mysqli_num_rows($resultQuery) > 0){
			$row = mysqli_fetch_object($resultQuery);
			$patentData = $row->number;
			$fromJOBID = $row->id;
			$con->query("UPDATE jobs SET status = 1 WHERE id = ".$fromJOBID);
		}
	}
	if($patentData!=''){
		switch(strlen($patentData)){
			case 9:
				$patentData =substr($patentData,2);
			break;
			case 11:
			case 13:
				$patentData =substr($patentData,2);
				$patentData =substr($patentData,0,-2);
			break;
			default:
				$patentData = $patentData;
			break;
		}
		try{
			newAssignmentNumber($patentData,$con);
			if($fromJOBID > 0){
				$con->query("DELETE FROM jobs WHERE id = ".$fromJOBID);
			}
		}catch(Exception $e){
			echo $e;
		}
		
	}
}
function getPDFFile($assList){
	$fileName = 'assignment-pat-'.$assList['reelNo'].'-'.$assList['frameNo'].'.pdf';
	if(file_exists('/var/www/html/PatenTrack/resources/shared/data/'.$fileName)===false){
		try{
			$content = file_get_contents('http://legacy-assignments.uspto.gov/assignments/'.$fileName);
			if($content!=""){
				$handle = fopen('/var/www/html/PatenTrack/resources/shared/data/'.$fileName,'w+');
				try{
					fwrite($handle, $content);
				}catch(Exception $e){
					
				}
				fclose($handle);
			} else {
				$fileName = "";
			}
		}catch(Exception $e) {
			$fileName = 'http://legacy-assignments.uspto.gov/assignments/'.$fileName;
		}		
	}
	return $fileName;
}
function formatText($text) {
	return ucfirst(strtolower(strtoupper(trim($text))));
}
function getName($getPatentNumber,$originalName,$con) {
	$queryFindName = "SELECT modified FROM lead_assignment_names WHERE patent_number='".$getPatentNumber."' AND original='".mysqli_real_escape_string($con,$originalName)."' LIMIT 1";
	$queryNameRes =  $con->query($queryFindName);
	if($queryNameRes && $queryNameRes->num_rows > 0 ) {
		$row = $queryNameRes->fetch_array(MYSQLI_ASSOC);
		if($row['modified'] != "") {
			$originalName = $row['modified'];
		}		
	} 
	return $originalName;
}
/*function checkConnectionTo($getPatentNumber,$originalName,$assignmentNo,$con){
	$connectionTo = 0;
	$queryFindConnection = "SELECT connection_to FROM lead_assignment_headings as ah WHERE ah.patent_number='".$getPatentNumber."' AND ah.original='".mysqli_real_escape_string($con,$originalName)."' AND ah.assignment_no=".$assignmentNo." LIMIT 1";
	if($queryNameRes && $queryNameRes->num_rows > 0 ) {
		$row = $queryNameRes->fetch_array(MYSQLI_ASSOC);
		if($row->connection_to > 0) {
			$connectionTo = $row->connection_to;
		}
	}
	return $connectionTo;
}*/
function getDescription($getPatentNumber,$originalName,$assignmentNo,$con) {
	$update = false;
	$boxDefinitionID = 0;
	$lineType = 0;
	/*$queryFindName = "SELECT ahl.name,ahl.id,ah.modified as box_type,ah.original_text as lineType FROM lead_assignment_headings as ah INNER JOIN lead_assignment_heading_list as ahl ON ahl.id = ah.modified WHERE ah.patent_number='".$getPatentNumber."' AND ah.original='".mysqli_real_escape_string($con,$originalName)."' AND ah.assignment_no=".$assignmentNo." LIMIT 1";*/
	$queryFindName = "SELECT ahl.name,ahl.id,ab.id as box_type,ah.original_text as lineType FROM lead_assignment_headings as ah INNER JOIN lead_assignment_heading_list as ahl ON ahl.id = ah.original_text LEFT JOIN lead_assignment_box as ab ON ab.id = ah.modified WHERE ah.patent_number='".$getPatentNumber."' AND ah.original='".mysqli_real_escape_string($con,$originalName)."' AND ah.assignment_no=".$assignmentNo." LIMIT 1";
	echo "queryDescriptionOne: ".$queryFindName."<br/>";
	$queryNameRes =  $con->query($queryFindName);
	if($queryNameRes && $queryNameRes->num_rows > 0 ) {
		$row = $queryNameRes->fetch_array(MYSQLI_ASSOC);
		$lineType = $row['lineType'];
		if($row['box_type'] != "" && (int)$row['box_type'] > 0) {
			echo "BFound<br/>";
			$originalName = $row['name'];
			$boxDefinitionID = $row['box_type'];
			$update = true;
		}	
	}
	if($update === false){
		$queryCheckFreeText = "SELECT ahl.name,ahl.id,fal.box_type,fal.original_text as lineType FROM lead_free_assignment_list as fal INNER JOIN lead_assignment_heading_list as ahl ON ahl.id = fal.original_text WHERE fal.free_text = '".mysqli_real_escape_string($con,trim($originalName))."'  LIMIT 1";
		echo "queryDescriptionTwo: ".$queryCheckFreeText."<br/>";
		$queryNameRes =  $con->query($queryCheckFreeText);
		if($queryNameRes && $queryNameRes->num_rows > 0 ) {
			$row = $queryNameRes->fetch_array(MYSQLI_ASSOC);
			$lineType = $row['lineType'];
			if($row['name'] != "" && (int)$row['box_type'] > 0) {
				echo "BFound1<br/>";
				$originalName = $row['name'];
				$boxDefinitionID = $row['box_type'];
				$update = true;
			} else {
				$originalName = "Ownership";
			}
		} else {
			$originalName = "Ownership";
		}
	}
	return array('text'=>$originalName,'update'=>$update,'box_type'=>$boxDefinitionID,'line_type'=>$lineType);
}
function getAllAssignmentType($con){
	$list = array();
	$query = "SELECT * FROM lead_assignment_heading_list";
	$queryNameRes =  $con->query($query);
	if($queryNameRes && $queryNameRes->num_rows > 0 ) {
		while($row = $queryNameRes->fetch_array(MYSQLI_ASSOC)){
			array_push($list,$row);
		}
	}
	return $list;
}

function findBoxType($type,$con){
	$boxID = 0;
	$query = "SELECT id FROM lead_assignment_box WHERE type = '".$type."'";
	//echo "BOXID: ".$query."<br/>";
	$queryNameRes =  $con->query($query);
	if($queryNameRes && $queryNameRes->num_rows > 0 ) {
		$row = $queryNameRes->fetch_array(MYSQLI_ASSOC);
		$boxID = $row['id'];
	}
	return $boxID;
}

function newAssignmentNumber($getPatentNumber,$con){
	
	/*$url = "https://assignment-api.uspto.gov/patent/advancedSearch?sort=ExecutionDate%20asc&patentNumber=".$getPatentNumber."&rows=500";*/
	$url = "https://assignment.uspto.gov/solr/aotw/select?fl=id,displayId,reelNo,frameNo,pctNum,applNum,patNum,publNum,issueDate,publDate,filingDate,conveyanceText,patAssigneeName,patAssignorName,inventors,inventionTitle,inventionTitleFirst,applNumFirst,publNumFirst,patNumFirst,intlRegNum,intlRegNumFirst,corrName,corrAddress1,corrAddress2,corrAddress3,corrAddress4,patAssignorEarliestExDate,recordedDate,filingDateFirst,publDateFirst,issueDateFirst,intlPublDateFirst,patNumSize,applNumSize,pageCount,patAssigneeAddress1,patAssigneeAddress2,patAssigneeCity,patAssigneeState,patAssigneePostcode,patAssigneeCountryName,assignmentRecordHasImages&fq=patNum:".$getPatentNumber."&hl=true&lowercaseOperators=true&q=*:*&rows=500&sort=patAssignorEarliestExDate+desc,+recordedDate+desc&wt=json";
	$assignmentData = array();
	$assigmentIllustrationData = array('inventors'=> array(), 'list'=>array());
	$ch = curl_init ();
	curl_setopt ( $ch, CURLOPT_URL, $url );
	/*curl_setopt ( $ch, CURLOPT_HEADER, array('Accept:application/xml') );*/
	curl_setopt ( $ch, CURLOPT_HEADER,false );
	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
	$dataUSPTO= curl_exec ( $ch );
	if (curl_errno ( $ch )) {			
		curl_close ( $ch );			
	} else {
		curl_close ( $ch );
		try{
			if($dataUSPTO != "" && $dataUSPTO != null) {
				$fileName = $getPatentNumber.'.json';
				$handle = fopen('/var/www/html/PatenTrack/resources/shared/data/'.$fileName,'w+');
				try{
					fwrite($handle, $dataUSPTO);
				}catch(Exception $e){
					
				}
				fclose($handle);
				$assignmentList = json_decode($dataUSPTO,true);
				$allBoxesList = array();
				$addedInventor = array(); 
				if(isset($assignmentList['response']) && isset($assignmentList['response']['docs'])) {
					if(count($assignmentList['response']['docs']) > 0) {
						
						$queryAssigmentIllustration = "DELETE FROM lead_patent_assignment WHERE patent_number='".$getPatentNumber."'";
						$con->query($queryAssigmentIllustration);
						$queryAssigmentIllustration = "DELETE FROM lead_patent_assigment_relation WHERE patent_number='".$getPatentNumber."'";
						$con->query($queryAssigmentIllustration);
						$insertAllNames = array();
						$inventorNameList = array();
						$insertAllHeading = array();
						/*Assignees*/
						$counter = 1;
						$inventorData = array('list'=>array(),'filling_date'=>'','recorded_date'=>'');
						$addedInventors = false;
						$inventorBoxID = findBoxType("Inventor",$con);
						$thirdPartyBoxID = findBoxType("3rdParties",$con);
						//$allAssignmentTypes = getAllAssignmentType($con);
						for($a = count($assignmentList['response']['docs'])-1;$a >= 0; $a--) {
							echo "A:".$a."<br/>";
							echo "Counter:".$counter."<br/>";
							
							$docs = $assignmentList['response']['docs'][$a];
							$frameNo = $docs['frameNo'];
							$reelNo = $docs['reelNo'];
							echo "FRAME:".$frameNo."@@REEL:".$reelNo ."<br/>";
							$recorded = substr($docs['recordedDate'],0,10);
							$originalRecorded = substr($docs['recordedDate'],0,10);
							$executedDate = substr($docs['patAssignorEarliestExDate'],0,10);
							$originalExecutedDate = substr($docs['patAssignorEarliestExDate'],0,10);
							$assigneeList = $docs['patAssigneeName'];
							$assignorList = $docs['patAssignorName'];
							$conveyanceText = $docs['conveyanceText'];
							$findText = getDescription($getPatentNumber,trim($conveyanceText),$counter,$con);
							echo "<pre>";
							print_r($findText);
							echo "</pre>";
							$description = $findText['text'];
							$boxType = $findText['box_type'];
							$lineType = $findText['line_type'];
							$fileName = getPDFFile($docs);
							if($fileName!="" && strpos($fileName, "http://legacy") === false){
								$fileName = "http://167.172.195.92/resources/shared/data/".$fileName;
							}		
							if($a === count($assignmentList['response']['docs'])-1) {
								$patentNumberList = $docs['patNum'];
								$patentIndex = array_search($getPatentNumber,$patentNumberList);
								if($patentIndex >= 0) {
									$inventorsAllList = $docs['inventors'];
									$filingDateList = $docs['filingDate'];
									$recordedDateList = $docs['publDate'];
									$allInventors = explode(',',$inventorsAllList[$patentIndex]);
									$inventorData['list'] = $allInventors;
									$inventorData['filling_date'] = $filingDateList[$patentIndex];
									$inventorData['recorded_date'] = $recordedDateList[$patentIndex];
									if($inventorData['recorded_date'] == "0001-01-01T00:00:00Z"){
										$inventorData['recorded_date'] = "0000-00-00T00:00:00Z";
									}
									if(count($inventorData['list']) > 0) {
										foreach($inventorData['list'] as $inventor) {
											$inventor = getName($getPatentNumber,trim($inventor),$con);
											$inventor = formatText($inventor);
											if(!in_array($inventor,$insertAllNames)) {
												array_push($insertAllNames,$inventor);
												array_push($inventorNameList,$inventor);
											}
										}
									}
								}
								foreach($inventorData['list'] as $inventor) {
									$inventor = getName($getPatentNumber,trim($inventor),$con);
									$inventor = formatText($inventor);
									$inventorExecutedDate = substr($inventorData['filling_date'],0,10);
									$inventorRecorded = substr($inventorData['recorded_date'],0,10);
									array_push($addedInventor,$inventor);
									$queryInsertAssignment = "INSERT INTO lead_patent_assignment(patent_number,name,description,execution_date,recorded,type,reel_no,frame_no,document_file,box_type) VALUES ('".$getPatentNumber."','".mysqli_real_escape_string($con,$inventor)."','".mysqli_real_escape_string($con,$description)."','".$inventorExecutedDate."','".$inventorRecorded."',0,'".$docs['reelNo']."','".$docs['frameNo']."','".$fileName."',".$inventorBoxID.")";
									echo "QueryAssignmentInventor: ".$queryInsertAssignment."<br/>";
									$con->query($queryInsertAssignment);
								}
							}							
							echo "Counter:".$counter."COUNTER:".$conveyanceText."DESCRIPTION:".$description."BOXID:".$boxType."<br/>";
							if(strpos(strtolower($description), "release") === false) {
								echo "1<br/>";
								foreach($assigneeList as $assignee) {
									$executedDate = $originalExecutedDate;
									$recorded = $originalRecorded;
									$assignee = getName($getPatentNumber,trim($assignee),$con);
									$assignee = formatText($assignee);
									$assigneeID = 0;
									$creator = 0;
									/*$connectionTo = checkConnectionTo($getPatentNumber,trim($conveyanceText),$counter,$con);
									if($connectionTo > 0)*/
									$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE type IN (0,1) AND patent_number='".$getPatentNumber."' AND name = '".mysqli_real_escape_string($con,$assignee)."' order by id ASC LIMIT 1";
									echo "BOB:".$queryCheck."<br/>";
									$queryResult =  $con->query($queryCheck);
									if($queryResult && mysqli_num_rows($queryResult) > 0) {
										$row = mysqli_fetch_array($queryResult);
										$assigneeID = $row['id'];
										/*if(strpos(strtolower($description),"Release") ===false) {
											if(strpos(strtolower($row['description']),"license") !== false || strpos(strtolower($row['description']),"security") !== false || strpos(strtolower($row['description']),"expiration") !== false) {
												$assigneeID = 0;
											}
										}*/
									}
									if($assigneeID === 0) {
										$queryInsertAssignment = "INSERT INTO lead_patent_assignment(patent_number,name,description,execution_date,recorded,type,reel_no,frame_no,document_file,assignment_no,box_type) VALUES ('".$getPatentNumber."','".mysqli_real_escape_string($con,$assignee)."','".mysqli_real_escape_string($con,$description)."','".$executedDate."','".$recorded."',1,'".$docs['reelNo']."','".$docs['frameNo']."','".$fileName."',".$counter.",".$boxType.")";
										echo "QueryAssignment1: ".$queryInsertAssignment."<br/>";
										$con->query($queryInsertAssignment);
										$assigneeID = mysqli_insert_id($con); 
										$creator = $assigneeID;
									}
									if($assigneeID > 0) {	
										$cType = 2;
										$executionDate = substr($docs['patAssignorEarliestExDate'],0,10);
										$as1 = 0;
										foreach($assignorList as $assignor) {	
											$assignor = getName($getPatentNumber,trim($assignor),$con);
											$assignor = formatText($assignor);
											$startCreatorID = 0;
											if(count($inventorData['list']) > 0) {
												if(in_array($assignor,$addedInventor) === false) {
													foreach($inventorData['list'] as $inventor) {
														$inventor = getName($getPatentNumber,trim($inventor),$con);
														$inventor = formatText($inventor);
														$testAssignor = "";
														if(strpos($assignor,",") !== false){
															$explodeAssignor = explode(",",$assignor);
															$testAssignor = trim($explodeAssignor[1])." ".trim($explodeAssignor[0]);
														}
														if($testAssignor == "") {
															$testAssignor = $assignor;
														}
														if(strtolower($testAssignor) == strtolower($inventor)) {
															$cType = 0;
															$assignor = $inventor;
															$executedDate = substr($inventorData['filling_date'],0,10);
															$recorded = substr($inventorData['recorded_date'],0,10);
															array_push($addedInventor,$inventor);
															break;
														}
													}
												}
											}
											$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE  patent_number='".$getPatentNumber."' AND name = '".mysqli_real_escape_string($con,$assignor)."' AND type IN(0,1) order by id ASC ";
											$assignorID = 0;
											
											$queryResult =  $con->query($queryCheck);
											if($queryResult && mysqli_num_rows($queryResult) > 0) {
												if(mysqli_num_rows($queryResult) == 1) {
													$row = mysqli_fetch_array($queryResult);
													$assignorID = $row['id'];
												} else {
													while($row = mysqli_fetch_array($queryResult)){
														if(strpos(strtolower($row['description']),"Ownership") !==false) {
															$assignorID = $row['id'];
															break;
														}
													}
												}												
											}  else {
												$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE  patent_number='".$getPatentNumber."' AND name = '".mysqli_real_escape_string($con,$assignor)."' AND type = 2 order by id ASC LIMIT 1";
												$queryResult =  $con->query($queryCheck);
												if($queryResult && mysqli_num_rows($queryResult) > 0) {
													$row = mysqli_fetch_array($queryResult);
													$assignorID = $row['id'];
												}
											}
											if($assignorID == 0){
												if($cType == 2) {
													$executedDate = '0000-00-00';
													$recorded = '0000-00-00';
												}
												if($cType == 2){
													$boxType = $thirdPartyBoxID;
												}
												$queryInsertAssignment = "INSERT INTO lead_patent_assignment(patent_number,name,description,execution_date,recorded,type,reel_no,frame_no,document_file,assignment_no,box_type) VALUES ('".$getPatentNumber."','".mysqli_real_escape_string($con,$assignor)."','".mysqli_real_escape_string($con,$description)."','".$executedDate."','".$recorded."','".$cType."','".$docs['reelNo']."','".$docs['frameNo']."','".$fileName."',".$counter.",".$boxType.")";
												echo "QueryAssignment2: ".$queryInsertAssignment."<br/>";
												$con->query($queryInsertAssignment);
												$assignorID = mysqli_insert_id($con); 
												$startCreatorID = $assignorID;
											}
												
											
											if($assignorID > 0) {
												$connectionType = 1;
												$insertBulkRelation ="INSERT INTO lead_patent_assigment_relation(patent_number,parent_id,child_id,connection_type,frame,reel,description,date,assignment_no,line_type,creator_id,start_creator_id) VALUES ('".$getPatentNumber."',".$assignorID.",".$assigneeID.",".$connectionType.",'".$docs['frameNo']."','".$docs['reelNo']."','".$description."','".substr($docs['patAssignorEarliestExDate'],0,10)."','".$counter."',".$lineType.",".$creator.",".$startCreatorID.") ";
												echo "QueryINSERTRELATION: ".$insertBulkRelation."<br/>";
												$con->query($insertBulkRelation);
												if(!in_array($assignee,$insertAllNames)) {
													array_push($insertAllNames,$assignee);
												}
												if(!in_array($assignor,$insertAllNames)) {
													array_push($insertAllNames,$assignor);
												}
												/*array_push($insertAllHeading,array('name'=>strtoupper(strtolower($description)),'assignment_no'=>$counter));*/
												/*if(!in_array(strtoupper(strtolower($description)),$insertAllHeading)) {
													array_push($insertAllHeading,strtoupper(strtolower($description)));
												}*/
											}
											echo "CREATOR:".$startCreatorID."<br/>";
											if($startCreatorID > 0){
												$startCreatorID = 0;
												echo "CREATOR:".$startCreatorID."<br/>";
											}
											if($as1 == 0 && $creator > 0){
												$creator = 0;
											}
											$as1++;
										}
									}
								}								
							} else if(strpos(strtolower($description), "release") !== false) {
								echo "2<br/>";
								//echo "<pre>";
								//print_r($docs);
								foreach($assigneeList as $assignee) {
									$assignee = getName($getPatentNumber,trim($assignee),$con);
									$assignee = formatText($assignee);
									$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE patent_number='".$getPatentNumber."' AND name = '".mysqli_real_escape_string($con,$assignee)."'    AND type IN(0,1) order by id ASC";
									//echo $queryCheck."<br/>";
									$assigneeID = 0;
									$creator = 0;
									$queryResult =  $con->query($queryCheck);
									if($queryResult && mysqli_num_rows($queryResult) > 0) {
										if(mysqli_num_rows($queryResult) == 1) {
											$row = mysqli_fetch_array($queryResult);
											$assigneeID = $row['id'];
										} else {
											while($row = mysqli_fetch_array($queryResult)){
												if(strpos(strtolower($row['description']),"Ownership") !==false) {
													$assigneeID = $row['id'];
													break;
												}
											}
										}
									} else {
										$queryCheck = "SELECT id FROM lead_patent_assignment WHERE patent_number='".$getPatentNumber."' AND name = '".mysqli_real_escape_string($con,$assignee)."' AND type = 2 order by id ASC LIMIT 1";
										$queryResult =  $con->query($queryCheck);
										if($queryResult && mysqli_num_rows($queryResult) > 0) {
											$row = mysqli_fetch_array($queryResult);
											$assigneeID = $row['id'];
										}
									}									
									if($assigneeID == 0) {
										$queryInsertAssignment = "INSERT INTO lead_patent_assignment(patent_number,name,description,execution_date,recorded,type,reel_no,frame_no,document_file,assignment_no,box_type) VALUES ('".$getPatentNumber."','".mysqli_real_escape_string($con,$assignee)."','".mysqli_real_escape_string($con,$description)."','".$executedDate."','".$recorded."',2,'".$docs['reelNo']."','".$docs['frameNo']."','".$fileName."',".$counter.",".$thirdPartyBoxID.")";
										echo "QueryReleaseAssignment2: ".$queryInsertAssignment."<br/>";
										$con->query($queryInsertAssignment);
										$assigneeID = mysqli_insert_id($con); 
										$creator = $assigneeID;
										echo "NEW<br/>";
									}
									//echo $assigneeID."<br/>";
									if($assigneeID > 0) { 
										$as = 0;
										foreach($assignorList as $assignor) {
											$assignor = getName($getPatentNumber,trim($assignor),$con);
											$assignor = formatText($assignor);
											$startCreatorID  = 0;
											$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE  patent_number='".$getPatentNumber."' AND name = '".mysqli_real_escape_string($con,$assignor)."' AND type IN(0,1) order by id ASC LIMIT 1";
											$assignorID = 0;
											$queryResult =  $con->query($queryCheck);
											if($queryResult && mysqli_num_rows($queryResult) > 0) {
												$row = mysqli_fetch_array($queryResult);
												$assignorID = $row['id'];
											}  else {
												$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE  patent_number='".$getPatentNumber."' AND name = '".mysqli_real_escape_string($con,$assignor)."' AND type = 2 order by id ASC LIMIT 1";
												$queryResult =  $con->query($queryCheck);
												if($queryResult && mysqli_num_rows($queryResult) > 0) {
													$row = mysqli_fetch_array($queryResult);
													$assignorID = $row['id'];
												}
											}
											if($assignorID == 0) {
												$queryInsertAssignment = "INSERT INTO lead_patent_assignment(patent_number,name,description,execution_date,recorded,type,reel_no,frame_no,document_file,assignment_no,box_type) VALUES ('".$getPatentNumber."','".mysqli_real_escape_string($con,$assignor)."','".mysqli_real_escape_string($con,$description)."','".$executedDate."','".$recorded."',1,'".$docs['reelNo']."','".$docs['frameNo']."','".$fileName."',".$counter.",".$boxType.")";
												echo "QUERYNEWASSIGNOR2 ".$queryInsertAssignment."<br/>";
												$con->query($queryInsertAssignment);
												$assignorID = mysqli_insert_id($con); 
												$startCreatorID = $assignorID;
												//echo "NEW<br/>";
											}
											//echo $assignorID ."<br/>";
											if($assignorID > 0) {
												$connectionType = 2;
												$insertBulkRelation ="INSERT INTO lead_patent_assigment_relation(patent_number,parent_id,child_id,connection_type,frame,reel,description,date,assignment_no,line_type,creator_id,start_creator_id) VALUES ('".$getPatentNumber."',".$assignorID.",".$assigneeID.",".$connectionType.",'".$docs['frameNo']."','".$docs['reelNo']."','".$description."','".substr($docs['patAssignorEarliestExDate'],0,10)."','".$counter."',".$lineType.",".$creator.",".$startCreatorID.") ";
												echo "QueryINSERTRELATION2: ".$insertBulkRelation."<br/>";
												$con->query($insertBulkRelation);
												if(!in_array($assignee,$insertAllNames)) {
													array_push($insertAllNames,$assignee);
												}
												if(!in_array($assignor,$insertAllNames)) {
													array_push($insertAllNames,$assignor);
												}
												/*if(!in_array(strtoupper(strtolower($description)),$insertAllHeading)) {
													array_push($insertAllHeading,strtoupper(strtolower($description)));
												}*/
											}
											echo "CREATOR:".$startCreatorID."<br/>";
											if($startCreatorID > 0){
												$startCreatorID = 0;
												echo "CREATOR:".$startCreatorID."<br/>";
											}
											if($as == 0 && $creator > 0){
												$creator = 0;
											}
											$as++;
										}
									}
								}								
							}
							$updateBoxID = 0;
							$updateLineType = $lineType;
							if($findText['update'] === true){
								$updateBoxID = $boxType;
							}
							echo "<br/><br/>C:".$counter."@@D:".strtoupper(strtolower($conveyanceText))."<br/><br/>";
							array_push($insertAllHeading,array('name'=>strtoupper(strtolower($conveyanceText)),'assignment_no'=>$counter,'modified'=>$updateBoxID,'original_text'=>$updateLineType,'order_no'=>$counter));
							$counter++;
						}
						if(count($insertAllNames)>0){
							$nameQuery = "";
							echo "<pre>";
							print_r($insertAllNames);
							print_r($inventorNameList);
							echo "</pre>";
							foreach($insertAllNames as $name) {
								$modifiedName = "";
								if(count($inventorNameList) > 0) {
									if(strpos($name,",") !== false){
										$explodeAssignor = explode(",",$name);
										echo '<pre>';
										print_r($explodeAssignor);
										echo '</pre>';
										$testAssignor = trim($explodeAssignor[1])." ".trim($explodeAssignor[0]);
										if($testAssignor != "") {
											foreach($inventorNameList as $invent){
												if($invent == $testAssignor){
													$modifiedName = $testAssignor;
													break;
												}
											}
										}
									} else{
										foreach($inventorNameList as $invent){
											if($invent == $name){
												$modifiedName = $invent;
												break;
											}
										}
									}
								}
								$queryName = "SELECT id FROM lead_assignment_names WHERE original = '".mysqli_real_escape_string($con,$name)."' AND patent_number='".$getPatentNumber."'";
								$queryNameResult =  $con->query($queryName);
								if($queryNameResult && mysqli_num_rows($queryNameResult) == 0) {
									
									$nameQuery .="('".$getPatentNumber."','".mysqli_real_escape_string($con,$name)."',''), ";
								} else {
									if($modifiedName != "") {
										$row = mysqli_fetch_array($queryNameResult);
										$con->query("UPDATE lead_assignment_names SET modified='".mysqli_real_escape_string($con,$modifiedName)."' WHERE id=".$row['id']);
									}
								}
							}
							$nameQuery = substr($nameQuery,0,-2);
							if($nameQuery != "") {
								$nameQuery = "INSERT INTO lead_assignment_names(patent_number,original,modified) VALUES ".$nameQuery;
								echo $nameQuery."<br/>";
								$con->query($nameQuery);
							}
						}
						if(count($insertAllHeading)>0){
							$nameQuery = "";
							echo "<pre>";
							print_r($insertAllHeading);
							echo "</pre>";
							foreach($insertAllHeading as $heading) {
								if($heading['name'] != "") {
									$queryName = "SELECT id FROM lead_assignment_headings WHERE  assignment_no = '".$heading['assignment_no']."' AND patent_number='".$getPatentNumber."'";
									$queryNameResult =  $con->query($queryName);
									if($queryNameResult && mysqli_num_rows($queryNameResult) == 0) {
										$nameQuery .="('".$getPatentNumber."','".mysqli_real_escape_string($con,$heading['name'])."','".mysqli_real_escape_string($con,$heading['modified'])."',".$heading['assignment_no'].",".$heading['original_text'].",".$heading['order_no']."), ";
									} else {
										if($heading['modified'] != "") {
											$row = mysqli_fetch_array($queryNameResult);
											$con->query("UPDATE lead_assignment_headings SET modified='".mysqli_real_escape_string($con,$heading['modified'])."',original_text=".$heading['original_text']." WHERE id=".$row['id']);
										}
									}
								}						
							}
							if($nameQuery != "") {
								$nameQuery = substr($nameQuery,0,-2);
								$nameQuery = "INSERT INTO lead_assignment_headings(patent_number,original,modified,assignment_no,original_text,order_no) VALUES ".$nameQuery;
								echo $nameQuery."<br/>";
								$con->query($nameQuery);
							}					
						}
					}
				}				
			}
		}catch(Exception $e) {
			
		}
	}
}
