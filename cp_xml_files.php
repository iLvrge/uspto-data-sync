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
$con = new mysqli($host, $user, $password, 'db_patent_application_bibliographic');


$query = "SELECT file_name FROM applicant WHERE name = ''";
$result = $con->query($query);
if($result && $result->num_rows > 0) {
    while($row = $result->fetch_object()){
        exec('cp /mnt/volume_sfo2_12/patent/XML/'.$row->file_name.' /mnt/volume_sfo2_12/patent/XML3/');
    }
}