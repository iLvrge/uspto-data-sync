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



$variables = $argv;
if(count($variables) == 1) {
    try {
        $organisationID = $variables[1];

        if((int)$organisationID > 0) {
            $queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
            
            $resultOrganisation = $con->query($queryOrganisation);
            
            if($resultOrganisation && $resultOrganisation->num_rows > 0) {

                $addAddress = array();
                $orgRow = $resultOrganisation->fetch_object();
                
                $orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
                
                if($orgConnect) {
                    /*Check from client database */
                    $queryRepresentative = "SELECT representative_name, representative_id FROM representative WHERE type = 1 AND parent_id = 0";	
                    $resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
                    $allRepresentative = array();
                    if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {					
                        while($getGroup = $resultRepresentativeParentCompany->fetch_object()) {
                            array_push($allRepresentative, $getGroup);
                            $queryGroupRepresentative = "SELECT representative_name, representative_id FROM representative 
                            WHERE type = 0 AND parent_id = ".$getGroup->representative_id;
                            
                            $resultRepresentativeGroupParentCompany = $orgConnect->query($queryGroupRepresentative);
                            if($resultRepresentativeGroupParentCompany && $resultRepresentativeGroupParentCompany->num_rows > 0) {
                                
                                
                                while($getCompanyRow = $resultRepresentativeGroupParentCompany->fetch_object()) {
                                    array_push($allRepresentative, $getCompanyRow);
                                }
                            }
                        }
                    }
                    $queryRepresentative = "SELECT representative_name, representative_id FROM representative WHERE type = 0 AND parent_id = 0";	
					$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
					
						
					if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
						while($getCompanyRow = $resultRepresentativeParentCompany->fetch_object()) {
							array_push($allRepresentative, $getCompanyRow);
						}
					}


                    if(count($allRepresentative) > 0) {
                        foreach($allRepresentative as $representative) {
                            $queryRepresentativeName = "SELECT ee.* FROM representative_address AS ra INNER JOIN representative AS r ON r.representative_id = ra.representative_id INNER JOIN assignee AS ee ON ee.rf_id = ra.rf_id AND ra.assignor_and_assignee_id = ee.assignor_and_assignee_id WHERE r.representative_name = '".$con->real_escape_string($representative->representative_name)."'";

                            $resultR = $orgConnect->query($queryRepresentativeName);

                            if($resultR && $resultR->num_rows > 0) {
                                while($row = $resultR->fetch_object()) {
                                    array($addAddress, array('representative_id'=>$representative->representative_id, 'street_address'=>$row->ee_address_1, 'suite'=>$row->ee_address_2, 'city'=>$row->ee_city, 'state'=> $row->ee_state, 'zip_code'=>$row->ee_postcode, 'country'=>$row->ee_country));
                                }
                            }
                        }
                    }
                }
                if(count($addAddress) > 0) {
                    $string = "";
									
                    foreach($addAddress as $address){
                        $string .= '('.$address['representative_id'].', '.$con->real_escape_string($address['street_address']).', '.$con->real_escape_string($address['suite']).', '.$address['city'].', '.$address['state'].', '.$address['zip_code'].', '.$address['country'].'), '; 
                    }
                    
                    $string = substr($string, 0, -2);
                    
                    $orgConnect->query('INSERT IGNORE INTO `address`(representative_id, street_address, suite, city, state, zip_code, country) VALUES '.$string)	;
                    sendNotifications("Address updated to client account.");
                }
            }
        }
    } catch( Exeception $e) {

    }
	
}

function sendNotifications($data) {	
	$pusher = new Pusher\Pusher(CONSTANT_PUSHER_KEY, CONSTANT_PUSHER_SECRET, CONSTANT_PUSHER_APPID, array( 'cluster' => CONSTANT_PUSHER_CLUSTER, 'useTLS' => CONSTANT_PUSHER_ENCRYPTED ) );
	$pusher->trigger( CONSTANT_PUSHER_CHANNEL, CONSTANT_PUSHER_EVENT, $data );
}