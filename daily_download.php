<?php 
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

// Include database connection and tracking helper
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/download_tracking_helper.php';

$downloadType = 'daily_download';
$filesDownloaded = 0;

// Get last download record to determine start date
$lastRecord = getDownloadTrackingByType($con, $downloadType);

if ($lastRecord) {
    $lastDownloadDate = new DateTime($lastRecord['last_download_datetime']);
    
    if ($lastRecord['status'] === 'success') {
        // If last download was successful, start from next day
        $lastDownloadDate->modify('+1 day');
        $startDate = $lastDownloadDate->format('Y-m-d');
    } else {
        // If last download failed, retry from the same date
        $startDate = $lastDownloadDate->format('Y-m-d');
    }
} else {
    // No previous record, start from today
    $startDate = date('Y-m-d');
}

$endDate = date('Y-m-d');

// Start tracking - mark as in_progress
$trackingId = upsertDownloadTracking($con, [
    'download_type' => $downloadType,
    'last_download_datetime' => date('Y-m-d H:i:s'),
    'schedule_frequency' => 'daily',
    'status' => 'in_progress',
    'files_downloaded' => 0,
    'error_message' => null
]);

try {

    while(1==1) {
        if(strtotime($startDate) <= strtotime($endDate)){
            $date = new DateTime($startDate);
            $fileName = 'ad'.$date->format('Ymd').'.zip';
            $fileURL = 'https://api.uspto.gov/api/v1/datasets/products/files/PASDL/'.$fileName;
            
            if (downloadFile($fileURL, '/dds/'.$fileName)) {
                $filesDownloaded++;
            }

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

    // Ensure database connection is alive before updates
    ensureConnection($con, $host, $user, $password, $dbUSPTO);

    // Update tracking - mark as success
    updateDownloadTracking($con, $trackingId, [
        'status' => 'success',
        'files_downloaded' => $filesDownloaded,
        'error_message' => null
    ]);
    
    // Set next scheduled date
    setNextScheduledDate($con, $trackingId, 'daily');

} catch (Exception $e) {
    // Update tracking - mark as failed
    updateDownloadTracking($con, $trackingId, [
        'status' => 'failed',
        'files_downloaded' => $filesDownloaded,
        'error_message' => $e->getMessage()
    ]);
    echo "Error: " . $e->getMessage() . "\n";
}

function downloadFile($url, $path, $retry = 0)
{ 
	echo "DOWNLOAD FILE URL: ".$url. "\n";
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
        return downloadFile($url, $path, $retry + 1);
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

?>
