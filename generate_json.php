<?php 

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);
error_reporting(-1);
//$con = new mysqli('localhost', 'db_user_all', 'wDv%5tgn0O0kMkMN', 'db_application');
$con = new mysqli('localhost', 'db_user_all', 'wDv%5tgn0O0kMkMN', 'db_uspto');
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
	array('id'=>4,'segment'=>1,'border_color'=>'#363636','border_px'=>'1','background_color'=>'#222222','dimension'=>'100x30','type'=>'Licenses','shape'=>'rectangle'),	
	array('id'=>5,'segment'=>3,'border_color'=>'#363636','border_px'=>'1','background_color'=>'#222222','dimension'=>'100x30','type'=>'3rdParties','shape'=>'rectangle'),	
);

$lines = array(
	array('id'=>2,'name'=>'Ownership','color'=>'#E60000','line_type'=>0,'segment'=>1,'order_no'=>1,'explanation'=>''),
	array('id'=>3,'name'=>'Name Change','color'=>'#2493f2','line_type'=>0,'segment'=>1,'order_no'=>2,'explanation'=>''),
	array('id'=>4,'name'=>'Security','color'=>'#ffaa00','line_type'=>0,'segment'=>2,'order_no'=>3,'explanation'=>''),
	array('id'=>5,'name'=>'License','color'=>'#C0C000','line_type'=>0,'segment'=>2,'order_no'=>4,'explanation'=>''),
	array('id'=>7,'name'=>'Release','color'=>'#70A800','line_type'=>0,'segment'=>3,'order_no'=>5,'explanation'=>''),
	array('id'=>8,'name'=>'License End','color'=>'#E38B4F','line_type'=>0,'segment'=>1,'order_no'=>6,'explanation'=>''),
	array('id'=>9,'name'=>'Correct','color'=>'#FFFFFF','line_type'=>1,'segment'=>1,'order_no'=>7,'explanation'=>''),
	array('id'=>10,'name'=>'Partial Release','color'=>'#70A800','line_type'=>1,'segment'=>1,'order_no'=>8,'explanation'=>''),
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
		return strtolower($v['name']) == strtolower($name) && in_array($v['type'],$type);
	}, ARRAY_FILTER_USE_BOTH));	
    /* echo "<pre>";
    print_r($array);
    print_r($data); */
	
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

