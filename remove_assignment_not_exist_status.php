<?php 
require_once '/var/www/html/trash/s3_bucket/vendor/autoload.php';

use Aws\S3\S3Client;

use Aws\Credentials\Credentials;

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

$credentials = new Credentials('AKIAYD2CUN6OLDBPT4SY', 'eEdtphVIqzGX7JsL0RVxlbHaEWAmVzq6B/QNm+Cq');

$client = S3Client::factory(array(
	'credentials' => $credentials,
	'version'           => '2006-03-01',
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

$con = new mysqli($host, $user, $password, $dbApplication);

$queryFindCorrectRFIDs = 'Select assignment.* FROM '.$dbUSPTO.'.assignment WHERE assignment.status = 1  GROUP BY assignment.rf_id';
echo $queryFindCorrectRFIDs;

$resultIDs = $con->query($queryFindCorrectRFIDs);
echo "COUNTER: ".$resultIDs->num_rows."<br/>";

if($resultIDs->num_rows > 0) {			
	try{
		$counter = 1;
		while($row = $resultIDs->fetch_object()){
			echo "COUNTER: ".$counter."<br/>";
			$fileName = 'assignment-pat-'.$row->reel_no.'-'.$row->frame_no.'.pdf';
			if(fileCheck($fileName, $bucket, $keyPrefix, $client) === true){
				$info = $client->headObject([
				'Bucket'=>$bucket,
				'Key'=>$keyPrefix.$fileName
				]);
				
				if($info['ContentLength'] == 15) {
					$result = $client->deleteObject(array(
						'Bucket' => $bucket,
						'Key'    => $keyPrefix.$fileName
					));
					$con->query("UPDATE ".$dbUSPTO.".assignment SET status = 0 WHERE rf_id = ".$row->rf_id);
				}
			}
			$counter++;
		}		
	}catch(Exception $e) {
	}
}