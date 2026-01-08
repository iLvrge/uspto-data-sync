<?php
/**
 * Download Tracking Helper
 * 
 * Provides functions for inserting and updating records in the download_tracking table.
 * 
 * Usage:
 *   require_once 'download_tracking_helper.php';
 *   
 *   // Insert a new record
 *   $id = insertDownloadTracking($con, [
 *       'download_type' => 'daily_download',
 *       'last_download_datetime' => date('Y-m-d H:i:s'),
 *       'schedule_frequency' => 'daily',
 *       'status' => 'in_progress'
 *   ]);
 *   
 *   // Update an existing record by ID
 *   updateDownloadTracking($con, $id, [
 *       'status' => 'success',
 *       'files_downloaded' => 5
 *   ]);
 *   
 *   // Update by download_type
 *   updateDownloadTrackingByType($con, 'daily_download', [
 *       'status' => 'success',
 *       'files_downloaded' => 10
 *   ]);
 */

/**
 * Insert a new download tracking record
 * 
 * @param mysqli $con Database connection
 * @param array $data Associative array with column => value pairs
 *   Required: download_type, last_download_datetime, schedule_frequency
 *   Optional: next_scheduled_date, schedule_day, status, files_downloaded, error_message
 * @return int|false Returns the inserted ID on success, false on failure
 */
