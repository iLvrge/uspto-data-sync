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


$variables = $argv;
echo count($variables);
if(count($variables) == 3) {
	$organisationID = $variables[1];
	$representativeID = $variables[2];
	
	if($representativeID > 0) {
		$queryApplication = "SELECT appno_doc_num,grant_doc_num FROM ".$dbUSPTO.".documentid as a WHERE rf_id IN(SELECT rf_id FROM ".$dbUSPTO.".representative_transactions WHERE organisation_id = '".$organisationID."' AND representative_id = ".$representativeID.") GROUP BY appno_doc_num";
	} else {
		$queryApplication = "SELECT appno_doc_num,grant_doc_num FROM ".$dbUSPTO.".documentid as a WHERE rf_id IN(SELECT rf_id FROM ".$dbUSPTO.".representative_transactions WHERE organisation_id = '".$organisationID."') GROUP BY appno_doc_num";
	}
	
	
	$con->query("SET FOREIGN_KEY_CHECKS = 0");

	$resultApplication = $con->query($queryApplication);
	
	if($resultApplication->num_rows > 0){
		$allApplications = array();
		$allCompareApplications = array();
		$allAssets = array();
		while($appRow = $resultApplication->fetch_object()){
			array_push($allApplications, $appRow->appno_doc_num);
			array_push($allCompareApplications, (int)$appRow->appno_doc_num);
			
			array_push($allAssets, array('appno_doc_num'=> $appRow->appno_doc_num, 'grant_doc_num'=> $appRow->grant_doc_num));
		}
		
		
		$findInventors = array();
		
		$queryGetAllInventors = "SELECT * FROM db_patent_grant_bibliographic.temp_inventor WHERE appno_doc_num IN (".implode(',', $allCompareApplications).")";
		
		$resultBiblioInventors = $con->query($queryGetAllInventors);
		
		$allInventors = array();
		
		if($resultBiblioInventors && $resultBiblioInventors->num_rows > 0) {
			while($rowInventor = $resultBiblioInventors->fetch_object()) {
				array_push($allInventors, $rowInventor);
				array_push($findInventors, (int)$rowInventor->appno_doc_num);
			}
		}

		$remainingApplications = array_diff($allCompareApplications, $findInventors);
		
		
		
		if(count($remainingApplications) > 0){
			$queryGetAllInventors = "SELECT * FROM db_patent_grant_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $remainingApplications).")";
			
			$resultBiblioInventors = $con->query($queryGetAllInventors);
			
			if($resultBiblioInventors && $resultBiblioInventors->num_rows > 0) {
				while($rowInventor = $resultBiblioInventors->fetch_object()) {
					array_push($allInventors, $rowInventor);
					array_push($findInventors, (int)$rowInventor->appno_doc_num);
				}
			}
		}
		
		$remainingApplications = array_diff($allCompareApplications, $findInventors);
		
		
		if(count($remainingApplications) > 0){
			$queryGetAllInventors = "SELECT * FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $remainingApplications).")";
			
			$resultBiblioInventors = $con->query($queryGetAllInventors);
			
			if($resultBiblioInventors && $resultBiblioInventors->num_rows > 0) {
				while($rowInventor = $resultBiblioInventors->fetch_object()) {
					array_push($allInventors, $rowInventor);
					array_push($findInventors, (int)$rowInventor->appno_doc_num);
				}
			}
		}
		
		$remainingApplications = array_diff($allCompareApplications, $findInventors);
		
		
		if(count($remainingApplications) > 0){
			$queryGetAllInventors = "SELECT * FROM db_patent_application_bibliographic.inventor_temp WHERE appno_doc_num IN (".implode(',', $remainingApplications).")";
			
			$resultBiblioInventors = $con->query($queryGetAllInventors);
			
			if($resultBiblioInventors && $resultBiblioInventors->num_rows > 0) {
				while($rowInventor = $resultBiblioInventors->fetch_object()) {
					array_push($allInventors, $rowInventor);
					array_push($findInventors, (int)$rowInventor->appno_doc_num);
				}
			}
		}
		
		$applicationWithInventorAndCounter = array();
		
		foreach($allApplications as $application) {
			$counter = 0;
			$list = array();
			foreach($allInventors as $inventor) {
				if($application == $inventor->appno_doc_num) {	
					//$string = preg_replace('/\s+/', '', $inventor->name);				
					if(!in_array(trim(strtolower($inventor->name)), $list)){
						$counter++;
						
						array_push($list, trim(strtolower($inventor->name)));
					}					
				}
			}
			array_push($applicationWithInventorAndCounter, array('appno_doc_num'=>$application, 'count'=>$counter, 'list'=>$list));
		}		
		
		$i = 0;
		sendNotifications("Total applications: ".count($applicationWithInventorAndCounter)." Retrieving the missing inventors via USPTO Assignment API.");
		$status = 0;
		foreach($allApplications as $applicationNumber) {
			$status = findProcess($con, $organisationID, $representativeID);
			
			if((int)$status == 0) {
				$appURL = "https://assignment.uspto.gov/solr/aotw/select?fl=inventors,applNum&fq=applNum:".$applicationNumber."&hl=true&lowercaseOperators=true&q=*:*&rows=500&sort=patAssignorEarliestExDate+desc,+recordedDate+desc&wt=json";
				
				$dataUSPTO = curl($appURL);
				try{
					if($dataUSPTO != "" && $dataUSPTO != null) {
						if($applicationNumber == '09622808') {
							$f = fopen('./file_api_inventor.log', "a");
							fwrite($f, $dataUSPTO);
							fclose($f);
						}
						$assignmentList = json_decode($dataUSPTO,true);
						if(isset($assignmentList['response']) && isset($assignmentList['response']['docs'])) {
							if(count($assignmentList['response']['docs']) > 0) {
								if(isset($assignmentList['response']['docs'][0]['applNum'])) {
									$applicationNumberList = $assignmentList['response']['docs'][0]['applNum'];
									if(count($applicationNumberList) > 0 ) {
										$applicationIndex = array_search($applicationNumber, $applicationNumberList);
										if($applicationIndex >= 0) {
											$inventorsAllList = $assignmentList['response']['docs'][0]['inventors'];
											$apiInventors = explode(',',$inventorsAllList[$applicationIndex]);
											$inventorList = array();
											echo "COUNTFROM: ".count($apiInventors)."@@".$applicationWithInventorAndCounter[$i]['count'];
											if(count($apiInventors) != $applicationWithInventorAndCounter[$i]['count']) {
												foreach($apiInventors as $inventor) {
													if(strlen($inventor) > 4) {
														//$inventor = formatText($inventor);
														array_push($inventorList, trim(strtolower($inventor)));
													}
												}
												if(count($inventorList) != $applicationWithInventorAndCounter[$i]['count']) {
													$findInventors = array_diff($inventorList, $applicationWithInventorAndCounter[$i]['list']);
													
													$findInventors2 = array_diff($applicationWithInventorAndCounter[$i]['list'], $inventorList );
													
													$missingInventor = array_merge($findInventors, $findInventors2);
													/*
													print_r($findInventors);
													print_r($findInventors2);
													print_r($inventorList);
													print_r($applicationWithInventorAndCounter[$i]['list']);
													*/
													$missingInventor = array_unique($missingInventor);
													//print_r($missingInventor);
													
													if(count($missingInventor) > 0 ) {
														$inventorList = array();
														foreach($missingInventor as $inventor) {
															$inventor = formatText($inventor);
															$explodeName = explode(" ", $inventor);
															$popArray = array_pop($explodeName);
															$givenName = implode(" ", $explodeName);
															array_push($inventorList, array('given_name'=>$givenName, 'middle_name'=>'', 'family_name'=>$popArray, 'name'=>$inventor));
														}
														insertInventors($applicationNumber, $inventorList, $con);
													}
													if($applicationNumber == '09622808') {
														$f = fopen('./file_api_inventor.log', "a");
														fwrite($f, $applicationNumber."\n");
														fwrite($f, $applicationIndex."\n");
														fwrite($f, $inventorsAllList[$applicationIndex]."\n");
														fwrite($f, json_encode($inventorList)."\n");
														fwrite($f, json_encode($findInventors)."\n");
														fwrite($f, json_encode($findInventors2)."\n");
														fwrite($f, json_encode($missingInventor)."\n");
														fclose($f);
													}
												}												
											}
											//insertInventors($appRow->appno_doc_num, $inventorList, $con);
										}
									}									
								}
								
							}
						}
					}
					sendNotifications("The number of assignments of missing inventors: ".($i + 1)."/".count($applicationWithInventorAndCounter)." Retrieving the missing inventors via USPTO Assignment API.");	
				} catch(Exception $e) {	
					sendNotifications("Error in ".$applicationNumber);	
				}
			} else {
				sendNotifications("Missing Inventors stopped.");	
				break;
			}
			$i++;
			sleep(1);
		}
		if($status == 0) {
			sendNotifications("Missing inventors retrieval is complete: ".$i."/".count($applicationWithInventorAndCounter).".");
		}
		deleteProcess($con, $organisationID, $representativeID);
	}
}

