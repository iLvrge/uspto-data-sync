<?php 
ignore_user_abort(true);
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);


$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbApplication);

function isJson($string) {
    return ((is_string($string) &&
            (is_object(json_decode($string)) ||
            is_array(json_decode($string))))) ? true : false;
}



$variables = $argv;
if(count($variables) > 1) {
	$organisationID = $variables[1];
	$companyID = 0;
    if(isset($variables[2]) && $variables[2] != ''){
        $companyID = $variables[2];
    }
	if((int)$organisationID > 0) {	
       /*  $queryAssets = "SELECT assets.grant_doc_num, COUNT(apf.grant_doc_num) AS counter FROM assets LEFT JOIN db_uspto.assets_family AS apf ON apf.grant_doc_num = assets.grant_doc_num  WHERE  assets.grant_doc_num <> '' AND assets.organisation_id = ".(int)$organisationID;

        if($companyID != '' && $companyID > 0) {
            $queryAssets .= " AND assets.company_id = ".$companyID;
        }
        
        $queryAssets .= " AND date_format(assets.appno_date, '%Y') > 1999  GROUP BY assets.grant_doc_num HAVING counter = 0 "; */
        $allCompanyIDs = array();
        if($companyID == "") {
            $query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID;	
            $result = $con->query($query);
            $accountType = "";
            $companyIDDDD = 0;
            $allCompanyIDs = array();
            $orgConnect = '';
            $orgDB = '';
            if($result && $result->num_rows > 0) {  
                while($row = $result->fetch_object()) {
                    $accountType = $row->organisation_type;
                    $orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
                    $orgDB = $row->org_db;
                    if($orgConnect) {
                        $queryRepresentative = "SELECT company_id AS representative_id  FROM representative WHERE company_id > 0 AND mode = 0 ";
                        $queryRepresentative .= " GROUP BY company_id ORDER BY status DESC";
                        echo $queryRepresentative."<br/>";
                        $resultRepresentative = $orgConnect->query($queryRepresentative);		
                                
                        if($resultRepresentative && $resultRepresentative->num_rows > 0) {
                            $companiesData = array();
                            
                            while($representative = $resultRepresentative->fetch_object()){ 
                                array_push($allCompanyIDs, $representative->representative_id);
                            }
                        }
                    }
                }
            }
        } else { 
            if(isJson($companyID)) {
                $allCompanyIDs = json_decode($companyID);
            } else { 
                array_push($allCompanyIDs, $companyID);
            } 
        }

        if(count($allCompanyIDs) > 0) { 
            $assetsRetreieved = array();
            $totalAssets = 0;
            $queryAssets = "SELECT di.patent AS grant_doc_num, COUNT(apf.grant_doc_num) AS counter FROM db_new_application.dashboard_items AS di LEFT JOIN db_uspto.assets_family AS apf ON apf.grant_doc_num = di.patent  WHERE /* di.type IN (30, 36, 17, 20, 21, 22)  AND */ di.patent <> '' AND (di.organisation_id = 0 OR di.organisation_id IS NULL)  AND di.representative_id IN (".implode(',', $allCompanyIDs).") ";
            $queryAssets .= "  GROUP BY di.patent HAVING counter = 0 ";
            echo $queryAssets; 
            $resultAllAssetsList = $con->query($queryAssets);
            if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                $totalAssets = $resultAllAssetsList->num_rows;
                echo "TOTAL: ".$totalAssets;
                $counter = 0;
                while($rowAsset = $resultAllAssetsList->fetch_object()) {
                    $assetsRetreieved[] = '"'.$rowAsset->grant_doc_num.'"';
                    echo "ASSET: ".$rowAsset->grant_doc_num;
                    
                    echo "FINDDDDDDDD";
                    $output = shell_exec('./node_modules/.bin/env-cmd node /var/www/html/script/assets_family.js "'.$rowAsset->grant_doc_num.'" > /var/www/html/log/assets_family_'.$organisationID.'.log 2>&1 &');

                    print_r($output);

                    echo "---------------IN SLEEPING MODE-------------";

                    sleep(15); // 15 seconds sleep;

                    $counter++;

                    if ($counter % 20 == 0) {
                        // Send your message here
                        sendNotifications("Total $counter / $totalAssets Family retrieved.");
                    }
                }
            }
 
            sendNotifications("Dashboard, KPI assets family retrieved. Now retriving for all transaction assets you can click update. You will see data for all the dashboard assets.");

            $queryAssets = "SELECT di.grant_doc_num AS grant_doc_num, COUNT(apf.grant_doc_num) AS counter FROM db_new_application.assets AS di LEFT JOIN db_uspto.assets_family AS apf ON apf.grant_doc_num = di.grant_doc_num  WHERE  di.grant_doc_num <> '' AND (di.organisation_id = 0 OR di.organisation_id IS NULL)  AND di.company_id IN (".implode(',', $allCompanyIDs).") ";
            $queryAssets .= "  GROUP BY di.grant_doc_num HAVING counter = 0 ";
            echo $queryAssets; 
            $resultAllAssetsList = $con->query($queryAssets);
            if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                echo "TOTAL: ".$resultAllAssetsList->num_rows;
                while($rowAsset = $resultAllAssetsList->fetch_object()) { 
                    $asset = '"'.$rowAsset->grant_doc_num.'"';
                    if(!in_array($asset, $assetsRetreieved)) {
                        echo "ASSET: ".$rowAsset->grant_doc_num;
                    
                        echo "FINDDDDDDDD";
                        $output = shell_exec('./node_modules/.bin/env-cmd node /var/www/html/script/assets_family.js "'.$rowAsset->grant_doc_num.'" > /var/www/html/log/assets_family_'.$organisationID.'.log 2>&1 &');
    
                        print_r($output);
    
                        echo "---------------IN SLEEPING MODE-------------";
    
                        sleep(15); // 15 seconds sleep;
                    }
                    
                }
            }
 
            sendNotifications("All assets family retrieved.");
        }  else {
            sendNotifications("No assets found for family to be retrieved.");
        }
    }
}
function sendNotifications($data) {	
	$pusher = new Pusher\Pusher(CONSTANT_PUSHER_KEY, CONSTANT_PUSHER_SECRET, CONSTANT_PUSHER_APPID, array( 'cluster' => CONSTANT_PUSHER_CLUSTER, 'useTLS' => CONSTANT_PUSHER_ENCRYPTED ) );
	$pusher->trigger( CONSTANT_PUSHER_CHANNEL, CONSTANT_PUSHER_EVENT, $data );
}