<?php 
require_once '/var/www/html/trash/s3_bucket/vendor/autoload.php';

use Aws\S3\S3Client;

use Aws\Common\Credentials\Credentials;

use Aws\S3\Sync\UploadSyncBuilder;

ignore_user_abort(true);
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

Class EpoDoc{
	public $_key;
	public $_secret;
	public $_con;
	public $_client;
	public $_bucket = 'static.patentrack.com';
	public $_region = 'us-west-1';
	public $_keyPrefix = 'images';
	public function __construct($credential){
		$this->_key = $credential['key'];
		$this->_secret = $credential['secret'];	
		$this->_con = $credential['con'];	
		/*$credentials = new Credentials(getenv('AWS_ACCESS_KEY_ID'), getenv('AWS_SECRET_KEY'));		*/
		$credentials = new Credentials('AKIAYD2CUN6OLDBPT4SY', 'eEdtphVIqzGX7JsL0RVxlbHaEWAmVzq6B/QNm+Cq');		
		$this->_client = S3Client::factory(array(
			'credentials' => $credentials,
			'region'  => $this->_region,
		));	
	}
	public function read_token($tokenName) {
		$error = '';
		$tokenFile = "/var/www/html/trash/tmp/$tokenName.dat";
		if(file_exists($tokenFile)) {
			$token = unserialize(file_get_contents($tokenFile));
			$tokenTime = substr($token['issued_at'], 0, -3) + $token['expires_in'] - 120;
			if($tokenTime < time()) $error .= "token '$tokenName' expired<br>\n";
			else $token['error']=$error;
		} else $error .= "tokenFile '$tokenName' notFound<br>\n";
		if($error) $token = $this->create_token($tokenName);
		return($token);
	}
	private function create_token($tokenName) {
		$error = '';
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
		$tokenFile = "/var/www/html/trash/tmp/$tokenName.dat";
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
	public function runUrl($token,$A,$B,$C,$D,$E,$F){
		$error = '';
		$requestHeader = array(
			'Accept: application/xml',
			'Authorization: Bearer '.$token['access_token'],
			'Connection: Keep-Alive',
			'Host: ops.epo.org',
			'X-Target-URI: http://ops.epo.org'
		);
		/*http://ops.epo.org/3.2/rest-services/family/publication/epodoc/EP1000000/biblio*/
		
		$request_url = "http://ops.epo.org/3.2/rest-services/%s/%s/%s/%s/%s";
		$request_url = sprintf($request_url,$A,$B,$C,$D,$E);
		echo $request_url."<br/>";
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
	public function singleUrl($token,$accept='application/pdf',$A){
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
	
	function uploadImageS3($fileData, $fileName, $options) {
		$fileLocation = '';
		try{
			$result = $this->_client->putObject([
				'Key'    => $this->_keyPrefix.'/'.$fileName,
				'Bucket' => $this->_bucket,
				'Body' => $fileData,
				'ACL'=> 'public-read'
			] + $options); 
			
			if($result != null) {
				$fileLocation = 'https://s3-'.$this->_region.'.amazonaws.com/'.$this->_bucket.'/'.$this->_keyPrefix.'/'.$fileName;
			}
		}catch(Exception $e) {
			print_r($e);
		}
		
		return $fileLocation;
		
	}
	
	function insertData($dbUSPTO, $tableName, $list, $childJSON = false, $param = ''){		
		if(count($list) > 0) {
			$i = 0;
			$stringName ="";
			$stringValue ="";
			for($i = 0; $i < count($list); $i++){
				$stringValue .="(";
				foreach($list[$i] as $key=>$value) {
					if($i == 0) {
						$stringName .= $key.", ";
					}
					if($childJSON === true && $param == $key){
						$stringValue .="'".json_encode($value)."'".", ";
					} else {
						$stringValue .="'".$this->_con->real_escape_string($value)."'".", ";
					}
					
				}
				$stringValue = substr($stringValue, 0, -2);
				$stringValue .="), ";
			}
			$stringValue = substr($stringValue, 0, -2);
			$stringName = substr($stringName, 0, -2);
			$sql = "INSERT IGNORE INTO ".$dbUSPTO.".".$tableName."(".$stringName.") VALUES ".$stringValue;	
			echo $sql."<br/>";
			$result = $this->_con->query($sql);
		}
	}
	
	function updateData($dbUSPTO, $tableName, $appnoDocNum, $postValues) {
		$stringName ="";
		foreach($postValues as $key=>$value){
			$stringName .=$key."='".mysqli_real_escape_string($this->_con,$value)."',";
		}
		$stringName = substr($stringName,0,-1);
		$sql = "UPDATE ".$dbUSPTO.".".$tableName." SET ".$stringName." WHERE appno_doc_num = ".$appnoDocNum;	
		/*echo $sql."<br/>";*/
		$result = $this->_con->query($sql);
		if($result){
			return $appnoDocNum;
		} else {
			return 0;
		}
	}
	
	function fileCheck($fileName) {
		$status = false;
		try {			
			$this->_client->registerStreamWrapper();
			$fileExists = file_exists("s3://".$this->_bucket."/".$this->_keyPrefix."/".$fileName);
			if ($fileExists) {
				$status = true;
			}
		} catch(Exception $e) {	
			echo $e->getMessage() . PHP_EOL;	
		}	
		return $status;
	}
	
}

$variables = $argv;

$epodoc = new EpoDoc(array('key'=>'9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs','secret'=>'WgLvbrHl9QOyykTT', 'con'=>$con));
			
$epoDocToken = $epodoc->read_token('HedCET');	

$publication = 'application';

$db = 'docdb';
//echo count($allPatents);

$getImagePDFData = $epodoc->singleUrl($epoDocToken,'application/pdf','published-data/images/US/8924044/B1/thumbnail.pdf?Range=1');


$fileURL = $epodoc->uploadImageS3($getImagePDFData['data'], '1.pdf',  ['ContentType' => 'application/pdf','ContentDisposition' => 'inline']);
print_r($fileURL);
//print_r($getImagePDFData);
die;

$getFamilyData = $epodoc->runUrl($epoDocToken,'published-data',$publication,$db,'US13996449A1','biblio','');
print_r($getFamilyData);

die;
//$variables = $_GET;
if(count($variables) == 3) {
//if(isset($_GET['patent'])) {
	//$application = $variables[1];	
	$organisationID = $variables[1];
	$representativeID = $variables[2];	
	
	$queryOrganisation = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE organisation_id ="'.$organisationID.'"';
		
	$resultOrganisation = $con->query($queryOrganisation);
	
	$allPatents = array();
	
	if($resultOrganisation && $resultOrganisation->num_rows > 0) {
		$orgRow = $resultOrganisation->fetch_object();
		
		$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
		
		if($orgConnect) {
			if($representativeID != "") {
				$queryRepresentative = 'SELECT representative_id FROM representative WHERE representative_id = '.$representativeID.' AND parent_id = 0';
			} else {
				$queryRepresentative = 'SELECT representative_id FROM representative WHERE parent_id = 0';
			}
			
			$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
	
			if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
				$representativeIDs = array();
				
				while($representativeData = $resultRepresentativeParentCompany->fetch_object()) {
					array_push($representativeIDs, $representativeData->representative_id);
				}					
				
				if( count($representativeIDs) > 0 ) {
	
					$rfIDs = [];
					
					$queryFindAllRFIDs = "SELECT rf_id FROM ".$dbUSPTO.".representative_transactions WHERE organisation_id = ".$organisationID." AND representative_id IN (".implode(',', $representativeIDs).")";
					
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}	
					
					
					if(count($rfIDs) > 0) {
						//RFIDs related with other Assets as well
						$queryFindAllGrantPatents = 'SELECT grant_doc_num, appno_doc_num FROM '.$dbUSPTO.'.documentid WHERE grant_doc_num <> "" AND  rf_id IN ('.implode(',', $rfIDs).') AND date_format(appno_date,"%Y") >= 2000 AND grant_doc_num <> "" GROUP BY grant_doc_num';
						
						//echo $queryFindCorrectRFIDs;
						
						$resultGrant = $con->query($queryFindAllGrantPatents);
						
						if($resultGrant->num_rows > 0) {
							while($row = $resultGrant->fetch_object()){
								array_push($allPatents, array('grant_doc_num'=> $row->grant_doc_num, 'appno_doc_num'=> $row->appno_doc_num, 'organisation_id'=>$organisationID));
							}
						}
					}
				}
			}
		}
	}
	//$allPatents = array(array('grant_doc_num'=>$application));
	//print_r($allPatents);
	if(count($allPatents) > 0) {
		
		$fetchedPatents = array();
		
		$patentList = array();
		
		foreach($allPatents as $patent) {
			array_push($patentList, "'".$patent['grant_doc_num']."'");
		}
		
		$queryList = "SELECT patent_id FROM ".$dbUSPTO.".patent_family_relation WHERE patent_id IN (".implode(',', $patentList).") AND family_id <> ''  AND family_id <> '0'";
		
		$resultList = $con->query($queryList);
		
		if($resultList && $resultList->num_rows > 0) {
			while($row = $resultList->fetch_object()) {
				array_push($fetchedPatents, $row->patent_id);
			}
		}
		echo "COUNT:".count($allPatents)."<br/>";
		if(count($fetchedPatents) > 0) {
			
			foreach($fetchedPatents as $patent) {
				$index = 0;
				foreach($allPatents as $exisitingPatent) {
					if($exisitingPatent['grant_doc_num'] == $patent) {
						unset($allPatents[$index]);
						break;
					}
					$index++;
				}
				
			}
		}
		
		echo "COUNT:".count($allPatents)."<br/>";
		$patentIndex = 0;
		
		$parentChildPatent = array();
		
		foreach($allPatents as $patent) {
			
			$grantDocNum = $patent['grant_doc_num'];
			$appnoDocNum = $patent['appno_doc_num'];
			echo $grantDocNum."<br/>";
			echo $appnoDocNum."<br/>";
			$familyData = array();
			$patImages = array();
			$classificationList = array();
			$allInventors = array();
			$allAssignee = array();
			$allApplicant = array();
			$allAssignments = array();
			$claimList = array();
			$abstract = "";
			$title = "";
			
			$epodoc = new EpoDoc(array('key'=>'9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs','secret'=>'WgLvbrHl9QOyykTT', 'con'=>$con));
			
			$epoDocToken = $epodoc->read_token('HedCET');	
			
			$publication = 'publication';
			
			$db = 'epodoc';
			//echo count($allPatents);
			
			
			$getFamilyData = $epodoc->runUrl($epoDocToken,'family',$publication,$db,'US'.$grantDocNum,'','');
			
			$mainFamilyID = "";
			$childList = array();
			try {
				if(empty($getFamilyData['error'])){
					if(!empty($getFamilyData['data'])){
						//file_put_contents('file_family_request.log',"FETCH".$grantDocNum."\n".PHP_EOL , FILE_APPEND | LOCK_EX);
						$xml=simplexml_load_string($getFamilyData['data']);
						file_put_contents('/var/www/html/trash/epo_xml/file_xml_'.$grantDocNum.'.xml', $getFamilyData['data']);
						if ($xml !== false) {							
							$xmlObject = new SimpleXMLElement($getFamilyData['data']);							
							if(isset($xmlObject->code) && $xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found"){
								//No Family data
							} else {
								$patentObject = $xmlObject->xpath('//ops:patent-family');
								$listOfFamilyMembers = $xmlObject->xpath('//ops:family-member');
								$publicationNumber = "";
								if(isset($patentObject[0])){
									$mainPatentPublicationReference = $patentObject[0]->xpath('//ops:publication-reference');
									if(isset($mainPatentPublicationReference[0])){
										$publicationNumber = (string)$mainPatentPublicationReference[0]->{'document-id'}->{'doc-number'};
									}
								}
								
								$mainPatentDetails = array();
								$searchNumber = $grantDocNum;
								$searchApplicationNumber = "";
								$allFamilyList = array();
								$primeFamily = array();
								
								if(count($listOfFamilyMembers)>0){
									foreach($listOfFamilyMembers as $family){
										$familyID = (string)$family['family-id'];
										$includeOther = false;
										if($mainFamilyID!="" && $mainFamilyID==$familyID){
											$includeOther = true;
										} else if($mainFamilyID==""){
											$includeOther = true;
										}
										
										$publicationReference = $family->{'publication-reference'}->{'document-id'};
										$publicationNumber = "";
										$publicationDate = "";
										$pubCountry="";
										$pubKind="";
										$applicationNumber = "";
										$applicationOriginal = "";
										$applicationDate = "";
										$applicationCountry="";
										$applicationKind="";	
										if(count($publicationReference)>0){
											foreach($publicationReference as $pubRef){												
												if((string)$pubRef['document-id-type']=="docdb"){
													$pubCountry = (string)$pubRef->{'country'};
													$pubKind = (string)$pubRef->{'kind'};
													$publicationNumber = (string)$pubRef->{'doc-number'};
													$publicationDate = (string)$pubRef->{'date'};
												}
											}
										}
										$applicationReference = $family->{'application-reference'}->{'document-id'};
										if(count($applicationReference)>0){
											foreach($applicationReference as $appRef){	
												if((string)$appRef['document-id-type']=="docdb"){
													$applicationCountry = (string)$appRef->{'country'};
													$applicationKind = (string)$appRef->{'kind'};
													$applicationNumber = (string)$appRef->{'doc-number'};
													$applicationDate = (string)$appRef->{'date'};
												}
											}
										}
										
										$priorityClaim =$family->{'priority-claim'};
										$familyList = array();
										if(count($priorityClaim)>0){
											foreach($priorityClaim as $prC){
												$country = (string)$prC->{'document-id'}->{'country'};
												$docNumber = (string)$prC->{'document-id'}->{'doc-number'};
												$kind = (string)$prC->{'document-id'}->{'kind'};
												$date = (string)$prC->{'document-id'}->{'date'};
												$linkageType = (string)$prC->{'priority-linkage-type'};
												$familyList[] = array('country'=>$country,'doc_number'=>$docNumber,'kind'=>$kind,'date'=>$date,'linkage_type'=>$linkageType);
											}
										}
										$familyMember = array('family_id'=>$familyID,'patent_number'=>$publicationNumber,'publication_date'=>$publicationDate,'country'=>$pubCountry,'kind'=>$pubKind,'application_number'=>$applicationNumber,'application_date'=>$applicationDate,'application_country'=>$applicationCountry,'application_kind'=>$applicationKind,'list'=>$familyList);
										if($publicationNumber==$grantDocNum && count($primeFamily)==0){
											$mainFamilyID = $familyID;
											$searchApplicationNumber = $familyMember['application_number'];
											$primeFamily = $familyMember;
										}
										$allFamilyList[] = $familyMember;
										
									}
									if(count($allFamilyList) > 0 && $mainFamilyID != ""){
										$addList = array();
										foreach($allFamilyList as $family){
											if($mainFamilyID == $family['family_id']){
												$queryCheck = "SELECT family_id FROM ".$dbUSPTO.".patent_family_member WHERE family_id = ".$mainFamilyID." AND application_number = '".$family['application_number']."'";
												$resultCheck = $con->query($queryCheck);
												if($resultCheck && $resultCheck->num_rows == 0) {
													unset($family['list']);									
													array_push($addList, $family);
												}
											}
										}										
										if(count($addList) > 0) {
											$removeIndex = array();
											$removeApplications = array();
											$i = 0;
											foreach($addList as $family){
												if($family['country'] == 'US' && strpos($family['kind'], 'A') !== false){
													foreach($addList as $family1)  {
														if($family1['application_number'] == $family['application_number'] && $family['patent_number'] != $family1['patent_number'] && !in_array($family['application_number'], $removeApplications)) {
															array_push($removeIndex, $i);
															array_push($removeApplications, $family['application_number']);
														}
													}
												}
												$i++;
											}
											
											if(count($removeIndex) > 0) {
												foreach($removeIndex as $index) {
													unset($addList[$index]);
												}
												array_values($addList);
											}
											if(count($addList) > 0) {
												$childList = $addList;											
											}
										}										
									}
								}
							}							
						}
					}
				} else {
					file_put_contents('file_family_request.log',"ERROR IN: ".$grantDocNum."@@".$appnoDocNum."\n".PHP_EOL , FILE_APPEND | LOCK_EX);
					file_put_contents('file_family.log', $grantDocNum."@@".$appnoDocNum."\n".PHP_EOL , FILE_APPEND | LOCK_EX);
				}
			}catch (Exception $e){
				file_put_contents('file_family_request.log',"ERROR IN: ".$grantDocNum."@@".$appnoDocNum."\n".PHP_EOL , FILE_APPEND | LOCK_EX);
				file_put_contents('file_family.log', "ERROR IN: ".$grantDocNum."@@".$appnoDocNum."\n".PHP_EOL , FILE_APPEND | LOCK_EX);
			}
			array_push($parentChildPatent, array('organisation_id'=>$organisationID, 'representative_id'=>$representativeID, 'child'=>$childList,'family_id'=>$mainFamilyID,'grant_doc_num'=>$grantDocNum));
			sleep(10);
		}

		if(count($parentChildPatent) > 0) {
			$epodoc->insertData($dbUSPTO, "family_flag", $parentChildPatent, true, 'child');
		}	
	}	
	
	$queryFamilyList = "SELECT * FROM ".$dbUSPTO.".family_flag WHERE organisation_id = ".$organisationID." ORDER BY id DESC";
	
	$resultFamilyList = $con->query($queryFamilyList);
	
	
	if($resultFamilyList && $resultFamilyList->num_rows > 0) {
		$epodoc = new EpoDoc(array('key'=>'9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs','secret'=>'WgLvbrHl9QOyykTT', 'con'=>$con));
			
		$epoDocToken = $epodoc->read_token('HedCET');	
		$publication = 'publication';
				
		$db = 'epodoc';
		while($row = $resultFamilyList->fetch_object()) {
			$childList = json_decode($row->child, true);
			$childList = array_values($childList);
			$mainFamilyID = "";
			$familyData = array();
			$patentFamilyMember = array();
			$patImages = array();
			$classificationList = array();
			$allInventors = array();
			$allAssignee = array();
			$allApplicant = array();
			$allAssignments = array();
			$claimList = array();
			$abstract = ""; 
			$title = "";
			if(count($childList) == 0) {
				$sqlDocument = "SELECT * FROM ".$dbUSPTO.".documentid WHERE grant_doc_num = '".$row->grant_doc_num."'";
				
				$resultDocument = $con->query($sqlDocument);
				$t = 0;
				if($resultDocument->num_rows == 0 && $row->appno_doc_num != ""){
					$sqlDocument = "SELECT * FROM ".$dbUSPTO.".documentid WHERE appno_doc_num = '".$row->appno_doc_num."'";
				
					$resultDocument = $con->query($sqlDocument);
					$t = 1;
				}
				
				if($resultDocument->num_rows > 0) {
					$rowDocument = $resultDocument->fetch_object();
					$childList = array(array(
								'family_id' => 0,
								'patent_number' => $rowDocument->grant_doc_num != '' ? $rowDocument->grant_doc_num : $rowDocument->pgpub_doc_num,
								'publication_date'=> $rowDocument->grant_date != '' ? $rowDocument->grant_date : $rowDocument->pgpub_date,
								'country'=> $rowDocument->grant_country != '' ? $rowDocument->grant_country : $rowDocument->pgpub_country,
								'kind'=> $t == 0 ? 'B2' : 'A',
								'application_number' => $rowDocument->appno_doc_num,
								'application_date' => $rowDocument->appno_date,
								'application_country' => $rowDocument->appno_country,
								'application_kind' => $t == 0 ? 'B2': 'A'
								));
				}
			}
			if(count($childList) > 0) {
				foreach($childList as $list) {
					if(isset($list["family_id"]) && isset($list["patent_number"]) && isset($list["application_number"]) && isset($list["kind"])){
						$mainFamilyID = $list["family_id"];
						$queryFindPatentFamily = "SELECT * FROM patent_family_member where family_id = ".$mainFamilyID." AND patent_number = '".$list['patent_number']."'";
						
						$resultFindPatentFamily = $con->query($queryFindPatentFamily);
						
						$runQuery = true;
						
						if( $resultFindPatentFamily && $resultFindPatentFamily->num_rows > 0 ) {
							$runQuery = false;
						} else {
							$queryFindPatentFamily = "SELECT * FROM patent_family_member where family_id = ".$mainFamilyID." AND application_number = '".$list['application_number']."'";
						
							$resultFindPatentFamily = $con->query($queryFindPatentFamily);
							
							if( $resultFindPatentFamily && $resultFindPatentFamily->num_rows > 0 ) {
								$runQuery = false;
							}						
						}
						
						if( $runQuery === true ) {
							$process = false;
							if($row->grant_doc_num != "") {
								$process = true;
								$type = "";
								if(strpos($list['kind'], 'A') === false){
									$type = 'patent';
								} else {
									$type = 'application';
								}
								array_push($familyData, array('patent_id'=>$row->grant_doc_num,'family_id'=>$mainFamilyID, 'filling_date'=>'0000-00-00', 'type'=>$type));
								$runAPI = true;
								//If it from US find data from database first instead to get from API
								if(strtolower($list['country']) == 'us'){
									$findApplicationNumber = "";
									
									$query = "SELECT appno_doc_num FROM documentid where grant_doc_num = '".$list['patent_number']."' LIMIT 1";
									$resultQuery = $con->query($query);
									
									if($resultQuery && $resultQuery->num_rows > 0) {
										$findApplicationNumber = $resultQuery->fetch_object()->appno_doc_num;
									}
									
									if($findApplicationNumber == "") {
										$findApplicationNumber = $list['application_number'];
									}
									
									echo $queryBiblio = "SELECT * FROM db_patent_grant_bibliographic.application_details WHERE appno_doc_num = '".$findApplicationNumber."' OR grant_doc_num = '".$list['patent_number']."' LIMIT 1";
									
									$resultQuery = $con->query($queryBiblio);
									$biblioData = array();
									if($resultQuery && $resultQuery->num_rows > 0) {
										$biblioData = $resultQuery->fetch_object();
									} else {
										echo $queryBiblio = "SELECT * FROM db_patent_grant_bibliographic.application_details WHERE appno_doc_num LIKE '%".$findApplicationNumber."' LIMIT 1";
										
										$resultQuery = $con->query($queryBiblio);
										if($resultQuery && $resultQuery->num_rows > 0) {
											$biblioData = $resultQuery->fetch_object();
										}
									}
									
									
									if(isset($biblioData->appno_doc_num)){
										$runAPI = false;
										if(isset($biblioData->appno_doc_num)){
											$title = $biblioData->title;
											$abstract = $biblioData->abstract;
											
											$queryClaims = "SELECT * FROM db_patent_grant_bibliographic.application_claims WHERE appno_doc_num = '".$findApplicationNumber."' OR grant_doc_num = '".$list['patent_number']."' ORDER BY id ASC";
											
											$resultQuery = $con->query($queryClaims);									
											
											if($resultQuery && $resultQuery->num_rows > 0) {
												while($claimRow = $resultQuery->fetch_object()){
													array_push($claimList, $claimRow->text);
												}
											}
											
											$queryAssignments = "SELECT * FROM ".$dbUSPTO.".assignment WHERE rf_id IN (SELECT rf_id FROM ".$dbUSPTO.".documentid WHERE appno_doc_num = '".$findApplicationNumber."') GROUP BY rf_id";
											
											$resultQuery = $con->query($queryAssignments);
											
											if($resultQuery && $resultQuery->num_rows == 0) {
												$queryAssignments = "SELECT * FROM ".$dbUSPTO.".assignment WHERE rf_id IN (SELECT rf_id FROM ".$dbUSPTO.".documentid WHERE grant_doc_num = '".$list['patent_number']."') GROUP BY rf_id";
												
												$resultQuery = $con->query($queryAssignments);
											}
											
											if($resultQuery && $resultQuery->num_rows > 0) {
												$assignments = array();
												$rfIDs = array();
												while($assignmentRow = $resultQuery->fetch_object()){
													array_push($assignments, $assignmentRow);
													array_push($rfIDs, $assignmentRow->rf_id);
												}
												
												if(count($rfIDs) > 0) {
													$queryAssignors = "SELECT or_name, exec_dt, rf_id FROM ".$dbUSPTO.".assignor WHERE rf_id IN (".implode(',', $rfIDs).")";
													$resultQuery = $con->query($queryAssignors);
													$assignors = array();
													while($assignorRow = $resultQuery->fetch_object()){
														array_push($assignors, $assignorRow);
													}
													$assignees = array();
													$queryAssignee = "SELECT ee_name, rf_id FROM ".$dbUSPTO.".assignee WHERE rf_id IN (".implode(',', $rfIDs).")";
													$resultQuery = $con->query($queryAssignee);
													$assignees = array();
													while($assigneeRow = $resultQuery->fetch_object()){
														array_push($assignees, $assigneeRow);
													}
												}
												foreach($assignments as $assignment){
													$findAssignors = array();
													$findAssignees = array();
													$exec_date = "";
													if(count($assignors) > 0) {
														foreach($assignors as $assignor){
															if($assignor->rf_id == $assignment->rf_id){
																array_push($findAssignors, $assignor->or_name);
																if($exec_date == "") {
																	$exec_date = $assignor->exec_dt;
																}
															}
														}
													}
													
													if(count($assignees) > 0) {
														foreach($assignees as $assignee){
															if($assignee->rf_id == $assignment->rf_id){
																array_push($findAssignees, $assignee->ee_name);
															}
														}
													}
													array_push($allAssignments, array('rights'=>$assignment->convey_text, 'inventors'=>array(),'assignor'=>implode(',', $findAssignors),'assignee'=>implode(',', $findAssignees), 'assignee_date'=>$exec_date, 'recorded'=>$assignment->record_dt));
												}
											}
										}
									}
								} 
								
								//RunAPI 
								if($runAPI === true){
									//Get Biblio Data
									
									$findApplicationNumber = "";
									if(strtolower($list['country']) == 'us'){
										$query = "SELECT appno_doc_num FROM documentid where grant_doc_num = '".$list['patent_number']."' LIMIT 1";
										$resultQuery = $con->query($query);
										
										if($resultQuery && $resultQuery->num_rows > 0) {
											$findApplicationNumber = $resultQuery->fetch_object()->appno_doc_num;
										}
									}
									
									if($findApplicationNumber == "") {
										$findApplicationNumber = $list['application_number'];
									}
									
									if($list['patent_number'] != '') {
										$getBiblioData = $epodoc->runUrl($epoDocToken,'published-data',$publication,$db,$list['country'].$list['patent_number'],'biblio','');
									} else {
										$getBiblioData = $epodoc->runUrl($epoDocToken,'published-data','application',$db,$list['application_country'].$findApplicationNumber,'biblio','');
									}
									
									try{
										if(empty($getBiblioData['error'])){
											if(!empty($getBiblioData['data'])){
												$xml=simplexml_load_string($getBiblioData['data']);
												$insert = false;
												
												if ($xml !== false) {
													$xmlObject = new SimpleXMLElement($getBiblioData['data']);
													if(isset($xmlObject->code) && (($xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found") || $xmlObject->code=="CLIENT.InvalidReferenceFormat")  ){
														$getBiblioData = $epodoc->runUrl($epoDocToken,'published-data','application',$db,$list['application_country'].$findApplicationNumber,'biblio','');
													} else if(( isset($xmlObject->code) && $xmlObject->code=="CLIENT.AmbiguousRequest")) {
														$getBiblioData = $epodoc->runUrl($epoDocToken,'published-data','publication',$db,$list['application_country'].$findApplicationNumber,'biblio','');
													} else {
														$insert = true;
													}
												}
											}
											if(!empty($getBiblioData['data']) && $insert === false){
												$xml=simplexml_load_string($getBiblioData['data']);
												if ($xml !== false) {
													$xmlObject = new SimpleXMLElement($getBiblioData['data']);
													
													if(isset($xmlObject->code) && (($xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found") || $xmlObject->code=="CLIENT.InvalidReferenceFormat")  ){
														$insert = false;
													} else {
														$insert = true;
													}
												} else {
													$insert = false;
												}
											}
											
											if($insert === true){
												$worldPatentData = $xmlObject->xpath('//ops:world-patent-data');
												if(isset($worldPatentData[0])){
													$exchangeDocuments = $worldPatentData[0]->{'exchange-documents'};
													$exchangeDocumentList = $exchangeDocuments->{'exchange-document'};							
													if(count($exchangeDocumentList)>0){
														
														$title = (string)$exchangeDocumentList[0]->{'bibliographic-data'}->{'invention-title'};
														
														$abs = $exchangeDocumentList[0]->{'abstract'};
														
														if($abs != null && isset($abs->p) && count($abs->p) > 0) {
															foreach($abs->p as $pString) {
																$abstract .= (string)$pString." ";
															}
														}
														$abstract = trim($abstract);
														
														/*CPC*/
														$cpc = $exchangeDocumentList[0]->{'bibliographic-data'}->{'patent-classifications'};
														
														if($cpc != null) {
															$classifications = $cpc[0]->{'patent-classification'};
															if(count($classifications) > 0) {
																$i = 0;
																foreach($classifications as $classification){	
																	array_push($classificationList, array('patent_number'=>$list['patent_number'], 'application_number'=>$findApplicationNumber, 'grant_date'=>'', 'section'=>(string)$classification->{'section'}, 'class'=>(string)$classification->{'class'},'sub_class'=>(string)$classification->{'subclass'},'main_group'=>(string)$classification->{'main-group'}, 'sub_group'=>(string)$classification->{'subgroup'}, 'classification_value_code'=>(string)$classification->{'classification-value'},'type'=>$i == 0 ? 0 : 1));
																	$i++;
																}
															}
														}
														
														$parties = $exchangeDocumentList[0]->{'bibliographic-data'}->{'parties'};
														
														if($parties != null && isset($parties[0]->{'inventors'})) {
															$inventors = $parties[0]->{'inventors'}->{'inventor'};
															if(count($inventors)>0){
																foreach($inventors as $inventor){
																	if($inventor['data-format']=="epodoc"){
																		array_push($allInventors,(string)$inventor->{'inventor-name'}->name);
																	}
																}
															}
														}
													}
												}
											}
										}
									} catch( Exception $e) {
										echo 'Error in biblio';
										file_put_contents('epo_api_retrieve_patent_data.log',"ERROR IN BIBLIO: ".$row->id." ====>".$list['patent_number']."@@".$list['application_number']."\n".PHP_EOL , FILE_APPEND | LOCK_EX);
									}
									
									
									//Get Claim Data, we can get claim for only specific countries 
									$claimList = array();
									$apiAccessPatentCountries = array('EP','WO','AT','CA','CH','GB','FR','ES');
									if(in_array($list['country'],$apiAccessPatentCountries)){
										
										$getClaimsData = $epodoc->runUrl($epoDocToken,'published-data',$publication,$db,$list['country'].$list['patent_number'],'claims','');
										$insert = false;
										
										try {
											if(empty($getClaimsData['error'])){
												if(!empty($getClaimsData['data'])){
													$xml=simplexml_load_string($getClaimsData['data']);
													if ($xml !== false) {
														$xmlObject = new SimpleXMLElement($getClaimsData['data']);
														try{
															if(isset($xmlObject->code) && (($xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found") || $xmlObject->code=="CLIENT.InvalidReferenceFormat")  ){
																$getClaimsData = $epodoc->runUrl($epoDocToken,'published-data','application',$db,$list['application_country'].$findApplicationNumber,'claims','');
															} else if(( isset($xmlObject->code) && $xmlObject->code=="CLIENT.AmbiguousRequest")) {
																$getClaimsData = $epodoc->runUrl($epoDocToken,'published-data','publication',$db,$list['application_country'].$findApplicationNumber,'claims','');
															} else {
																$insert = true;
															}
														}catch(Exception $e){
															
														}
													}
												}
												if($insert === true && !empty($getClaimsData['data'])){
													$xml=simplexml_load_string($getClaimsData['data']);
													if ($xml !== false) {
														$xmlObject = new SimpleXMLElement($getClaimsData['data']);
														if(isset($xmlObject->code) && (($xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found") || $xmlObject->code=="CLIENT.InvalidReferenceFormat")  ){
															$insert = false;
														}
													} else {
														$insert = false;
													}
												}
												
												if($insert == true){				
													$worldPatentData = $xmlObject->xpath('//ops:world-patent-data');
													
													if(isset($worldPatentData[0])){
														$worldPatentData[0]->registerXPathNamespace('ftxt', 'http://www.epo.org/fulltext');
														$fulltextDocuments = $worldPatentData[0]->xpath('//ftxt:fulltext-documents');
													
														$fulltextDocument = $fulltextDocuments[0]->xpath('//ftxt:fulltext-document');
														if(isset($fulltextDocument[0]->{'claims'})>0){
															$claims = $fulltextDocument[0]->{'claims'};
															if(count($claims)>0){
																$claimContent = "";
																foreach($claims as $claim){
																	$claimT = $claim->{'claim'};
																	if(isset($claimT->{'claim-text'}) && count($claimT->{'claim-text'})>0){
																		foreach($claimT->{'claim-text'} as $text){
																			array_push($claimList, array('text'=>(string)$text, 'appno_doc_num'=> $findApplicationNumber, 'grant_doc_num'=> $list['patent_number']));
																		}
																	}
																}
															}
														}
													}
												}
											}
										} catch( Exception $e) {
											echo "Error in claim";
											file_put_contents('epo_api_retrieve_patent_data.log',"ERROR IN CLAIM: ".$row->id." ====>".$list['patent_number']."@@".$list['application_number']."\n".PHP_EOL , FILE_APPEND | LOCK_EX);
										}
									}							
									// if patent is from US then get the assignments data from the database
									if($list['country'] == 'US') {
										echo $queryAssignments = "SELECT * FROM ".$dbUSPTO.".assignment WHERE rf_id IN (SELECT rf_id FROM ".$dbUSPTO.".documentid WHERE appno_doc_num = '".$findApplicationNumber."') GROUP BY rf_id";
											
										$resultQuery = $con->query($queryAssignments);
										
										if($resultQuery && $resultQuery->num_rows == 0) {
											echo $queryAssignments = "SELECT * FROM ".$dbUSPTO.".assignment WHERE rf_id IN (SELECT rf_id FROM ".$dbUSPTO.".documentid WHERE grant_doc_num = '".$list['patent_number']."') GROUP BY rf_id";
											
											$resultQuery = $con->query($queryAssignments);
										}
										
										if($resultQuery && $resultQuery->num_rows > 0) {
											$assignments = array();
											$rfIDs = array();
											while($assignmentRow = $resultQuery->fetch_object()){
												array_push($assignments, $assignmentRow);
												array_push($rfIDs, $assignmentRow->rf_id);
											}
											
											if(count($rfIDs) > 0) {
												$queryAssignors = "SELECT or_name, exec_dt, rf_id FROM ".$dbUSPTO.".assignor WHERE rf_id IN (".implode(',', $rfIDs).")";
												$resultQuery = $con->query($queryAssignors);
												$assignors = array();
												while($assignorRow = $resultQuery->fetch_object()){
													array_push($assignors, $assignorRow);
												}
												$assignees = array();
												$queryAssignee = "SELECT ee_name, rf_id FROM ".$dbUSPTO.".assignee WHERE rf_id IN (".implode(',', $rfIDs).")";
												$resultQuery = $con->query($queryAssignee);
												$assignees = array();
												while($assigneeRow = $resultQuery->fetch_object()){
													array_push($assignees, $assigneeRow);
												}
											}
											foreach($assignments as $assignment){
												$findAssignors = array();
												$findAssignees = array();
												$exec_date = "";
												if(count($assignors) > 0) {
													foreach($assignors as $assignor){
														if($assignor->rf_id == $assignment->rf_id){
															array_push($findAssignors, $assignor->or_name);
															if($exec_date == "") {
																$exec_date = $assignor->exec_dt;
															}
														}
													}
												}
												
												if(count($assignees) > 0) {
													foreach($assignees as $assignee){
														if($assignee->rf_id == $assignment->rf_id){
															array_push($findAssignees, $assignee->ee_name);
														}
													}
												}
												array_push($allAssignments, array('rights'=>$assignment->convey_text, 'inventors'=>array(),'assignor'=>implode(',', $findAssignors),'assignee'=>implode(',', $findAssignees), 'assignee_date'=>$exec_date, 'recorded'=>$assignment->record_dt));
											}
										}
									}							
								}
								//Get Images for US or Non-US family members
								$getImagesData = $epodoc->runUrl($epoDocToken,'published-data',$publication,$db,$list['country'].'.'.$list['patent_number'].'.'.$list['kind'],'images','');
								$insert = false;
								try {
									if(!empty($getImagesData['data'])){
										$xml=simplexml_load_string($getImagesData['data']);
										if ($xml !== false) {
											$xmlObject = new SimpleXMLElement($getImagesData['data']);
											if(isset($xmlObject->code) && (($xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found") || $xmlObject->code=="CLIENT.InvalidReferenceFormat")){
												
												$getImagesData = $epodoc->runUrl($epoDocToken,'published-data','application',$db,$list['application_country'].'.'.$findApplicationNumber.'.'.$list['application_kind'],'images','');
											}else if(( isset($xmlObject->code) && $xmlObject->code=="CLIENT.AmbiguousRequest")) {
												$getImagesData = $epodoc->runUrl($epoDocToken,'published-data','publication',$db,$list['application_country'].'.'.$findApplicationNumber.'.'.$list['application_kind'],'images','');
											} else {
												$insert = true;
											}
										}
									}
									
									if($insert === false && !empty($getImagesData['data'])){
										$xml=simplexml_load_string($getImagesData['data']);
										if ($xml !== false) {
											$xmlObject = new SimpleXMLElement($getImagesData['data']);
											if(isset($xmlObject->code) && (($xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found") || $xmlObject->code=="CLIENT.InvalidReferenceFormat" )  ){
												$insert = false;
											} else {
												$insert = true;
											}
										} else {
											$insert = false;
										}
									}
									if($insert === true ){		
										$documentObject = $xmlObject->xpath('//ops:document-inquiry');
										
										if(isset($documentObject[0])) {
											$patentObject = $xmlObject->xpath('//ops:inquiry-result');
											
											if(isset($patentObject[0])){
												$mainDocumentReference = $patentObject[0]->xpath('//ops:document-instance');
												
												if(count($mainDocumentReference)>0){
													foreach($mainDocumentReference as $documentR){
														$imageLink =  (string)$documentR['link'];
														if((string)$documentR['desc']=='Drawing' || (string)$documentR['desc']=='FirstPageClipping'){
															$noOfPages = $documentR['number-of-pages'];
															if($noOfPages>0){
																for($i=1;$i<=$noOfPages;$i++){
																	$accept = 'application/pdf';
																	$getImagePDFData = $epodoc->singleUrl($epoDocToken,$accept,$imageLink.".pdf?Range=".$i);
																	if(isset($getImagePDFData['data']) && $getImagePDFData['data']!=''){
																		echo $imageLink."<br/>";
																		$explodeLink = explode('/',$imageLink);
																		$imageType = array_pop($explodeLink);
																		
																		$fileName = $list['patent_number'].'_'.$imageType."_".$documentR['desc']."_".$i.".pdf"; 
																		if($epodoc->fileCheck($fileName)) {
																			$fileURL  = 'https://s3-'.$epodoc->_region.'.amazonaws.com/'.$epodoc->_bucket.'/'.$epodoc->_keyPrefix.'/'.$fileName;
																		} else {
																			$fileURL = $epodoc->uploadImageS3($getImagePDFData['data'], $fileName,  ['ContentType' => 'application/pdf','ContentDisposition' => 'inline']);
																		}
																		
																		if($fileURL != "") {
																			array_push($patImages,$fileURL);
																		}
																	}
																}
															}
														}
													}
												}
											}
										}
									}
								} catch (Exception $e) {
									echo "Error in Images";
									file_put_contents('epo_api_retrieve_patent_data.log',"ERROR IN IMAGE: ".$row->id." ====>".$list['patent_number']."@@".$list['application_number']."\n".PHP_EOL , FILE_APPEND | LOCK_EX);
								}
							}
							if($process === true) {
								$patentFamilyData = $list;
								$patentFamilyData['application_number'] = $findApplicationNumber;
								$patentFamilyData['publication_country'] = $list['country'];
								$patentFamilyData['publication_number'] = $list['patent_number'];
								$patentFamilyData['publication_kind'] = $list['kind'];
								$patentFamilyData['title'] = $title;
								$patentFamilyData['abstracts'] = $abstract;
								$patentFamilyData['images'] = json_encode($patImages);
								$patentFamilyData['claims'] = json_encode($claimList);
								$patentFamilyData['inventors'] = implode(',', $allInventors);
								$patentFamilyData['assignee'] = implode(',', $allAssignee);
								$patentFamilyData['applicants'] = implode(',', $allApplicant);
								$patentFamilyData['assigments'] = json_encode($allAssignments);
								if(isset($patentFamilyData['country'])) {
									unset($patentFamilyData['country']);
								}
								if(isset($patentFamilyData['kind'])) {
									unset($patentFamilyData['kind']);
								}
								
								
								array_push($patentFamilyMember, $patentFamilyData);
							}
						}
						if($runQuery === false) {
							$con->query('DELETE FROM '.$dbUSPTO.'.family_flag WHERE id='.$row->id);
						}
					}
					if($runQuery === true) {
						sleep(5);
					}
				}
			}
			if(count($patentFamilyMember) > 0) {
				$epodoc->insertData($dbUSPTO, 'patent_family_relation',$familyData);
				$epodoc->insertData($dbUSPTO, 'patent_family_member',$patentFamilyMember);
				$con->query('DELETE FROM '.$dbUSPTO.'.family_flag WHERE id='.$row->id);
				
			}
			sleep(10);
		}
		//end loop
	}
}