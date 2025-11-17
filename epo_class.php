<?php 

Class EpoDoc{
	public $_key = '9Xx2YnGvcjhCkeGLFAyVJLJgZSuaPjYs';
	public $_secret = 'WgLvbrHl9QOyykTT';
	public $_con;
	public function __construct(){
		
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
		echo "==============================REQUESTING URL: ".$request_url."============================<br/>";
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
	
}