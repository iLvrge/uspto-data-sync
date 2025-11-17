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
$con = new mysqli($host, $user, $password, $dbUSPTO);

$variables = $argv;
if(count($variables) == 3) {
    echo "INNNNNNN";
	$companyID = $variables[1];
	$organisationID = $variables[2];
	if((int)$organisationID > 0) {
        echo "ENTER";	
        $listAllAssets = array();
		$queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbApplication.".assets_with_bank WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." GROUP BY appno_doc_num";
		
		$resultAllAssetsList = $con->query($queryAllAssetsList);
		if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
			while($rowAsset = $resultAllAssetsList->fetch_object()) {
				array_push($listAllAssets, '"'.$rowAsset->appno_doc_num.'"');
			}
		} 
        $year = 1997;
		if(count($listAllAssets) > 0) {
            $implodeAssetsList = implode(',', $listAllAssets);
            $currentDate = new DateTime();

            /**
             * Expired Assets
             */
            $con->query("DELETE FROM ".$dbApplication.".assets_with_bank_expired_status WHERE representative_id = ".$companyID." AND organisation_id = ".$organisationID);  
            $queryExpiredAssets = "SELECT application FROM ( SELECT MAX(doc.appno_doc_num) AS application, doc.grant_doc_num AS patent, doc.appno_date, release_rf_id,  timestampdiff(YEAR, doc.appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer,  IF (temp_expired.status <> '', 1, 0) AS expiredStatus  FROM db_new_application.activity_parties_transactions AS apt INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = apt.rf_id  LEFT JOIN ( SELECT appno_doc_num, status FROM db_uspto.application_status  WHERE (`status` LIKE '%abandoned%' OR `status` LIKE '%expired%' OR `status` LIKE '%final rejection%')  AND appno_doc_num IN (".$implodeAssetsList.") ) AS temp_expired ON temp_expired.appno_doc_num = doc.appno_doc_num  WHERE doc.appno_doc_num IN (".$implodeAssetsList.") AND apt.activity_id IN (5, 12) AND (release_rf_id IS NULL OR release_rf_id  = 0) AND apt.organisation_id = ".$organisationID."  AND company_id = ".$companyID."  GROUP BY apt.assignor_and_assignee_id, doc.appno_doc_num) AS temp WHERE yearDiffer > 19 OR expiredStatus = 1";
            echo $queryExpiredAssets ;
            $resultAllExpiredAssetsList = $con->query($queryExpiredAssets);
            $expiredAssets = array();
            if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                    array_push($expiredAssets, '"'.$rowAsset->application.'"');
                }
            }

            if(count($expiredAssets) > 0) {
                $queryExpiredWithDate = "INSERT INTO ".$dbApplication.".assets_with_bank_expired_status(appno_doc_num, status_date, expire_date, company_id, organisation_id) SELECT appno_doc_num, MAX(status_date) AS sDate, MAX(expiry_date) AS exDate, ".$companyID.", ".$organisationID." FROM ( SELECT appno_doc_num, MAX(status_date) AS status_date, '' AS expiry_date FROM db_uspto.application_status WHERE appno_doc_num IN (".implode(',', $expiredAssets).") AND (`status` LIKE '%abandoned%' OR `status` LIKE '%expired%' OR `status` LIKE '%final rejection%')  GROUP BY appno_doc_num UNION ALL SELECT appno_doc_num, '' AS status_date, DATE_ADD(appno_date, INTERVAL 20 YEAR) AS expiry_date FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $expiredAssets).") GROUP BY appno_doc_num ) AS temp GROUP BY appno_doc_num";
                echo $queryExpiredWithDate;
                $con->query($queryExpiredWithDate);
            } 




            $allTypes = [1,17,18,19,20,21,22,23,24,25,26,27];
            $deleteQuery = "DELETE FROM ".$dbApplication.".dashboard_items WHERE type IN (".implode(',', $allTypes).") AND representative_id = ".$companyID." AND organisation_id = ".$organisationID;

            $con->query($deleteQuery);

            echo "ENTER2";
            
             /**
             * Client Transactions
             */
            $type = 21;
            
            $queryClientTransactions = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, apt.assignor_and_assignee_id, ".$type.", '' AS patent, '' AS application, apt.rf_id, COUNT(DISTINCT doc.appno_doc_num) AS total FROM db_new_application.activity_parties_transactions AS apt INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = apt.rf_id WHERE apt.activity_id IN (5, 12, 11, 13) AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID." AND date_format(apt.exec_dt, '%Y') >= ".$year." GROUP BY apt.assignor_and_assignee_id, apt.rf_id";
            $con->query($queryClientTransactions);

            
            /**
             * Collteralized Assets
             */
            $type = 17; 
            $queryCollateralizedAsset = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, apt.assignor_and_assignee_id, ".$type.", MAX(doc.grant_doc_num), MAX(doc.appno_doc_num), 0 AS rf_id, 0 AS total FROM ".$dbApplication.".activity_parties_transactions AS apt INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id
            WHERE apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID." AND apt.activity_id IN (5, 12) AND doc.appno_doc_num IN (".$implodeAssetsList.") AND (apt.release_exec_dt IS NULL OR apt.full_match = 1)  GROUP BY doc.appno_doc_num, apt.company_id, apt.assignor_and_assignee_id";

            $con->query($queryCollateralizedAsset);
             
            /**
             * Non-Expired Collaterals
             */
            $type = 18; 
            if(count($expiredAssets) > 0) {
                $queryNonExpiredCollateralizedAsset = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT organisation_id, representative_id, assignor_id, ".$type.", patent, application, rf_id, total FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND application NOT IN (".implode(',', $expiredAssets).") AND type = 17";
            } else {
                $queryNonExpiredCollateralizedAsset = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT organisation_id, representative_id, assignor_id, ".$type.", patent, application, rf_id, total FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = 17";
            }
            //echo $queryNonExpiredCollateralizedAsset;
            $con->query($queryNonExpiredCollateralizedAsset);

            /**
             * Invalid Collaterals
             */
            $type = 20; 

            $queryCollaterals = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, tawb.assignor_id AS assignor_id, ".$type.",  MAX(doc.grant_doc_num)  AS patent, MAX(doc.appno_doc_num) AS application, '' AS rf_id, ".count($listAllAssets)." FROM  ".$dbApplication.".assets_with_bank AS tawb INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = tawb.rf_id INNER JOIN  ".$dbApplication.".assets_with_bank_expired_status AS tawbes ON doc.appno_doc_num = tawbes.appno_doc_num 
            WHERE doc.appno_doc_num IN (".$implodeAssetsList.") AND tawb.company_id = ".$companyID." AND tawb.organisation_id = ".$organisationID." AND date_format(doc.appno_date, '%Y') >= ".$year." AND tawbes.company_id = ".$companyID." AND tawbes.organisation_id = ".$organisationID."  AND ((tawbes.status_date <> '0000-00-00' AND tawbes.status_date < tawb.exec_dt) OR (tawbes.expire_date < tawb.exec_dt)) GROUP BY tawb.assignor_id, tawbes.appno_doc_num ";
            //echo $queryCollaterals;
            $con->query($queryCollaterals); 
            /**
             * Broken Chain of Title
             */
            $type = 1; 
            $queryBrokenChain = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.", grant_doc_num, appno_doc_num, 0 AS rf_id, ".count($listAllAssets)." FROM ".$dbApplication.".assets_bank_broken 
            WHERE organisation_id = ".$organisationID." AND company_id = ".$companyID."  GROUP BY appno_doc_num, company_id, assignor_id";

            $con->query($queryBrokenChain);
 

            /**
             * Expired Collaterals
             */
            
            $type = 22; 
            $queryExpiredCollaterals = "SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, tawb.assignor_id AS assignor_id, ".$type.",  MAX(d.grant_doc_num)  AS patent, MAX(d.appno_doc_num) AS application, '' AS rf_id, ".count($listAllAssets)." FROM db_new_application.assets_with_bank AS tawb INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = tawb.rf_id INNER JOIN db_uspto.assets_with_bank_expired_status AS tawbes ON doc.appno_doc_num = tawbes.appno_doc_num 
            WHERE doc.appno_doc_num IN (".$implodeAssetsList.") AND tawb.company_id = ".$companyID." AND tawb.organisation_id = ".$organisationID." AND date_format(doc.appno_date, '%Y') >= ".$year." AND tawbes.company_id = ".$companyID." AND tawbes.organisation_id = ".$organisationID."  AND ((tawbes.status_date <> '0000-00-00' AND tawbes.status_date > tawb.exec_dt) OR (tawbes.expire_date > tawb.exec_dt AND tawbes.expire_date < '".$currentDate->format('Y-m-d')."')) GROUP BY tawb.assignor_id AS assignor_id, tawbes.appno_doc_num ";
            
            //echo $queryExpiredCollaterals;
            
            $con->query($queryExpiredCollaterals);  
 
            /**
             * Encumbtrances
             */

            $type = 24; 

            $queryEncumbrances = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, max_date.assignor_id AS assignor_id, ".$type.", d.grant_doc_num AS patent, d.appno_doc_num AS application, rac.rf_id As rf_id, ".count($listAllAssets)."  FROM db_uspto.documentid AS d 
            INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = d.rf_id AND rac.convey_ty NOT IN ('namechg')
            INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = rac.rf_id 
            INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = rac.rf_id
            INNER JOIN LATERAL (
                SELECT appno_doc_num, assignor_id, exec_dt, rf_id FROM db_new_application.assets_with_bank
                WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID."   
                GROUP BY assignor_id, rf_id
            ) AS max_date ON max_date.appno_doc_num = d.appno_doc_num AND aor.exec_dt > max_date.exec_dt AND max_date.rf_id <> rac.rf_id AND aor.assignor_and_assignee_id = max_date.assignor_id
            GROUP BY application, assignor_id";
            //echo $queryEncumbrances;
            $con->query($queryEncumbrances);
             
            /**
            * Late Recordings
            */  
            $days = 90;
            $type = 25; 
            $queryLateRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.", '' AS patent, '' AS application, rf_id, (SELECT COUNT(transactions) FROM ( 
                SELECT  rf_id AS transactions FROM db_uspto.documentid
                   WHERE appno_doc_num IN (".implode(',', $listAllAssets).")
                   GROUP BY rf_id                                
               ) as temp1) AS total FROM (SELECT temp_exec_dt.rf_id AS rf_id, tawb.assignor_id AS assignor_id, DATEDIFF(ass.record_dt, temp_exec_dt.exec_dt) AS noOfDays   
                                    FROM db_new_application.assets_with_bank as tawb
                                    INNER JOIN (
                                        SELECT appno_doc_num, rf_id FROM db_uspto.documentid
                                        WHERE appno_doc_num IN (".implode(',', $listAllAssets).") 
                                        GROUP BY appno_doc_num, rf_id
                                    ) AS doc ON doc.appno_doc_num = tawb.appno_doc_num
                                    INNER JOIN db_uspto.assignment AS ass ON ass.rf_id = doc.rf_id
                                    INNER JOIN LATERAL (
                                        SELECT aor.rf_id, aor.exec_dt FROM db_uspto.assignor AS aor
                                        INNER JOIN (
                                            SELECT appno_doc_num, rf_id FROM db_uspto.documentid
                                            WHERE appno_doc_num IN (".implode(',', $listAllAssets).") 
                                            GROUP BY appno_doc_num, rf_id
                                        ) AS doc1 ON doc1.rf_id = aor.rf_id
                                        INNER JOIN db_new_application.assets_with_bank AS tawb1 ON tawb1.appno_doc_num = doc1.appno_doc_num
                                        WHERE company_id = ".$companyID."
                                        AND organisation_id = ".$organisationID. " 
                                        GROUP BY aor.rf_id
                                    ) AS temp_exec_dt ON  temp_exec_dt.rf_id = ass.rf_id
                                    WHERE company_id = ".$companyID."
                                    AND organisation_id = ".$organisationID. "
                                    GROUP BY rf_id, assignor_id
                                    HAVING noOfDays > ".$days.") AS temp ";  
            $con->query($queryLateRecording);   


            /**
             * Find Owned Assets of Borrower
             */
            $getListOfBorrowers = "SELECT aaa.assignor_and_assignee_id, aaa.representative_id 
            FROM ".$dbApplication.".activity_parties_transactions AS apt
            INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = apt.assignor_and_assignee_id 
            WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND activity_id IN (5, 12)
            GROUP BY aaa.assignor_and_assignee_id"; 
            
            
            $resultAllAssetsList = $con->query($getListOfBorrowers);
            $borrowers = array();
            if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                
                while($row = $resultAllAssetsList->fetch_object()) {
                    echo "SS:".$row->assignor_and_assignee_id."<br/>";
                    $allAssignorAndAssigneeIDs = array($row->assignor_and_assignee_id);
                    if($row->representative_id > 0) {
                        $queryOtherIDs = "SELECT assignor_and_assignee_id FROM ".$dbUSPTO.".assignor_and_assignee AS aaa WHERE representative_id = ".$row->representative_id." GROUP BY assignor_and_assignee_id";

                        $resultOtherIDs = $con->query($queryOtherIDs);
                        if($resultOtherIDs && $resultOtherIDs->num_rows > 0) {
                            while($rowOther = $resultOtherIDs->fetch_object()) {
                                array_push($allAssignorAndAssigneeIDs, $rowOther->assignor_and_assignee_id);
                            }
                        }
                        $allAssignorAndAssigneeIDs = array_unique($allAssignorAndAssigneeIDs);
                        array_values($allAssignorAndAssigneeIDs);
                    }
                    echo "ALL ASSIGNORS IDs: ".count($allAssignorAndAssigneeIDs)."<br/>";
                    print_r($allAssignorAndAssigneeIDs); 
                    if(count($allAssignorAndAssigneeIDs) > 0) {
                        /**
                         * All RFIDs
                         */
                        //$con->query('CALL db_uspto.routine_borrower_list2("'.implode(',', $allAssignorAndAssigneeIDs).'", '.$companyID.', '.$organisationID.');');

                        $con->query('DELETE FROM db_uspto.borrower_list2 WHERE assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).') AND company_id = '.$companyID.' AND organisation_id = '.$organisationID) ; /*Delete all companies*/
    
                        $con->query('INSERT IGNORE INTO db_uspto.borrower_list2
                            SELECT rf_id, assignor_and_assignee_id, '.$companyID.', '.$organisationID.' FROM assignee 
                            WHERE assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                            GROUP BY rf_id
                            UNION ALL
                            
                            SELECT rf_id, assignor_and_assignee_id, '.$companyID.', '.$organisationID.' FROM assignor 
                            WHERE assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                            GROUP BY rf_id');
                        
                        
                        /**
                         * OTA Assets
                         */
                        // $con->query('CALL db_uspto.routine_borrower_tableB("'.implode(',', $allAssignorAndAssigneeIDs).'", '.$companyID.', '.$organisationID.');');

                        $con->query('DELETE FROM db_uspto.table_borrower_b WHERE assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).') AND company_id = '.$companyID.' AND organisation_id = '.$organisationID) ; 
                        
                        $con->query('INSERT IGNORE INTO db_uspto.table_borrower_b (appno_doc_num, assignor_and_assignee_id, company_id, organisation_id)
                        SELECT documentid.appno_doc_num, assignee.assignor_and_assignee_id, '.$companyID.', '.$organisationID.' FROM assignee 
                        INNER JOIN borrower_list2 ON borrower_list2.rf_id = assignee.rf_id AND borrower_list2.organisation_id = '.$organisationID.' AND borrower_list2.company_id = '.$companyID.' AND borrower_list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                        INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = assignee.rf_id 
                        INNER JOIN conveyance ON conveyance.convey_name = representative_assignment_conveyance.convey_ty AND conveyance.is_ota = 1
                        INNER JOIN documentid ON assignee.rf_id = documentid.rf_id AND documentid.appno_doc_num <> 0  AND documentid.appno_doc_num <> ""   
                        GROUP BY documentid.appno_doc_num');
                        
                        /**
                         * All Activities
                         */
                        //$con->query('CALL db_uspto.routine_borrowersactivities_parties_transactions("'.implode(',', $allAssignorAndAssigneeIDs).'", '.$companyID.', '.$organisationID.');');
                        $con->query('DELETE FROM db_new_application.borrowers_activity_parties_transactions WHERE assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).') AND company_id = '.$companyID.' AND organisation_id = '.$organisationID) ;
                        

                        $con->query('INSERT IGNORE INTO db_new_application.borrowers_activity_parties_transactions (company_id,organisation_id, rf_id, exec_dt, assignor_and_assignee_id, recorded_assignor_and_assignee_id, activity_id)      
                        /*Acquisition 8.1*/
                        SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 1 as activity_id FROM assignor 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id 
                        INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                        INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                        INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id 
                                        WHERE representative_assignment_conveyance.convey_ty IN ("assignment", "partialassignment", "namechg") 
                                                AND list2.company_id = '.$companyID.'
                                                AND list2.organisation_id =  '.$organisationID.' 
                                                AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                AND representative_assignment_conveyance.employer_assign = 0 
                                        GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                        GROUP BY assignor.rf_id,assignor.assignor_and_assignee_id
                        UNION ALL
                        /*Sales 8.2*/
                        SELECT '.$companyID.', '.$organisationID.', assignee.rf_id, (SELECT exec_dt FROM assignor WHERE assignor.rf_id = assignee.rf_id LIMIT 1) as exec_dt, assignee.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 2 as activity_id FROM assignee 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id  
                        INNER JOIN (
                                SELECT assignor.rf_id, list2.assignor_and_assignee_id FROM assignor 
                                INNER JOIN representative_assignment_conveyance ON assignor.rf_id = representative_assignment_conveyance.rf_id 
                                INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignor.rf_id
                                WHERE representative_assignment_conveyance.convey_ty IN ("assignment", "partialassignment", "namechg") 
                                        AND list2.company_id = '.$companyID.'
                                        AND list2.organisation_id =  '.$organisationID.' 
                                        AND assignor.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                        AND representative_assignment_conveyance.employer_assign = 0  
                                GROUP BY assignor.rf_id
                                ) as temp ON temp.rf_id = assignee.rf_id 
                        GROUP BY assignee.rf_id, assignee.assignor_and_assignee_id
                        UNION ALL
                        /*LicenseIn 8.3*/
                        SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 3 as activity_id  FROM assignor 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id 
                        INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                        INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                        INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id
                                        WHERE representative_assignment_conveyance.convey_ty IN ("license", "licenseend", "govern")
                                                AND list2.company_id = '.$companyID.'
                                                AND list2.organisation_id =  '.$organisationID.' 
                                                AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                AND representative_assignment_conveyance.employer_assign = 0 
                                        GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                        GROUP BY assignor.rf_id,assignor.assignor_and_assignee_id
                        UNION ALL
                        /*LicenseOut 8.4*/
                        SELECT '.$companyID.', '.$organisationID.', assignee.rf_id,(SELECT exec_dt FROM assignor WHERE assignor.rf_id = assignee.rf_id LIMIT 1) as exec_dt, assignee.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 4 as activity_id  FROM assignee 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id  
                        INNER JOIN (
                                SELECT assignor.rf_id, list2.assignor_and_assignee_id FROM assignor 
                                INNER JOIN representative_assignment_conveyance ON assignor.rf_id = representative_assignment_conveyance.rf_id 
                                INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignor.rf_id
                                WHERE representative_assignment_conveyance.convey_ty IN ("license", "licenseend", "govern")
                                        AND list2.company_id = '.$companyID.'
                                        AND list2.organisation_id =  '.$organisationID.' 
                                        AND assignor.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                        AND representative_assignment_conveyance.employer_assign = 0  
                                GROUP BY assignor.rf_id
                                ) as temp ON temp.rf_id = assignee.rf_id 
                        GROUP BY assignee.rf_id, assignee.assignor_and_assignee_id
                        UNION ALL
                        /*Lending 8.5*/
                        SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 5 as activity_id  FROM assignor 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id  
                        INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                        INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                        INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id
                                        WHERE representative_assignment_conveyance.convey_ty IN ("security", "restatedsecurity")
                                                AND list2.company_id = '.$companyID.'
                                                AND list2.organisation_id =  '.$organisationID.' 
                                                AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                AND representative_assignment_conveyance.employer_assign = 0 
                                        GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                        GROUP BY assignor.rf_id, assignor.assignor_and_assignee_id
                        UNION ALL
                        /*Borrowing 8.7*/
                        SELECT '.$companyID.', '.$organisationID.', assignee.rf_id, (SELECT exec_dt FROM assignor WHERE assignor.rf_id = assignee.rf_id LIMIT 1) as exec_dt, assignee.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 12 as activity_id  FROM assignee 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id 
                        INNER JOIN (SELECT assignor.rf_id, list2.assignor_and_assignee_id FROM assignor 
                                INNER JOIN representative_assignment_conveyance ON assignor.rf_id = representative_assignment_conveyance.rf_id 
                                INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignor.rf_id
                                WHERE representative_assignment_conveyance.convey_ty IN ("security", "restatedsecurity")
                                        AND list2.company_id = '.$companyID.'
                                        AND list2.organisation_id =  '.$organisationID.' 
                                        AND assignor.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                        AND representative_assignment_conveyance.employer_assign = 0  
                                GROUP BY assignor.rf_id
                                ) as temp ON temp.rf_id = assignee.rf_id 
                        GROUP BY assignee.rf_id,assignee.assignor_and_assignee_id
                        UNION ALL
                        /*ReleaseIn 8.8*/
                        SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 13 as activity_id  FROM assignor 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id  
                        INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                        INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                        INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id
                                        WHERE representative_assignment_conveyance.convey_ty IN ("release")
                                                AND list2.company_id = '.$companyID.'
                                                AND list2.organisation_id =  '.$organisationID.' 
                                                AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                AND representative_assignment_conveyance.employer_assign = 0 
                                        GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                        GROUP BY assignor.rf_id, assignor.assignor_and_assignee_id
                        UNION ALL
                        /*ReleaseOut 8.6*/
                        SELECT '.$companyID.', '.$organisationID.', assignee.rf_id, (SELECT exec_dt FROM assignor WHERE assignor.rf_id = assignee.rf_id LIMIT 1) as exec_dt, assignee.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 11 as activity_id  FROM assignee 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id 
                        INNER JOIN (SELECT assignor.rf_id, list2.assignor_and_assignee_id FROM assignor 
                                INNER JOIN representative_assignment_conveyance ON assignor.rf_id = representative_assignment_conveyance.rf_id 
                                INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignor.rf_id
                                WHERE representative_assignment_conveyance.convey_ty IN ("release")
                                        AND list2.company_id = '.$companyID.'
                                        AND list2.organisation_id =  '.$organisationID.' 
                                        AND assignor.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                        AND representative_assignment_conveyance.employer_assign = 0  
                                GROUP BY assignor.rf_id
                                ) as temp ON temp.rf_id = assignee.rf_id 
                        GROUP BY assignee.rf_id,assignee.assignor_and_assignee_id
                        UNION ALL
                        /*MergersIn 8.9*/
                        SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 6 as activity_id  FROM assignor 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id  
                        INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                        INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                        INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id
                                        WHERE representative_assignment_conveyance.convey_ty IN ("merger")
                                                AND list2.company_id = '.$companyID.'
                                                AND list2.organisation_id =  '.$organisationID.' 
                                                AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                AND representative_assignment_conveyance.employer_assign = 0 
                                        GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                        GROUP BY assignor.rf_id, assignor.assignor_and_assignee_id
                        UNION ALL
                        /*MergersOut 8.10*/
                        SELECT '.$companyID.', '.$organisationID.', assignee.rf_id,(SELECT exec_dt FROM assignor WHERE assignor.rf_id = assignee.rf_id LIMIT 1) as exec_dt, assignee.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 7 as activity_id  FROM assignee 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id  
                        INNER JOIN (
                                SELECT assignor.rf_id, list2.assignor_and_assignee_id FROM assignor 
                                INNER JOIN representative_assignment_conveyance ON assignor.rf_id = representative_assignment_conveyance.rf_id 
                                INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignor.rf_id
                                WHERE representative_assignment_conveyance.convey_ty IN ("merger")
                                        AND list2.company_id = '.$companyID.'
                                        AND list2.organisation_id =  '.$organisationID.' 
                                        AND assignor.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).') 
                                        AND representative_assignment_conveyance.employer_assign = 0  
                                GROUP BY assignor.rf_id
                                ) as temp ON temp.rf_id = assignee.rf_id 
                        GROUP BY assignee.rf_id, assignee.assignor_and_assignee_id
                        UNION ALL
                        /*Options 8.11*/
                        SELECT '.$companyID.', '.$organisationID.', assignee.rf_id,(SELECT exec_dt FROM assignor WHERE assignor.rf_id = assignee.rf_id LIMIT 1) as exec_dt, assignee.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 8 as activity_id  FROM assignee 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id  
                        INNER JOIN (
                                SELECT assignor.rf_id, list2.assignor_and_assignee_id FROM assignor 
                                INNER JOIN representative_assignment_conveyance ON assignor.rf_id = representative_assignment_conveyance.rf_id 
                                INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignor.rf_id
                                WHERE representative_assignment_conveyance.convey_ty IN ("option")
                                        AND list2.company_id = '.$companyID.'
                                        AND list2.organisation_id =  '.$organisationID.' 
                                        AND assignor.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                        AND representative_assignment_conveyance.employer_assign = 0  
                                GROUP BY assignor.rf_id
                                ) as temp ON temp.rf_id = assignee.rf_id 
                        GROUP BY assignee.rf_id, assignee.assignor_and_assignee_id
                        UNION ALL
                        SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 8 as activity_id  FROM assignor 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id 
                        INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                        INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                        INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id
                                        WHERE representative_assignment_conveyance.convey_ty IN ("option")
                                                AND list2.company_id = '.$companyID.'
                                                AND list2.organisation_id =  '.$organisationID.' 
                                                AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                AND representative_assignment_conveyance.employer_assign = 0 
                                        GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                        GROUP BY assignor.rf_id,assignor.assignor_and_assignee_id
                        UNION ALL
                        /*CourtOrders 8.12*/
                        SELECT '.$companyID.', '.$organisationID.', assignee.rf_id,(SELECT exec_dt FROM assignor WHERE assignor.rf_id = assignee.rf_id LIMIT 1) as exec_dt, assignee.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 9 as activity_id  FROM assignee 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id  
                        INNER JOIN (
                                SELECT assignor.rf_id, list2.assignor_and_assignee_id FROM assignor 
                                INNER JOIN representative_assignment_conveyance ON assignor.rf_id = representative_assignment_conveyance.rf_id 
                                INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignor.rf_id
                                WHERE representative_assignment_conveyance.convey_ty IN ("courtorder", "courtappointment")
                                        AND list2.company_id = '.$companyID.'
                                        AND list2.organisation_id =  '.$organisationID.' 
                                        AND assignor.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                        AND representative_assignment_conveyance.employer_assign = 0  
                                GROUP BY assignor.rf_id
                                ) as temp ON temp.rf_id = assignee.rf_id 
                        GROUP BY assignee.rf_id, assignee.assignor_and_assignee_id
                        UNION ALL
                        SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 9 as activity_id  FROM assignor 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id 
                        INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                        INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                        INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id
                                        WHERE representative_assignment_conveyance.convey_ty IN ("courtorder", "courtappointment")
                                                AND list2.company_id = '.$companyID.'
                                                AND list2.organisation_id =  '.$organisationID.' 
                                                AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                AND representative_assignment_conveyance.employer_assign = 0 
                                        GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                        GROUP BY assignor.rf_id,assignor.assignor_and_assignee_id
                        UNION ALL
                        /*Employees 8.13*/
                            SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 10 as activity_id  FROM assignor 
                            LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id 
                            INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                            INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                            INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id 
                                            WHERE (representative_assignment_conveyance.convey_ty = "employee" OR   representative_assignment_conveyance.convey_ty IN ("assignment", "partialassignment", "employee") AND representative_assignment_conveyance.employer_assign = 1 )
                                                    AND list2.company_id = '.$companyID.'
                                                    AND list2.organisation_id =  '.$organisationID.' 
                                                    AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                    
                                            GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                            GROUP BY assignor.rf_id,assignor.assignor_and_assignee_id
                        UNION ALL
                        /*Other 8.15*/
                        SELECT '.$companyID.', '.$organisationID.', assignee.rf_id,(SELECT exec_dt FROM assignor WHERE assignor.rf_id = assignee.rf_id LIMIT 1) as exec_dt, assignee.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 15 as activity_id  FROM assignee 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id  
                        INNER JOIN (
                                SELECT assignor.rf_id, list2.assignor_and_assignee_id FROM assignor 
                                INNER JOIN representative_assignment_conveyance ON assignor.rf_id = representative_assignment_conveyance.rf_id 
                                INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignor.rf_id
                                WHERE representative_assignment_conveyance.convey_ty IN ("correct")
                                        AND list2.company_id = '.$companyID.'
                                        AND list2.organisation_id =  '.$organisationID.' 
                                        AND assignor.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                        AND representative_assignment_conveyance.employer_assign = 0  
                                GROUP BY assignor.rf_id
                                ) as temp ON temp.rf_id = assignee.rf_id 
                        GROUP BY assignee.rf_id, assignee.assignor_and_assignee_id
                        UNION ALL
                        SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 15 as activity_id  FROM assignor 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id 
                        INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                        INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                        INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id
                                        WHERE representative_assignment_conveyance.convey_ty IN ("correct")
                                                AND list2.company_id = '.$companyID.'
                                                AND list2.organisation_id =  '.$organisationID.' 
                                                AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                AND representative_assignment_conveyance.employer_assign = 0 
                                        GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                        GROUP BY assignor.rf_id,assignor.assignor_and_assignee_id   
                        UNION ALL
                        /*Other 8.14*/
                        SELECT '.$companyID.', '.$organisationID.', assignee.rf_id,(SELECT exec_dt FROM assignor WHERE assignor.rf_id = assignee.rf_id LIMIT 1) as exec_dt, assignee.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 14 as activity_id  FROM assignee 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id  
                        INNER JOIN (
                                SELECT assignor.rf_id, list2.assignor_and_assignee_id FROM assignor 
                                INNER JOIN representative_assignment_conveyance ON assignor.rf_id = representative_assignment_conveyance.rf_id 
                                INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignor.rf_id
                                WHERE  (representative_assignment_conveyance.convey_ty NOT IN ("courtorder", "courtappointment", "assignment", "partialassignment", "employee", "option", "merger", "release", "security", "restatedsecurity", "license", "licenseend", "govern", "assignment", "partialassignment", "namechg", "correct") OR representative_assignment_conveyance.convey_ty IN ("other"))
                                        AND list2.company_id = '.$companyID.'
                                        AND list2.organisation_id =  '.$organisationID.' 
                                        AND assignor.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                        AND representative_assignment_conveyance.employer_assign = 0  
                                GROUP BY assignor.rf_id
                                ) as temp ON temp.rf_id = assignee.rf_id 
                        GROUP BY assignee.rf_id, assignee.assignor_and_assignee_id
                        UNION ALL
                        SELECT '.$companyID.', '.$organisationID.', assignor.rf_id, assignor.exec_dt, assignor.assignor_and_assignee_id, temp.assignor_and_assignee_id as recorded_assignor_and_assignee, 14 as activity_id  FROM assignor 
                        LEFT JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id 
                        INNER JOIN (SELECT assignee.rf_id, list2.assignor_and_assignee_id FROM assignee
                                        INNER JOIN representative_assignment_conveyance ON assignee.rf_id = representative_assignment_conveyance.rf_id 
                                        INNER JOIN borrower_list2 AS list2 ON list2.rf_id = assignee.rf_id
                                        WHERE (representative_assignment_conveyance.convey_ty NOT IN ("courtorder", "courtappointment", "assignment", "partialassignment", "employee", "option", "merger", "release", "security", "restatedsecurity", "license", "licenseend", "govern", "assignment", "partialassignment", "namechg", "correct") OR representative_assignment_conveyance.convey_ty IN ("other"))
                                                AND list2.company_id = '.$companyID.'
                                                AND list2.organisation_id =  '.$organisationID.' 
                                                AND list2.assignor_and_assignee_id IN ('.implode(',', $allAssignorAndAssigneeIDs).')
                                                AND representative_assignment_conveyance.employer_assign = 0 
                                        GROUP BY assignee.rf_id) as temp ON temp.rf_id = assignor.rf_id 
                        GROUP BY assignor.rf_id,assignor.assignor_and_assignee_id');  



                        /**
                         * 
                         * Find company OTA Assets minus Sold Assets AND merger Out
                         */
                        $queryBorrowerAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_borrower_b WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND assignor_and_assignee_id IN (".implode(',', $allAssignorAndAssigneeIDs).") AND appno_doc_num NOT IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".borrowers_activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 2, 7 ) AND recorded_assignor_and_assignee_id IN (".implode(',', $allAssignorAndAssigneeIDs).") ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";
                            echo $queryBorrowerAllAssetsList."<br/>";
                        $listBorrowerAllAssets = array();
                        
                        $resultBorrowerAllAssetsList = $con->query($queryBorrowerAllAssetsList);
                        if($resultBorrowerAllAssetsList && $resultBorrowerAllAssetsList->num_rows > 0) {
                            echo "TOTAL ROWS".$resultBorrowerAllAssetsList->num_rows."<br/>";
                            while($rowBorrowerAsset = $resultBorrowerAllAssetsList->fetch_object()) {
                                array_push($listBorrowerAllAssets, '"'.$rowBorrowerAsset->appno_doc_num.'"');
                            }
                        }  
                        $listValidBorrowerAllAssets = [];
                        if(count($listBorrowerAllAssets) > 0) {
                            $queryValidBorrowerAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid AS doc  WHERE appno_doc_num IN ( ".implode(',', $listBorrowerAllAssets).")  AND date_format(appno_date, '%Y') > ".$year." GROUP BY appno_doc_num";
                            echo $queryValidBorrowerAllAssetsList."<br/>";
                        
                            $resultBorrowerActiveAllAssetsList = $con->query($queryValidBorrowerAllAssetsList);
                            if($resultBorrowerActiveAllAssetsList && $resultBorrowerActiveAllAssetsList->num_rows > 0) {
                                while($rowAsset = $resultBorrowerActiveAllAssetsList->fetch_object()) {
                                    array_push($listValidBorrowerAllAssets, '"'.$rowAsset->appno_doc_num.'"');
                                }
                            }
                        }
                        

                         

                        $patentedBorrowerAssetsStatus = array();
                        if(count($listValidBorrowerAllAssets) > 0) {
                            $queryPatentedAssets = "SELECT appno_doc_num FROM (SELECT appno_doc_num, status, MAX(status_date) FROM ".$dbUSPTO.".application_status WHERE appno_doc_num IN (".implode(',', $listBorrowerAllAssets).") AND status = 'Patented Case' GROUP BY  appno_doc_num) AS temp GROUP BY  appno_doc_num";
                            echo $queryPatentedAssets;
                            $resultPatentedAssetsList = $con->query($queryPatentedAssets);
                            if($resultPatentedAssetsList && $resultPatentedAssetsList->num_rows > 0) {
                                while($rowBorrowerPatentedAsset = $resultPatentedAssetsList->fetch_object()) {
                                    array_push($patentedBorrowerAssetsStatus, '"'.$rowBorrowerPatentedAsset->appno_doc_num.'"');
                                }
                            } 
                        }

                        $implodeBorrowerAssetsList = implode(',', $listValidBorrowerAllAssets);
                        $implodeBorrowePatentedAssetsList = implode(',', $patentedBorrowerAssetsStatus);
                        print_r($patentedBorrowerAssetsStatus);
                        $con->query("DELETE FROM ".$dbApplication.".borrower_owned_assets WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_and_assignee_id IN (".implode(',', $allAssignorAndAssigneeIDs).")");
                        if(count($patentedBorrowerAssetsStatus) > 0) {
                            echo "INSERT IGNORE INTO ".$dbApplication.".borrower_owned_assets(appno_doc_num, company_id, organisation_id, assignor_and_assignee_id) SELECT appno_doc_num, company_id, organisation_id, assignor_and_assignee_id FROM ".$dbUSPTO.".table_borrower_b WHERE appno_doc_num IN (".$implodeBorrowePatentedAssetsList.") AND company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_and_assignee_id IN (".implode(',', $allAssignorAndAssigneeIDs).") ";


                            $con->query("INSERT IGNORE INTO ".$dbApplication.".borrower_owned_assets(appno_doc_num, company_id, organisation_id, assignor_and_assignee_id) SELECT appno_doc_num, company_id, organisation_id, assignor_and_assignee_id FROM ".$dbUSPTO.".table_borrower_b WHERE appno_doc_num IN (".$implodeBorrowePatentedAssetsList.") AND company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_and_assignee_id IN (".implode(',', $allAssignorAndAssigneeIDs).") ");
                        }

                        /**
                         * Non-Client\'s Collaterals
                         */
                        $type = 19; 
                        $queryAssetsNotOwned = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.", patent, application, 0 AS rf_id, (SELECT COUNT(*) FROM ".$dbApplication.".dashboard_items WHERE type = 17 AND representative_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_id IN (".implode(',', $allAssignorAndAssigneeIDs).")) AS total FROM ".$dbApplication.".dashboard_items WHERE type = 17 AND representative_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_id IN (".implode(',', $allAssignorAndAssigneeIDs).") AND application NOT IN (SELECT appno_doc_num FROM ".$dbApplication.".borrower_owned_assets WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_and_assignee_id IN (".implode(',', $allAssignorAndAssigneeIDs).") GROUP BY appno_doc_num )   ";
                        echo "NOT OWNED <br/>". $queryAssetsNotOwned."<br/>";
                        $con->query($queryAssetsNotOwned);

                        /**
                         * Conflicting Transactions
                         */
                        //$type = 23;
                        

                        $queryOwnedAssets = "SELECT appno_doc_num FROM ".$dbApplication.".borrower_owned_assets WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_and_assignee_id IN (".implode(',', $allAssignorAndAssigneeIDs).") GROUP BY appno_doc_num";

                        $resultBorrowerOwnedAssetList = $con->query($queryOwnedAssets);

                        $ownedAssets = array();
                        if($resultBorrowerOwnedAssetList && $resultBorrowerOwnedAssetList->num_rows > 0) {
                            while($rowOwned = $resultBorrowerOwnedAssetList->fetch_object()) {
                                array_push($ownedAssets, '"'.$rowOwned->appno_doc_num.'"');
                            }
                            /**
                             * Client Current Assets
                             */

                            $type = 26; 
                            $queryAssetsOwned = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, ".$allAssignorAndAssigneeIDs[0].", ".$type.", MAX(doc.grant_doc_num), MAX(doc.appno_doc_num), 0 AS rf_id, 0 AS total FROM ".$dbUSPTO.".documentid AS doc WHERE doc.appno_doc_num IN (".implode(',', $ownedAssets).") GROUP BY doc.appno_doc_num";

                            $con->query($queryAssetsOwned);


                            /**
                             * Other Banks
                             */

                            $type = 27;

                            $queryClientTransactions = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, apt.assignor_and_assignee_id, ".$type.", '' AS patent, '' AS application, apt.rf_id, COUNT(DISTINCT doc.appno_doc_num) AS total FROM ".$dbApplication.".borrowers_activity_parties_transactions AS apt INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id WHERE apt.activity_id IN (5, 12) AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID." AND assignor_and_assignee_id IN (".implode(',', $allAssignorAndAssigneeIDs).") AND date_format(apt.exec_dt, '%Y') >= ".$year." GROUP BY apt.assignor_and_assignee_id, apt.rf_id";
                            $con->query($queryClientTransactions);
                        } 
                    } 
                }
            }
        }
	}
}
?>

