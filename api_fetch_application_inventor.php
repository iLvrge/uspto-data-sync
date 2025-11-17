<?php 

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


$queryApplication = "Select appno_doc_num,grant_doc_num from db_application.documentid as a where a.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_application_bibliographic.inventor) AND appno_doc_num <> '' GROUP BY appno_doc_num";
$queryApplication = "Select appno_doc_num,grant_doc_num from db_application.documentid as a where rf_id IN(SELECT rf_id FROM db_uspto.representative_transactions WHERE organisation_id = '73') GROUP BY appno_doc_num";
$con->query("SET FOREIGN_KEY_CHECKS = 0");
$resultApplication = $con->query($queryApplication);

if($resultApplication->num_rows > 0){
	while($appRow = $resultApplication->fetch_object()){
		$dataFound = false;
		if($appRow->grant_doc_num != "" && $appRow->appno_doc_num != "") {
			$patURL = 'https://www.patentsview.org/api/patents/query?q={"patent_number":"'.$appRow->grant_doc_num.'"}&f=["inventor_first_name","inventor_last_name"]';
			$dataPatentsView = curl($patURL);
			try{
				if($dataPatentsView != "" && $dataPatentsView != null) {
					$assignmentList = json_decode($dataPatentsView,true);
					
					if(isset($assignmentList['patents']) && isset($assignmentList['patents'][0]["inventors"])) {
						if(count($assignmentList['patents'][0]["inventors"]) > 0){
							$inventorList = array();
							foreach($assignmentList['patents'][0]["inventors"] as $inventor) {
								$firstName = $inventor['inventor_first_name'];
								$lastName = $inventor['inventor_last_name'];
								$name = $firstName.' '.$lastName;
								array_push($inventorList, array('given_name'=>$firstName, 'middle_name'=>'', 'family_name'=>$lastName, 'name'=>$name));
							}
							$dataFound = true;
							print_r($inventorList);
							insertInventors($appRow->appno_doc_num, $inventorList, $con);
						}
					}
				}
			} catch(Exception $e) {				
			}
		} 
		if($appRow->appno_doc_num != "" && $dataFound === false) {
			$appURL = "https://assignment.uspto.gov/solr/aotw/select?fl=inventors,applNum&fq=applNum:".$appRow->appno_doc_num."&hl=true&lowercaseOperators=true&q=*:*&rows=500&sort=patAssignorEarliestExDate+desc,+recordedDate+desc&wt=json";
			$dataUSPTO = curl($appURL);
			try{
				if($dataUSPTO != "" && $dataUSPTO != null) {
					$assignmentList = json_decode($dataUSPTO,true);
					if(isset($assignmentList['response']) && isset($assignmentList['response']['docs'])) {
						if(count($assignmentList['response']['docs']) > 0) {
							$applicationNumberList = $assignmentList['response']['docs'][0]['applNum'];
							$applicationIndex = array_search($appRow->appno_doc_num,$applicationNumberList);
							if($applicationIndex >= 0) {
								$inventorsAllList = $assignmentList['response']['docs'][0]['inventors'];
								$allInventors = explode(',',$inventorsAllList[$applicationIndex]);
								$inventorList = array();
								if(count($allInventors) > 0) {
									foreach($allInventors as $inventor) {
										$inventor = formatText($inventor);
										$explodeName = explode(" ", $inventor);
										$popArray = array_pop($explodeName);
										$givenName = implode(" ", $explodeName);
										array_push($inventorList, array('given_name'=>$givenName, 'middle_name'=>'', 'family_name'=>$popArray, 'name'=>$inventor));
									}
								}
								insertInventors($appRow->appno_doc_num, $inventorList, $con);
							}
						}
					}
				}
			} catch(Exception $e) {				
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

function insertInventors($applicationNumber,$inventorsRecord, $con) {
	if(count($inventorsRecord) > 0) {
		$queryInventor = "INSERT IGNORE INTO db_patent_application_bibliographic.inventor_temp(appno_doc_num, name, given_name, middle_name, family_name,file_name,other_name) VALUES ";
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
			$queryInventor .= '("'.$con->real_escape_string($applicationNumber).'", "'.$con->real_escape_string($name).'", "'.$con->real_escape_string($invent['given_name']).'", "'.$con->real_escape_string($invent['middle_name']).'", "'.$con->real_escape_string($invent['family_name']).'", "", "'.$con->real_escape_string(trim($other_name)).'"), ';
		}
		$queryInventor = substr($queryInventor, 0, -2);
		echo $queryInventor."<br/>";
		$con->query($queryInventor);
	}
}

function formatText($text) {
	return ucfirst(strtolower(strtoupper(trim($text))));
}
?>