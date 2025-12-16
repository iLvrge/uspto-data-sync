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

/**
 * SET SESSION group_concat_max_len = 1000000;
 */
$variables = $argv;
if(count($variables) > 1) {
    try {
        $organisationID = $variables[1];
        $representativeID = 0;

        if(isset($variables[2])) {
            $representativeID = $variables[2];
        }
        $YEAR = 1997;
        /**
         * Find Release assets with date of execution
         */
        $queryFindRelease = "SELECT doc.rf_id, apt.exec_dt, GROUP_CONCAT(DISTINCT doc.appno_doc_num) AS releasedAssets, GROUP_CONCAT(DISTINCT apt.assignor_and_assignee_id) AS parties, GROUP_CONCAT(DISTINCT r.representative_id) AS representatives FROM ".$dbUSPTO.".documentid AS doc INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = doc.rf_id INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = apt.assignor_and_assignee_id LEFT JOIN ".$dbUSPTO.".representative AS r ON r.representative_id = aaa.representative_id WHERE apt.organisation_id =".$organisationID." AND apt.activity_id IN (11, 13) AND DATE_FORMAT(apt.exec_dt, '%Y') > ".$YEAR;
        
        if($representativeID != '' && $representativeID > 0) {
            $queryFindRelease .= " AND apt.company_id = ".$representativeID;
        }
        
        $queryFindRelease .= "  GROUP BY doc.rf_id ORDER BY apt.exec_dt ASC";

        echo $queryFindRelease;
       

        $resultRelease = $con->query($queryFindRelease);
        $releaseAssetsWithDate = array();
        $releaseAssets = array();

        if($resultRelease && $resultRelease->num_rows > 0) {
            while($row = $resultRelease->fetch_object()){
                array_push($releaseAssetsWithDate, $row);
            }
        }

        if(count($releaseAssetsWithDate) > 0) {

            /**
             * Find securities RFID
             */
            $allSecuredAsetsWithDate = array();
            $allReleaseAssersWithDate = array();
            $querySecurity = "SELECT apt.rf_id, apt.exec_dt, GROUP_CONCAT(DISTINCT doc.appno_doc_num) AS securedAssets, GROUP_CONCAT(DISTINCT apt.assignor_and_assignee_id) AS parties, GROUP_CONCAT(DISTINCT r.representative_id) AS representatives FROM ".$dbApplication.".activity_parties_transactions AS apt INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = apt.assignor_and_assignee_id LEFT JOIN ".$dbUSPTO.".representative AS r ON r.representative_id = aaa.representative_id WHERE apt.organisation_id = ".$organisationID." AND apt.activity_id IN (5, 12) AND DATE_FORMAT(apt.exec_dt, '%Y') > ".$YEAR."  AND apt.release_exec_dt IS NULL ";

            if($representativeID != '' && $representativeID > 0) {
                $querySecurity .= " AND apt.company_id = ".$representativeID;
            }
            
            
            $querySecurity .= " GROUP BY apt.rf_id ORDER BY exec_dt ASC";
            
            $resultSecurity = $con->query($querySecurity);
            $excludeRFIDs = array();
            $updateSecurityRfIDs = array();
            if($resultSecurity && $resultSecurity->num_rows > 0) {
                while($rowSecurity = $resultSecurity->fetch_object()) {
                    $allAssets = $rowSecurity->securedAssets;
                    $allParties = $rowSecurity->parties;
                    $allRepresentatives = $rowSecurity->representatives;

                    if($allAssets != '' ) {
                        $explodeSecuredAssets = explode(',', $allAssets);
                        $explodeSecuredParties = explode(',', $allParties);
                        $explodeSecuredRepresentatives = explode(',', $allRepresentatives);
                        $securedASSETS = array_map(function($piece){
                            return (int) $piece;
                        }, $explodeSecuredAssets);
                        foreach($securedASSETS as $ass) {
                            array_push($allSecuredAsetsWithDate, array('date'=>$rowSecurity->exec_dt, 'asset'=>$ass));
                        }
                        echo 'RFID: '.$rowSecurity->rf_id;
                        if(count($explodeSecuredAssets) > 0) {
                            $releaseSecurities = array();
                            $releaseSecuritiesAssets = array();
                            /**
                             * 0 Search for a matching release
                             * 1 a match was found - flag that matched release and go to 0 to look for additional releases.
                             * 3 no more matches . Are all the secured assets included  in the released assets?
                             * 4 set the border date at the last flagged release.      
                             */
                            foreach($releaseAssetsWithDate As $releaseAsset) {
                                
                                if(strtotime($releaseAsset->exec_dt) > strtotime($rowSecurity->exec_dt)) {
                                    $allReleaseAssets = explode(',', $releaseAsset->releasedAssets);
                                    $releaseASSETS = array_map(function($piece){
                                        return (int) $piece;
                                    }, $allReleaseAssets);

                                    $assetsComparison = array_intersect($securedASSETS, $releaseASSETS);

                                    /* if($rowSecurity->rf_id == '415760001') {
                                        
                                        echo "<pre>";
                            
                                        echo "RELEASE";
                                       
                                        print_r($assetsComparison);
                                        print_r($releaseASSETS);
                                        echo "SECURED";
                                        print_r($securedASSETS);
                                        die;
                                    } */
                                    /**
                                     * Found similar assets
                                     */
                                    if(count($assetsComparison) > 0) {
                                        foreach($assetsComparison as $assetReleased){
                                            array_push($allReleaseAssersWithDate, array('date'=>$releaseAsset->exec_dt, 'asset'=>$assetReleased));
                                        }
                                        $allReleaseParties = explode(',', $releaseAsset->parties);
                                        $allReleaseRepresentatives = explode(',', $releaseAsset->representatives);
                                        $partiesComparison = array_intersect($explodeSecuredParties, $allReleaseParties);
                                        $releaseComparison = array_intersect($explodeSecuredRepresentatives, $allReleaseRepresentatives);

                                        if($rowSecurity->rf_id == '415760001') {
                                            echo count($partiesComparison)."@@".count($releaseComparison)."<br/>";
                                        }

                                        if(count($partiesComparison) > 0 || count($releaseComparison) > 0 ) {
                                            array_push($releaseSecurities, array('exec_dt'=>$releaseAsset->exec_dt, 'rf_id'=>$releaseAsset->rf_id, 'total_assets'=>count($releaseComparison)));
                                            $releaseSecuritiesAssets = array_merge($releaseSecuritiesAssets, $releaseASSETS);
                                        } 
                                    }
                                }
                            }
                           
                            /**
                             * No. of matches
                             */
                            if(count($releaseSecurities) > 0) {
                                $checkHalfMatch = true;
                                $releaseSecuritiesAssets = array_unique($releaseSecuritiesAssets);
                                $assetsComparison = array_intersect($securedASSETS, $releaseSecuritiesAssets);

                                
                                if(count($assetsComparison) == count($securedASSETS)) {
                                    $checkHalfMatch = false;
                                }

                                

                                $releaseLatest = $releaseSecurities[count($releaseSecurities) - 1];

                                array_push($updateSecurityRfIDs, array('rf_id'=>$rowSecurity->rf_id, 'release_rf_id'=>$releaseLatest['rf_id'], 'release_exec_dt'=>$releaseLatest['exec_dt'], 'full_match'=> $checkHalfMatch === false ? 0 : 1, 'total_assets'=>count($assetsComparison), 'all_release_ids'=>json_encode($releaseSecurities)));
                                echo "ADDED<br/>";
                            }

                            
                            
                        }
                    }
                }
                //print_r($updateSecurityRfIDs);
                if(count($updateSecurityRfIDs) > 0) {
                    foreach($updateSecurityRfIDs as $release){
                        $updateQuery = "UPDATE ".$dbApplication.".activity_parties_transactions SET release_rf_id = '" .$release['release_rf_id']. "', release_exec_dt = '" .$release['release_exec_dt']. "', full_match = '" .$release['full_match']. "' , total_assets = '" .$release['total_assets']. "', all_release_ids = '" .$release['all_release_ids']. "' WHERE rf_id =".$release['rf_id'];
                        echo $updateQuery."<br/>";
                        $con->query($updateQuery);
                    }
                }
            }


            /* echo "SECURITY";
            print_r($allSecuredAsetsWithDate);
            echo "RELEASED";
            print_r($allReleaseAssersWithDate);
            if(count($allSecuredAsetsWithDate) > 0 && count($allReleaseAssersWithDate) > 0) {
                $releaseList = array();
                foreach($allSecuredAsetsWithDate as $secureAsset) {
                    $release = false;
                    foreach($allReleaseAssersWithDate as $releaseAsset) {
                        if($release === false) {
                            if(strtotime($releaseAsset['date']) > strtotime($releaseAsset['date']) && $secureAsset['asset'] == $releaseAsset['asset']) {
                                $release = true;
                                array_push($releaseList, $releaseAsset['asset']);
                            }
                        }
                    }
                }
                print_r($releaseList );
            } */
        }
    } catch( Exception $e) {

    }
}