function deleteProcess($con, $orgID, $representativeID) {
	$query = "DELETE FROM missing_inventor_process WHERE organisation_id = ".$orgID;
	
	if($representativeID > 0) {
		$query .=" AND representative_id = ".$representativeID;
	}
	
	$con->query($query);
}


function findProcess($con, $orgID, $representativeID) {
	$query = "SELECT `status` FROM missing_inventor_process WHERE organisation_id = ".$orgID;
	
	if($representativeID > 0) {
		$query .=" AND representative_id = ".$representativeID;
	}
	
	$query .=" ORDER BY process_id DESC LIMIT 1";
	echo $query;
	$result = $con->query($query);
	$status = 0;
	
	if($result && $result->num_rows > 0) {
		$row = $result->fetch_object();
		
		$status = $row->status;
	} else {
		$status = 1;
	}
	
	return $status;
}


function insertInventors($applicationNumber,$inventorsRecord, $con) {
	if(count($inventorsRecord) > 0) {
		/*$queryInventor = "INSERT IGNORE INTO db_patent_application_bibliographic.missing_inventor(appno_doc_num, name, given_name, middle_name, family_name,file_name,other_name) VALUES ";*/
		$queryInventor = "INSERT IGNORE INTO db_patent_application_bibliographic.inventor(appno_doc_num, name, given_name, middle_name, family_name, file_name, other_name, insert_api) VALUES ";
		foreach($inventorsRecord as $invent){
			$name = $invent['given_name'];
			if($invent['middle_name'] != "") {
				$name .= " ".$invent['middle_name'];
			}
			if($invent['family_name'] != "") {
				$name .= " ".$invent['family_name'];
			}
			
			$other_name = $invent['family_name'];
								
			if(!empty($invent['given_name']) && $invent['given_name'] != null){
				$other_name .= " ".$invent['given_name'];
			}
			
			if(!empty($invent['middle_name']) && $invent['middle_name'] != null){
				$other_name .= " ".$invent['middle_name'];
				
			}
			$queryInventor .= '("'.$con->real_escape_string($applicationNumber).'", "'.$con->real_escape_string($name).'", "'.$con->real_escape_string($invent['given_name']).'", "'.$con->real_escape_string($invent['middle_name']).'", "'.$con->real_escape_string($invent['family_name']).'", "", "'.$con->real_escape_string(trim($other_name)).'", 1), ';
		}
		$queryInventor = substr($queryInventor, 0, -2);
		echo $queryInventor."<br/>";
		$con->query($queryInventor);
	}
}

function formatText($text) {
	return ucfirst(strtolower(strtoupper(trim($text))));
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

function sendNotifications($data) {	
	$pusher = new Pusher\Pusher(CONSTANT_PUSHER_KEY, CONSTANT_PUSHER_SECRET, CONSTANT_PUSHER_APPID, array( 'cluster' => CONSTANT_PUSHER_CLUSTER, 'useTLS' => CONSTANT_PUSHER_ENCRYPTED ) );
	$pusher->trigger( CONSTANT_PUSHER_CHANNEL, CONSTANT_PUSHER_EVENT, $data );
}