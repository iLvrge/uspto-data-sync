<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
$overAllArray = array();
$db = array();
ini_set('max_execution_time', 0);
$db['default']['hostname'] = '167.172.195.92';
$db['default']['username'] = 'patent_user';
$db['default']['password'] = 'P@t3nt@u5r';
$db['default']['database'] = 'db_patentrack';
$db['default']['database1'] = 'big_data_uspto';
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
		$queryJob = "SELECT * FROM jobs as j WHERE j.status = 1 ORDER BY j.id DESC limit 1";
		$resultQuery = $con->query($queryJob);
		if($resultQuery && mysqli_num_rows($resultQuery) > 0){
			$row = mysqli_fetch_object($resultQuery);
			if($row->type == 1){
				$con->query("UPDATE jobs SET status = 1 WHERE id = ".$row->id);
				/*Find Patent List*/
				if($row->default_db == 1){
					$con1 = mysqli_connect($db['default']['hostname'],$db['default']['username'],$db['default']['password'],$db['default']['database1']);
					callFindPatentListFromCSVServer($con,$con1,$row);
				} else {
					callFindPatentList($con,$row);
				}				
			} else {
				/*Scrapping Patent*/
				$query = "SELECT j.patent_id, p.number,j.id FROM jobs as j INNER JOIN patents as p ON p.id = j.patent_id WHERE j.id = ".$row->id." ORDER BY j.id DESC limit 1";
				$resultQuery = $con->query($query);
				if($resultQuery && mysqli_num_rows($resultQuery) > 0){
					$row = mysqli_fetch_object($resultQuery);
					$patentData = $row->number;
					$fromJOBID = $row->id;
					$con->query("UPDATE jobs SET status = 1 WHERE id = ".$fromJOBID);
				}
			}
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
				$handle = fopen('/var/www/html/PatenTrack/resources/shared/data/'.$fileName,'a');
				if($handle != null){
					try{
						fwrite($handle, $content);
					}catch(Exception $e){
						$fileName = "";
					}
					fclose($handle);
				} else {
					print_r(error_get_last());die;
					$fileName = "";
				}
			} else {
				$fileName = "";
			}
		}catch(Exception $e) {
			$fileName = '';
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

function findLineType($name,$con){
	$boxID = 0;
	$query = "SELECT id FROM lead_assignment_heading_list WHERE name = '".$name."'";
	//echo "BOXID: ".$query."<br/>";
	$queryNameRes =  $con->query($query);
	if($queryNameRes && $queryNameRes->num_rows > 0 ) {
		$row = $queryNameRes->fetch_array(MYSQLI_ASSOC);
		$boxID = $row['id'];
	}
	return $boxID;
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

function callFindPatentListFromCSVServer($db, $db1, $jobData) {
	$reelNo = $jobData->reel;
	$frameNo = $jobData->frame;
	
	if($reelNo != "" && $frameNo != ""){
		$queryAssignment = "SELECT * FROM assignments WHERE reelNO = '".$reelNo."' AND frame_no = '".$frameNo."'";
		$queryResult =  $db1->query($queryAssignment);
		if($queryResult && $queryResult->num_rows > 0 ) {
			$row = $queryResult->fetch_object();
			$rfID = $row->rf_id;
			/*GET List of patents*/
			$queryList = "SELECT * FROM documentids WHERE rf_id = ".$rfID;
			
			$queryListResult =  $db1->query($queryList);
			if($queryListResult && $queryListResult->num_rows > 0 ) {
				$queryInsert = "INSERT INTO patents (number, project_id, created_at, updated_at) VALUES ";
				$date = date('Y-m-d H:i:s');
				while($listRow = $queryListResult->fetch_object()){
					$patentNumber = "";
					$patentDate = "";
					$applicationDate = "";
					$applicationNumber = "";
					$title = "";
					if($listRow->grant_doc_num != null && $listRow->grant_doc_num != ""){
						$patentNumber = $listRow->grant_doc_num;
					}
					if($listRow->grant_date != null && $listRow->grant_date != ""){
						$patentDate = $listRow->grant_date;
					}
					if($listRow->appno_doc_num != null && $listRow->appno_doc_num != ""){
						$applicationNumber = $listRow->appno_doc_num;
					}
					if($listRow->appno_date != null && $listRow->appno_date != ""){
						$applicationDate = $listRow->appno_date;
					}
					if($listRow->title != null && $listRow->title != ""){
						$title = $listRow->title;
					}
					$queryInsert .= "('".$patentNumber."', '".$applicationNumber."', '".mysqli_real_escape_string($db,stripslashes($title))."', '".date('Y-m-d h:i:s', strtotime($patentDate))."','".date('Y-m-d h:i:s',strtotime($applicationDate))."',".$jobData->project_id.", '".$date."', '".$date."'), ";
				}
				$queryInsert = substr($queryInsert,0,-2);
				echo $queryInsert;
				$db->query($queryInsert);
				$queryFind = "SELECT count(*) as countPatent FROM patents WHERE project_id=".$jobData->project_id;
				$countResult = $db->query($queryFind);
				if($countResult && mysqli_num_rows($countResult) > 0){
					$patentC = mysqli_fetch_object($countResult);
					if($patentC->countPatent == count($patList)){
						//$db->query("DELETE FROM jobs WHERE id = ".$jobData->id);
					}
				}
				/**/
				$queryPatentList = "SELECT number FROM patents WHERE project_id=".$jobData->project_id;
				$queryResultPatent = $db->query($queryPatentList);
				if($queryResultPatent && $queryResultPatent->num_rows > 0){
					/*include EPO API file*/					
					while($patent = $queryResultPatent->fetch_object()){
						$queryDocs = "SELECT * FROM documentids WHERE grant_doc_num = '".$patent->number."'";
						$docResult = $db1->query($queryDocs);
						if($docResult){
							$noOfDocs =  $docResult->num_rows;
							if($noOfDocs > 0){
								
								while($doc = $docResult->fetch_object()){
									$getEPOData = runEPOAPI($patent->number);
									echo "<pre>";
									print_r($getEPOData);
									
									die;
									$insertAllNames = array();
									$inventorNameList = array();
									$insertAllHeading = array();
									/*Assignees*/
									$counter = 1;
									$inventorData = array('list'=>array(),'filling_date'=>'','recorded_date'=>'');
									$addedInventors = false;
									$inventorBoxID = findBoxType("Inventor",$db);
									$thirdPartyBoxID = findBoxType("3rdParties",$db);
									
									$queryAssignment = "SELECT * FROM assignments WHERE rf_id=".$doc->rf_id;
									$resultAssignment = $db1->query($queryAssignment);
									
									if($resultAssignment && $resultAssignment->num_rows > 0){
										$assignmentData = $resultAssignment->fetch_object();
										
										$country = $assignmentData->caddress_4;
										if($country == "" || $country == null){
											$country = $assignmentData->caddress_3;
											if(strpos($country,",") !== false){
												$country = explode(",",$country);
												if(isset($country[1]) && $country[1] !="" && !is_numeric($country[1])){
													$country = $country[1];											
												} else {
													$country = $country[0];
												}
											}
											$country = preg_replace('/[0-9]+/', '', $country);
										}
										
										$queryAssignors = "SELECT or_name, exec_dt FROM assignors WHERE rf_id=".$doc->rf_id." ORDER BY exec_dt ASC";
										$assignorList = array();
										$patAssignorEarliestExDate = "";
										$resultAssignor = $db1->query($queryAssignors);
										if($resultAssignor && $resultAssignor->num_rows > 0) {
											while($assignor = $resultAssignor->fetch_object()) {
												if($patAssignorEarliestExDate == "") {
													$patAssignorEarliestExDate = $assignor->exec_dt;
												}
												array_push($assignorList,$assignor->or_name);
											}
										}
										
										$queryAssignees = "SELECT ee_name, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country FROM assignees WHERE rf_id=".$doc->rf_id;
										$assigneeList = array();
										$patAssignorEarliestExDate = "";
										$resultAssignees = $db1->query($queryAssignees);
										if($resultAssignees && $resultAssignees->num_rows > 0) {
											while($assignee = $resultAssignees->fetch_object()) {
												
												array_push($assigneeList,array('ee_name'=>$assignee->ee_name, 'ee_address_1'=>$assignee->ee_address_1, 'ee_address_2'=>$assignee->ee_address_2, 'ee_city'=>$assignee->ee_city, 'ee_state'=>$assignee->ee_state, 'ee_postcode'=>$assignee->ee_postcode, 'ee_country'=>$assignee->ee_country));
											}
										}
										
										if(count($assignorList) > 0 && count($assigneeList) > 0) {
											
											/*Delete old data*/
											$queryAssigmentIllustration = "DELETE FROM lead_patent_assignment WHERE patent_number='".$patent->number."'";
											$db->query($queryAssigmentIllustration);
											$queryAssigmentIllustration = "DELETE FROM lead_patent_assigment_relation WHERE patent_number='".$patent->number."'";
											$db->query($queryAssigmentIllustration);
											$queryAssigmentIllustration = "DELETE FROM patent_assignments WHERE patent_number='".$patent->number."'";
											$db->query($queryAssigmentIllustration);
											$queryAssigmentIllustration = "DELETE FROM patent_assignees WHERE patent_number='".$patent->number."'";
											$db->query($queryAssigmentIllustration);
											/*end delete old data*/
											
											
											$appNumSize = 0;
											
											$queryApp = "SELECT COUNT(d.id) as countDocs FROM documentids as d LEFT JOIN assignments as a ON a.rf_id = d.rf_id WHERE a.reel_no = ".$assignmentData->reel_no." AND a.frame_no = ".$assignmentData->frame_no;
											
											$resultApp  = $db1->query($queryApp);
											if($resultApp && $resultApp->num_rows > 0){
												$countApp = $resultApp->fetch_object();
												$appNumSize = $countApp->countDocs;
											}
											
											/*patent_assignments*/
											
											$assignmentInsertData = array('patent_number'=>$patent->number,'assignment_name'=>formatText($assignmentData->convey_text),'transactions'=>$appNumSize,'execution_date'=>date('Y-m-d H:i:s',strtotime($patAssignorEarliestExDate)),'recorded_date'=>date('Y-m-d H:i:s',strtotime($assignmentData->record_dt)),'correspondence_name'=>formatText($assignmentData->cname),'correspondence_address'=>formatText($assignmentData->caddress_1." ".$assignmentData->caddress_2),'correspondence_country'=>formatText($country),'counter'=>$counter);
											
											
											add("patent_assignments",$assignmentInsertData,$db);	
											
											
											$conveyanceType = "";
											
											$queryAssignmentConveyance = "SELECT convey_ty FROM assignment_conveyances WHERE rf_id = ".$doc->rf_id;
											
											$resultConveyance = $db1->query($queryAssignmentConveyance);
											if($resultConveyance && $resultConveyance->num_rows > 0){
												$conveyanceRow = $resultConveyance->fetch_object();
												$conveyanceType = $conveyanceRow->convey_ty;
											}
											
											/*patent_assignees*/
											$assigneeListAdd = "INSERT INTO patent_assignees (patent_number, assignment_name, assignee_name, execution_date, address1, address2, city, state, postal_code, country, counter) VALUES ";
											
											foreach($assigneeList as $assignee){
												
												/*$assigneeInsertData = array('patent_number'=>$patent->number,'assignment_name'=>formatText($assignmentData->convey_text),'assignee_name'=>formatText($assignee['ee_name']),'execution_date'=>date('Y-m-d H:i:s',strtotime($patAssignorEarliestExDate)),'address1'=>formatText($assignee['ee_address_1']),'address2'=>formatText($assignee['ee_address_2']),'city'=>formatText($assignee['ee_city']),'state'=>formatText($assignee['ee_state']),'postal_code'=>$assignee['ee_postcode'],'country'=>formatText($assignee['ee_country']),'counter'=>$counter);*/
												
												$assigneeListAdd .= "('".$patent->number."','".mysqli_real_escape_string($db, formatText($assignmentData->convey_text))."','".mysqli_real_escape_string($db, formatText($assignee['ee_name']))."','".date('Y-m-d H:i:s',strtotime($patAssignorEarliestExDate))."','".mysqli_real_escape_string($db, formatText($assignee['ee_address_1']))."','".mysqli_real_escape_string($db, formatText($assignee['ee_address_2']))."','".mysqli_real_escape_string($db, formatText($assignee['ee_city']))."','".mysqli_real_escape_string($db, formatText($assignee['ee_state']))."','".$assignee['ee_postcode']."','".formatText($assignee['ee_country'])."','".$counter."'), ";
												
											}
											
											$assigneeListAdd = substr($assigneeListAdd, 0, -2);
											try{
												$db->query($assigneeListAdd);
											}catch(Exception $e){
												
											}
											
											$frameNo = $assignmentData->frame_no;
											$reelNo = $assignmentData->reel_no;
											echo "FRAME:".$frameNo."@@REEL:".$reelNo ."<br/>";
											$recorded = $assignmentData->record_dt;
											$originalRecorded = $assignmentData->record_dt;
											$executedDate = $patAssignorEarliestExDate;
											$originalExecutedDate = $patAssignorEarliestExDate;
											$assigneeList = $docs['patAssigneeName'];
											$assignorList = $docs['patAssignorName'];
											$conveyanceText = $assignmentData->convey_text;
											$findText = getDescription($patent->number,trim($conveyanceText),$counter,$db);
											
											$description = $findText['text'];
											
											$boxType = 0;
											$lineType = 0;
											
											switch(strtolower($conveyanceType)){
												case 'assignment':
													$boxType = findBoxType("Ownership",$db);
													$lineType = findLineType("Ownership",$db);
													$description = "Ownership";
													break;
												case 'namechg':
													$boxType = findBoxType("Ownership",$db);
													$lineType = findLineType("Name Change",$db);
													$description = "Name Change";
													break;
												case 'security':
													$boxType =  findBoxType("Security",$db);
													$lineType = findLineType("Security",$db);
													$description = "Security";
													break;
												case 'release':
													$boxType = findBoxType("Security",$db);
													$lineType = findLineType("Release",$db);
													$description = "Release";
													break;												
												case 'merger':
												case 'other':
												case 'correct':
												case 'missing':
												case 'govern':
												case 'employee':
												default:
													$boxType = findBoxType("Ownership",$db);
													$lineType = findLineType("Ownership",$db);
													break;
											}
											$fileName = getPDFFile(array('reelNo'=>$reelNo, 'frameNo'=>$frameNo ));
											
											if($fileName!="" && strpos($fileName, "http://legacy") === false){
												$fileName = "https://patentrack.com/resources/shared/data/".$fileName;
											}
											
											echo "Counter:".$counter."COUNTER:".$conveyanceText."DESCRIPTION:".$description."BOXID:".$boxType."<br/>";
											
											/*if security not release or license not release*/
											if(strpos(strtolower($conveyanceType), "release") === false) {
												echo "1<br/>";
												foreach($assigneeList as $assignee) {
													$executedDate = date('Y-m-d', strtotime($originalExecutedDate));
													$recorded = date('Y-m-d', strtotime($originalRecorded));
													$assignee = getName($patent->number,trim($assignee),$db);
													$assignee = formatText($assignee);
													$assigneeID = 0;
													$creator = 0;
													
													$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE type IN (0,1) AND patent_number='".$patent->number."' AND name = '".mysqli_real_escape_string($db,$assignee)."' order by id ASC LIMIT 1";
													echo "BOB:".$queryCheck."<br/>";
													$queryResult =  $db->query($queryCheck);
													if($queryResult && $queryResult->num_rows > 0) {
														$checkAssignee = $queryResult->fetch_array(MYSQLI_ASSOC);
														$assigneeID = $checkAssignee['id'];
													}
													/*If assignee not exist insert it*/
													if($assigneeID === 0) {
														$insertAssigneeData = array('patent_number'=>$patent->number,'name'=>$assignee,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>1,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'assignment_no'=>$counter,'box_type'=>$boxType, 'assignment_type'=>strtolower($conveyanceType));
														
														$assigneeID = add("lead_patent_assignment", $insertAssigneeData, $db);
														$creator = $assigneeID;
													}
													
													if($assigneeID > 0) {	
														$cType = 2;
														$assignorBoxType = $boxType;
														$as1 = 0;
														foreach($assignorList as $assignor) {	
															$assignor = getName($patent->number,trim($assignor),$db);
															$assignor = formatText($assignor);
															$startCreatorID = 0;
															/*if(count($inventorData['list']) > 0) {
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
															}*/
															
															/*check Assignor exist*/
															
															$queryCheck = "SELECT id, description, assignment_type FROM lead_patent_assignment WHERE  patent_number='".$patent->number."' AND name = '".mysqli_real_escape_string($db,$assignor)."' AND type IN(0,1) order by id ASC ";
															$assignorID = 0;
															
															$queryResult =  $con->query($queryCheck);
															if($queryResult && $queryResult->num_rows > 0) {
																if($queryResult->num_rows == 1) {
																	$checkAssignor = $queryResult->fetch_array(MYSQLI_ASSOC);
																	$assignorID = $checkAssignor['id'];
																} else {
																	while($checkAssignor = $queryResult->fetch_array(MYSQLI_ASSOC)){
																		if($checkAssignor['assignment_type'] != ""){
																			if($checkAssignor['assignment_type'] == "assignment" || $checkAssignor['assignment_type'] == "namechg"){
																				$assignorID = $checkAssignor['id'];
																			}
																		} else {
																			$desc = strtolower($checkAssignor['description']);
																			if(strpos($desc,"Ownership") !==false || strpos($desc,"Name Change") !==false) {
																				$assignorID = $checkAssignor['id'];
																				break;
																			}
																		}
																	}
																}												
															}  else {
																/*check assignor exist in 3rd party*/
																$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE  patent_number='".$getPatentNumber."' AND name = '".mysqli_real_escape_string($db,$assignor)."' AND type = 2 order by id ASC LIMIT 1";
																$queryResult =  $con->query($queryCheck);
																if($queryResult && $queryResult->num_rows > 0) {
																	$checkAssignor = $queryResult->fetch_array(MYSQLI_ASSOC);
																	$assignorID = $row['id'];
																}
															}
															
															/*If Assignor not exist*/
															
															if($assignorID == 0){
																
																if($cType == 2) {
																	$executedDate = '0000-00-00';
																	$recorded = '0000-00-00';
																	$assignorBoxType = $thirdPartyBoxID;
																}
																
																$insertAssignorData = array('patent_number'=>$patent->number,'name'=>$assignor,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>$cType,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'assignment_no'=>$counter,'box_type'=>$assignorBoxType, 'assignment_type'=>strtolower($conveyanceType));
																
																$assignorID = add("lead_patent_assignment", $insertAssignorData, $db);
																$startCreatorID = $assignorID;
															}
																
															
															if($assignorID > 0) {
																
																$connectionType = 1;
																
																$insertRelationData = array('patent_number'=>$patent->number,'parent_id'=>$assignorID,'child_id'=>$assigneeID,'connection_type'=>$connectionType,'frame'=>$frameNo,'reel'=>$reelNo,'description'=>$description,'date'=>date('Y-m-d',strtotime($patAssignorEarliestExDate)),'assignment_no'=>$counter,'line_type'=>$lineType,'creator_id'=>$creator,'start_creator_id'=>$startCreatorID);
																
																add("lead_patent_assigment_relation", $insertRelationData, $db);
																
																if(!in_array($assignee,$insertAllNames)) {
																	array_push($insertAllNames,$assignee);
																}
																if(!in_array($assignor,$insertAllNames)) {
																	array_push($insertAllNames,$assignor);
																}
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
												/* end foreach loop*/
											} else if(strpos(strtolower($description), "release") !== false) {
												/*license or security is released*/
												echo "2<br/>";
												foreach($assigneeList as $assignee) {
													$assignee = getName($getPatentNumber,trim($assignee),$con);
													$assignee = formatText($assignee);
													
													/*check Assignee exist*/
													$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE patent_number='".$patent->number."' AND name = '".mysqli_real_escape_string($db,$assignee)."'    AND type IN(0,1) order by id ASC";
													
													$assigneeID = 0;
													$creator = 0;
													
													$queryResult =  $db->query($queryCheck);
													if($queryResult && $queryResult->num_rows > 0) {
														if($queryResult->num_rows == 1){
															$checkAssignee = $queryResult->fetch_array(MYSQLI_ASSOC);
															$assigneeID = $checkAssignee['id'];
														} else {
															while($checkAssignee = $queryResult->fetch_array(MYSQLI_ASSOC)){
																if($checkAssignee['assignment_type'] != ""){
																	if($checkAssignee['assignment_type'] == "assignment" || $checkAssignee['assignment_type'] == "namechg"){
																		$assigneeID = $checkAssignee['id'];
																	}
																} else {
																	$desc = strtolower($checkAssignee['description']);
																	if(strpos($desc,"Ownership") !==false || strpos($desc,"Name Change") !==false) {
																		$assigneeID = $checkAssignee['id'];
																		break;
																	}
																}
															}
														}														
													} else {
														$queryCheck = "SELECT id FROM lead_patent_assignment WHERE patent_number='".$patent->number."' AND name = '".mysqli_real_escape_string($db,$assignee)."' AND type = 2 order by id ASC LIMIT 1";
														
														$queryResult =  $db->query($queryCheck);
														if($queryResult && $queryResult->num_rows > 0){
															$checkAssignee = $queryResult->fetch_array(MYSQLI_ASSOC);
															$assigneeID = $checkAssignee['id'];
														}
													}
													
													/*if assignee not exist*/									
													if($assigneeID == 0) {
														
														$insertAssigneeData = array('patent_number'=>$patent->number,'name'=>$assignee,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>2,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'assignment_no'=>$counter,'box_type'=>$thirdPartyBoxID, 'assignment_type'=>strtolower($conveyanceType));
														
														$assigneeID = add("lead_patent_assignment", $insertAssigneeData, $db);
														$creator = $assigneeID;
														
														echo "NEW<br/>";
													}
													
													if($assigneeID > 0) { 
														$as = 0;
														foreach($assignorList as $assignor) {
															
															$assignor = getName($patent->number,trim($assignor),$db);
															$assignor = formatText($assignor);
															$startCreatorID  = 0;
															$assignorID = 0;
															
															$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE  patent_number='".$patent->number."' AND name = '".mysqli_real_escape_string($db,$assignor)."' AND type IN(0,1) order by id ASC LIMIT 1";
															
															$queryResult =  $db->query($queryCheck);
															
															if($queryResult && $queryResult->num_rows > 0) {
																$checkAssignor = $queryResult->fetch_array(MYSQLI_ASSOC);
																$assignorID = $checkAssignor['id'];		
															}  else {
																/*check assignor exist in 3rd party*/
																$queryCheck = "SELECT id,description FROM lead_patent_assignment WHERE  patent_number='".$getPatentNumber."' AND name = '".mysqli_real_escape_string($db,$assignor)."' AND type = 2 order by id ASC LIMIT 1";
																$queryResult =  $con->query($queryCheck);
																if($queryResult && $queryResult->num_rows > 0) {
																	$checkAssignor = $queryResult->fetch_array(MYSQLI_ASSOC);
																	$assignorID = $row['id'];
																}
															}															
															
															if($assignorID == 0) {
																
																
																$insertAssignorData = array('patent_number'=>$patent->number,'name'=>$assignor,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>1,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'assignment_no'=>$counter,'box_type'=>$boxType, 'assignment_type'=>strtolower($conveyanceType));
																
																$assignorID = add("lead_patent_assignment", $insertAssignorData, $db);
																$startCreatorID = $assignorID;
																//echo "NEW<br/>";
															}
															
															if($assignorID > 0) {
																$connectionType = 2;
																
																$insertRelationData = array('patent_number'=>$patent->number,'parent_id'=>$assignorID,'child_id'=>$assigneeID,'connection_type'=>$connectionType,'frame'=>$frameNo,'reel'=>$reelNo,'description'=>$description,'date'=>date('Y-m-d',strtotime($patAssignorEarliestExDate)),'assignment_no'=>$counter,'line_type'=>$lineType,'creator_id'=>$creator,'start_creator_id'=>$startCreatorID);
																
																add("lead_patent_assigment_relation", $insertRelationData, $db);
																
																if(!in_array($assignee,$insertAllNames)) {
																	array_push($insertAllNames,$assignee);
																}
																if(!in_array($assignor,$insertAllNames)) {
																	array_push($insertAllNames,$assignor);
																}			
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
												/*end foreach loop*/
											}
											/*end if condition*/
										}
										/*end condition of counter assignee and assignor*/
									}
									/*end if condition if assignment data exist in the csv database*/
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
										$queryName = "SELECT id FROM lead_assignment_names WHERE original = '".mysqli_real_escape_string($db,$name)."' AND patent_number='".$patent->number."'";
										$queryNameResult =  $db->query($queryName);
										if($queryNameResult && mysqli_num_rows($queryNameResult) == 0) {
											
											$nameQuery .="('".$patent->number."','".mysqli_real_escape_string($db,$name)."',''), ";
										} else {
											if($modifiedName != "") {
												$row = mysqli_fetch_array($queryNameResult);
												
												update("lead_assignment_names",array("modified"=>$modifiedName, "original_text"=>$modifiedName),$row['id'],$db);
												
											}
										}
									}
									$nameQuery = substr($nameQuery,0,-2);
									if($nameQuery != "") {
										$nameQuery = "INSERT INTO lead_assignment_names(patent_number,original,modified) VALUES ".$nameQuery;
										echo $nameQuery."<br/>";
										$db->query($nameQuery);
									}
								}
								if(count($insertAllHeading)>0){
									$nameQuery = "";
									echo "<pre>";
									print_r($insertAllHeading);
									echo "</pre>";
									foreach($insertAllHeading as $heading) {
										if($heading['name'] != "") {
											$queryName = "SELECT id FROM lead_assignment_headings WHERE  assignment_no = '".$heading['assignment_no']."' AND patent_number='".$patent->number."'";
											$queryNameResult =  $db->query($queryName);
											if($queryNameResult && mysqli_num_rows($queryNameResult) == 0) {
												$nameQuery .="('".$patent->number."','".mysqli_real_escape_string($db,$heading['name'])."','".mysqli_real_escape_string($db,$heading['modified'])."',".$heading['assignment_no'].",".$heading['original_text'].",".$heading['order_no']."), ";
											} else {
												if($heading['modified'] != "") {
													$row = mysqli_fetch_array($queryNameResult);
													
													update("lead_assignment_headings",array("modified"=>$heading['modified'], "original_text"=>$heading['original_text']),$row['id'],$db);
												}
											}
										}						
									}
									if($nameQuery != "") {
										$nameQuery = substr($nameQuery,0,-2);
										$nameQuery = "INSERT INTO lead_assignment_headings(patent_number,original,modified,assignment_no,original_text,order_no) VALUES ".$nameQuery;
										echo $nameQuery."<br/>";
										$db->query($nameQuery);
									}
									/*Update Inventor name if original and modified is same*/
									$query = "Select name from lead_patent_assignment WHERE box_type = 1 AND patent_number = '".$getPatentNumber."'";
									$resultQuery = $db->query($query);
									if($resultQuery && mysqli_num_rows($resultQuery) > 0){
										$getList = array();
										while($row = mysqli_fetch_object($resultQuery)){
											array_push($getList, '"'.mysqli_real_escape_string($db,$row->name).'"');
										}
										if(count($getList) > 0){
											$queryNames = "SELECT * FROM lead_assignment_names WHERE original IN (".implode(',', $getList).") AND patent_number='".$patent->number."'";
											$resultQueryNames = $db->query($queryNames);
											if($resultQueryNames && mysqli_num_rows($resultQueryNames) > 0){
												$IDlist = array();
												while($row = mysqli_fetch_object($resultQueryNames)){
													if($row->original != "" && $row->modified != "" && (trim($row->original) == trim($row->modified))){
														array_push($IDlist , $row->id);
													}
												}
												if(count($IDlist) > 0){
													$db->query("UPDATE lead_assignment_names SET modified='' WHERE id IN (".implode(',', $IDlist).")");
												}
											}
										}
									}
								}
								/*end while loop of no of docs i.e no of patents in reel and frame*/
							}
							/*end if condition of noOfDocs*/
						}
					}
					/*end while of patent list*/					
				}
			}
		}
	}
}

function callFindPatentList($con,$jobData){
	$reelNo = $jobData->reel;
	$frameNo = $jobData->frame;
	$url = "https://assignment.uspto.gov/solr/aotw/select?fl=*&fq=id:%22".$reelNo."-".$frameNo."%22&hl=true&hl.fl=*&hl.fragsize=999&hl.requireFieldMatch=true&hl.simple.post=%3C%2Fem%3E&hl.simple.pre=%3Cem+class%3D%27high-lighted%27%3E&hl.snippets=10000&lowercaseOperators=true&q=reelNo:".$reelNo."+AND+frameNo:".$frameNo."&sort=&wt=json";
	try{
		$dataUSPTO = getCurlData($url);
		$patList = array();
		if($dataUSPTO != "" && $dataUSPTO != null) {
			$assignmentList = json_decode($dataUSPTO,true);
			if(isset($assignmentList['response']) && isset($assignmentList['response']['docs'])) {
				if(count($assignmentList['response']['docs']) > 0) {
					echo count($assignmentList['response']['docs']);
					for($a = 0;$a < count($assignmentList['response']['docs']); $a++) {
						$docs = $assignmentList['response']['docs'][$a];
						$patNum = $docs['patNum'];
						echo count($patNum);
						if(count($patNum) > 0){
							$patList = array_merge($patList,$patNum );
						}
					}
				}
			}
		}
		if(count($patList) > 0){
			$queryInsert = "INSERT INTO patents (number, project_id, created_at, updated_at) VALUES ";
			$date = date('Y-m-d H:i:s');
			foreach($patList as $number){
				if($number != null && $number != NULL && $number != 'null' && $number != 'NULL'){
					$queryInsert .= "('".$number."', ".$jobData->project_id.", '".$date."', '".$date."'), ";
				}				
			}
			$queryInsert = substr($queryInsert,0,-2);
			echo $queryInsert;
			$con->query($queryInsert);
			$con->query("INSERT INTO JOBS (patent_id, created_at, updated_at) SELECT id, created_at, updated_at FROM patents WHERE project_id = ".$jobData->project_id);
			$queryFind = "SELECT count(*) as countPatent FROM patents WHERE project_id=".$jobData->project_id;
			$countResult = $con->query($queryFind);
			if($countResult && mysqli_num_rows($countResult) > 0){
				$row = mysqli_fetch_object($countResult);
				if($row->countPatent == count($patList)){
					$con->query("DELETE FROM jobs WHERE id = ".$jobData->id);
				}
			}			
		}
	}catch(Exception $e){
		$con->query("UPDATE jobs SET status = 2 WHERE id = ".$jobData->id);
	} finally {
		$con->query("DELETE FROM jobs WHERE id = ".$jobData->id);
	}	
}

function getCurlData($url){
	$ch = curl_init ();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_HEADER,false );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
	$dataUSPTO = curl_exec ( $ch );
	if (curl_errno ( $ch )) {	
		$dataUSPTO = "";
		curl_close ( $ch );			
	} else {
		curl_close ( $ch );
	}
	return $dataUSPTO;
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
	$sql = "INSERT INTO ".$tableName."(".$stringName.") VALUES (".$stringValue.")";		
	echo $sql ."<br/>";
	$result = $con->query($sql);
	if($result){
		return mysqli_insert_id($con);
	} else {
		return 0;
	}
}
function update($tableName,$postValues,$where,$con){
	$stringName ="";
	foreach($postValues as $key=>$value){
		$stringName .=$key."='".mysqli_real_escape_string($con,$value)."',";
	}
	$stringName = substr($stringName,0,-1);
	$sql = "UPDATE ".$tableName." SET ".$stringName." WHERE id= ".$where;	
	echo $sql."<br/>";
	$result = $con->query($sql);
	if($result){
		return $where;
	} else {
		return 0;
	}
}
function newAssignmentNumber($getPatentNumber,$con){
	try{
		$assignmentData = array();
		$assigmentIllustrationData = array('inventors'=> array(), 'list'=>array());
		$url = "https://assignment.uspto.gov/solr/aotw/select?fl=id,displayId,reelNo,frameNo,pctNum,applNum,patNum,publNum,issueDate,publDate,filingDate,conveyanceText,patAssigneeName,patAssignorName,inventors,inventionTitle,inventionTitleFirst,applNumFirst,publNumFirst,patNumFirst,intlRegNum,intlRegNumFirst,corrName,corrAddress1,corrAddress2,corrAddress3,corrAddress4,patAssignorEarliestExDate,recordedDate,filingDateFirst,publDateFirst,issueDateFirst,intlPublDateFirst,patNumSize,applNumSize,pageCount,patAssigneeAddress1,patAssigneeAddress2,patAssigneeCity,patAssigneeState,patAssigneePostcode,patAssigneeCountryName,assignmentRecordHasImages&fq=patNum:".$getPatentNumber."&hl=true&lowercaseOperators=true&q=*:*&rows=500&sort=patAssignorEarliestExDate+desc,+recordedDate+desc&wt=json";
		$dataUSPTO = getCurlData($url);
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
					$queryAssigmentIllustration = "DELETE FROM patent_assignments WHERE patent_number='".$getPatentNumber."'";
					$con->query($queryAssigmentIllustration);
					$queryAssigmentIllustration = "DELETE FROM patent_assignees WHERE patent_number='".$getPatentNumber."'";
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
						$corrAddress3 = explode(',',$docs['corrAddress3']);
						$assignmentInsertData = array('patent_number'=>$getPatentNumber,'assignment_name'=>formatText($docs['conveyanceText']),'transactions'=>$docs['applNumSize'],'execution_date'=>date('Y-m-d H:i:s',strtotime($docs['patAssignorEarliestExDate'])),'recorded_date'=>date('Y-m-d H:i:s',strtotime($docs['recordedDate'])),'correspondence_name'=>formatText($docs['corrName']),'correspondence_address'=>formatText($docs['corrAddress1']." ".$docs['corrAddress2']),'correspondence_country'=>formatText($corrAddress3[0]),'counter'=>$counter);
						echo "<pre>";
						print_r($assignmentInsertData);		
						
						add("patent_assignments",$assignmentInsertData,$con);							
						
						for($as = 0; $as < count($docs['patAssigneeName']); $as++){
							$assigneeNameFromList = $docs['patAssigneeName'][$as];
							$address1 = $docs['patAssigneeAddress1'][$as];
							$address2 = $docs['patAssigneeAddress2'][$as];
							$city = $docs['patAssigneeCity'][$as];
							$state = $docs['patAssigneeState'][$as];
							$postal_code = $docs['patAssigneePostcode'][$as];
							$country = $docs['patAssigneeCountryName'][$as];
							if($country == null || $country == NULL || $country == 'null' || $country == 'NULL'){
								$country = $state;
							}
							$assigneeInsertData = array('patent_number'=>$getPatentNumber,'assignment_name'=>formatText($docs['conveyanceText']),'assignee_name'=>formatText($assigneeNameFromList),'execution_date'=>date('Y-m-d H:i:s',strtotime($docs['patAssignorEarliestExDate'])),'address1'=>formatText($address1),'address2'=>formatText($address2),'city'=>formatText($city),'state'=>formatText($state),'postal_code'=>$postal_code,'country'=>formatText($country),'counter'=>$counter);
							add("patent_assignees",$assigneeInsertData,$con);			
						}
						
						
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
						if((int)$boxType == 0){
							$boxType = 2;
						}
						if((int)$lineType == 0){
							$lineType = 2;
						}
						$fileName = getPDFFile($docs);
						if($fileName!="" && strpos($fileName, "http://legacy") === false){
							$fileName = "https://patentrack.com/resources/shared/data/".$fileName;
						}		
						if($a === count($assignmentList['response']['docs'])-1) {
							$patentNumberList = $docs['patNum'];
							$patentIndex = array_search($getPatentNumber,$patentNumberList);
							if($patentIndex >= 0) {
								$inventorsAllList = $docs['inventors'];
								$filingDateList = $docs['filingDate'];
								$recordedDateList = $docs['publDate'];
								$titleList = $docs['inventionTitle'];
								$allInventors = explode(',',$inventorsAllList[$patentIndex]);
								$inventorData['list'] = $allInventors;
								$inventorData['filling_date'] = $filingDateList[$patentIndex];
								$inventorData['recorded_date'] = $recordedDateList[$patentIndex];
								if($inventorData['recorded_date'] == "0001-01-01T00:00:00Z"){
									$inventorData['recorded_date'] = "0000-00-00T00:00:00Z";
								}
								$title = $titleList[$patentIndex];
								$application =  $docs['applNumFirst'];
								$application_date =  date('Y-m-d H:i:s',strtotime($docs['filingDateFirst']));
								$patent_date =  date('Y-m-d H:i:s',strtotime($docs['issueDateFirst']));
								echo "UPDATE patents SET title = '".mysqli_real_escape_string($con,trim($title))."', application = '".$application."', patent_date = '".$patent_date."', application_date = '".$application_date."' WHERE number = '".$_REQUEST['p']."'<br/>";
								$con->query("UPDATE patents SET title = '".mysqli_real_escape_string($con,trim($title))."', application = '".$application."', patent_date = '".$patent_date."', application_date = '".$application_date."' WHERE number = '".$_REQUEST['p']."'");
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
									$assignorBoxType = $boxType;
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
												$assignorBoxType = $thirdPartyBoxID;
											}
											$queryInsertAssignment = "INSERT INTO lead_patent_assignment(patent_number,name,description,execution_date,recorded,type,reel_no,frame_no,document_file,assignment_no,box_type) VALUES ('".$getPatentNumber."','".mysqli_real_escape_string($con,$assignor)."','".mysqli_real_escape_string($con,$description)."','".$executedDate."','".$recorded."','".$cType."','".$docs['reelNo']."','".$docs['frameNo']."','".$fileName."',".$counter.",".$assignorBoxType.")";
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
									$updateModified = "UPDATE lead_assignment_names SET modified='".mysqli_real_escape_string($con,$modifiedName)."' WHERE id=".$row['id'];
									echo "Modified:<br/>";
									echo $updateModified."<br/>";
									$con->query($updateModified);
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
										$updateQuery = "UPDATE lead_assignment_headings SET modified='".mysqli_real_escape_string($con,$heading['modified'])."',original_text=".$heading['original_text']." WHERE id=".$row['id'];
										echo $updateQuery."<br/>";
										$con->query($updateQuery);
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
						/*Update Inventor name if original and modified is same*/
						$query = "Select name from lead_patent_assignment WHERE box_type = 1 AND patent_number = '".$getPatentNumber."'";
						$resultQuery = $con->query($query);
						if($resultQuery && mysqli_num_rows($resultQuery) > 0){
							$getList = array();
							while($row = mysqli_fetch_object($resultQuery)){
								array_push($getList, '"'.mysqli_real_escape_string($con,$row->name).'"');
							}
							if(count($getList) > 0){
								$queryNames = "SELECT * FROM lead_assignment_names WHERE original IN (".implode(',', $getList).") AND patent_number='".$getPatentNumber."'";
								$resultQueryNames = $con->query($queryNames);
								if($resultQueryNames && mysqli_num_rows($resultQueryNames) > 0){
									$IDlist = array();
									while($row = mysqli_fetch_object($resultQueryNames)){
										if($row->original != "" && $row->modified != "" && (trim($row->original) == trim($row->modified))){
											array_push($IDlist , $row->id);
										}
									}
									if(count($IDlist) > 0){
										$con->query("UPDATE lead_assignment_names SET modified='' WHERE id IN (".implode(',', $IDlist).")");
									}
								}
							}
						}
					}
				}
			}				
		}
	}catch(Exception $e) {
		
	}
}

function runEPOAPI($patentNumber) {
	/*--EPO--*/
	$listInventor = array();
	$epoDocToken = read_token('HedCET');
	if(!$epoDocToken['error']) {	
		$patentType = 'publication';
		$getBiblioData = runUrl($epoDocToken,'published-data',$patentType,'epodoc',$patentNumber,'biblio,abstract','');
		$worldPatentData = "";
		if(empty($getBiblioData['error'])){
			if(!empty($getBiblioData['data'])){
				$xml=simplexml_load_string($getBiblioData['data']);
				if ($xml !== false) {
					$xmlObject = new SimpleXMLElement($getBiblioData['data']);
					try{
						if(isset($xmlObject->code) && $xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found"){
							
						} else{				
							$worldPatentData = $xmlObject->xpath('//ops:world-patent-data');
						}
					}catch(Exception $e){}					
				}
			}
		}
		try{
			if(isset($worldPatentData[0])){
				$exchangeDocuments = $worldPatentData[0]->{'exchange-documents'};
				$exchangeDocumentList = $exchangeDocuments->{'exchange-document'};
				if(count($exchangeDocumentList)>0){
					$abs = $exchangeDocumentList[0]->{'abstract'};
					$abstractString['abstract'] = (string)$abs->p;
					$abstractString['title'] =  (string)$exchangeDocumentList[0]->{'bibliographic-data'}->{'invention-title'};
					$parties = $exchangeDocumentList[0]->{'bibliographic-data'}->{'parties'};
					if(count($parties)>0){
						$inventors = $parties[0]->{'inventors'}->{'inventor'};
						if(count($inventors)>0){
							foreach($inventors as $inventor){
								if($inventor['data-format']=="original"){
									array_push($listInventor,(string)$inventor->{'inventor-name'}->name);
								}
							}
						}
					}
				}
			}
		} catch(Exception $e){
			
		}
	}
	return $listInventor;
	/*END EPO*/
}

function read_token($tokenName) {
	$error = '';
	$tokenFile = "/var/www/html/trash/$tokenName.dat";
	if(file_exists($tokenFile)) {
		$token = unserialize(file_get_contents($tokenFile));
		$tokenTime = substr($token['issued_at'], 0, -3) + $token['expires_in'] - 120;
		if($tokenTime < time()) $error .= "token '$tokenName' expired<br>\n";
		else $token['error']=$error;
	} else $error .= "tokenFile '$tokenName' notFound<br>\n";
	if($error) $token = create_token($tokenName);
	return($token);
}

function create_token($tokenName) {
	$error = '';
	$ops_key = '9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs';
	$ops_secret = 'WgLvbrHl9QOyykTT';
	switch($tokenName) {
		case 'HedCET':
			$ops_key = '9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs';
			$ops_secret = 'WgLvbrHl9QOyykTT';
		break;
		default:
			$ops_key = '9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs';
			$ops_secret = 'WgLvbrHl9QOyykTT';
		break;
	}
	$tokenFile = "/var/www/html/trash/$tokenName.dat";
	/*$tokenFile = "./$tokenName.dat";*/
	$tokenHeader = array(
		'Authorization: Basic '.base64_encode($ops_key.':'.$ops_secret),
		'Content-Type: application/x-www-form-urlencoded'
	);
	$token_post_data = 'grant_type=client_credentials';
	$token_url = 'https://ops.epo.org/3.2/auth/accesstoken';
	$curlOpt = array(
		CURLOPT_HTTPHEADER => $tokenHeader,
		CURLOPT_POST => 1,
		CURLOPT_POSTFIELDS => $token_post_data,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL => $token_url,
	);
	$token_request = curl_init();
	curl_setopt_array($token_request, $curlOpt);
	if(! $ops_token_response = curl_exec($token_request)) $error .= curl_error($token_request)."<br>\n";
	curl_close($token_request);
	$tokenResponse = explode(',',trim($ops_token_response, '{}'));
	$token = array();
	foreach($tokenResponse as $token_val){
		$token_pair = explode(':', trim($token_val));
		$token[trim($token_pair[0], '"')] = substr(trim($token_pair[1]),1,-1);
	}
	/*
	foreach(explode(',', trim($ops_token_response, '{}')) as $token_val) {
		$token_pair = explode(' : ', trim($token_val));
		$token[trim($token_pair[0], '"')] = trim($token_pair[1], '"');
	}*/
	file_put_contents($tokenFile, serialize($token));
	$token['error'] = $error;
	return($token);
}
function runUrl($token,$A,$B,$C,$D,$E,$F){
	$error = '';
	$requestHeader = array(
		'Accept: application/xml',
		'Authorization: Bearer '.$token['access_token'],
		'Connection: Keep-Alive',
		'Host: ops.epo.org',
		'X-Target-URI: http://ops.epo.org'
	);
	/*http://ops.epo.org/3.2/rest-services/family/publication/epodoc/EP1000000/biblio*/
	
	$request_url = "http://ops.epo.org/3.2/rest-services/%s/%s/%s/%s/%s/%s";
	$request_url = sprintf($request_url,$A,$B,$C,$D,$E,$F);
	/*echo $request_url."<br/>";*/
	$curlOpt = array(
		// CURLOPT_HEADER => 1,
		CURLOPT_HTTPHEADER => $requestHeader,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL => $request_url
	);

	// echo "<PRE>";
	// print_r($requestHeader);
	// echo "</PRE>";

	$ops_request = curl_init();
	curl_setopt_array($ops_request, $curlOpt);
	if(! $ops_response = curl_exec($ops_request)) $error .= curl_error($ops_request)."<br>\n";
	curl_close($ops_request);
	if($error){
		return array('error'=>$error,'data'=>'');
	} else {
		return array('error'=>'','data'=>$ops_response);
	}
}
function singleUrl($token,$accept='application/pdf',$A){
	$error = '';
	$requestHeader = array(
		'Authorization: Bearer '.$token['access_token'],
		'Connection: Keep-Alive',
		'Host: ops.epo.org',
		'X-Target-URI: http://ops.epo.org'
	);
	/*http://ops.epo.org/3.2/rest-services/family/publication/epodoc/EP1000000/biblio*/
	
	$request_url = "http://ops.epo.org/3.2/rest-services/%s";
	$request_url = sprintf($request_url,$A);
	$curlOpt = array(
		// CURLOPT_HEADER => 1,
		CURLOPT_HTTPHEADER => $requestHeader,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL => $request_url
	);

	// echo "<PRE>";
	// print_r($requestHeader);
	// echo "</PRE>";

	$ops_request = curl_init();
	curl_setopt_array($ops_request, $curlOpt);
	if(! $ops_response = curl_exec($ops_request)) $error .= curl_error($ops_request)."<br>\n";
	curl_close($ops_request);
	if($error){
		return array('error'=>$error,'data'=>'');
	} else {
		return array('error'=>'','data'=>$ops_response);
	}
}
