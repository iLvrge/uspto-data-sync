

<?php 


ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set("log_errors", 1);
ini_set("error_log", "/var/www/html/trash/daily_file.log");

ini_set('xdebug.max_nesting_level', 1000);
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$con->query("INSERT IGNORE INTO db_uspto.assignees(rf_id, original_name, ee_name, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country, assignor_and_assignee_id) SELECT rf_id, original_name, ee_name, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country, assignor_and_assignee_id FROM db_uspto.assignee");