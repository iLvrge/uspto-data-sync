<?php 


require_once '/var/www/html/trash/s3_bucket/vendor/autoload.php';

use Aws\S3\S3Client;

use Aws\Common\Credentials\Credentials;

use Aws\S3\Sync\UploadSyncBuilder;

ini_set('max_execution_time', '0');


$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$bucket = 'static.patentrack.com';
$region = 'us-west-1';
$keyPrefix = 'assignments/var/www/html/beta/resources/shared/data/';

$credentials = new Credentials(getenv('AWS_ACCESS_KEY_ID'), getenv('AWS_SECRET_KEY'));

$client = S3Client::factory(array(
	'credentials' => $credentials,
	'region'  => $region,
));


function fileCheck($fileName, $bucket, $keyPrefix, $client) {
	$status = false;
	try{
		/*print_r([
			'Bucket' => $bucket,
			'Key'    => $keyPrefix.$fileName
		]);
		$result = $client->getObject([
			'Bucket' => $bucket,
			'Key'    => $keyPrefix.$fileName
		]);
		print_r($result);die;*/
		echo "s3://".$bucket."/".$keyPrefix.$fileName."<br/>";

		$client->registerStreamWrapper();
		$fileExists = file_exists("s3://".$bucket."/".$keyPrefix.$fileName);
		if ($fileExists) {
			$status = true;
		}
	}catch(Exception $e) {	
		echo $e->getMessage() . PHP_EOL;	
	}
	return $status;
}

function fileGetContent($fileName, $bucket, $keyPrefix, $client) {
	$fileContent = '';
	try{
		/*print_r([
			'Bucket' => $bucket,
			'Key'    => $keyPrefix.$fileName
		]);
		$result = $client->getObject([
			'Bucket' => $bucket,
			'Key'    => $keyPrefix.$fileName
		]);
		print_r($result);die;*/
		$client->registerStreamWrapper();
		$fileContent = file_get_contents("s3://".$bucket."/".$keyPrefix.$fileName);
		
	}catch(Exception $e) {	
		echo $e->getMessage() . PHP_EOL;	
	}	
	return $fileContent;
}

function fileUpload($fileData, $fileName, $options, $bucket, $keyPrefix, $client, $region) {	
	$fileLocation = "";
	try{
		$result = $client->putObject([
			'Key'    => $keyPrefix.'/'.$fileName,
			'Bucket' => $bucket,
			'Body' => $fileData,
			'ACL'=> 'public-read'
		] + $options); 
		
		if($result != null) {
			$fileLocation = 'https://s3-'.$region.'.amazonaws.com/'.$bucket.'/'.$keyPrefix.$fileName;
		}
	}catch(Exception $e) {
	}
	
	return $fileLocation;
}

function getPDFFile($assList, $region, $bucket, $keyPrefix, $client){
	$fileName = 'assignment-pat-'.$assList->reel_no.'-'.$assList->frame_no.'.pdf';
	$fileLocation = ''; $content = '';	
	if(fileCheck($fileName, $bucket, $keyPrefix, $client) === false){
		echo "FILE NOT FOUND :".$fileName."<br/>";
		try{
			$content = @file_get_contents('http://legacy-assignments.uspto.gov/assignments/'.$fileName);
			
			if($content!== false){
				$fileLocation = fileUpload($content, $fileName,  ['ContentType' => 'application/pdf','ContentDisposition' => 'inline'], $bucket, $keyPrefix, $client, $region);
			}
		}catch(Exception $e) {
			echo "Error in file";
		}		
	} else {
		$fileLocation = 'https://s3-'.$region.'.amazonaws.com/'.$bucket.'/'.$keyPrefix.$fileName;
	}
	
	/*Check Split File*/
	$fileNameForm = 'assignment-pat-'.$assList->reel_no.'-'.$assList->frame_no.'_form.pdf';
	$fileFound = true ;
	if(fileCheck($fileNameForm, $bucket, $keyPrefix, $client) === false){
		echo "FILE NOT FOUND :".$fileNameForm."<br/>";
		$fileFound = false;
	}
	$fileNameAgreement = 'assignment-pat-'.$assList->reel_no.'-'.$assList->frame_no.'_agreement.pdf';
	if(fileCheck($fileNameAgreement, $bucket, $keyPrefix, $client) === false){
		echo "FILE NOT FOUND :".$fileNameAgreement."<br/>";
		$fileFound = false;
	}
	
	if($fileFound === false) {
		if($content == '') {
			$content = fileGetContent($fileName, $bucket, $keyPrefix, $client);
		}
		
		if($content != "") {
			createSplitFile($content,$fileName, $bucket, $keyPrefix, $client, $region);
		}
	}
	
	return $fileLocation;
}

function createSplitFile($content, $fileName, $bucket, $keyPrefix, $client, $region) {
	$fileCheck = '/var/www/html/beta/resources/shared/data/';
	$f = fopen($fileCheck.$fileName, "w+");
	fwrite($f, $content);
	fclose($f);
	echo $fileCheck.$fileName;
	if(file_exists($fileCheck.$fileName)) {
		
		exec('python3 /var/www/html/python_script/split_pdf_v4.py "'.$fileCheck.$fileName.'" "'.$fileCheck.'" "'.$fileCheck.'"');
		$fileRename = str_replace(".pdf", "", $fileName );
		$agreementName = $fileRename.'_agreement.pdf';
		$tap = 0;
		if(file_exists($fileCheck.$agreementName)){			
			$agreementContent = file_get_contents($fileCheck.$agreementName);
			$tap = 1;
		} else {			
			$agreementContent = $content;
		}
		
		fileUpload($agreementContent, $agreementName,  ['ContentType' => 'application/pdf','ContentDisposition' => 'inline'], $bucket, $keyPrefix, $client, $region);
		
		if($tap == 1) {
			unlink($fileCheck.$agreementName);
		}
		
		$formName = $fileRename.'_form.pdf';
		$tap = 0;
		if(file_exists($fileCheck.$formName)){			
			$formContent = file_get_contents($fileCheck.$formName);
			$tap = 1;
		} else {
			$formContent = $content;
		}
		
		fileUpload($formContent, $formName,  ['ContentType' => 'application/pdf','ContentDisposition' => 'inline'], $bucket, $keyPrefix, $client, $region);
		
		if($tap == 1) {
			unlink($fileCheck.$formName);
		}

		unlink($fileCheck.$fileName);		
	}
}


/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$con = new mysqli($host, $user, $password, $dbUSPTO);

$queryFindCorrectRFIDs = 'SELECT assignment.* FROM list2 INNER JOIN assignment ON assignment.rf_id = list2.rf_id WHERE status = 0 GROUP by assignment.rf_id';



$resultIDs = $con->query($queryFindCorrectRFIDs);
echo "COUNTER: ".$resultIDs->num_rows."<br/>";

if($resultIDs->num_rows > 0) {			
	try{
		$counter = 1;
		while($row = $resultIDs->fetch_object()){
			echo "COUNTER: ".$counter."<br/>";
			$fileName = getPDFFile($row, $region, $bucket, $keyPrefix, $client);
			echo "FILE: ".$fileName."<br/>";
			if($fileName == "") {
				$con->query("UPDATE ".$dbUSPTO.".assignment SET status = 2 WHERE rf_id = ".$row->rf_id);
			} else {
				$con->query("UPDATE ".$dbUSPTO.".assignment SET status = 1 WHERE rf_id = ".$row->rf_id);
			}
			$counter++;
		}		
	}catch(Exception $e) {
	}
}

?>