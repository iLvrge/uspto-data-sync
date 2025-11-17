<?php 
ini_set('max_execution_time', 0);
$startDate = '2025-06-02';
$week = 01;
$endDate = '2025-06-20';

while(1==1) {
	if(strtotime($startDate) <= strtotime($endDate)){
		$date = new DateTime($startDate);
		$fileName = 'ad'.$date->format('Ymd').'.zip';
		$fileURL = 'https://api.uspto.gov/api/v1/datasets/products/files/PASDL/'.$fileName;
		
		downloadFile($fileURL, '/dds/'.$fileName);

		$date->modify('+1 day');
		$startDate = $date->format('Y-m-d');
		
	} else {
		break;
	}
}
chdir('/var/www/html/trash/dds');
exec('find . -name "*.zip" -exec unzip -o {} \;',$output, $return);
chdir('/var/www/html/trash');
exec('find . -name "*.zip" -type f -delete');
exec('php -f update_record_daily_xml.php');

/* function downloadFile($url, $path){
	echo $url;  
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 
	$data = curl_exec($ch);
	
	curl_close($ch);
	
	file_put_contents(dirname(__FILE__) .$path, $data);
} */

function downloadFile($url, $path, $retry = 0)
{ 
	echo $url;
    $MAX_RETRY = 5;
    $SLEEP_AFTER_429 = 1; // seconds 
	$apiKey = getenv('USPTO_OPEN_API_KEY'); 
    $headers = [
        'x-api-key: ' . $apiKey
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true, // handle 302 redirects
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADER => true, // get headers to check HTTP status
    ]);

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $body = substr($response, $headerSize);

    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    if ($httpCode === 429 && $retry < $MAX_RETRY) {
        echo "429 Too Many Requests — Retrying in {$SLEEP_AFTER_429}s (attempt $retry)\n";
        sleep($SLEEP_AFTER_429);
        return downloadFile($url, $path, $apiKey, $retry + 1);
    }

    if ($httpCode !== 200) {
        echo "HTTP error $httpCode\n";
        return false;
    }

    $fullPath = dirname(__FILE__) . $path;
    if (file_put_contents($fullPath, $body) === false) {
        echo "Failed to save file to $fullPath\n";
        return false;
    }

    return true;
} 

/*

function fetchUSPTOData() {
    $url = 'https://api.uspto.gov/api/v1/datasets/products/pasdl?fileDataFromDate=2025-01-01&fileDataToDate=2025-05-13&includeFiles=true'; 

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'cURL Error: ' . curl_error($ch);
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    // Convert JSON response to PHP array
    return json_decode($response, true);
}

// Example usage
$data = fetchUSPTOData();
print_r($data);

*/

?>
