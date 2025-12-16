<?php 
require_once '/var/www/html/trash/s3_bucket/vendor/autoload.php';

use Aws\S3\S3Client;

use Aws\Common\Credentials\Credentials;

use Aws\S3\Sync\UploadSyncBuilder;

Class EpoDoc{
	public $_key;
	public $_secret;
	public $_con;
	public $_client;
	public $_bucket = 'static.patentrack.com';
	public $_region = 'us-west-1';
	public $_keyPrefix = 'figures';
	public function __construct($credential){
		$this->_key = $credential['key'];
		$this->_secret = $credential['secret'];				
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
$epodoc = new EpoDoc(array('key'=>'9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs','secret'=>'WgLvbrHl9QOyykTT'));
$epoDocToken = $epodoc->read_token('HedCET');	
$publication = 'application';
$db = 'docdb';		
$variables = $argv;
$getImagePDFData = $epodoc->singleUrl($epoDocToken,'application/tiff',$variables[1]);

if(isset($getImagePDFData['data'])) {
	$f = fopen('/var/www/html/trash/content.tif', 'w');
	fwrite($f, $getImagePDFData['data']);
	fclose($f);
	echo '/var/www/html/trash/content.tif';
} else {
	echo '';
} 
?>