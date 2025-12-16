<?php 
require_once('/var/www/html/trash/vendor/autoload.php');
require_once('/var/www/html/trash/noti_config.php');
ignore_user_abort(true);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
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
		$queryApplication = "SELECT appno_doc_num,grant_doc_num FROM db_application.documentid as a WHERE rf_id IN(SELECT rf_id FROM db_uspto.representative_transactions WHERE organisation_id = '".$organisationID."' AND representative_id = ".$representativeID." GROUP BY rf_id) AND date_format(appno_date, '%Y') BETWEEN 2000 AND 2004 GROUP BY appno_doc_num";
	} else {
		$queryApplication = "SELECT appno_doc_num,grant_doc_num FROM db_application.documentid as a WHERE rf_id IN(SELECT rf_id FROM db_uspto.representative_transactions WHERE organisation_id = '".$organisationID."' GROUP BY rf_id) AND date_format(appno_date, '%Y') BETWEEN 2000 AND 2004 GROUP BY appno_doc_num";
	}
	
	
	$con->query("SET FOREIGN_KEY_CHECKS = 0");

	$resultApplication = $con->query($queryApplication);

	if($resultApplication->num_rows > 0){
		$allApplications = array();
		$allAssets = array();
		while($appRow = $resultApplication->fetch_object()){
			array_push($allApplications, $appRow->appno_doc_num);
			
			array_push($allAssets, array('appno_doc_num'=> $appRow->appno_doc_num, 'grant_doc_num'=> $appRow->grant_doc_num));
		}
		
		$queryGetAllInventors = "SELECT appno_doc_num FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $allApplications).")";
		
		$resultBiblioInventors = $con->query($queryGetAllInventors);
		
		$allInventors = array();
		
		if($resultBiblioInventors && $resultBiblioInventors->num_rows > 0) {
			while($rowInventor = $resultBiblioInventors->fetch_object()) {
				array_push($allInventors, $rowInventor);
			}
		}
		
		
		$queryGetAllInventors = "SELECT appno_doc_num FROM db_patent_grant_bibliographic.temp_inventor WHERE appno_doc_num IN (".implode(',', $allApplications).")";
		
		$resultBiblioInventors = $con->query($queryGetAllInventors);
		
		$allInventors = array();
		
		if($resultBiblioInventors && $resultBiblioInventors->num_rows > 0) {
			while($rowInventor = $resultBiblioInventors->fetch_object()) {
				array_push($allInventors, $rowInventor);
			}
		}
		
		$queryGetAllInventors = "SELECT appno_doc_num FROM db_patent_grant_bibliographic.inventor_temp	WHERE appno_doc_num IN (".implode(',', $allApplications).")";
		
		$resultBiblioInventors = $con->query($queryGetAllInventors);
		
		$allInventors = array();
		
		if($resultBiblioInventors && $resultBiblioInventors->num_rows > 0) {
			while($rowInventor = $resultBiblioInventors->fetch_object()) {
				array_push($allInventors, $rowInventor);
			}
		}
		
		
		
		$applicationWithInventorAndCounter = array();
		
		foreach($allApplications as $application) {
			$counter = 0;
			$list = array();
			foreach($allInventors as $inventor) {
				if((int)$application == (int)$inventor->appno_doc_num) {
					$counter++;break;
					//array_push($list, trim($inventor->name));
				}
			}
			if($counter === 0) {
				array_push($applicationWithInventorAndCounter, array('appno_doc_num'=>$application, 'count'=>$counter, 'list'=>$list));
			}			
		}
		
		echo count($applicationWithInventorAndCounter);
		sendNotifications("The number of assignments of missing inventors: ".count($applicationWithInventorAndCounter)." Retrieving the missing inventors via USPTO Assignment API.");
		$status = 0;
		if(count($applicationWithInventorAndCounter) > 0) {
			$i = 1;
			foreach($applicationWithInventorAndCounter as $application) {
				/*$status = findProcess($con, $organisationID, $representativeID);
				
				if($status === 0) {*/
					$appURL = "https://assignment.uspto.gov/solr/aotw/select?fl=inventors,applNum&fq=applNum:".$application['appno_doc_num']."&hl=true&lowercaseOperators=true&q=*:*&rows=500&sort=patAssignorEarliestExDate+desc,+recordedDate+desc&wt=json";
					$dataUSPTO = curl($appURL);
					try{
						if($dataUSPTO != "" && $dataUSPTO != null) {
							$assignmentList = json_decode($dataUSPTO,true);
							if(isset($assignmentList['response']) && isset($assignmentList['response']['docs'])) {
								if(count($assignmentList['response']['docs']) > 0) {
									$applicationNumberList = $assignmentList['response']['docs'][0]['applNum'];
									$applicationIndex = array_search($application['appno_doc_num'], $applicationNumberList);
									if($applicationIndex >= 0) {
										$inventorsAllList = $assignmentList['response']['docs'][0]['inventors'];
										$allInventors = explode(',',$inventorsAllList[$applicationIndex]);
										$inventorList = array();
										if(count($allInventors)> 0) {
											foreach($allInventors as $inventor) {
												if(strlen($inventor) > 4) {
													//$inventor = formatText($inventor);
													array_push($inventorList, trim($inventor));
												}
											}
											if(count($inventorList) > 0) {
												$inventors = array();
												foreach($inventorList as $inventor) {
													$inventor = formatText($inventor);
													$explodeName = explode(" ", $inventor);
													$popArray = array_pop($explodeName);
													$givenName = implode(" ", $explodeName);
													array_push($inventors, array('given_name'=>$givenName, 'middle_name'=>'', 'family_name'=>$popArray, 'name'=>$inventor));
												}
												insertInventors($application['appno_doc_num'], $inventors, $con);	
												
sendNotifications("The number of assignments of missing inventors: ".$i."/".count($applicationWithInventorAndCounter)." Retrieving the missing inventors via USPTO Assignment API.");												
											}
										}
									}
								}
							}
						}
					} catch(Exception $e) {	
						sendNotifications("Error in ".$application['appno_doc_num']);	
					}
				/*} else {
					sendNotifications("Missing Inventors 2000-2004 stopped.");	
					break;
				}*/
				$i++;
			}
		}
		if($status === 0) {
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
	$query = "SELECT status FROM missing_inventor_process WHERE organisation_id = ".$orgID;
	
	if($representativeID > 0) {
		$query .=" AND representative_id = ".$representativeID;
	}
	
	$query .=" ORDER BY process_id DESC LIMIT 1";
	
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
		$queryInventor = "INSERT IGNORE INTO db_patent_application_bibliographic.inventor(appno_doc_num, name, given_name, middle_name, family_name,file_name,other_name, insert_api) VALUES ";
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