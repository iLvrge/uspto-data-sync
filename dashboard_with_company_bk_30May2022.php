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
	$company = $variables[1];
	$organisationID = $variables[2];
	if((int)$organisationID > 0) {	
        $listAllAssets = array();
		$companiesData = array();

        $query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID;	
        $result = $con->query($query);
        if($result && $result->num_rows > 0) {  
            while($row = $result->fetch_object()) {
                $orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
                if($orgConnect) {
                    $queryRepresentative = "SELECT representative_id, original_name, representative_name, parent_id, child FROM representative WHERE type = 0 "; 
                    
                    if($company != "") {
                        $queryRepresentative .= " AND representative_id = ".$company;
                    }
                    $resultRepresentative = $orgConnect->query($queryRepresentative);	
                    if($resultRepresentative && $resultRepresentative->num_rows > 0) {
                        while($representative = $resultRepresentative->fetch_object()){
            array_push($companiesData, array('representative_id'=>$representative->representative_id, 'name'=>$representative->representative_name));
                        }
                    }
                }
            }
        }

        if(count($companiesData) > 0) {
            foreach($companiesData as $company) {	
                $companyID = $company['representative_id'];
                $companyAllAssignorAndAssigneeIDs = array();

                $queryFindCompanyRepresentative = "SELECT representative_id FROM ".$dbUSPTO.".representative WHERE representative_name = '".$con->real_escape_string($company['name'])."' ORDER BY representative_id DESC LIMIT 1";
                echo $queryFindCompanyRepresentative;
                $resultCompanyRepresentative = $con->query($queryFindCompanyRepresentative);	
                $representativeID = 0;
                if($resultCompanyRepresentative->num_rows > 0) {
                    $representativeRow = $resultCompanyRepresentative->fetch_object();
                    $representativeID = $representativeRow->representative_id;
                }

                $queryAssignorAndAssigneeIDs = "SELECT assignor_and_assignee_id FROM ".$dbUSPTO.".assignor_and_assignee WHERE name = '".$con->real_escape_string($company['name'])."' ";

                if($representativeID > 0) {
                    $queryAssignorAndAssigneeIDs .= "  OR representative_id = ".$representativeID." GROUP BY assignor_and_assignee_id";
                }
                echo  $queryAssignorAndAssigneeIDs;
                $resultCompanyAssignorAndAssigneeIDs = $con->query($queryAssignorAndAssigneeIDs);	
                $companyAssignorAndAssigneeIDs = array();
                if($resultCompanyAssignorAndAssigneeIDs->num_rows > 0) {
                    while($companyAssignorAssigneeRow = $resultCompanyAssignorAndAssigneeIDs->fetch_object()) {
                        array_push($companyAssignorAndAssigneeIDs, $companyAssignorAssigneeRow->assignor_and_assignee_id);
                    }
                }
                print_r($companyAssignorAndAssigneeIDs);
                echo "COMPANYID: ".$companyID."<br/>";
                /*$queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbApplication.".assets WHERE company_id = ".$companyID." AND date_format(assets.appno_date, '%Y') > 1999 AND organisation_id = ".$organisationID." AND assets.layout_id = 15 GROUP BY appno_doc_num";*/
                /**
                 * 
                 * Find company OTA Assets minus Sold Assets
                 */
                $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND appno_doc_num NOT IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id = 2 ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";

                
                
                $resultAllAssetsList = $con->query($queryAllAssetsList);
                if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                    while($rowAsset = $resultAllAssetsList->fetch_object()) {
                        array_push($listAllAssets, '"'.$rowAsset->appno_doc_num.'"');
                    }
                } 
                echo "COUNT: ".count($listAllAssets);
                /**
                 * Remove expired assets
                 */
                $expiredAssets = array();
                if(count($listAllAssets) > 0) {
                    $queryExpiredAssets = "SELECT appno_doc_num FROM ".$dbUSPTO.".application_status WHERE appno_doc_num IN (".implode(',', $listAllAssets).") AND (`status` LIKE '%abandoned%' OR `status` LIKE '%expired%' OR `status` LIKE '%final rejection%')";
                    echo $queryExpiredAssets;
                    $resultExpiredAssetsList = $con->query($queryExpiredAssets);
                    if($resultExpiredAssetsList && $resultExpiredAssetsList->num_rows > 0) {
                        while($rowAsset = $resultExpiredAssetsList->fetch_object()) {
                            array_push($expiredAssets, '"'.$rowAsset->appno_doc_num.'"');
                        }
                    } 
                }
                if(count($expiredAssets) > 0) {
                    $listAllAssets = array_diff($listAllAssets, $expiredAssets);
                }
                
                
                echo "COUNT2: ".count($listAllAssets);

                echo implode(',', $expiredAssets);

                $con->query("DELETE FROM ".$dbApplication.".owned_assets WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);

                $con->query("INSERT IGNORE INTO ".$dbApplication.".owned_assets(appno_doc_num, company_id, organisation_id) SELECT appno_doc_num, company_id, organisation_id FROM ".$dbApplication.".assets WHERE appno_doc_num IN (".implode(',', $listAllAssets).") AND company_id = ".$companyID." AND organisation_id = ".$organisationID." AND layout_id = 15");

                /**
                 * Find SOld Assets
                 */

                /* $querySoldAssets = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id = 2 ) GROUP BY appno_doc_num";

                $listSoldAssets = array();
                $resultSoldAssetsList = $con->query($querySoldAssets);
                if($resultSoldAssetsList && $resultSoldAssetsList->num_rows > 0) {
                    while($rowAsset = $resultSoldAssetsList->fetch_object()) {
                        array_push($listSoldAssets, '"'.$rowAsset->appno_doc_num.'"');
                    }
                }

                $listAllAssets = array_diff($listAllAssets, $listSoldAssets); */

                if(count($listAllAssets) > 0) {
                    /**s
                     * Broken Chain of Title
                     */
                    $type = 1;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $queryBrokenChain = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type." AS type, grant_doc_num, appno_doc_num, 0 AS rf_id, ".count($listAllAssets)."  FROM db_new_application.assets AS assets  WHERE date_format(assets.appno_date, '%Y') > 1999 AND assets.layout_id = 1 AND assets.company_id = ".$companyID." AND assets.organisation_id = ".$organisationID."  GROUP BY appno_doc_num";

                    $con->query($queryBrokenChain);
                    /**
                     * Incorrect Names
                     */
                    $type = 17;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $queryIncorrectNames = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, recorded_assignor_and_assignee_id AS assignor_id, ".$type." AS type, grantNo, appno, rf_id, (SELECT COUNT(transactions) FROM ( 
                        SELECT  rf_id AS transactions FROM db_uspto.documentid
                        WHERE appno_doc_num IN (".implode(',', $listAllAssets).")
                        GROUP BY rf_id                                
                    ) as temp1) AS total FROM (
                        SELECT apt.recorded_assignor_and_assignee_id, MAX(appno_doc_num) AS appno, MAX(appno_date) AS appnoDt, MAX(grant_doc_num) AS grantNo, MAX(grant_date) AS grantDt,  rac.rf_id, aaa.name AS name,
                                            (SELECT representative_name FROM db_uspto.representative WHERE representative_id = aaa.representative_id) AS representative_name  FROM db_new_application.activity_parties_transactions AS apt
                        INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = apt.rf_id
                        INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id 
                        INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = rac.rf_id 
                        INNER JOIN db_uspto.conveyance AS con ON con.convey_name = rac.convey_ty AND con.is_ota = 1 
                        INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = ass.assignor_and_assignee_id
                        WHERE apt.company_id = ".$companyID." AND apt.organisation_id = ".$organisationID." AND appno_doc_num IN (".implode(',', $listAllAssets).")
                        GROUP BY apt.recorded_assignor_and_assignee_id, appno_doc_num, rac.rf_id
                        ) AS temp
                        WHERE representative_name <> '' AND LOWER(name) <> LOWER(representative_name)";
                    
                        $con->query($queryIncorrectNames);
                    /**
                     * Encumbrances
                    */   
                    $type = 18;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    /*$queryEncumbrances = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, max_date.assignor_id, ".$type.", d.grant_doc_num, d.appno_doc_num, rac.rf_id, ".count($listAllAssets)."  FROM db_uspto.documentid AS d 
                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = d.rf_id AND rac.convey_ty NOT IN ('namechg', 'licenseend', 'release', 'addresschg', 'correspondchange')
                    INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = rac.rf_id 
                    INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = rac.rf_id
                    INNER JOIN LATERAL (
                        SELECT assets.appno_doc_num, apt.assignor_and_assignee_id AS assignor_id, apt.exec_dt, apt.rf_id FROM db_new_application.assets AS assets
                        INNER JOIN db_new_application.activity_parties_transactions AS apt ON apt.rf_id = assets.rf_id
                        WHERE assets.company_id = ".$companyID." AND assets.organisation_id = ".$organisationID."
                        AND assets.appno_doc_num IN (".implode(',', $listAllAssets).")
                        GROUP BY assignor_id, rf_id
                    ) AS max_date ON max_date.appno_doc_num = d.appno_doc_num AND aor.exec_dt > max_date.exec_dt AND max_date.rf_id <> rac.rf_id AND aor.assignor_and_assignee_id = max_date.assignor_id
                    GROUP BY d.appno_doc_num";*/
/*
                    $queryEncumbrances = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, max_date.assignor_id, ".$type.", d.grant_doc_num, d.appno_doc_num, rac.rf_id, ".count($listAllAssets)."  FROM db_uspto.documentid AS d 
                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = d.rf_id AND rac.convey_ty NOT IN ('namechg', 'licenseend', 'release', 'addresschg', 'correspondchange')
                    INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = rac.rf_id 
                    INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = rac.rf_id
                    INNER JOIN LATERAL (
                        SELECT assets.appno_doc_num, apt.assignor_and_assignee_id AS assignor_id, apt.exec_dt, apt.rf_id FROM db_new_application.assets AS assets
                        INNER JOIN db_new_application.activity_parties_transactions AS apt ON apt.rf_id = assets.rf_id
                        WHERE assets.company_id = ".$companyID." AND assets.organisation_id = ".$organisationID."
                        AND assets.appno_doc_num IN (".implode(',', $listAllAssets).")
                        GROUP BY assignor_id, rf_id
                    ) AS max_date ON max_date.appno_doc_num = d.appno_doc_num AND aor.exec_dt > max_date.exec_dt AND max_date.rf_id <> rac.rf_id AND aor.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).")
                    GROUP BY d.appno_doc_num";*/

                    $queryEncumbrances = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, aor.assignor_and_assignee_id, ".$type.", d.grant_doc_num, d.appno_doc_num, rac.rf_id, ".count($listAllAssets)."  FROM db_uspto.documentid AS d 
                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = d.rf_id AND rac.convey_ty NOT IN ('namechg', 'licenseend', 'release', 'addresschg', 'correspondchange')                    
                    INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = rac.rf_id 
                    INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = rac.rf_id 
                    WHERE d.appno_doc_num IN (".implode(',', $listAllAssets).") AND aor.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).")                  
                    GROUP BY d.appno_doc_num";
                    
                    $con->query($queryEncumbrances);

                    /**
                    * Late Maintainence
                    */
                    $type = 23;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $queryLateMaintainence = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", emf.grant_doc_num, tawb.appno_doc_num, '' AS rf_id, ".count($listAllAssets)."                               
                    FROM db_new_application.assets as tawb
                    INNER JOIN db_patent_maintainence_fee.event_maintainence_fees AS emf ON emf.appno_doc_num = tawb.appno_doc_num
                    WHERE company_id  = ".$companyID."
                    AND organisation_id = ".$organisationID."
                    AND tawb.appno_doc_num IN (".implode(',', $listAllAssets).")
                    AND emf.event_code IN ('F176', 'M1554', 'M1555', 'M1556', 'M1557', 'M1558', 'M176', 'M177', 'M178', 'M181', 'M182', 'M186', 'M187', 'M188', 'M2554', 'M2555', 'M2556', 'M2558', 'M277', 'M281', 'M282', 'M286', 'M3554', 'M3555', 'M3556', 'M3557', 'M3558') GROUP BY tawb.appno_doc_num";
                    
                    $con->query($queryLateMaintainence);

                    /**
                    * Incorrect Recordings
                    */
                    $type = 24;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    /*$queryIncorrectRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.", '' AS patent, '' AS application, rf_id, (SELECT COUNT(transactions) FROM ( 
                        SELECT  rf_id AS transactions FROM db_uspto.documentid AS documentid
                        INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = documentid.rf_id AND apt.comapany_id = ".$companyID."
                        WHERE appno_doc_num IN (".implode(',', $listAllAssets).")
                        GROUP BY rf_id                                
                    ) as temp1) AS total FROM ( SELECT rac.rf_id AS rf_id, 0 AS assignor_id
                    FROM db_new_application.assets as tawb
                    INNER JOIN (
                        SELECT appno_doc_num, rf_id FROM db_uspto.documentid
                        WHERE appno_doc_num IN (".implode(',', $listAllAssets).")   
                        GROUP BY appno_doc_num, rf_id                                         
                    ) AS doc ON doc.appno_doc_num = tawb.appno_doc_num
                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id
                    WHERE company_id = ".$companyID." 
                    AND organisation_id = ".$organisationID." 
                    AND rac.convey_ty = 'correct'
                    GROUP BY tawb.appno_doc_num) AS temp";*/
                    $queryIncorrectRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.", '' AS patent, '' AS application, rf_id, (SELECT COUNT(transactions) FROM ( 
                        SELECT  apt.rf_id AS transactions FROM db_uspto.documentid AS doc
                        INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = doc.rf_id AND apt.company_id = ".$companyID."
                        WHERE doc.appno_doc_num IN (".implode(',', $listAllAssets).")
                        GROUP BY apt.rf_id                                
                    ) as temp1) AS total FROM ( SELECT rac.rf_id AS rf_id, 0 AS assignor_id
                    FROM ".$dbApplication.".activity_parties_transactions AS apt 
                    INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id
                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id
                    WHERE apt.company_id = ".$companyID." 
                    AND apt.organisation_id = ".$organisationID." 
                    AND doc.appno_doc_num IN (".implode(',', $listAllAssets).")
                    AND rac.convey_ty = 'correct'
                    GROUP BY apt.rf_id) AS temp";
                    $con->query($queryIncorrectRecording);

                    /**
                    * Late Recordings
                    */  
                    $days = 90;
                    $type = 25;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);   
                    $queryLateRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0, ".$type.", '' AS patent, '' AS application, rf_id, (SELECT COUNT(transactions) FROM ( 
                        SELECT  rf_id AS transactions FROM db_uspto.documentid
                        WHERE appno_doc_num IN (".implode(',', $listAllAssets).")
                        GROUP BY rf_id                                
                    ) as temp1) AS total FROM (SELECT temp_exec_dt.rf_id, DATEDIFF(ass.record_dt, temp_exec_dt.exec_dt) AS noOfDays   
                    FROM db_new_application.assets as tawb
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
                        INNER JOIN db_new_application.assets AS tawb1 ON tawb1.appno_doc_num = doc1.appno_doc_num
                        WHERE company_id = ".$companyID." 
                        AND organisation_id = ".$organisationID." 
                        GROUP BY aor.rf_id
                    ) AS temp_exec_dt ON  temp_exec_dt.rf_id = ass.rf_id
                    WHERE company_id = ".$companyID." 
                    AND organisation_id = ".$organisationID." 
                    GROUP BY temp_exec_dt.rf_id
                    HAVING noOfDays > ".$days.") AS temp ";  

                    $con->query($queryLateRecording);  

                }
            }
        }
    }
}