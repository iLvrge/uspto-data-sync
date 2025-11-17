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

function fileGetContent($url) {
	$fileContent = '';
	try{
		$ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 960 );
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Encoding: none','Content-Type: application/pdf')); 

        //header('Content-type: application/pdf');
        $fileContent = curl_exec($ch);
        curl_close($ch);
		
	}catch(Exception $e) {	
		echo $e->getMessage() . PHP_EOL;	
	}	
	return $fileContent;
}


function getPDFFile($assList, $region, $bucket, $keyPrefix, $client){
	$fileName = 'assignment-pat-'.$assList->reel_no.'-'.$assList->frame_no.'.pdf';
	$fileLocation = ''; $content = '';	
	if(fileCheck($fileName, $bucket, $keyPrefix, $client) !== false){
		echo "FILE FOUND :".$fileName."<br/>";
		try{
            $fileLocation = 'https://s3-'.$region.'.amazonaws.com/'.$bucket.'/'.$keyPrefix.$fileName;
			$content = fileGetContent($fileLocation);
            if($content == '' || ((stripos($content, "AccessDenied") !== false || stripos($content, "Access Denied") !== false) && stripos($content, "<Error>") !== false)) {
                echo "I AM IN ACCESS DENIED";
                $fileLocation =  '';
            } else {
                echo $content;
            }
		}catch(Exception $e) {
			echo "Error in file";
		}		
	} 
	
	return $fileLocation;
}



/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$con = new mysqli($host, $user, $password, $dbUSPTO);

$queryFindCorrectRFIDs = 'SELECT assignment.* FROM assignment WHERE status = 1 GROUP by assignment.rf_id';

$resultIDs = $con->query($queryFindCorrectRFIDs);
echo "COUNTER: ".$resultIDs->num_rows."<br/>";

if($resultIDs->num_rows > 0) {			
	try{
		$counter = 1;
		while($row = $resultIDs->fetch_object()){
			echo "COUNTER: ".$counter."<br/>";
			$fileName = getPDFFile($row, $region, $bucket, $keyPrefix, $client);
			echo "FILE: ".$fileName."<br/>";
			/* if($fileName == "") {
				$con->query("UPDATE ".$dbUSPTO.".assignment SET status = 0 WHERE rf_id = ".$row->rf_id);
			} */
			$counter++;
		}		
	}catch(Exception $e) {
	}
}

?>