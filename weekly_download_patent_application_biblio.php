<?php 
ini_set('max_execution_time', 0);
$startDate = '2025-06-05';
$week = 01;
$endDate = '2026-01-01';

while(1==1) {
	if(strtotime($startDate) <= strtotime($endDate)){
		
		//$weekNo = date('W', strtotime($startDate));
		$date = new DateTime($startDate);
		$fileName = 'I'.$date->format('Ymd').'.tar';
        $year = $date->format('Y');
		$fileURL = 'https://api.uspto.gov/api/v1/datasets/products/files/APPDT/'.$year.'/'.$fileName;
		
		downloadFile($fileURL, '/mnt/volume_sfo2_12/applications/DOWNLOAD/'.$fileName);

        exec('php -f /var/www/html/trash/application_weekly_download.php');

		$date->modify('+7 days');
        $startDate = $date->format('Y-m-d');
	} else {
		break;
	}
}

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
    echo $url . "\n";
    $MAX_RETRY = 5;
    $SLEEP_AFTER_429 = 1; 
    $apiKey = getenv('USPTO_OPEN_API_KEY'); 

    $fp = fopen($path, 'w+');
    if ($fp === false) {
        echo "Failed to open file at $path\n";
        exit;
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey
        ],
        CURLOPT_FAILONERROR => false, // We check HTTP code manually
    ]);

    curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch) . "\n";
        curl_close($ch);
        fclose($fp);
        exit;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($httpCode === 429 && $retry < $MAX_RETRY) {
        echo "429 Too Many Requests — Retrying in {$SLEEP_AFTER_429}s (attempt $retry)\n";
        sleep($SLEEP_AFTER_429);
        return downloadFile($url, $path, $retry + 1);
    }

    if ($httpCode !== 200) {
        echo "HTTP error $httpCode\n";
        if (file_exists($path)) {
            unlink($path); // Delete the partial/error file
        }
        exit;
    }

    return true;
}

?>
