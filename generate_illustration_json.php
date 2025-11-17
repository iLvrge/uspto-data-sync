<?php 
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbApplication);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);*/
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            // may also be using PUT, PATCH, HEAD etc
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: *");

        exit(0);
    }
$boxes = array(
	array('id'=>1,'segment'=>0,'border_color'=>'#363636','border_px'=>'1','background_color'=>'#222222','dimension'=>'100x30','type'=>'Inventor','shape'=>'rectangle'),	
	array('id'=>2,'segment'=>1,'border_color'=>'#363636','border_px'=>'1','background_color'=>'#222222','dimension'=>'100x30','type'=>'Ownership','shape'=>'rectangle'),	
	array('id'=>3,'segment'=>2,'border_color'=>'#363636','border_px'=>'1','background_color'=>'#222222','dimension'=>'100x30','type'=>'Security','shape'=>'rectangle'),	
	array('id'=>4,'segment'=>3,'border_color'=>'#363636','border_px'=>'1','background_color'=>'#222222','dimension'=>'100x30','type'=>'Licenses','shape'=>'rectangle'),	
	array('id'=>5,'segment'=>3,'border_color'=>'#363636','border_px'=>'1','background_color'=>'#222222','dimension'=>'100x30','type'=>'3rdParties','shape'=>'rectangle'),	
);

$lines = array(
	array('id'=>2,'name'=>'Ownership','color'=>'#E60000','line_type'=>0,'segment'=>1,'order_no'=>1,'explanation'=>''),
	array('id'=>3,'name'=>'Name Change','color'=>'#2493f2','line_type'=>0,'segment'=>1,'order_no'=>2,'explanation'=>''),
	array('id'=>4,'name'=>'Security','color'=>'#ffaa00','line_type'=>0,'segment'=>2,'order_no'=>3,'explanation'=>''),
	array('id'=>5,'name'=>'License','color'=>'#E6E600','line_type'=>0,'segment'=>2,'order_no'=>4,'explanation'=>''),
	array('id'=>7,'name'=>'Release','color'=>'#70A800','line_type'=>0,'segment'=>3,'order_no'=>5,'explanation'=>''),
	array('id'=>8,'name'=>'License End','color'=>'#E38B4F','line_type'=>0,'segment'=>1,'order_no'=>6,'explanation'=>''),
);


function findLineType($name,$lines){	
	$data = array_values(array_filter($lines, function($v, $k) use ($name){
		return $v['name'] == $name;
	}, ARRAY_FILTER_USE_BOTH));
	return count($data) > 0 ? $data[0]['id'] : 0;
}

function findBoxType($type,$boxes){
	$data = array_values(array_filter($boxes, function($v, $k) use ($type) {
		return $v['type'] == $type;
	}, ARRAY_FILTER_USE_BOTH));	
	return count($data) > 0 ? $data[0]['id'] : 0;
}

function findBoxData($all_boxes,$box_type){
	$boxArray = array();
	if(count($all_boxes) > 0){
		foreach($all_boxes as $box){
			if($box['id'] == $box_type){
				array_push($boxArray,$box);
				break;
			}
		}
	}
	return $boxArray;
}

function findEntity($array, $name, $type) {
	$data = array_values(array_filter($array, function($v, $k) use ($name, $type) {
		return $v['name'] == $name && in_array($v['type'],$type);
	}, ARRAY_FILTER_USE_BOTH));
	return count($data) > 0 ? $data[0] : array();
}

function findLocalEntity($name, $list) {
	$updatedName = "";
	foreach($list as $representatives){
		if($representatives['original_name'] == $name && $representatives['representative_name'] != '' && $representatives['representative_name'] != null){
			$updatedName = $representatives['representative_name'];
			break;
		}
	}
	return $updatedName;
}

function findIndex($array,$findValue){
	$findArray = array();
	foreach($array as $list){
		if($list['id'] == $findValue){
			array_push($findArray,$list);
			break;
		}
	}
	return $findArray;
}

