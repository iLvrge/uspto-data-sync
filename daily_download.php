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

    $ddsDir = __DIR__ . '/dds';
    if (!is_dir($ddsDir)) {
        mkdir($ddsDir, 0777, true);
    }

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

    chdir($ddsDir);
    exec('find . -name "*.zip" -exec unzip -o {} \;',$output, $return);
    chdir(__DIR__);
    exec('find ./dds -name "*.zip" -type f -delete');
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
    global $dotenv;
    $apiKey = isset($dotenv['USPTO_OPEN_API_KEY']) ? $dotenv['USPTO_OPEN_API_KEY'] : getenv('USPTO_OPEN_API_KEY'); 
    $headers = [
        'x-api-key: ' . $apiKey
    ];


    $fullPath = dirname(__FILE__) . $path;
    
    // Open file handle for writing
    $fp = fopen($fullPath, 'w+');
    if ($fp === false) {
        echo "Failed to open file for writing: $fullPath\n";
        return false;
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_WRITEFUNCTION => function($curl, $data) use ($fp) {
            return fwrite($fp, $data);
        },
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 3600, // 1 hour timeout for large files
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_HEADER => false, // Don't include headers in output
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    fclose($fp);

    if ($result === false) {
        echo 'cURL error: ' . $curlError . "\n";
        unlink($fullPath); // Clean up partial file
        return false;
    }

    if ($httpCode === 429 && $retry < $MAX_RETRY) {
        echo "429 Too Many Requests — Retrying in {$SLEEP_AFTER_429}s (attempt $retry)\n";
        unlink($fullPath); // Clean up
        sleep($SLEEP_AFTER_429);
        return downloadFile($url, $path, $retry + 1);
    }

    if ($httpCode !== 200) {
        echo "HTTP error $httpCode\n";
        unlink($fullPath); // Clean up partial file
        return false;
    }

    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if (strpos(strtolower($contentType), 'text/html') !== false || strpos(strtolower($contentType), 'application/json') !== false) {
        echo "Error: Downloaded file is an HTML/JSON page (File not found on USPTO server). Content-Type: $contentType\n";
        unlink($fullPath); // Clean up file
        return false;
    }

    // Verify it's actually a zip file by checking first 4 bytes (PK\x03\x04)
    $handle = fopen($fullPath, 'rb');
    if ($handle) {
        $header = fread($handle, 4);
        fclose($handle);
        if ($header !== "\x50\x4b\x03\x04") {
            echo "Error: Downloaded file is not a valid ZIP file.\n";
            unlink($fullPath);
            return false;
        }
    }

    $fileSize = filesize($fullPath);
    echo "Download completed successfully. File size: " . number_format($fileSize) . " bytes\n";
    return true;
}

?>
