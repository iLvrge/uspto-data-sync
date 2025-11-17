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



$variables = $argv; 
if(count($variables) > 0) {
    try {
    
        $organisationID = $variables[1]; 
        $representatives = json_decode($variables[2]); 
        
        $query = "Select application, patent FROM db_new_application.dashboard_items where organisation_id = ".$organisationID." AND representative_id IN (".implode(',', $representatives).")";
        echo $query;
        
        $result = $con->query($query);
        if($result && $result->num_rows > 0) {
            while($row = $result->fetch_object()) { 
               echo $queryDetails = "Select file_name from db_patent_application_bibliographic.application_grant WHERE appno_doc_num = '".$row->application."'";

                $resultDetails = $con->query($queryDetails);
                if($resultDetails && $resultDetails->num_rows > 0) {
                    $rowDetails = $resultDetails->fetch_object();
                    $fileName = $rowDetails->file_name;
                    $fileName  = str_replace('.XML','', $fileName);
                    if($fileName != '') {  
                        echo 'node /var/www/html/script/update_grant_title.js '.$fileName;
                        exec('node /var/www/html/script/update_grant_title.js '.$fileName);
                        die;
                    } else {
                        $queryDetails = "Select file_name from db_patent_application_bibliographic.application_details WHERE appno_doc_num = '".$row->application."'";
                        $resultDetails = $con->query($queryDetails);
                        if($resultDetails && $resultDetails->num_rows > 0) {
                            $rowDetails = $resultDetails->fetch_object();
                            $fileName = $rowDetails->file_name;
                            $fileName  = str_replace('.XML','', $fileName);
                            if($fileName != '') { 
                                echo 'node /var/www/html/script/update_application_title.js '.$fileName;
                                exec('node /var/www/html/script/update_application_title.js '.$fileName);
                                die;
                            }
                        }
                    }
                } else {
                    $queryDetails = "Select file_name from db_patent_application_bibliographic.application_details WHERE appno_doc_num = '".$row->application."'";
                    $resultDetails = $con->query($queryDetails);
                    if($resultDetails && $resultDetails->num_rows > 0) {
                        $rowDetails = $resultDetails->fetch_object();
                        $fileName = $rowDetails->file_name;
                        $fileName  = str_replace('.XML','', $fileName);
                        if($fileName != '') { 
                            echo 'node /var/www/html/script/update_application_title.js '.$fileName;
                            exec('node /var/www/html/script/update_application_title.js '.$fileName);
                            die;
                        }
                    }
                }
            }
        }

    } catch (Exception $e) {

    }
}