function findBoxLineType($conveyanceType, $boxes, $lines, $conveyanceText) {
	$boxType = 0;
	$lineType = 0;
	$description = '';
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
		case 'license':
			$boxType =  findBoxType("Licenses", $boxes);
			$lineType = findLineType("License",$lines);
			$description = "License";
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
		case 'partialrelease':
			$boxType = findBoxType("Security", $boxes);
			
			$lineType = findLineType("Partial Release",$lines);
			$description = "Partial Release";
			break;
		case 'correct':
			if(strpos(strtolower($conveyanceText),"security" ) !== false){
				$boxType =  findBoxType("Security", $boxes);
				$lineType = findLineType("Correct",$lines);
				$description = "Correct";
			} else {
				$boxType = findBoxType("Ownership", $boxes);
				$lineType = findLineType("Correct",$lines);
				$description = "Correct";
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
	return array('boxType'=>$boxType, 'lineType' => $lineType, 'description' => $description);
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
	if(isset($_REQUEST['p']) && $_REQUEST['p'] != ''){
		$serverPath = '/var/www/html/PatenTrack/resources/shared/data/';
		$patentNumber = trim($_REQUEST['p']);
		$data = array('box'=>array(),'inventor_boxes'=>array(),'connection'=>array(),'popup'=>array(),'assignments'=>array(),'names'=>array());
		
		if($patentNumber != null && $patentNumber != '') {
			$originalPatentNumber = $patentNumber;
			try{
				$firstString = substr($patentNumber, 0, 1);
				if(!is_numeric($firstString)){
					$secondString = substr($patentNumber, 1, 1); 
					if(is_numeric($secondString) && (int)$secondString == 0) {
						$patentNumber = substr($patentNumber, 0, 1).substr($patentNumber, 2);
					}
				}
				 
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
				
				$queryDocument = "SELECT d.*, a.exec_dt, ass.rf_id, ass.frame_no, ass.reel_no, ass.convey_text, ass.record_dt, ac.convey_ty, ass.page_count, ass.cname, ass.caddress_1, ass.caddress_2, ass.status as assignment_status FROM documentid as d INNER JOIN assignor as a ON a.rf_id = d.rf_id INNER JOIN assignment as ass ON ass.rf_id = d.rf_id INNER JOIN representative_assignment_conveyance as ac ON ac.rf_id = ass.rf_id ";
				
				if(isset($_REQUEST['f']) && $_REQUEST['f'] >= 0) {
					if($_REQUEST['f'] == 1) {
						$queryDocument .= " WHERE d.grant_doc_num = '".$patentNumber."' " ;
					} else {
						$assetType = 5;
						$queryDocument .= " WHERE d.appno_doc_num = '".$patentNumber."' " ;
					}
				} else {
					$queryDocument .= " WHERE d.grant_doc_num = '".$patentNumber."' " ;
				}
				
				
				
				/*if(isset($_REQUEST['o']) && $_REQUEST['o'] > 0) {
					$queryDocument .= " AND d.rf_id IN (SELECT rf_id FROM list2 WHERE organisation_id = ".$_REQUEST['o'].") ";
				}*/
				
				
				
				
				$queryDocument .= " GROUP BY d.rf_id ORDER BY a.exec_dt ASC, ass.record_dt ASC";
				

				
				
				$inventorBoxID = findBoxType("Inventor", $boxes);
				
				$thirdPartyBoxID = findBoxType("3rdParties", $boxes);
				
				$resultDocument = $con->query($queryDocument);
				
				$increment = 1;
				$relation_increment = 1;
				$lead_patent_assignment = array();

				
				
				if($resultDocument && $resultDocument->num_rows == 0 && !isset($_REQUEST['f'])) {
					
					$queryDocument = "SELECT d.*, a.exec_dt, ass.rf_id, ass.frame_no, ass.reel_no, ass.convey_text, ass.record_dt, ac.convey_ty, ass.page_count, ass.cname, ass.caddress_1, ass.caddress_2, ass.status as assignment_status FROM documentid as d INNER JOIN assignor as a ON a.rf_id = d.rf_id INNER JOIN assignment as ass ON ass.rf_id = d.rf_id INNER JOIN representative_assignment_conveyance as ac ON ac.rf_id = ass.rf_id WHERE d.appno_doc_num = '".$patentNumber."' ";
					
					/*if(isset($_REQUEST['o']) && $_REQUEST['o'] > 0) {
						$queryDocument .= " AND d.rf_id IN (SELECT rf_id FROM list2 WHERE organisation_id = ".$_REQUEST['o'].") ";
					}*/
					
					$queryDocument .= " GROUP BY d.rf_id ORDER BY a.exec_dt ASC, ass.record_dt ASC";
					
					$assetType = 5;
					$resultDocument = $con->query($queryDocument) ;
					
					if($resultDocument && $resultDocument->num_rows == 0) {
						$queryDocument = "SELECT d.*, a.exec_dt, ass.rf_id, ass.frame_no, ass.reel_no, ass.convey_text, ass.record_dt, ac.convey_ty, ass.page_count, ass.cname, ass.caddress_1, ass.caddress_2, ass.status as assignment_status FROM documentid as d INNER JOIN assignor as a ON a.rf_id = d.rf_id INNER JOIN assignment as ass ON ass.rf_id = d.rf_id INNER JOIN representative_assignment_conveyance as ac ON ac.rf_id = ass.rf_id WHERE d.appno_doc_num = '".$patentNumber."' GROUP BY d.rf_id ORDER BY a.exec_dt ASC, ass.record_dt ASC";
						$resultDocument = $con->query($queryDocument) ;
					}
				} /* else {
					$queryDocument = "SELECT d.*, a.exec_dt, ass.rf_id, ass.frame_no, ass.reel_no, ass.convey_text, ass.record_dt, ac.convey_ty, ass.page_count, ass.cname, ass.caddress_1, ass.caddress_2, ass.status as assignment_status FROM documentid as d INNER JOIN assignor as a ON a.rf_id = d.rf_id INNER JOIN assignment as ass ON ass.rf_id = d.rf_id INNER JOIN representative_assignment_conveyance as ac ON ac.rf_id = ass.rf_id WHERE d.grant_doc_num = '".$patentNumber."' GROUP BY d.rf_id ORDER BY a.exec_dt ASC, ass.record_dt ASC";
					$resultDocument = $con->query($queryDocument) ;
				}*/

				
				$applicationNumber = "";
				if($resultDocument && $resultDocument->num_rows == 0) {
					/**
					 * Check From applicant
					 */
					$queryDocument  = '';
					if($_REQUEST['f'] == 1) {
						$queryDocument = "SELECT title, appno_doc_num, appno_date, grant_doc_num, grant_date, 0 AS rf_id  FROM db_patent_application_bibliographic.application_grant WHERE grant_doc_num = '".$patentNumber."' OR grant_doc_num = '".$originalPatentNumber."'  LIMIT 1" ;
					} else {
						$assetType = 5;
						$queryDocument = "SELECT title, appno_doc_num, appno_date, '' AS grant_doc_num, '' AS grant_date, 0 AS rf_id FROM db_patent_grant_bibliographic.application_publication WHERE appno_doc_num = '".$patentNumber."' or appno_doc_num = '".$originalPatentNumber."' LIMIT 1" ;
					}
					$resultDocument = $con->query($queryDocument) ;
					if($resultDocument && $resultDocument->num_rows > 0) {
						$rowD = $resultDocument->fetch_object();
						$applicationNumber = $rowD->appno_doc_num;
						$queryCheck = "SELECT COUNT(rf_id) AS numRows FROM db_uspto.documentid WHERE appno_doc_num = '".$rowD->appno_doc_num."'";
						
						$resultCheck = $con->query($queryCheck);
						if($resultCheck){
							$rowCheck = $resultCheck->fetch_object();  
							if($rowCheck->numRows > 0) {
								$queryDocument = "SELECT d.*, a.exec_dt, ass.rf_id, ass.frame_no, ass.reel_no, ass.convey_text, ass.record_dt, ac.convey_ty, ass.page_count, ass.cname, ass.caddress_1, ass.caddress_2, ass.status as assignment_status FROM documentid as d INNER JOIN assignor as a ON a.rf_id = d.rf_id INNER JOIN assignment as ass ON ass.rf_id = d.rf_id INNER JOIN representative_assignment_conveyance as ac ON ac.rf_id = ass.rf_id WHERE d.appno_doc_num = '".$rowD->appno_doc_num."' GROUP BY d.rf_id ORDER BY a.exec_dt ASC, ass.record_dt ASC";
								$resultDocument = $con->query($queryDocument) ;
							} else {
								$resultDocument = $con->query($queryDocument) ;
							}
						} else {
							$resultDocument = $con->query($queryDocument) ;
						}
					}
				} 
				$data['asset_type'] = $assetType;				
				$allAssignors = array();
				if($resultDocument && $resultDocument->num_rows > 0) {
					
					$documentList = array();
					$inventorNames = array();
					$assignmentList = array();
					$inventorList = array();
					$boxesList = array();
					$allRFIDs = array();
					$fillingDate = "";
					$grantDate = "";
					
					while($doc = $resultDocument->fetch_object()) { 

						if(isset($doc->title) && $data['title'] == "") {
							$data['title'] = $doc->title;
						}	
						if($applicationNumber == "") {
							$applicationNumber = $doc->appno_doc_num;
						}						
						if($fillingDate == "") {
							$fillingDate = $doc->appno_date;
						}
						if($doc->grant_date != "" && $doc->grant_date != null && $doc->grant_date != '0000-00-00') {
							$grantDate = $doc->grant_date;
						}

						$queryAssignee = "SELECT a.*, aaa.name, r.representative_name as normalize_name FROM assignee as a LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE a.rf_id = ".$doc->rf_id." GROUP BY a.rf_id, a.assignor_and_assignee_id";
							
							
						$resultAssignee = $con->query($queryAssignee);
						$assigneeList = array();
						$assignorList = array();
						
						if($resultAssignee && $resultAssignee->num_rows > 0) {
							while($assignee = $resultAssignee->fetch_object()) {
								array_push($assigneeList, $assignee) ;
							}
						}
					
					
						$queryAssignor = "SELECT a.*, aaa.name, r.representative_name as normalize_name FROM assignor as a LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE a.rf_id = ".$doc->rf_id."  GROUP BY a.rf_id, a.assignor_and_assignee_id";
						
						/*echo $queryAssignor."<br/>";*/
						
						$resultAssignor = $con->query($queryAssignor);							
						
						if($resultAssignor && $resultAssignor->num_rows > 0) {
							while($assignor = $resultAssignor->fetch_object()) {
								$name = $assignor->normalize_name;
								if($name == null || $name == ''){
									$name = $assignor->name;
								}
								array_push($allAssignors, $name);
								array_push($assignorList, $assignor) ;
							}
						}

						$doc->assignees = $assigneeList;
						$doc->assignors = $assignorList;
						array_push($documentList, $doc);
						if($doc->rf_id > 0) {
							array_push($allRFIDs, $doc->rf_id);
						}
					} 

					/**
					 * Shuffle rfID
					 * If Execution date is same with previous rf ID and Previous Assignor name is same as RFID Asignee name swap row
					 */

					if(count($documentList) > 0) {
						for($i = 1; $i < count($documentList); $i++) {
							/**
							 * Check Execution Date of the transaction
							 */
							if($documentList[$i]->exec_dt == $documentList[$i-1]->exec_dt) {
								$currentAssigneesList = $documentList[$i]->assignees;
								$previousAssignorsList = $documentList[$i-1]->assignors;

								$curAssignorNames = array();
								$curAssigneeNames = array();

								foreach($previousAssignorsList  as $or) {
									$name = $or->normalize_name;
									if($name == null || $name == ''){
										$name = $or->name;
										array_push($curAssignorNames, $name);
									}
								}

								foreach($currentAssigneesList  as $ee) {
									$name = $ee->normalize_name;
									if($name == null || $name == ''){
										$name = $ee->name;
										array_push($curAssigneeNames, $name);
									}
								}

								$intersectArray = array_intersect($curAssigneeNames, $curAssignorNames);

								if(count($intersectArray) > 0) {
									/** 
									 * Found Assignee in Assignor
									 */
									$prev = $documentList[$i-1];
									$documentList[$i-1] = $documentList[$i];
									$documentList[$i] = $prev;
								}
							}
						}
					}

					
					/*echo $grantDate;
					die;*/
					$biblioInventor = array();
					if($applicationNumber != "") {
						$grantInventorApp = $applicationNumber;
						if(substr($grantInventorApp, 0, 1) == '0'){
							$grantInventorApp = substr($grantInventorApp, 1);
						}
						$queryBiblioInventor = "SELECT inv.*, r.representative_name  as normalize_name  FROM db_patent_application_bibliographic.inventor AS inv INNER JOIN db_patent_application_bibliographic.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = inv.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id WHERE appno_doc_num = '".$grantInventorApp."' GROUP BY aaa.name";
						
						$resultBiblioInventor = $con->query($queryBiblioInventor);
						
						if($resultBiblioInventor && $resultBiblioInventor->num_rows == 0) {
							$queryBiblioInventor = "SELECT inv.*, r.representative_name  as normalize_name  FROM db_patent_grant_bibliographic.inventor_new AS inv LEFT JOIN db_patent_application_bibliographic.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = inv.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id  WHERE appno_doc_num = '".$applicationNumber."' GROUP BY aaa.name";
							$resultBiblioInventor = $con->query($queryBiblioInventor);
						}
						 
						if($resultBiblioInventor && $resultBiblioInventor->num_rows > 0) {
							while($inventorN = $resultBiblioInventor->fetch_object()) {
								$name = $inventorN->normalize_name != '' && $inventorN->normalize_name != null ? $inventorN->normalize_name : $inventorN->family_name ." ".$inventorN->given_name . " ".$inventorN->middle_name ;
								//$name = removeDoubleSpace( $name );
								//$name = strReplace( $name );
								/* $findString = 0;
								$stringC = remove_if_trailing($name, "corporation");
								if($stringC[1] === 0) {
									$stringC = remove_if_trailing($name, "incorporated");
									if($stringC[1] === 0) {
										$stringC = remove_if_trailing($name, "limited");
										if($stringC[1] === 0) {
											$stringC = remove_if_trailing($name, "company");
											if($stringC[1] === 1) {
												$findString = $stringC[1];
												$name = removeDoubleSpace($stringC[0]);
											}
										} else {
											$findString = $stringC[1];
											$name = removeDoubleSpace($stringC[0]);
										}	
									} else {
										$findString = $stringC[1];
										$name = removeDoubleSpace($stringC[0]);
									}
								} else {
									$findString = $stringC[1];
									$name = removeDoubleSpace($stringC[0]);
								} */
								$givenName = $inventorN->given_name;
								//$givenName = removeDoubleSpace( $givenName );
								//$givenName = strReplace( $givenName );
								$familyName = $inventorN->family_name;
								//$familyName = removeDoubleSpace( $familyName );
								//$familyName = strReplace( $familyName );
								
								
								array_push($inventorNames, $name);
								array_push($biblioInventor, array('name'=>$name, 'family_name'=>$familyName, 'given_name'=>$givenName, 'normalize'=>$inventorN->normalize_name ));
								
								array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>'Inventor','execution_date'=>$fillingDate, 'recorded'=>'', 'type'=>0, 'reel_no'=>'', 'frame_no'=>'', 'document_file'=>'','document'=>'', 'box_type'=>$inventorBoxID,"flag"=>0));
								array_push($data['inventor_boxes'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>'Inventor','execution_date'=>$fillingDate,'recorded'=>$grantDate, 'recorded_date'=>$grantDate, 'type'=>'Inventor','document_file'=>'','document'=>'','date_1'=>$fillingDate,'assignment_no'=>0,'box_type'=>$inventorBoxID,"flag"=>0));
								$increment++;
							}
						}
					} 
					/*Get Inventor List RFID*/
					if(count($inventorNames) == 0 && count($allRFIDs) > 0) {
						$queryInventor = "SELECT acc.or_name, r.representative_name as normalize_name, acc.exec_dt FROM representative_assignment_conveyance as c INNER JOIN assignor as acc ON acc.rf_id = c.rf_id LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = acc.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE c.rf_id IN (".implode(',', $allRFIDs).") AND c.convey_ty IN ('assignment', 'employee', 'partialassignment', 'correct') AND c.employer_assign = 1 GROUP BY or_name, normalize_name ORDER BY acc.exec_dt ASC";
					
						$resultInventor = $con->query($queryInventor);
						
						if($resultInventor && $resultInventor->num_rows > 0) {
							while($inventor = $resultInventor->fetch_object()) {
								
								$name = $inventor->normalize_name;
								if($name == "" || $name == null) {
									$name = $inventor->or_name;
								}
								
								array_push($inventorNames, $name);
								array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>'Inventor','execution_date'=>$fillingDate, 'recorded'=>'', 'type'=>0, 'reel_no'=>'', 'frame_no'=>'', 'document_file'=>'','document'=>'', 'box_type'=>$inventorBoxID,"flag"=>0));
								array_push($data['inventor_boxes'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'description'=>'Inventor','execution_date'=>$fillingDate,'recorded'=>$grantDate, 'recorded_date'=>$grantDate, 'type'=>'Inventor','document_file'=>'','document'=>'','date_1'=>$fillingDate,'assignment_no'=>0,'box_type'=>$inventorBoxID,"flag"=>0));
								$increment++;
							}
						}
					}
					
					
					
					
					
					/*$connectionInventor = array();
					$countInventor = 0;
					if(count($biblioInventor) > 0) {
						foreach($biblioInventor as $inv) {
							foreach($allAssignors as $name) {
								if()
								if(strpos(strtolower($name), strtolower($inv['given_name'])) !== false ){
									if(strlen($inv['family_name']) > 2 && strpos(strtolower($name), strtolower($inv['family_name'])) === false){
										array_push($connectionInventor, $name);
									} else {
										$countInventor++;
									}	
									break;
								} else if(strlen($inv['family_name']) > 2 && strpos(strtolower($name), strtolower($inv['family_name'])) !== false){
									array_push($connectionInventor, $name);
								}
							}							
						}
					}*/
					
					
					
					$data['box'] = $data['inventor_boxes'];
					$jsonRelations = array();
					$counter = 1;
					$staticPath = "https://s3-us-west-1.amazonaws.com/static.patentrack.com/assignments/var/www/html/beta/resources/shared/data/";
					$usptoStaticPath = "https://legacy-assignments.uspto.gov/assignments/";
					$fileNoFoundAssignmentsRfID = array();

					if(count($allRFIDs) > 0) {
						for($i = 0; $i < count($documentList); $i++){
							$doc = $documentList[$i];
							
							$fileStaticName = count($allRFIDs) > 0 ? 'assignment-pat-'.$doc->reel_no.'-'.$doc->frame_no : '';
							$file = $fileStaticName.'.pdf';
							$formFileName = $fileStaticName."_form.pdf";
							$agreementFileName = $fileStaticName."_agreement.pdf";
							if(count($allRFIDs) > 0) {
								if((int)$doc->assignment_status == 0) {
									$fileName = $usptoStaticPath.$file;
									$fileForm = $usptoStaticPath.$formFileName;
									$fileAgreement = $usptoStaticPath.$agreementFileName;
								} else {
									$fileName = $staticPath.$file;
									$fileForm = $staticPath.$formFileName;
									$fileAgreement = $staticPath.$agreementFileName;
								}	
							}
							//agreement file is not found
							if((int)$doc->assignment_status == 2) {
								$fileName = "";
								$fileForm = "";
								$fileAgreement = "";
								array_push($fileNoFoundAssignmentsRfID, $doc->reel_no.$doc->frame_no);
							}
							
							/*
							if(file_exists($sourceDIR."resources/shared/data/".$file)){
								$fileName = "https://patentrack.com/resources/shared/data/".$file;
								$fileForm = "https://patentrack.com/resources/shared/data/".$file;
								$fileAgreement = "https://patentrack.com/resources/shared/data/".$file;
								$rfIDNO = $doc->reel_no.'-'.$doc->frame_no;
								$formFileName = "resources/shared/data/assignment-pat-".$rfIDNO."_form.pdf";
								if(file_exists($sourceDIR.$formFileName)){
									$fileForm = "https://patentrack.com/".$formFileName;
								}
								$agreementFileName = "resources/shared/data/assignment-pat-".$rfIDNO."_agreement.pdf";
								if(file_exists($sourceDIR.$agreementFileName)){
									$fileAgreement = "https://patentrack.com/".$agreementFileName;
								}
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
								
								
								$assigneeList = $doc->assignees;
								$assignorList = $doc->assignors;
								
								
								$findBoxLineType = findBoxLineType(strtolower($conveyanceType), $boxes, $lines, $conveyanceText);
								
								$boxType = $findBoxLineType['boxType'];
								$lineType = $findBoxLineType['lineType'];
								$description = $findBoxLineType['description'];
							
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
											array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'original_name'=> $assignee->original_name,'description'=>$description,'other_execution_date'=>$originalExecutedDate,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>1,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'document'=>$fileName,'document_form'=>$fileForm,'document_agreement'=>$fileAgreement,'assignment_no'=>$counter,'box_type'=>$boxType, 'assignment_type'=>strtolower($conveyanceType),"flag"=>0));
											array_push($data['box'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'original_name'=> $assignee->original_name, 'description'=>$description,'other_execution_date'=>$originalExecutedDate,'execution_date'=>date('M d,Y',strtotime($executedDate)),'recorded'=>$recorded, 'recorded_date'=>$recorded, 'type'=>$description,'document_file'=>$fileName,'document'=>$fileName,'document_form'=>$fileForm,'document_agreement'=>$fileAgreement,'date_1'=>$executedDate,'assignment_no'=>$counter,'box_type'=>$boxType,"flag"=>0));
											
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
												
												
												
												$entity = findEntity($lead_patent_assignment, $name, array(1));
												
												/*echo "<br/>NAME: ".$name."<br/>";*/
												$findName = false;
												if(count($entity) > 0){
													$assignorID = $entity['id'];
												} else {
													if(count($inventorNames) > 0) {
														$checkBiblio = false; 
														if(count($biblioInventor) > 0) {
															if(!in_array($name,$inventorNames)) {
																foreach($biblioInventor as $bibInventor) {
																	if (
																	    preg_replace('/\s+/', ' ', strtolower(trim($name))) == preg_replace('/\s+/', ' ', strtolower(trim($bibInventor['name']))) ||
																	    preg_replace('/\s+/', ' ', strtolower(trim($name))) == preg_replace('/\s+/', ' ', strtolower(trim($bibInventor['normalize'])))
																	) {
                                                                        
																		$cType = 0;
																		$checkBiblio = true;
																		$name = $bibInventor['normalize'] != '' && $bibInventor['normalize'] != null ? $bibInventor['normalize'] : $bibInventor['name'];
																		$findName = true;
																		break;
																	}
																}
																if(!$findName) {
																	/*echo "Loop End dint find name ". strtolower($name)."@@<br/>";*/
																	foreach($biblioInventor as $bibInventor) {
																		$explodeName = explode(" ", $name);
																		$start = 0;
																		/* echo "NAME: ".$bibInventor['name']."<br/>";
																		echo "<pre>";
																		print_r($explodeName); */
																		foreach($explodeName as $n) {
																			if(strpos(strtolower($bibInventor['name']), strtolower(trim($n))) !== false){
																				$start++;
																			}
																		}
																		/* echo "START: ".$start."<br/>"; */
																		if($start > 1) {
																			$cType = 0;
																			$checkBiblio = true;
																			$name = $bibInventor['name'];
																			break;
																		}
																		/* if(strlen($bibInventor['family_name']) > 2 && strpos(strtolower($name), strtolower($bibInventor['family_name'])) !== false){
																			
																			$cType = 0;
																			$checkBiblio = true;
																			$name = $bibInventor['name'];
																			break;
																		} else if(strpos(strtolower($name), strtolower($bibInventor['given_name'])) !== false  || strpos(strtolower($bibInventor['given_name']), strtolower($name)) !== false){
																			if(strlen($bibInventor['family_name']) > 2 && strpos(strtolower($name), strtolower($bibInventor['family_name'])) !== false){
																				$cType = 0;
																				$checkBiblio = true;
																				$name = $bibInventor['name'];
																				break;
																			} else if(strlen($bibInventor['family_name']) < 2){
																				$cType = 0;
																				$checkBiblio = true;
																				$name = $bibInventor['name'];
																				break;
																			} else {
																				$explodeName = explode(" ", $name);
																				$start = 0;
																				foreach($explodeName as $n) {
																					if(strpos(strtolower($bibInventor['given_name']), strtolower($n)) !== false){
																						$start++;
																					}
																				}
																				if($start > 1) {
																					$cType = 0;
																					$checkBiblio = true;
																					$name = $bibInventor['name'];
																					break;
																				}
																			}																
																		} else {
																			$explodeName = explode(" ", $name);
																			$start = 0;
																			foreach($explodeName as $n) {
																				if(strpos(strtolower($bibInventor['given_name']), strtolower($n)) !== false){
																					$start++;
																				}
																			}
																			if($start > 1) {
																				$cType = 0;
																				$checkBiblio = true;
																				$name = $bibInventor['name'];
																				break;
																			}
																		} */												
																	}
																}
															}
														}
														if($checkBiblio ===  false && in_array($name,$inventorNames)) {
															$cType = 0;
														}
													}
                                                    /*echo "<br/>NAME: ".$name."<br/>";*/
													$entity = findEntity($lead_patent_assignment, $name, array(0));
													/*echo "<pre>";
													print_r($entity);*/
													if(count($entity) > 0){
														$assignorID = $entity['id'];
													} else {
														/*Check ThirdParty*/
														$entity = findEntity($lead_patent_assignment, $name, array(2));
														if(count($entity) > 0){
															$assignorID = $entity['id'];
														}
													}
												}
												/* print_r($biblioInventor);
												echo "<br/>CTYPE: ".$cType."<br/>";
												echo "NAME: ".$name."<br/>"; */
												
												if($assignorID == 0){
													if($cType == 2) {
														$executedDate = '';
														$recorded = '';
														$assignorBoxType = $thirdPartyBoxID;
													}
													array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'original_name'=> $assignor->original_name,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>$cType,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'document'=>$fileName,'document_form'=>$fileForm,'document_agreement'=>$fileAgreement,'assignment_no'=>$counter,'box_type'=>$assignorBoxType, 'assignment_type'=>strtolower($conveyanceType),"flag"=>0));
													array_push($data['box'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'original_name'=> $assignor->original_name,'description'=>$description,'other_execution_date'=>$originalExecutedDate,'execution_date'=>date('M d,Y',strtotime($executedDate)),'recorded'=>$recorded, 'recorded_date'=>$recorded, 'type'=>$cType,'document_file'=>$fileName,'document'=>$fileName,'document_form'=>$fileForm,'document_agreement'=>$fileAgreement,'date_1'=>$executedDate,'assignment_no'=>0,'box_type'=>$assignorBoxType,"flag"=>0));
													$assignorID = $increment;
													$startCreatorID = $assignorID;
													$increment++;
												}
												
												
												if($assignorID > 0) {
													$connectionType = 1;
													array_push($jsonRelations,  array(
														'id'=>$relation_increment,
														'rf_id'=>$doc->rf_id,
														'patent_number'=>$patentNumber,
														'parent_id'=>$assignorID,
														'child_id'=>$assigneeID,
														'connection_type'=>$connectionType,
														'frame'=>$frameNo,
														'reel'=>$reelNo,
														'description'=>$description,
														'date'=>date('Y-m-d',strtotime($assignor->exec_dt)),
														'recorded'=>$originalRecorded,
														'assignment_no'=>$counter,
														'line_type'=>$lineType,
														'creator_id'=>$creator,
														'document1' => $fileName,
														'document1_form' => $fileForm,
														'document1_agreement' => $fileAgreement,
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
									
								} else if(strpos(strtolower($conveyanceType), "partialrelease") !== false || strpos(strtolower($conveyanceType), "release") !== false) {
									
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
										}  else {
											$entity = findEntity($lead_patent_assignment, $name, array(2));
											if(count($entity) > 0){
												$assigneeID = $entity['id'];
											}
										} 
										
										$boxType = $cpType ;
										/*if assignee not exist*/									
										if($assigneeID == 0) {	
											$boxType = $thirdPartyBoxID;
											array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'original_name'=> $assignee->original_name,'description'=>$description,'other_execution_date'=>$originalExecutedDate, 'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>1,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'document'=>$fileName,'document_form'=>$fileForm,'document_agreement'=>$fileAgreement,'assignment_no'=>$counter,'box_type'=>$boxType, 'assignment_type'=>strtolower($conveyanceType),"flag"=>0));
											array_push($data['box'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'original_name'=> $assignee->original_name,'description'=>$description,'other_execution_date'=>$originalExecutedDate,'execution_date'=>date('M d,Y',strtotime($executedDate)),'recorded'=>$recorded, 'recorded_date'=>$recorded, 'type'=>$description,'document_file'=>$fileName,'document'=>$fileName,'document_form'=>$fileForm,'document_agreement'=>$fileAgreement,'date_1'=>$executedDate,'assignment_no'=>0,'box_type'=>$boxType,"flag"=>0));
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
												
												
												$entity = findEntity($lead_patent_assignment, $name, array(1));
												
												if(count($entity) > 0){
													$assignorID = $entity['id'];
												} else {
													if(count($inventorNames) > 0) {
														$checkBiblio = false;
														if(count($biblioInventor) > 0) {
															if(!in_array($name,$inventorNames)) {
																foreach($biblioInventor as $bibInventor) {
                                                                    /* echo 'asd123321212'. strtolower($name) .'@@@@'. strtolower($bibInventor['name']) .'@@@@@@'. strtolower($bibInventor['normalize'])."@@@@@<br/>";  */
																	if((strpos(strtolower($name), strtolower($bibInventor['family_name'])) !== false && strpos(strtolower($name), strtolower($bibInventor['given_name'])) !== false) || (strtolower($name) == strtolower($bibInventor['normalize']))){
																		$cType = 0;
																		$checkBiblio = true;
																		$name = $bibInventor['normalize'] != '' && $bibInventor['normalize'] != null ? $bibInventor['normalize'] : $bibInventor['name'];
																		break;
																	}
																}
															}
														}
														if($checkBiblio ===  false && in_array($name,$inventorNames)) {
															$cType = 0;
														}
													}
													$entity = findEntity($lead_patent_assignment, $name, array(0));
													if(count($entity) > 0){
														$assignorID = $entity['id'];
													} else {
														/*Check ThirdParty*/
														$entity = findEntity($lead_patent_assignment, $name, array(2));
														if(count($entity) > 0){
															$assignorID = $entity['id'];
														}
													}
												}
												
												if($assignorID == 0) {
													array_push($lead_patent_assignment, array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'original_name'=> $assignor->original_name,'description'=>$description,'execution_date'=>$executedDate,'recorded'=>$recorded,'type'=>1,'reel_no'=>$reelNo,'frame_no'=>$frameNo,'document_file'=>$fileName,'document'=>$fileName,'document_form'=>$fileForm,'document_agreement'=>$fileAgreement,'assignment_no'=>$counter,'box_type'=>$boxType, 'assignment_type'=>strtolower($conveyanceType),"flag"=>0));
													array_push($data['box'], array('id'=>$increment,'patent_number'=>$patentNumber,'name'=>$name,'original_name'=> $assignor->original_name,'description'=>$description,'other_execution_date'=>$originalExecutedDate,'execution_date'=>date('M d,Y',strtotime($executedDate)),'recorded'=>$recorded, 'recorded_date'=>$recorded, 'type'=>$description,'document_file'=>$fileName,'document'=>$fileName,'document_form'=>$fileForm,'document_agreement'=>$fileAgreement,'date_1'=>$executedDate,'assignment_no'=>0,'box_type'=>$boxType,"flag"=>0));
													$assignorID = $increment;
													$startCreatorID = $assignorID;
													$increment++;
												}
												
												if($assignorID > 0) {
													$connectionType = 2;
													array_push($jsonRelations,  array(
														'id'=>$relation_increment,
														'rf_id'=>$doc->rf_id,
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
														'start_creator_id'=>$startCreatorID,
														'note_file'=>'',
														'note'=>'',
														'document1' => $fileName,
														'document1_form' => $fileForm,
														'document1_agreement' => $fileAgreement,
														'recorded'=>$originalRecorded)
													);
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
					} 
					
					if(count($data['box']) > 0){
						$boxesShuffle = array();
						
						for($b=0;$b<count($data['box']);$b++){
							$boxType = -1;
							if(is_array($data['box'][$b])){
								$boxType = $data['box'][$b]['box_type'];
							} else {
								$boxType = $data['box'][$b]->box_type;
							}
							if($boxType >= 0){
								$inc = $b+1 ;
								if($inc < count($data['box'])) {
									$nextItem = $data['box'][$inc]; 
									if($boxType == 5 && $data['box'][$b]['name'] == $nextItem['name'] && strtotime($data['box'][$b]['other_execution_date']) == strtotime($nextItem['execution_date'])){
										//if($data['box'][$b]['description'] == $nextItem['description']) {
											$ID = $data['box'][$b]['id'];
											$data['box'][$b] = $nextItem;

											$remove = $b; 

											array_push($boxesShuffle, array('from'=>$ID, 'to'=>$nextItem['id'], 'remove'=>$remove));

											
											
										//}
									}
								}
							}
						}

						
						if(count($boxesShuffle) > 0) {
							foreach($boxesShuffle as $shufle) {
								for($i = 0; $i < count($jsonRelations); $i++) {
									if($jsonRelations[$i]['parent_id'] == $shufle['from']) {
										$jsonRelations[$i]['parent_id'] = $shufle['to'];
									}
								}
								unset($data['box'][$shufle['remove']]);
							}
							$oldArray = $data['box'];
							$data['box'] = array();
							foreach($oldArray as $array) {
								array_push($data['box'], $array);
							}
							
						} 

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
								$jsonRelations[$jsRIncrement]['document2'] = $relation['org_relation']['document1'];
								$jsonRelations[$jsRIncrement]['document2_form'] = $relation['org_relation']['document1_form'];
								$jsonRelations[$jsRIncrement]['document2_agreement'] = $relation['org_relation']['document1_agreement'];
								
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
							$sourceDIR = '/var/www/html/beta/';
							$fileName = "resources/shared/data/assignment-pat-".$pouptop.".pdf";
							$document2_form = "";
							$document2_agreement = "";
							$document1_form = "";
							$document1_agreement = "";
							/*if(file_exists($sourceDIR.$fileName)){
								$document1 = "https://patentrack.com/".$fileName;
								$document1_form = "https://patentrack.com/".$fileName;
								$document1_agreement = "https://patentrack.com/".$fileName;
								$formFileName = "resources/shared/data/assignment-pat-".$pouptop."_form.pdf";
								if(file_exists($sourceDIR.$formFileName)){
									$document1_form = "https://patentrack.com/".$formFileName;
								}
								$agreementFileName = "resources/shared/data/assignment-pat-".$pouptop."_agreement.pdf";
								if(file_exists($sourceDIR.$agreementFileName)){
									$document1_agreement = "https://patentrack.com/".$agreementFileName;
								}
							}*/
							/*if(!in_array($relation['reel'].$relation['frame'], $fileNoFoundAssignmentsRfID)){
								$fileStaticName = 'assignment-pat-'.$pouptop;
								$file = $fileStaticName.'.pdf';
								$formFileName = $fileStaticName."_form.pdf";
								$agreementFileName = $fileStaticName."_agreement.pdf";
								
								
								$document1 = $staticPath.$file;
								$document1_form = $staticPath.$formFileName;
								$document1_agreement = $staticPath.$agreementFileName;
							}*/
							
							if(isset($relation['reverse']) && $relation['reverse'] === true) {
								$poupbottom = $relation['reverse_reel'].'-'.$relation['reverse_frame'];
								$reffID = $relation['reff_id'];
								$assignment_no2 = $relation['assignment_no2'];
								$note2 = $relation['note2'];
								$noteFile2 = $relation['note_file2'];
								//$fileName = "resources/shared/data/assignment-pat-".$poupbottom.".pdf";
								if(!in_array($relation['reverse_reel'].$relation['reverse_frame'], $fileNoFoundAssignmentsRfID)){
								
									$fileStaticName = 'assignment-pat-'.$poupbottom;
									$file = $fileStaticName.'.pdf';
									$formFileName = $fileStaticName."_form.pdf";
									$agreementFileName = $fileStaticName."_agreement.pdf";
									
									/*
									$document2 = $staticPath.$file;
									$document2_form = $staticPath.$formFileName;
									$document2_agreement = $staticPath.$agreementFileName;*/
									$document2 = $relation['document2'];
									$document2_form = $relation['document2_form'];
									$document2_agreement = $relation['document2_agreement'];
								}
								/*if(file_exists($sourceDIR.$fileName)){
									$document2 = "https://patentrack.com/".$fileName;
									$document2_form = "https://patentrack.com/".$fileName;
									$document2_agreement = "https://patentrack.com/".$fileName;
									$formFileName = "resources/shared/data/assignment-pat-".$poupbottom."_form.pdf";
									if(file_exists($sourceDIR.$formFileName)){
										$document2_form = "https://patentrack.com/".$formFileName;
									}
									$agreementFileName = "resources/shared/data/assignment-pat-".$poupbottom."_agreement.pdf";
									if(file_exists($sourceDIR.$agreementFileName)){
										$document2_agreement = "https://patentrack.com/".$agreementFileName;
									}
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
							array_push($data['connection'],array('id'=>$relation['id'],'assignment_no1'=>$assignment_no1,'color'=>$findLineColorType['color'], 'rf_id'=>$relation['rf_id'], 'type'=>$type,'type_line'=>$typeLine,'ref_id'=>$reffID,'start_id'=>$relation['parent_id'],'end_id'=>$relation['child_id'],'box_creator_id'=>$relation['creator_id'],'box_creator_id2'=>$relation['start_creator_id'],'popup'=>$popup,'comment'=>$comments,'user_files'=>$userFiles,'tooltip'=>$findLineColorType['tooltip'],'date'=>date('M d,Y',strtotime($relation['date'])),'date_1'=>strtotime($relation['date']),'recorded'=>date('M d,Y',strtotime($relation['recorded'])),'document1'=>$relation['document1'],'document1_form'=>$relation['document1_form'],'document1_agreement'=>$relation['document1_agreement'],'document2'=>$document2,'document2_form'=>$document2_form,'document2_agreement'=>$document2_agreement,'note1'=>$note1,'pdf1'=>$noteFile1,'note2'=>$note2,'pdf2'=>$noteFile2,'popuptop'=>$pouptop,'popupbottom'=>$poupbottom));
						}				 
					} 
					/*if(count($data['box']) > 0 ) {
						for($b = 0; $b < count($data['box']); $b++) {
							if(isset($data['box'][$b]['original_name']) && $data['box'][$b]['original_name'] != '' && $data['box'][$b]['original_name'] != null) {
								$name = $data['box'][$b]['name'];
								$data['box'][$b]['name'] = $data['box'][$b]['original_name'];
								$data['box'][$b]['original_name'] = $name;
							}
						}
					}*/
					$data['all_boxes'] = $boxes;
					$data['legend'] = array(
										array("id"=>2,"tooltip"=>"Ownership","color"=>"#E60000","type"=>0,"explanation"=>""),
										array("id"=>3,"tooltip"=>"Name Change","color"=>"#2493f2","type"=>0,"explanation"=>""),
										array("id"=>4,"tooltip"=>"Security","color"=>"#ffaa00","type"=>0,"explanation"=>""),array("id"=>5,"tooltip"=>"License","color"=>"#E6E600","type"=>0,"explanation"=>""),array("id"=>7,"tooltip"=>"Release","color"=>"#70A800","type"=>0,"explanation"=>""),array("id"=>8,"tooltip"=>"License End","color"=>"#E38B4F","type"=>0,"explanation"=>""),array("id"=>9,"tooltip"=>"Partial Release","color"=>"#70A800","type"=>0,"explanation"=>""));
					$data['line'] = $data['connection'];
					
					$popsData = array();
					/*for($i = 0; $i < count($documentList); $i++){
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
								$queryInventors = "Select ac.* , r.representative_name as normalize_name, d.appno_doc_num, d.grant_doc_num from documentid as d INNER JOIN assignor as ac ON ac.rf_id = d.rf_id LEFT JOIN assignor_and_assignee as aaa ON ac.assignor_and_assignee_id = aaa.assignor_and_assignee LEFT JOIN representative as r.representative_id = aaa.representative_id INNER JOIN representative_assignment_conveyance as acc ON ac.rf_id = acc.rf_id WHERE  acc.employer_assign = 1 AND (d.grant_doc_num IN (".implode(',',$grantNo ).") OR d.appno_doc_num IN (".implode(',',$appNo ).") )  GROUP BY ac.rf_id, acc.assignor_and_assignee_id ORDER BY ac.exec_dt ASC";
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
					}*/
					
					$data['popup'] = $popsData;
					if($assetType === 4) {
						if(strpos($patentNumber, '/') === false) {
							$patentNumber = is_numeric($patentNumber) ? formatNumber($patentNumber) : substr($patentNumber, 0, 2).','.formatNumber(substr($patentNumber,2));
							if(!is_numeric(substr($patentNumber,0,1))){
								$patentNumber = preg_replace('/,/', '',  $patentNumber, 1);
							}
						}						
					} else {
						$patentNumber = substr($patentNumber, 0, 2).'/'.number_format(substr($patentNumber,2));
					}
					$data['general']['patent_number'] = 'US'.$patentNumber." - ".$data['title'];
					echo json_encode($data);
				}				
			} catch(Exception $e){
				
			}
		}
	}
}

function formatNumber ($string) {
	return preg_replace("/\B(?=(\d{3})+(?!\d))/", ",", $string);
}
?>
