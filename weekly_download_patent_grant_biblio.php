<?php 
ini_set('max_execution_time', 0);
$startDate = '2025-05-27';
$week = 01;
$endDate = '2025-12-30';

while(1==1) {
	if(strtotime($startDate) <= strtotime($endDate)){
		
		//$weekNo = date('W', strtotime($startDate));
		$date = new DateTime($startDate);
		$fileName = 'I'.$date->format('Ymd').'.tar';
        $year = $date->format('Y');
		$fileURL = 'https://api.uspto.gov/api/v1/datasets/products/files/PTGRDT/'.$year.'/'.$fileName;
		
		downloadFile($fileURL, '/mnt/volume_sfo2_12/patent/DOWNLOAD/'.$fileName);

        //exec('php -f /var/www/html/trash/patent_weekly_download.php');

		$date->modify('+7 days');
        $startDate = $date->format('Y-m-d');
		
	} else {
		break;
	}
}

 //exec('php -f /var/www/html/trash/patent_weekly_download.php');

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

    $fp = fopen($path, 'w+');
    if ($fp === false) {
        echo "Failed to open file at $savePath\n";
        return;
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
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

    if (file_put_contents($path, $body) === false) {
        echo "Failed to save file to $path\n";
        return false;
    }

    return true;
}

?>
