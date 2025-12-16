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
$con->query('SET SESSION group_concat_max_len = 5000000;');

$variables = $argv;
if(count($variables) == 3) {
	$company = $variables[1];
	$organisationID = $variables[2];
	if((int)$organisationID > 0) {	
        $listAllAssets = array();
		$companiesData = array();
        $companyAddress = array();
        $query = "SELECT appno_doc_num FROM ".$dbApplication.".owned_assets WHERE organisation_id = ".(int)$organisationID;	

        if($company > 0) {
            $query .=" AND company_id = ".$company;
        }
        $result = $con->query($query);
        $collateralizeAssets = array();
        if($result && $result->num_rows > 0) {
            
            while($rowAsset = $result->fetch_object()) {

                $querySecurity = "SELECT ass.assignor_and_assignee_id, apt.exec_dt, r.representative_id FROM ".$dbApplication.".activity_parties_transactions AS apt INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = ass.assignor_and_assignee_id LEFT JOIN ".$dbUSPTO.".representative AS r ON r.representative_id = aaa.representative_id WHERE apt.activity_id IN (5, 12) AND doc.appno_doc_num = '".$rowAsset->appno_doc_num."' AND organisation_id = ".(int)$organisationID;

                if($company > 0) {
                    $querySecurity .=" AND company_id = ".$company;
                }

                $queryRelease = "SELECT aor.assignor_and_assignee_id, apt.exec_dt, r.representative_id FROM ".$dbApplication.".activity_parties_transactions AS apt INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignor AS aor ON aor.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id LEFT JOIN ".$dbUSPTO.".representative AS r ON r.representative_id = aaa.representative_id WHERE apt.activity_id IN (11, 13) AND doc.appno_doc_num = '".$rowAsset->appno_doc_num."' AND organisation_id = ".(int)$organisationID;

                if($company > 0) {
                    $queryRelease .=" AND company_id = ".$company;
                }
                
                $resultRelease = $con->query($queryRelease);
                if($resultRelease && $resultRelease->num_rows > 0) {
                    $allReleases = array();
                    while($rowRelease = $resultRelease->fetch_object()) {
                        array_push($allReleases, $rowRelease);
                    }

                    $resultSecurity = $con->query($querySecurity);

                    if($resultSecurity && $resultSecurity->num_rows > 0) {
                        $findID = false;
                        while($rowSecurity = $resultSecurity->fetch_object()) {
                            if($findID === false) {
                                foreach($allReleases as $release) {
                                    if(strtotime($release->exec_dt) > strtotime($rowSecurity->exec_dt)){
                                        if(($rowSecurity->assignor_and_assignee_id == $release->assignor_and_assignee_id) || ($rowSecurity->representative_id == $release->representative_id)) {
                                            $findID = true;
                                            break;
                                        }
                                    }
                                }
                            } else {
                                break;
                            }
                        }
                        if($findID === false) {
                            array_push($collateralizeAssets, '"'.$rowAsset->appno_doc_num.'"');
                        }
                    }
                } else {
                    array_push($collateralizeAssets, '"'.$rowAsset->appno_doc_num.'"');
                }
            }

            $type = 34;
            /**
             * Collateralized
             * Show all the assets which are not released yet
             */
            $deleteQuery = "DELETE FROM ".$dbApplication.".dashboard_items WHERE type IN (".$type.") AND representative_id = ".$company." AND organisation_id = ".$organisationID;

            $con->query($deleteQuery);
            if(count($collateralizeAssets) > 0) {
                
                /* $year = 1997;
                $securityActivityID = implode(',', array(5, 12));
                $queryCollateralized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$company.", 0, ".$type.", d.MAX(grant_doc_num), d.appno_doc_num, 0, 0 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE date_format(d.appno_date, '%Y') > ".$year." AND apt.activity_id IN (".$securityActivityID.") AND apt.release_rf_id IS NULL AND apt.organisation_id = ".$organisationID." AND company_id = ".$company." AND d.appno_doc_num IN (".implode(',', $collateralizeAssets).") GROUP BY d.appno_doc_num";
                echo $queryCollateralized;
                $con->query($queryCollateralized); */
            }
        }
    }
}
