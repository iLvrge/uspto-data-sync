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
        echo "ENTER1";	
        $listAllAssets = array();
		$queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbApplication.".assets_with_bank WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." GROUP BY appno_doc_num";
		
		$resultAllAssetsList = $con->query($queryAllAssetsList);
		if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
			while($rowAsset = $resultAllAssetsList->fetch_object()) {
				array_push($listAllAssets, '"'.$rowAsset->appno_doc_num.'"');
			}
		} 
        
		if(count($listAllAssets) > 0) {
            echo "ENTER2";
            /**
             * Broken Chain of Title
             */
            $type = 1;
            $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
            $queryBrokenChain = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.", grant_doc_num, appno_doc_num, 0 AS rf_id, ".count($listAllAssets)." FROM db_new_application.assets_bank_broken 
            WHERE organisation_id = ".$organisationID." AND company_id = ".$companyID."  GROUP BY appno_doc_num, company_id, assignor_id";

            $con->query($queryBrokenChain);


            /**
             * Incorrect Names
             */
            $type = 17;
            $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
            $queryBrokenChain = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.", grant_doc_num, appno_doc_num, rf_id, (SELECT COUNT(transactions) FROM ( 
                SELECT  rf_id AS transactions FROM db_uspto.documentid
                   WHERE appno_doc_num IN (".implode(',', $listAllAssets).")
                   GROUP BY rf_id                                
               ) as temp1) AS total FROM db_new_application.lost_assets 
            WHERE organisation_id = ".$organisationID." AND company_id = ".$companyID."  GROUP BY appno_doc_num, rf_id, company_id, assignor_id";

            $con->query($queryBrokenChain);


            /**
             * Encumbrances
             */
            $type = 18;
            $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
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
            echo $queryEncumbrances;
            $con->query($queryEncumbrances);

            /**
             * Invalid Collaterals
             */
            $year = 2000;
            $type = 20;
            $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
            $queryCollaterals = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT organisation_id, representative_id, assignor_id, ".$type.", patent, application, rf_id, ".count($listAllAssets)." FROM (SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, tawb.assignor_id AS assignor_id, 20 AS type, d.grant_doc_num  AS patent, d.appno_doc_num AS application, '' AS rf_id, (SELECT tawbe.appno_doc_num
            FROM ".$dbApplication.".assets_with_bank_expired AS tawbe WHERE tawbe.appno_doc_num = d.appno_doc_num AND tawbe.expire_date < tawb.exec_dt) AS expired_assets FROM db_new_application.assets_with_bank AS tawb
            INNER JOIN db_uspto.documentid AS d ON d.rf_id = tawb.rf_id
            WHERE tawb.company_id = ".$companyID." AND tawb.organisation_id = ".$organisationID." AND date_format(d.appno_date, '%Y') >= ".$year."
            GROUP BY assignor_id, d.appno_doc_num) AS temp WHERE expired_assets <> ''";
            $con->query($queryCollaterals);
            /**
            * Late Maintainence
            */
            $type = 23;
            $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
            $queryLateMaintainence = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, tawb.assignor_id AS assignor_id, ".$type.", emf.grant_doc_num AS patent, tawb.appno_doc_num AS application, '' AS rf_id, ".count($listAllAssets)."                               
                            FROM db_new_application.assets_with_bank as tawb
                            INNER JOIN db_patent_maintainence_fee.event_maintainence_fees AS emf ON emf.appno_doc_num = tawb.appno_doc_num
                            WHERE company_id = ".$companyID." 
                            AND organisation_id = ".$organisationID." 
                            AND emf.event_code IN ('F176', 'M1554', 'M1555', 'M1556', 'M1557', 'M1558', 'M176', 'M177', 'M178', 'M181', 'M182', 'M186', 'M187', 'M188', 'M2554', 'M2555', 'M2556', 'M2558', 'M277', 'M281', 'M282', 'M286', 'M3554', 'M3555', 'M3556', 'M3557', 'M3558') GROUP BY assignor_id, tawb.appno_doc_num";
            $con->query($queryLateMaintainence);
            /**
            * Incorrect Recordings
            */
            $type = 24;
            $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
            $queryIncorrectRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.", '' AS patent, '' AS application, rf_id, (SELECT COUNT(transactions) FROM ( 
                SELECT  rf_id AS transactions FROM db_uspto.documentid
                   WHERE appno_doc_num IN (".implode(',', $listAllAssets).")
                   GROUP BY rf_id                                
               ) as temp1) AS total FROM ( SELECT rac.rf_id AS rf_id, tawb.assignor_id AS assignor_id
                                    FROM db_new_application.assets_with_bank as tawb
                                    INNER JOIN (
                                        SELECT appno_doc_num, rf_id FROM db_uspto.documentid
                                        WHERE appno_doc_num IN (".implode(',', $listAllAssets).")   
                                        GROUP BY appno_doc_num, rf_id                                         
                                    ) AS doc ON doc.appno_doc_num = tawb.appno_doc_num
                                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id
                                    WHERE company_id = ".$companyID." 
                                    AND organisation_id  = ".$organisationID." 
                                    AND rac.convey_ty = 'correct'
                                    GROUP BY rac.rf_id, tawb.assignor_id) AS temp";
            $con->query($queryIncorrectRecording);
            /**
            * Late Recordings
            */  
            $days = 90;
            $type = 25;
            $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);   
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
        }
	}
}
?>