function insertDownloadTracking($con, $data) {
    $columns = [];
    $values = [];
    $types = '';
    $params = [];
    
    // Define allowed columns and their types
    $allowedColumns = [
        'download_type' => 's',
        'last_download_datetime' => 's',
        'next_scheduled_date' => 's',
        'schedule_frequency' => 's',
        'schedule_day' => 's',
        'status' => 's',
        'files_downloaded' => 'i',
        'error_message' => 's'
    ];
    
    foreach ($data as $column => $value) {
        if (isset($allowedColumns[$column])) {
            $columns[] = $column;
            $values[] = '?';
            $types .= $allowedColumns[$column];
            $params[] = $value;
        }
    }
    
    if (empty($columns)) {
        return false;
    }
    
    $sql = "INSERT INTO download_tracking (" . implode(', ', $columns) . ") 
            VALUES (" . implode(', ', $values) . ")";
    
    $stmt = mysqli_prepare($con, $sql);
    
    if (!$stmt) {
        error_log("InsertDownloadTracking prepare error: " . mysqli_error($con));
        return false;
    }
    
    // Bind parameters dynamically
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        $insertId = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);
        return $insertId;
    }
    
    error_log("InsertDownloadTracking execute error: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Update an existing download tracking record by ID
 * 
 * @param mysqli $con Database connection
 * @param int $id Record ID to update
 * @param array $data Associative array with column => value pairs to update
 * @return bool Returns true on success, false on failure
 */
function updateDownloadTracking($con, $id, $data) {
    if (empty($data) || empty($id)) {
        return false;
    }
    
    $setClauses = [];
    $types = '';
    $params = [];
    
    // Define allowed columns and their types
    $allowedColumns = [
        'download_type' => 's',
        'last_download_datetime' => 's',
        'next_scheduled_date' => 's',
        'schedule_frequency' => 's',
        'schedule_day' => 's',
        'status' => 's',
        'files_downloaded' => 'i',
        'error_message' => 's'
    ];
    
    foreach ($data as $column => $value) {
        if (isset($allowedColumns[$column])) {
            $setClauses[] = "$column = ?";
            $types .= $allowedColumns[$column];
            $params[] = $value;
        }
    }
    
    if (empty($setClauses)) {
        return false;
    }
    
    // Add id to params
    $types .= 'i';
    $params[] = $id;
    
    $sql = "UPDATE download_tracking SET " . implode(', ', $setClauses) . " WHERE id = ?";
    
    $stmt = mysqli_prepare($con, $sql);
    
    if (!$stmt) {
        error_log("UpdateDownloadTracking prepare error: " . mysqli_error($con));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return true;
    }
    
    error_log("UpdateDownloadTracking execute error: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Update an existing download tracking record by download_type
 * 
 * @param mysqli $con Database connection
 * @param string $downloadType The download_type to update
 * @param array $data Associative array with column => value pairs to update
 * @return bool Returns true on success, false on failure
 */
function updateDownloadTrackingByType($con, $downloadType, $data) {
    if (empty($data) || empty($downloadType)) {
        return false;
    }
    
    $setClauses = [];
    $types = '';
    $params = [];
    
    // Define allowed columns and their types
    $allowedColumns = [
        'last_download_datetime' => 's',
        'next_scheduled_date' => 's',
        'schedule_frequency' => 's',
        'schedule_day' => 's',
        'status' => 's',
        'files_downloaded' => 'i',
        'error_message' => 's'
    ];
    
    foreach ($data as $column => $value) {
        if (isset($allowedColumns[$column])) {
            $setClauses[] = "$column = ?";
            $types .= $allowedColumns[$column];
            $params[] = $value;
        }
    }
    
    if (empty($setClauses)) {
        return false;
    }
    
    // Add download_type to params
    $types .= 's';
    $params[] = $downloadType;
    
    $sql = "UPDATE download_tracking SET " . implode(', ', $setClauses) . " WHERE download_type = ?";
    
    $stmt = mysqli_prepare($con, $sql);
    
    if (!$stmt) {
        error_log("UpdateDownloadTrackingByType prepare error: " . mysqli_error($con));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return true;
    }
    
    error_log("UpdateDownloadTrackingByType execute error: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    return false;
}

/**
 * Insert or update a download tracking record (upsert)
 * Inserts if download_type doesn't exist, updates if it does
 * 
 * @param mysqli $con Database connection
 * @param array $data Associative array with column => value pairs
 *   Required: download_type, last_download_datetime, schedule_frequency
 * @return int|false Returns the ID (new or existing) on success, false on failure
 */
function upsertDownloadTracking($con, $data) {
    if (!isset($data['download_type'])) {
        return false;
    }
    
    $downloadType = $data['download_type'];
    
    // Check if record exists
    $checkSql = "SELECT id FROM download_tracking WHERE download_type = ?";
    $checkStmt = mysqli_prepare($con, $checkSql);
    
    if (!$checkStmt) {
        error_log("UpsertDownloadTracking check prepare error: " . mysqli_error($con));
        return false;
    }
    
    mysqli_stmt_bind_param($checkStmt, 's', $downloadType);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);
    $existingRow = mysqli_fetch_assoc($result);
    mysqli_stmt_close($checkStmt);
    
    if ($existingRow) {
        // Update existing record
        $id = $existingRow['id'];
        unset($data['download_type']); // Don't update the type itself
        if (updateDownloadTracking($con, $id, $data)) {
            return $id;
        }
        return false;
    } else {
        // Insert new record
        return insertDownloadTracking($con, $data);
    }
}

/**
 * Get a download tracking record by type
 * 
 * @param mysqli $con Database connection
 * @param string $downloadType The download_type to fetch
 * @return array|null Returns the record as an associative array, or null if not found
 */
function getDownloadTrackingByType($con, $downloadType) {
    $sql = "SELECT * FROM download_tracking WHERE download_type = ?";
    $stmt = mysqli_prepare($con, $sql);
    
    if (!$stmt) {
        error_log("GetDownloadTrackingByType prepare error: " . mysqli_error($con));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, 's', $downloadType);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $row;
}

/**
 * Calculate and set the next scheduled date based on frequency
 * 
 * @param mysqli $con Database connection
 * @param int $id Record ID
 * @param string $frequency Schedule frequency: 'daily', 'weekly', 'monthly'
 * @param string|null $scheduleDay For weekly (e.g., 'Thursday') or monthly (e.g., '1' for 1st)
 * @return bool Returns true on success, false on failure
 */
function setNextScheduledDate($con, $id, $frequency, $scheduleDay = null) {
    $nextDate = null;
    $today = new DateTime();
    
    switch ($frequency) {
        case 'daily':
            $nextDate = $today->modify('+1 day')->format('Y-m-d');
            break;
            
        case 'weekly':
            if ($scheduleDay) {
                $nextDate = $today->modify('next ' . $scheduleDay)->format('Y-m-d');
            } else {
                $nextDate = $today->modify('+1 week')->format('Y-m-d');
            }
            break;
            
        case 'monthly':
            if ($scheduleDay && is_numeric($scheduleDay)) {
                $today->modify('first day of next month');
                $today->setDate($today->format('Y'), $today->format('m'), min((int)$scheduleDay, $today->format('t')));
                $nextDate = $today->format('Y-m-d');
            } else {
                $nextDate = $today->modify('+1 month')->format('Y-m-d');
            }
            break;
            
        case 'on_demand':
        default:
            $nextDate = null;
            break;
    }
    
    return updateDownloadTracking($con, $id, [
        'next_scheduled_date' => $nextDate,
        'schedule_day' => $scheduleDay
    ]);
}
?>
