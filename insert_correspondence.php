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

$query = "SELECT rf_id, cname, caddress_1, caddress_2, caddress_7, caddress_5, caddress_6, caddress_3, caddress_4 FROM assignment where rf_id IN (SELECT rf_id from list2 where organisation_id = 178 AND company_id IN (190,182,52)) GROUP BY rf_id";

$result = $con->query($query);
if($result && $result->num_rows > 0) {
    while($row = $result->fetch_object()) {
        echo "UPDATE correspondent SET cname = '".$con->real_escape_string($row->cname)."', caddress_1 = '".$con->real_escape_string($row->caddress_1)."', caddress_2 = '".$con->real_escape_string($row->caddress_2)."', caddress_7 = '".$con->real_escape_string($row->caddress_7)."', caddress_5 = '".$con->real_escape_string($row->caddress_5)."', caddress_6 = '".$con->real_escape_string($row->caddress_6)."', caddress_3 = '".$con->real_escape_string($row->caddress_3)."', caddress_4 = '".$con->real_escape_string($row->caddress_4)."' WHERE rf_id = ".$row->rf_id."<br/>";
        $con->query("UPDATE correspondent SET cname = '".$con->real_escape_string($row->cname)."', caddress_1 = '".$con->real_escape_string($row->caddress_1)."', caddress_2 = '".$con->real_escape_string($row->caddress_2)."', caddress_7 = '".$con->real_escape_string($row->caddress_7)."', caddress_5 = '".$con->real_escape_string($row->caddress_5)."', caddress_6 = '".$con->real_escape_string($row->caddress_6)."', caddress_3 = '".$con->real_escape_string($row->caddress_3)."', caddress_4 = '".$con->real_escape_string($row->caddress_4)."' WHERE rf_id = ".$row->rf_id);
    }
}
/*
$con->query("TRUNCATE `db_uspto`.`correspondent`");
$con->query('INSERT IGNORE INTO correspondent (rf_id, cname, caddress_1, caddress_2, caddress_7, caddress_5, caddress_6, caddress_3, caddress_4) SELECT rf_id, cname, caddress_1, caddress_2, caddress_7, caddress_5, caddress_6, caddress_3, caddress_4 FROM assignment');
*/

//$con->query("ALTER TABLE `db_uspto`.`correspondent` ADD INDEX `cname` (`cname` ASC) VISIBLE");
/*
$con->query("ALTER TABLE `db_uspto`.`correspondent` ADD COLUMN `arrows` INT NULL AFTER `law_firm_id`, ADD INDEX `arrows` (`arrows` ASC) VISIBLE");*/
/*
$query = "SELECT rf_id FROM correspondent";

$result = $con->query($query);

if($result && $result->num_rows > 0) {
    while($row = $result->fetch_object()) {
        $resultAssignee = $con->query("SELECT count(*) as countAssignee FROM assignee Where rf_id = ".$row->rf_id);
        $resultAssignor = $con->query("SELECT count(*) as countAssignor FROM assignor Where rf_id = ".$row->rf_id);

        if($resultAssignee && $resultAssignor) {
            $rowAssignor = $resultAssignor->fetch_object();
            $rowAssignee = $resultAssignee->fetch_object();
            $arrow = $rowAssignee->countAssignee * $rowAssignor->countAssignor; 
            echo "UPDATE correspondent SET arrows = ".$arrow." WHERE rf_id = ".$row->rf_id."<br/>";
            $con->query("UPDATE correspondent SET arrows = ".$arrow." WHERE rf_id = ".$row->rf_id);
        }
    }
}*/

/* 
$query = "INSERT IGNORE INTO assignment_arrows(rf_id, arrows) SELECT rf_id, ao * ae FROM (
	SELECT rf_id, (SELECT count(*) as countAssignor FROM assignor AS aor Where aor.rf_id = ass.rf_id) AS ao, 
    (SELECT count(*) as countAssignee FROM assignee AS aee Where aee.rf_id = ass.rf_id) AS ae 
    FROM assignment AS ass) AS temp";

$con->query($query); */