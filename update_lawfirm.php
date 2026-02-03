<?php 

require_once('/var/www/html/trash/vendor/autoload.php');
require_once('/var/www/html/trash/noti_config.php');
ignore_user_abort(true);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000); 

require_once __DIR__ . '/connection.php';

$con->query('SET GLOBAL range_optimizer_max_mem_size=0');
$con->query('SET global internal_tmp_mem_storage_engine=Memory;');
$con->query('SET SESSION group_concat_max_len = 1000000;');

$con->query('TRUNCATE temp_lawfirm');
$query = "INSERT INTO temp_lawfirm (name, instances) SELECT caddress_1 AS name, 1 FROM assignment WHERE caddress_1 <> '' AND cname IS NOT NULL ";
$con->query($query);

$query = "INSERT INTO temp_lawfirm (name, instances)  SELECT cname AS name, 1 FROM assignment WHERE cname <> '' AND cname IS NOT NULL";
$con->query($query);


$queryCompanyTemp = "SELECT name, COUNT(name) AS instances FROM temp_lawfirm GROUP BY name";

$resultCompanyTemp = $con->query($queryCompanyTemp);

if( $resultCompanyTemp && $resultCompanyTemp->num_rows > 0) {
	while($rowName = $resultCompanyTemp->fetch_object()) {
		$queryFind = 'SELECT law_firm_id FROM law_firm WHERE name = "'.$con->real_escape_string($rowName->name).'"';
		$query = "";
		$lawFirmID = 0;
        echo $queryFind;
		$resultFindName = $con->query($queryFind);
        if( $resultFindName && $resultFindName->num_rows > 0) {
            echo "FIND";
            $row = $resultFindName->fetch_object();
            $query = "UPDATE law_firm SET instances =  ".$rowName->instances." WHERE law_firm_id = ".$row->law_firm_id;
			$con->query($query);
            $lawFirmID = $row->law_firm_id;
        } else {
            echo "INSERT";
            $query = "INSERT INTO law_firm (name, instances) VALUES ('".$con->real_escape_string($rowName->name)."', '".$rowName->instances."')";
			$con->query($query);
			if($con->insert_id > 0) {
				$lawFirmID = $con->insert_id;
			}
        }
        echo $lawFirmID."<br/>";
		if($lawFirmID > 0) {
			echo $lawFirmID."<br/>";
			$con->query("UPDATE assignment SET law_firm_id = ".$lawFirmID." WHERE caddress_1 = '".$con->real_escape_string($rowName->name)."'");
			
			$con->query("UPDATE assignment SET law_firm_id = ".$lawFirmID." WHERE cname = '".$con->real_escape_string($rowName->name)."' AND law_firm_id IS NULL");
		}
	}
	$con->query("TRUNCATE db_uspto.temp_lawfirm");
} 
