<?php 
ini_set('max_execution_time', 0);
if(isset($argv[1])){
	$startDate = $argv[1];
} else {
	echo "Enter start date (Y-m-d): ";
	$startDate = trim(fgets(STDIN));
}
$week = 01;
$endDate = $startDate;

while(1==1) {
    if(strtotime($startDate) <= strtotime($endDate)){
        
        //$weekNo = date('W', strtotime($startDate));
        $date = new DateTime($startDate);
        $fileName = $date->format('Ymd').'.zip';
        $year = $date->format('Y');
        $fileURL = 'https://api.uspto.gov/api/v1/datasets/products/files/PTFWPRE/2021-2025-patent-filewrapper-full-json-'.$fileName;
        
        downloadFile($fileURL, '/mnt/volume_sfo2_12/STATUS/'.$fileName);
        exit;
    } else {
        break;
    }
}
chdir('/mnt/volume_sfo2_12/STATUS');
/* exec('find . -name "*.zip" -exec unzip -o {} \;',$output, $return);
exec('php -f /var/www/html/trash/maintainaince_file.php');

exec('find . -name "*.zip" -type f -delete'); */


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
