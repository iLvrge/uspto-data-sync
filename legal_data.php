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
$con = new mysqli($host, $user, $password, $dbApplication);

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
		$credentials = new Credentials(getenv('AWS_ACCESS_KEY_ID'), getenv('AWS_SECRET_KEY'));		
		$this->_client = S3Client::factory(array(
			'credentials' => $credentials,
			'region'  => $this->_region,
		));	
	}
	public function read_token($tokenName) {
		$error = '';
		/*$tokenFile = "/var/www/html/trash/tmp/$tokenName.dat";
		if(file_exists($tokenFile)) {
			$token = unserialize(file_get_contents($tokenFile));
			$tokenTime = substr($token['issued_at'], 0, -3) + $token['expires_in'] - 120;
			if($tokenTime < time()) $error .= "token '$tokenName' expired<br>\n";
			else $token['error']=$error;
		} else $error .= "tokenFile '$tokenName' notFound<br>\n";
		if($error)*/ $token = $this->create_token($tokenName);
		return($token);
	}
	private function create_token($tokenName) {
		$error = '';
		/*switch($tokenName) {
			case 'HedCET':
				$ops_key = '9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs';
				$ops_secret = 'WgLvbrHl9QOyykTT';
			break;
			default:
				$ops_key = '9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs';
				$ops_secret = 'WgLvbrHl9QOyykTT';
			break;
		}*/
		$tokenFile = "/var/www/html/trash/tmp/$tokenName.dat";
		
		$tokenHeader = array(
			'Authorization: Basic '.base64_encode($this->_key.':'.$this->_secret),
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
	
	function insertData($dbUSPTO, $tableName, $list){		
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
					$stringValue .="'".$this->_con->real_escape_string($value)."'".", ";
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
		
}
/*
Consumer Key: nmjS19rDAB6mOzUJDiAbg6ZHBPAMfddd
Consumer Secret Key: SiZAxixKvGlU7JO6

Consumer Key: 9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs
Consumer Secret Key: WgLvbrHl9QOyykTT
*/
$epodoc = new EpoDoc(array('key'=>'nmjS19rDAB6mOzUJDiAbg6ZHBPAMfddd','secret'=>'SiZAxixKvGlU7JO6', 'con'=>$con));
	
$epoDocToken = $epodoc->read_token('HedCET');	

$publication = 'publication';

$db = 'docdb';
$getLegalData = $epodoc->runUrl($epoDocToken, 'family', $publication, $db, 'CA2415888A1','legal','');
echo "<pre>";
print_r($getLegalData);
die;

$allLegalData = array();
if(!empty($getLegalData['data'])){
	try{
		$xml=simplexml_load_string($getLegalData['data']);
		if ($xml !== false) {				
			$xmlObject = new SimpleXMLElement($getLegalData['data']);
			if(isset($xmlObject->code) && $xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found"){
				$allLegalData = array();
			} else{
				print_r($xmlObject);
				die;
				$patentObject = $xmlObject->xpath('//ops:patent-family');
				$listOfFamilyMembers = $xmlObject->xpath('//ops:family-member');
				if(count($listOfFamilyMembers)>0){
					$mainPatentDetails = array();
					//$searchNumber = $grantDocNum;
					$searchApplicationNumber = "";
					$allFamilyList = array();
					$primeFamily = array();
					$mainFamilyID = "";
					foreach($listOfFamilyMembers as $family){
						$familyID = (string)$family['family-id'];
						$includeOther = false;
						if($mainFamilyID!="" && $mainFamilyID==$familyID){
							$includeOther = true;
						} else if($mainFamilyID==""){
							$includeOther = true;
						}
						/*if($includeOther===true):*/
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
						$legalEvents = array();
						if(count($publicationReference)>0){
							foreach($publicationReference as $pubRef){	
								/*if((string)$pubRef['document-id-type']=="epodoc"){
									$publicationNumber = (string)$pubRef->{'doc-number'};
									
									$publicationDate = (string)$pubRef->{'date'};
								} else*/ 
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
						/*check family*/
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
						if(strtolower($pubCountry) != 'us'){
							$legalObject = $family->xpath('//ops:legal');
							if(count($legalObject) > 0) {
								for($i=0;$i<count($legalObject);$i++){
									$event = array();
									$event['appno_doc_num'] = $applicationNumber;
									$event['grant_doc_num'] = $publicationNumber;
									$event['country'] = $pubCountry;
									$event['kind'] = $pubKind;
									$event['event_date'] = (string)$legalObject[$i]->xpath('ops:L007EP')[0];
									$event['event_code'] = (string)$legalObject[$i]['code'];
									$event['title'] = (string)$legalObject[$i]['desc'];
									array_push($legalEvents, $event);
								}
							}
						}
						$familyMember = array('family_id'=>$familyID,'patent_number'=>$publicationNumber,'publication_date'=>$publicationDate,'country'=>$pubCountry,'kind'=>$pubKind,'application_number'=>$applicationNumber,'application_date'=>$applicationDate,'application_country'=>$applicationCountry,'application_kind'=>$applicationKind,'list'=>$familyList, 'events'=>$legalEvents);
						if($publicationNumber==$grantDocNum && count($primeFamily)==0){
							$mainFamilyID = $familyID;
							$searchApplicationNumber = $familyMember['application_number'];
							$primeFamily = $familyMember;
						}
						$allFamilyList[] = $familyMember;
						/*endif;*/
					}
					echo "<pre>";
					print_r($allFamilyList);
					die;
				}
			}
		}
	}catch(Exception $e){
		
	}
}