<?php
ini_set('max_execution_time', 0);
ignore_user_abort(true);
ini_set('xdebug.max_nesting_level', 1000);
ini_set("memory_limit","256M");

$dotenv = parse_ini_file(__DIR__ . '/.env');

$host = $dotenv['DB_HOST'];
$user = $dotenv['DB_USER']; 
$password = $dotenv['DB_PASS'] ?? $dotenv['DB_PASSWORD'] ?? '';
$dbUSPTO = $dotenv['DB_NAME'] ?? $dotenv['DB_USPTO_DB'] ?? '';

$dbBusiness = $dotenv['DB_BUSINESS'];
$dbApplication = $dotenv['DB_APPLICATION_DB'];

$dbAppBiblio = $dotenv['DB_APPLICATION_BIBLIO'];
$dbGrantBiblio = $dotenv['DB_GRANT_BIBLIO'];

// Daily Logging setup
$logDir = __DIR__ . '/log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/' . date('Y-m-d') . '.log';
ini_set("log_errors", 1);
ini_set("error_log", $logFile);

/**
 * Ensure database connection is alive, reconnect if needed
 */
function ensureConnection(&$con, $host, $user, $password, $database) {
    if ($con === null || (is_object($con) && $con->connect_errno) || !@$con->ping()) {
        error_log("Connection lost or not established. Attempting to connect/reconnect...");
        // Close existing connection if it exists
        if ($con !== null && is_object($con)) {
            @$con->close();
        }
        // Create new connection
        $con = new mysqli($host, $user, $password, $database);
        if ($con->connect_errno) {
            error_log("CRITICAL: Failed to connect to MySQL: " . $con->connect_error);
            return false;
        }
        mysqli_set_charset($con, 'utf8');
        mysqli_query($con, "SET NAMES 'utf8';");
        mysqli_query($con, "SET CHARACTER SET 'utf8';");
        mysqli_query($con, "SET COLLATION_CONNECTION = 'utf8_unicode_ci';");
        error_log("Database connection established/re-established successfully");
    }
    return true;
}

/**
 * Create initial database connection
 */
$con = null;
if (!ensureConnection($con, $host, $user, $password, $dbUSPTO)) {
    die("CRITICAL: Initial database connection failed. Check logs at " . $logFile);
}
?>