function findLineColorType($type,$list){
	$data = array('color'=>'','line_type'=>'','tooltip'=>'');
	foreach($list as $line){
		if($line['id'] == $type){
			$data['color'] = $line['color'];
			$data['line_type'] = $line['line_type'];
			$data['tooltip'] = $line['name'];
			break;
		}
	}
	return $data;
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

if (mysqli_connect_errno()) {	
  exit();  
} else {
	
	if(isset($_REQUEST['rf_id']) && $_REQUEST['rf_id'] != ''){
		$serverPath = '/var/www/html/PatenTrack/resources/shared/data/';
		$patentNumber = trim($_REQUEST['rf_id']);
		$data = array('box'=>array(),'inventor_boxes'=>array(),'connection'=>array(),'popup'=>array(),'assignments'=>array(),'names'=>array());
		if($patentNumber != null && $patentNumber != '') {
			try{
				$data['general'] = array('original_number'=>$patentNumber,'patent_number'=>$patentNumber,'logo_1'=>'https://patentrack.com/resources/shared/images/company-default.png','logo_2'=>'https://patentrack.com/resources/shared/images/user-default.png','copyright'=>date('Y').' copyright PatenTrack.');		
				$localList = array();
				
				if(isset($_REQUEST['o']) && $_REQUEST['o'] > 0) {
					$queryOrg = "SELECT org_host, org_usr, org_pass, org_db, logo, name FROM db_business.organisation WHERE organisation_id = ".(int)$_REQUEST['o'];
					$resultOrg = $con->query($queryOrg);
					if($resultOrg && $resultOrg->num_rows > 0){
						$row = mysqli_fetch_object($resultOrg);
						
						if($row->logo != "" && $row->logo != null){
							$data['general']['logo_1'] = $row->logo;
						}
						$data['general']['copyright'] = date('Y'). ' '.$row->name;
						
						/*Connect locallist database*/
						if($row->org_host != null && $row->org_usr != null && $row->org_pass != null && $row->org_db != null){
							$orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
			
							if($orgConnect) {
								$queryRepresentative = "SELECT representative_id, original_name, representative_name FROM representative WHERE parent_id = 0";
								
								$resultRepresentative = $orgConnect->query($queryRepresentative);
								
								if($resultRepresentative && $resultRepresentative->num_rows > 0) {
									while($representative = $resultRepresentative->fetch_object()){
										$name = $representative->representative_name;
										if($name == '' || $name == null) {
											$name = $representative->original_name;
										}
										
										array_push($localList, array('original_name'=>$representative->original_name,'representative_name'=>$name));
										
										$queryChildRepresentative = "SELECT original_name, representative_name FROM representative WHERE parent_id = ".$representative->representative_id;
								
										$resultChildRepresentative = $orgConnect->query($queryChildRepresentative);
										if($resultChildRepresentative && $resultChildRepresentative->num_rows > 0) {
											while($representativeChild = $resultChildRepresentative->fetch_object()){
												$childName = $representativeChild->representative_name;
												if($childName == null || $childName == '') {
													$childName = $name;
												}
												
												array_push($localList, array('original_name'=>$representativeChild->original_name,'representative_name'=>$childName));
											}
										}										
									}
								}
							}
						}
						
					}					
				}
				
				if(isset($_REQUEST['u']) && $_REQUEST['u'] > 0) {
					$queryUser = "SELECT logo FROM db_business.users WHERE user_id = ".(int)$_REQUEST['u'];
					$resultUser = $con->query($queryUser);
					if($resultUser && mysqli_num_rows($resultUser) > 0){
						$row = mysqli_fetch_object($resultUser);
						if($row->logo != "" && $row->logo != null){
							$data['general']['logo_2'] = $row->logo;
						}
					}					
				}
				
				$data['box_menu'] = array('border_color'=>array('#e8665d','#e8a41c','#c1ed0e','#ed0e2f'),'background_color'=>array('#fae3e3','#f5f5d7','#d7f0f5','#f5d7dc'));
				$data['title'] = '';
				$data['comment'] = '';
				/*Find comments against patents*/
				
				
				
				/*Get List of all RFID for patent*/
				$assetType = 4;
				$queryDocument = "SELECT d.*, a.exec_dt, ass.frame_no, ass.reel_no, ass.convey_text, ass.record_dt, ac.convey_ty, ass.page_count, ass.cname, ass.caddress_1, ass.caddress_2, ac.employer_assign FROM documentid as d INNER JOIN assignor as a ON a.rf_id = d.rf_id INNER JOIN assignment as ass ON ass.rf_id = d.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ass.rf_id WHERE d.grant_doc_num = '".$patentNumber."' GROUP BY d.rf_id ORDER BY a.exec_dt ASC, ass.record_dt ASC";
				
				$inventorBoxID = findBoxType("Inventor", $boxes);
				
				$thirdPartyBoxID = findBoxType("3rdParties", $boxes);
				
				$resultDocument = $con->query($queryDocument);
				
				$increment = 1;
				$relation_increment = 1;
				$lead_patent_assignment = array();
				if($resultDocument && $resultDocument->num_rows == 0) {
					$queryDocument = "SELECT d.*, a.exec_dt, ass.frame_no, ass.reel_no, ass.convey_text, ass.record_dt, ac.convey_ty, ass.page_count, ass.cname, ass.caddress_1, ass.caddress_2 FROM documentid as d INNER JOIN assignor as a ON a.rf_id = d.rf_id INNER JOIN assignment as ass ON ass.rf_id = d.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ass.rf_id WHERE d.appno_doc_num = '".$patentNumber."' GROUP BY d.rf_id ORDER BY a.exec_dt ASC, ass.record_dt ASC";
					$assetType = 5;
					$resultDocument = $con->query($queryDocument);
				}
				$data['asset_type'] = $assetType;
				if($resultDocument && $resultDocument->num_rows > 0) {
					$documentList = array();
					$inventorNames = array();
					$assignmentList = array();
					$inventorList = array();
					$boxesList = array();
					$allRFIDs = array();
					$fillingDate = "";
					$grantDate = "";
					$applicationNumber = "";
					while($doc = $resultDocument->fetch_object()){
						/*print_r($doc);*/
						if($data['title'] == "") {
							$data['title'] = $doc->title;
						}	
						if($applicationNumber == "") {
							$applicationNumber = $doc->appno_doc_num;
						}						
						if($fillingDate == "") {
							$fillingDate = $doc->appno_date;
						}
						if($doc->grantDate != "" && $doc->grant_date != null && $doc->grantDate != '0000-00-00') {
							$grantDate = $doc->grantDate;
						}
						array_push($documentList, $doc);
						array_push($allRFIDs, $doc->rf_id);
					}
					
					
					
					
					
					
					
					$jsonRelations = array();
					$counter = 1;
					for($i = 0; $i < count($documentList); $i++){
						$doc = $documentList[$i];
						
						$file = 'assignment-pat-'.$doc->reel_no.'-'.$doc->frame_no.'.pdf';
						$fileName = "https://patentrack.com/resources/shared/data/".$file;
						/*echo $serverPath.$file."<br/>";
						$fileName = "";

						if(file_exists($_SERVER['DOCUMENT_ROOT']."/../PatenTrack/resources/shared/data/".$file)){
							$fileName = "https://patentrack.com/resources/shared/data/".$file;
						} else {
							echo $_SERVER['DOCUMENT_ROOT']."/../PatenTrack/resources/shared/data/$file<br/>";
						}*/
						//echo $fileName."<br/>";
						
						$conveyanceText = $doc->convey_text;
						$conveyanceType = $doc->convey_ty;
						$frameNo = $doc->frame_no;
						$reelNo = $doc->reel_no;
						$recorded = $doc->record_dt;
						$originalRecorded = $doc->record_dt;
						$executedDate = $doc->exec_dt;
						$originalExecutedDate = $doc->exec_dt;
						if($conveyanceType != 'addresschg'):
						$queryAssignee = "SELECT a.*, r.representative_name as normalize_name FROM assignee as a LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE a.rf_id = ".$doc->rf_id." GROUP BY a.rf_id, a.assignor_and_assignee_id";
						
						
						$resultAssignee = $con->query($queryAssignee);
						
						$assigneeList = array();
						$assignorList = array();
						
						if($resultAssignee && $resultAssignee->num_rows > 0) {
							while($assignee = $resultAssignee->fetch_object()) {
								array_push($assigneeList, $assignee) ;
							}
						}
						
						
						
						$queryAssignor = "SELECT a.*,  r.representative_name as normalize_name FROM assignor as a INNER JOIN assignment_conveyance as ac ON ac.rf_id = a.rf_id LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE a.rf_id = ".$doc->rf_id."  GROUP BY a.rf_id, a.assignor_and_assignee_id";
						
						/*echo $queryAssignor."<br/>";*/
						
						$resultAssignor = $con->query($queryAssignor);							
						
						if($resultAssignor && $resultAssignor->num_rows > 0) {
							while($assignor = $resultAssignor->fetch_object()) {
								array_push($assignorList, $assignor) ;
							}
						}
						
						$boxType = 0;
						$lineType = 0;
						
						switch(strtolower($conveyanceType)){
							case 'assignment':
								$boxType = findBoxType("Ownership", $boxes);
								$lineType = findLineType("Ownership",$lines);
								$description = "Ownership";
								break;
							case 'namechg':
								$boxType = findBoxType("Ownership", $boxes);
								$lineType = findLineType("Name Change",$lines);
								$description = "Name Change";
								break;
							case 'security':
								$boxType =  findBoxType("Security", $boxes);
								$lineType = findLineType("Security",$lines);
								$description = "Security";
								break;
							case 'release':
								$boxType = findBoxType("Security", $boxes);
								
								$lineType = findLineType("Release",$lines);
								$description = "Release";
								break;
							case 'correct':
								if(strpos(strtolower($conveyanceText),"security" ) !== false){
									$boxType =  findBoxType("Security", $boxes);
									$lineType = findLineType("Security",$lines);
									$description = "Security";
								} else {
									$boxType = findBoxType("Ownership", $boxes);
									$lineType = findLineType("Ownership",$lines);
									$description = "Ownership";
								}
								break;
							case 'merger':
							case 'other':
							case 'missing':
							case 'govern':
							case 'employee':
							default:
								$boxType = findBoxType("Ownership", $boxes);
								$lineType = findLineType("Ownership",$lines);
								$description = "Ownership";
								break;
						}						
						/*if security not release or license not release*/
						if(strpos(strtolower($conveyanceType), "release") === false) {
							foreach($assigneeList as $assignee) {
								
								$assigneeID = 0;
								$creator = 0;
								
								/*Check Name From Local*/
								$check = false;
								if(count($localList) > 0) {									
									$name = findLocalEntity($assignee->ee_name, $localList);
									if($name != "") {
										$check = true;
									}
								} 
								
								if($check === false) {
									$name = $assignee->normalize_name;
									if($name == "" || $name == null) {
										$name = $assignee->ee_name;
									}
								}
								
								
								
								
								$entity = findEntity($lead_patent_assignment, $name, array(0,1));
								if(count($entity) > 0){
									$assigneeID = $entity['id'];
								}
								
								
								
								/*If assignee not exist insert it*/
								if($assigneeID === 0) {
									array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>1,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'document'=>$fileName,'assignment_no'=>$counter,'box_type'=>$boxType, 'assignment_type'=>strtolower($conveyanceType)));
									array_push($data['box'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>$description,'execution_date'=>date('M d,Y',strtotime($executedDate)),'recorded'=>$recorded, 'recorded_date'=>$recorded, 'type'=>$description,'document_file'=>$fileName,'document'=>$fileName,'date_1'=>$executedDate,'assignment_no'=>$counter,'box_type'=>$boxType));
									
									$assigneeID = $increment;
									$creator = $assigneeID;
									$increment++;
								}
								if($assigneeID > 0) {	
									
									$assignorBoxType = $boxType;
									$as1 = 0;
									
									foreach($assignorList as $assignor) {
										$cType = 2;
										
										$check = false;
										if(count($localList) > 0) {									
											$name = findLocalEntity($assignor->or_name, $localList);
											if($name != "") {
												$check = true;
											}
										} 
										
										if($check === false) {
											$name = $assignor->normalize_name;
											if($name == "" || $name == null) {
												$name = $assignor->or_name;
											}
										}
										
										$startCreatorID = 0;											
										$assignorID = 0;	
										$newName = "";
										if(count($inventorNames) > 0) {
											$checkBiblio = false;
											if(count($biblioInventor) > 0) {
												if(!in_array($name,$inventorNames)) {
													foreach($biblioInventor as $bibInventor) {
														if(strpos(strtolower($name), strtolower($bibInventor['given_name'])) !== false ){
															if(strpos(strtolower($name), strtolower($bibInventor['family_name'])) !== false){
																$cType = 0;
																$checkBiblio = true;
																$name = $bibInventor['name'];
																break;
															} else {
																if(count($connectionInventor) > 0 && count($biblioInventor) - 1 == $countInventor){
																	if(in_array($name, $connectionInventor)){
																		$cType = 0;
																		$checkBiblio = true;
																		$name = $bibInventor['name'];
																		break;
																	}
																}
															}															
														}
													}
												}
											}
											if($checkBiblio ===  false && in_array($name,$inventorNames)) {
												$cType = 0;
											}
										}
										
										$entity = findEntity($lead_patent_assignment, $name, array(0,1));
										
										if(count($entity) > 0){
											$assignorID = $entity['id'];
										} else {
											/*Check ThirdParty*/
											$entity = findEntity($lead_patent_assignment, $name, array(2));
											if(count($entity) > 0){
												$assignorID = $entity['id'];
											}
										}
										
										if($assignorID == 0){
											if($cType == 2) {
												$executedDate = '';
												$recorded = '';
												$assignorBoxType = $thirdPartyBoxID;
											}
											array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>$cType,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'document'=>$fileName,'assignment_no'=>$counter,'box_type'=>$assignorBoxType, 'assignment_type'=>strtolower($conveyanceType)));
											array_push($data['box'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>$description,'execution_date'=>date('M d,Y',strtotime($executedDate)),'recorded'=>$recorded, 'recorded_date'=>$recorded, 'type'=>$cType,'document_file'=>$fileName,'document'=>$fileName,'date_1'=>$executedDate,'assignment_no'=>0,'box_type'=>$assignorBoxType));
											$assignorID = $increment;
											$startCreatorID = $assignorID;
											$increment++;
										}
										if($assignorID > 0) {
											$connectionType = 1;
											array_push($jsonRelations,  array(
												'id'=>$relation_increment,
												'patent_number'=>$patentNumber,
												'parent_id'=>$assignorID,
												'child_id'=>$assigneeID,
												'connection_type'=>$connectionType,
												'frame'=>$frameNo,
												'reel'=>$reelNo,
												'description'=>$description,
												'date'=>date('Y-m-d',strtotime($assignor->exec_dt)),
												'assignment_no'=>$counter,
												'line_type'=>$lineType,
												'creator_id'=>$creator,
												'start_creator_id'=>$startCreatorID,'note_file'=>'','note'=>'')
											);
											$relation_increment++;
										}
										if($startCreatorID > 0){
											$startCreatorID = 0;
										}
										if($as1 == 0 && $creator > 0){
											$creator = 0;
										}
										$as1++;
									}
								}
							}/* end foreach loop*/
						} else if(strpos(strtolower($conveyanceType), "release") !== false) {
							
							$cpType = $boxType;
							/*license or security is released*/
							foreach($assigneeList as $assignee) {
								
								$assigneeID = 0;
								$creator = 0;
								$check = false;
								if(count($localList) > 0) {									
									$name = findLocalEntity($assignee->ee_name, $localList);
									if($name != "") {
										$check = true;
									}
								} 
								
								if($check === false) {
									$name = $assignee->normalize_name;
									if($name == "" || $name == null) {
										$name = $assignee->ee_name;
									}
								}
								
								
								
								
								$entity = findEntity($lead_patent_assignment, $name, array(0,1));
								if(count($entity) > 0){
									$assigneeID = $entity['id'];
								} else {
									$entity = findEntity($lead_patent_assignment, $name, array(2));
									if(count($entity) > 0){
										$assigneeID = $entity['id'];
									}
								}
								
								$boxType = $cpType ;
								/*if assignee not exist*/									
								if($assigneeID == 0) {	
									$boxType = $thirdPartyBoxID;
									array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>1,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'document'=>$fileName,'assignment_no'=>$counter,'box_type'=>$boxType, 'assignment_type'=>strtolower($conveyanceType)));
									array_push($data['box'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>$description,'execution_date'=>date('M d,Y',strtotime($executedDate)),'recorded'=>$recorded, 'recorded_date'=>$recorded, 'type'=>$description,'document_file'=>$fileName,'document'=>$fileName,'date_1'=>$executedDate,'assignment_no'=>0,'box_type'=>$boxType));
									$assigneeID = $increment;
									$creator = $assigneeID;
									$increment++;
								}
								if($assigneeID > 0) { 
									$as = 0;
									foreach($assignorList as $assignor) {
										
										
										$check = false;
										if(count($localList) > 0) {									
											$name = findLocalEntity($assignor->or_name, $localList);
											if($name != "") {
												$check = true;
											}
										} 
										
										if($check === false) {
											$name = $assignor->normalize_name;
											if($name == "" || $name == null) {
												$name = $assignor->or_name;
											}
										}
										
										$startCreatorID = 0;											
										$assignorID = 0;	
										
										
										$newName = "";
										if(count($inventorNames) > 0) {
											$checkBiblio = false;
											if(count($biblioInventor) > 0) {
												if(!in_array($name,$inventorNames)) {
													foreach($biblioInventor as $bibInventor) {
														if(strpos(strtolower($name), strtolower($bibInventor['family_name'])) !== false && strpos(strtolower($name), strtolower($bibInventor['given_name'])) !== false){
															$cType = 0;
															$checkBiblio = true;
															$name = $bibInventor['name'];
															break;
														}
													}
												}
											}
											if($checkBiblio ===  false && in_array($name,$inventorNames)) {
												$cType = 0;
											}
										}
										
										$entity = findEntity($lead_patent_assignment, $name, array(0,1));
										
										if(count($entity) > 0){
											$assignorID = $entity['id'];
										} else {
											/*Check ThirdParty*/
											$entity = findEntity($lead_patent_assignment, $name, array(2));
											if(count($entity) > 0){
												$assignorID = $entity['id'];
											}
										}
										
										if($assignorID == 0) {
											array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>1,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'document'=>$fileName,'assignment_no'=>$counter,'box_type'=>$boxType, 'assignment_type'=>strtolower($conveyanceType)));
											array_push($data['box'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>$description,'execution_date'=>date('M d,Y',strtotime($executedDate)),'recorded'=>$recorded, 'recorded_date'=>$recorded, 'type'=>$description,'document_file'=>$fileName,'document'=>$fileName,'date_1'=>$executedDate,'assignment_no'=>0,'box_type'=>$boxType));
											$assignorID = $increment;
											$startCreatorID = $assignorID;
											$increment++;
										}
										
										if($assignorID > 0) {
											$connectionType = 2;
											array_push($jsonRelations,  array('id'=>$relation_increment,'patent_number'=>$patentNumber,'parent_id'=>$assignorID,'child_id'=>$assigneeID,'connection_type'=>$connectionType,'frame'=>$frameNo,'reel'=>$reelNo,'description'=>$description,'date'=>date('Y-m-d',strtotime($assignor->exec_dt)),'assignment_no'=>$counter,'line_type'=>$lineType,'creator_id'=>$creator,'start_creator_id'=>$startCreatorID,'note_file'=>'','note'=>''));
											$relation_increment++;
										}
										if($startCreatorID > 0){
											$startCreatorID = 0;
										}
										if($as == 0 && $creator > 0){
											$creator = 0;
										}
										$as++;
									}
								}
							}
						}
						$counter++;
						endif;
					}
					if(count($data['box']) > 0){
						for($b=0;$b<count($data['box']);$b++){
							$boxType = -1;
							if(is_array($data['box'][$b])){
								$boxType = $data['box'][$b]['box_type'];
							} else {
								$boxType = $data['box'][$b]->box_type;
							}
							if($boxType >= 0){
								$findBoxData = findBoxData($boxes,$boxType);
								if(count($findBoxData) > 0){
									if(is_array($data['box'][$b])){
										$data['box'][$b]['shape'] = $findBoxData[0]['shape'];
										$data['box'][$b]['dimension'] = $findBoxData[0]['dimension'];
										$data['box'][$b]['segment'] = $findBoxData[0]['segment'];
										$data['box'][$b]['border_color'] = $findBoxData[0]['border_color'];
										$data['box'][$b]['border_linepx'] = $findBoxData[0]['border_px'];
										$data['box'][$b]['background_color'] = $findBoxData[0]['background_color'];
									} else {
										$data['box'][$b]->shape = $findBoxData[0]['shape'];
										$data['box'][$b]->dimension = $findBoxData[0]['dimension'];
										$data['box'][$b]->segment = $findBoxData[0]['segment'];
										$data['box'][$b]->border_color = $findBoxData[0]['border_color'];
										$data['box'][$b]->border_linepx = $findBoxData[0]['border_px'];
										$data['box'][$b]->background_color = $findBoxData[0]['background_color'];
									}
								}
							}
						}
					}
					
					$re = 0;
					foreach($jsonRelations as $relation){
						if($relation['connection_type'] == 2) {
							$parentID = $relation['parent_id'];
							$childID = $relation['child_id'];
							$pRelation = array();
							$parentData = findIndex($lead_patent_assignment,$childID);
							if(count($parentData) == 1){
								$childData = findIndex($lead_patent_assignment,$parentID);
								if(count($childData) == 1) {
									foreach($jsonRelations as $r){
										if($r['parent_id'] == $childID && $r['child_id'] == $parentID) {
											$pRelation = $r;
											break;
										}
									}
								}
							}
							if(is_object($pRelation)) {
								$pRelation['parent_name'] = $parentData[0]['name'];
								$pRelation['child_name'] = $childData[0]['name'];
								$jsonRelations[$re]['org_relation'] = $pRelation;
							}
						}
						$re++;
					}
						
					$jsRIncrement = 0;
					foreach($jsonRelations as $relation){		
						if((int)$relation['connection_type'] == 2) {			
							if(isset($relation->org_relation)) {
								$jsonRelations[$jsRIncrement]['reverse'] = true;
								$jsonRelations[$jsRIncrement]['reverse_frame'] = $relation['org_relation']['frame'];
								$jsonRelations[$jsRIncrement]['reverse_reel'] = $relation['org_relation']['reel'];
								$jsonRelations[$jsRIncrement]['reff_id'] = $relation['org_relation']['id'];
								$jsonRelations[$jsRIncrement]['assignment_no2'] = $relation['org_relation']['assignment_no'];
								$jsonRelations[$jsRIncrement]['note2'] = $relation['org_relation']['note'];
								$jsonRelations[$jsRIncrement]['note_file2'] = $relation['org_relation']['note_file'];
								
							}
						}
						$jsRIncrement++;
					}

					if(count($jsonRelations)>0){
						foreach($jsonRelations as $relation){
							$pouptop = $relation['reel'].'-'.$relation['frame'];
							$assignment_no1 = $relation['assignment_no'];
							$poupbottom = "";
							$reffID = 0;
							$assignment_no2 = 0;
							$document1 = "";
							$document2 = "";
							$note1 = $relation['note'];
							$note2 = "";
							$noteFile1 = $relation['note_file'];
							if($note1 == null){
								$note1 = "";
							}
							if($noteFile1 == null){
								$noteFile1 = "";
							}
							$noteFile2 = "";
							$sourceDIR = '/var/www/html/PatenTrack/';
							$fileName = "resources/shared/data/assignment-pat-".$pouptop.".pdf";
							$document1 = "https://patentrack.com/".$fileName;
							/*if(file_exists($sourceDIR.$fileName)){
								$document1 = "https://patentrack.com/".$fileName;
							}*/
							if(isset($relation['reverse']) && $relation['reverse'] === true) {
								$poupbottom = $relation['reverse_reel'].'-'.$relation['reverse_frame'];
								$reffID = $relation['reff_id'];
								$assignment_no2 = $relation['assignment_no2'];
								$note2 = $relation['note2'];
								$noteFile2 = $relation['note_file2'];
								$fileName = "resources/shared/data/assignment-pat-".$poupbottom.".pdf";
								$document2 = "https://patentrack.com/".$fileName;
								/*if(file_exists($sourceDIR.$fileName)){
									$document2 = "https=>//patentrack.com/".$fileName;
								}*/
							}
							$type = $relation['description'];
							$lineType = $relation['line_type'];
							$popup = array();
							$comments = array();
							if($pouptop != ""){
								array_push($popup,$pouptop);
								$noteArray = array();
								array_push($noteArray,$note1);
								array_push($noteArray,'');
								$com = array();
								$com[$pouptop] = $noteArray ;
								array_push($comments,$com);
							}
							if($poupbottom != ""){
								array_push($popup,$poupbottom);
								$noteArray = array();
								array_push($noteArray,$note2);
								array_push($noteArray,'');
								$com = array();
								$com[$poupbottom] = $noteArray ;
								array_push($comments,$com);
							}
							$findLineColorType = findLineColorType($lineType,$lines);
							$userFiles = array();
							array_push($userFiles,$noteFile1);
							if($poupbottom != ""){
								array_push($userFiles,$noteFile2);
							}
							$typeLine = "";
							if($findLineColorType['line_type'] == 0){
								$typeLine = "Solid";
							}else if($findLineColorType['line_type'] == 1){
								$typeLine = "Dashed";
							}
							array_push($data['connection'],array('id'=>$relation['id'],'assignment_no1'=>$assignment_no1,'color'=>$findLineColorType['color'],'type'=>$type,'type_line'=>$typeLine,'ref_id'=>$reffID,'start_id'=>$relation['parent_id'],'end_id'=>$relation['child_id'],'box_creator_id'=>$relation['creator_id'],'box_creator_id2'=>$relation['start_creator_id'],'popup'=>$popup,'comment'=>$comments,'user_files'=>$userFiles,'tooltip'=>$findLineColorType['tooltip'],'date'=>date('M d,Y',strtotime($relation['date'])),'date_1'=>strtotime($relation['date']),'document1'=>$document1,'document2'=>$document2,'note1'=>$note1,'pdf1'=>$noteFile1,'note2'=>$note2,'pdf2'=>$noteFile2,'popuptop'=>$pouptop,'popupbottom'=>$poupbottom));
						}				 
					}
					
					$data['all_boxes'] = $boxes;
					$data['legend'] = array(
										array("id"=>2,"tooltip"=>"Ownership","color"=>"#E60000","type"=>0,"explanation"=>""),
										array("id"=>3,"tooltip"=>"Name Change","color"=>"#2493f2","type"=>0,"explanation"=>""),
										array("id"=>4,"tooltip"=>"Security","color"=>"#ffaa00","type"=>0,"explanation"=>""),array("id"=>5,"tooltip"=>"License","color"=>"#E6E600","type"=>0,"explanation"=>""),array("id"=>7,"tooltip"=>"Release","color"=>"#70A800","type"=>0,"explanation"=>""),array("id"=>8,"tooltip"=>"License End","color"=>"#E38B4F","type"=>0,"explanation"=>""));
					$data['line'] = $data['connection'];
					
					$popsData = array();
					for($i = 0; $i < count($documentList); $i++){
						$rowTrans = $documentList[$i];
						$queryAssignment = "SELECT * FROM assignment WHERE rf_id =".$rowTrans->rf_id;
						
						$resultAssignment = $con->query($queryAssignment);
						
						$assignmentData = array();
						$assignees = array();
						$assigneesAddress1 = array();
						$assigneesAddress2 = array();
						$assigneesCity = array();
						$assigneesState = array();
						$assigneesCountryName = array();
						$assigneesPostCode = array();
						$assignors = array();
						$inventionTitle = array();
						$applNum = array();
						$patNum = array();
						$filingDate = array();
						$issueDate = array();
						$intlRegNum = array();
						$pctNum = array();
						$publDate = array();
						$publNum = array();
						$inventors = array();
						$applNumSize = 0;
						$patNumSize = 0;
						$inventionTitleFirst = "";
						$patAssignorEarliestExDate = "";
						$applNumFirst  = "";
						$filingDateFirst = "";
						$intlPublDateFirst = "";
						$intlRegNumFirst = "";
						$issueDateFirst = "";
						$patNumFirst = "";
						$publDateFirst = "";
						$publNumFirst = "";
						$queryAssigneeData = "SELECT a.*, r.representative_name as normalize_name FROM assignee as a LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE a.rf_id =".$rowTrans->rf_id."  GROUP BY a.rf_id, a.assignor_and_assignee_id";
							
							$resultAssignee = $con->query($queryAssigneeData);
							
							if($resultAssignee  && $resultAssignee->num_rows > 0) { 
								while($rowAssignee = $resultAssignee->fetch_object()) {
									$name = $rowAssignee->normalize_name;
									
									if($name == null || $name == '') {
										$name = $rowAssignee->ee_name;
									}
									array_push($assignees, $name);
									array_push($assigneesAddress1, $rowAssignee->ee_address_1);
									array_push($assigneesAddress2, $rowAssignee->ee_address_2);
									array_push($assigneesCity, $rowAssignee->ee_city);
									array_push($assigneesState, $rowAssignee->ee_state);
									array_push($assigneesCountryName, $rowAssignee->ee_country);
									array_push($assigneesPostCode, $rowAssignee->ee_postcode);
								}
							}
							
							$queryAssignorData = "SELECT a.or_name, r.representative_name as normalize_name, a.exec_dt FROM assignor as a LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE a.rf_id =".$rowTrans->rf_id. "  GROUP BY a.rf_id, a.assignor_and_assignee_id ORDER BY exec_dt ASC"; 
							
							$resultAssignor = $con->query($queryAssignorData);
							
							if($resultAssignor  && $resultAssignor->num_rows > 0) { 
								$a = 0;
								while($rowAssignor = $resultAssignor->fetch_object()) {
									$name = $rowAssignor->normalize_name;
									
									if($name == null || $name == '') {
										$name = $rowAssignor->or_name;
									}
									array_push($assignors, $name);
									if($a== 0) {
										$patAssignorEarliestExDate = $rowAssignor->exec_dt;
									}
									$a++;
								}
							}
							
							$extraQuery = "Select * from documentid WHERE rf_id =".$rowTrans->rf_id;
							
							$resultExtraQuery = $con->query($extraQuery);
							if($resultExtraQuery && $resultExtraQuery->num_rows > 0) {
								
								$applNumSize = $resultExtraQuery->num_rows;
								$patNumSize = $resultExtraQuery->num_rows;
								$ci = 0;
								$patAppNo = array();
								$extraRows = array();
								$grantNo = array();
								$appNo = array();
								while($rowExtra = $resultExtraQuery->fetch_object()) {
									array_push($extraRows,array('appno_doc_num'=>$rowExtra->appno_doc_num, 'grant_doc_num'=>$rowExtra->grant_doc_num));
									if($rowExtra->grant_doc_num != null && $rowExtra->grant_doc_num != '') {
										array_push($grantNo, "'".$rowExtra->grant_doc_num."'");
									} else {
										array_push($appNo, "'".$rowExtra->appno_doc_num."'");
									}
									
									array_push($inventionTitle, $rowExtra->title);
									array_push($applNum, $rowExtra->appno_doc_num ? $rowExtra->appno_doc_num : 'NULL');
									array_push($filingDate, $rowExtra->appno_date ? $rowExtra->appno_date : 'NULL');
									array_push($intlRegNum, "NULL");
									array_push($pctNum, "NULL");
									array_push($issueDate, $rowExtra->grant_date ? $rowExtra->grant_date : 'NULL');
									array_push($patNum, $rowExtra->grant_doc_num ? $rowExtra->grant_doc_num : 'NULL');
									array_push($publDate, $rowExtra->pgpub_date ? $rowExtra->pgpub_date : 'NULL');
									array_push($publNum, $rowExtra->pgpub_doc_num ? $rowExtra->pgpub_doc_num : 'NULL');
									if($ci == 0) {
										$inventionTitleFirst = $rowExtra->title;
										$applNumFirst = $rowExtra->appno_doc_num;
										$filingDateFirst = $rowExtra->appno_date;
										$intlPublDateFirst = "0001-01-01T00:00:00Z";
										$intlRegNumFirst = "NULL";
										$issueDateFirst = $rowExtra->grant_date;
										$patNumFirst = $rowExtra->grant_doc_num;
										$publDateFirst = $rowExtra->pgpub_date;
										$publNumFirst = $rowExtra->pgpub_doc_num;
									}
									
									$ci++;
								}
								
								$ci = 0;
								$queryInventors = "Select ac.* , r.representative_name as normalize_name, d.appno_doc_num, d.grant_doc_num from documentid as d INNER JOIN assignor as ac ON ac.rf_id = d.rf_id LEFT JOIN assignor_and_assignee as aaa ON ac.assignor_and_assignee_id = aaa.assignor_and_assignee LEFT JOIN representative as r.representative_id = aaa.representative_id INNER JOIN assignment_conveyance as acc ON ac.rf_id = acc.rf_id WHERE  acc.employer_assign = 1 AND (d.grant_doc_num IN (".implode(',',$grantNo ).") OR d.appno_doc_num IN (".implode(',',$appNo ).") )  GROUP BY ac.rf_id, acc.assignor_and_assignee_id ORDER BY ac.exec_dt ASC";
								$resultInventors = $con->query($queryInventors);
								
								if($resultInventors && $resultInventors->num_rows > 0){
									$allInventor = array();
									while($rowInventor = $resultInventors->fetch_object()){
										array_push($allInventor, $rowInventor);
									}									
									foreach($extraRows as $etRows) {
										$invent = array();
										if($etRows['appno_doc_num'] != '' && $etRows['appno_doc_num'] != null) {
											foreach($allInventor as $inventor){
												if($inventor->appno_doc_num == $etRows['appno_doc_num']){
													$name = $inventor->normalize_name;
													if($name == null || $name == '') {
														$name = $inventor->or_name;
													}
													array_push($invent, $name);
												}
											}
										} else {
											if($etRows['grant_doc_num'] != '' && $etRows['grant_doc_num'] != null) {
												foreach($allInventor as $inventor){
													if($inventor->grant_doc_num == $etRows['grant_doc_num']){
														$name = $inventor->normalize_name;
														if($name == null || $name == '') {
															$name = $inventor->or_name;
														}
														array_push($invent, $name);
													}
												}
											}
										}
										array_push($inventors, implode(',',$invent));
									}									
								} else {
									array_push($inventors, 'NULL');
								}							
							}
							array_push($popsData, array("id"=>$rowTrans->reel_no.'-'.$rowTrans->frame_no, "displayId"=>$rowTrans->reel_no.'-'.$rowTrans->frame_no, "reelNo"=>$rowTrans->reel_no, "frameNo"=>$rowTrans->frame_no, 'recordedDate'=> $rowTrans->record_dt, 'pageCount'=> $rowTrans->page_count, 'conveyanceText'=> $rowTrans->convey_text, 'recordedDate'=> $rowTrans->record_dt, 'corrName'=> $rowTrans->cname, 'corrAddress1'=> $rowTrans->caddress_1, 'corrAddress2'=> $rowTrans->caddress_2, 'patAssignorEarliestExDate'=> $patAssignorEarliestExDate, 'patAssignorName'=> $assignors, 'patAssigneeName'=> $assignees, 'patAssigneeAddress1'=> $assigneesAddress1, 'patAssigneeAddress2'=> $assigneesAddress2, 'patAssigneeCity'=> $assigneesCity, 'patAssigneeState'=> $assigneesState, 'patAssigneeCountryName'=> $assigneesCountryName, 'patAssigneePostcode'=> $assigneesPostCode, 'applNum'=>$applNum, 'filingDate'=>$filingDate, 'intlRegNum'=>$intlRegNum, 'inventionTitle'=>$inventionTitle, 'issueDate'=>$issueDate, 'patNum'=>$patNum, 'pctNum'=>$pctNum, 'publDate'=>$publDate, 'publNum'=>$publNum, 'inventors'=>$inventors,'applNumSize'=>$applNumSize,'patNumSize'=>$patNumSize,'inventionTitleFirst'=>$inventionTitleFirst,'applNumFirst'=>$applNumFirst,'filingDateFirst'=>$filingDateFirst,'intlPublDateFirst'=>$intlPublDateFirst,'intlRegNumFirst'=>$intlRegNumFirst,'issueDateFirst'=>$issueDateFirst,'patNumFirst'=>$patNumFirst,'publDateFirst'=>$publDateFirst,'publNumFirst'=>$publNumFirst));
					}
					
					$data['popup'] = $popsData;
					$data['general']['patent_number'] = $patentNumber." ".ucfirst(strtolower($data['title']));
					echo json_encode($data);
				}				
			} catch(Exception $e){
				
			}
		}
	}
}
?>
