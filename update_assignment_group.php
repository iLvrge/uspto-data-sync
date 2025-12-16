<?php 

require_once('/var/www/html/trash/vendor/autoload.php');
require_once('/var/www/html/trash/noti_config.php');
ignore_user_abort(true);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000); 
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$con->query('SET GLOBAL range_optimizer_max_mem_size=0');
$con->query('SET global internal_tmp_mem_storage_engine=Memory;');

//$con->query("TRUNCATE `db_uspto`.`assignment_group`");
$conveyanceTypes = array("assignment"/*, "correct", "employee", "govern", "license", "merger", "missing", "namechg", "other", "release", "security"*/);
foreach($conveyanceTypes as $types) {
    $con->query("INSERT IGNORE INTO assignment_group (id, text, reel_frame, counter, convey_ty, updated_convey_ty)
    SELECT rf_id AS id, convey_text AS text, reel_frame, COUNT(convey_text) AS counter, convey_ty, updated_convey_ty FROM (
    SELECT ass.rf_id, ass.convey_text, '' AS reel_frame, ac.convey_ty, rac.convey_ty AS updated_convey_ty FROM db_uspto.assignment_conveyance AS ac 
    INNER JOIN db_uspto.assignment AS ass ON ass.rf_id = ac.rf_id
    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = ac.rf_id
    WHERE ac.convey_ty = '".$types ."' AND date_format(ass.record_dt, 'Y') >= 2000
    ) AS temp
    GROUP BY convey_text");
}

