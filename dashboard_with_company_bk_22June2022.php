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

function removeDoubleSpace($string) {
	return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return trim(ucwords(strtolower($string)));
}

function get_corpus_index($corpus = array(), $separator=' ') {

    $dictionary = array();

    $doc_count = array();

    foreach($corpus as $doc_id => $doc) {

        $terms = explode($separator, $doc);

        $doc_count[$doc_id] = count($terms);

        // tf–idf, short for term frequency–inverse document frequency, 
        // according to wikipedia is a numerical statistic that is intended to reflect 
        // how important a word is to a document in a corpus

        foreach($terms as $term) {

            if(!isset($dictionary[$term])) {

                $dictionary[$term] = array('document_frequency' => 0, 'postings' => array());
            }
            if(!isset($dictionary[$term]['postings'][$doc_id])) {

                $dictionary[$term]['document_frequency']++;

                $dictionary[$term]['postings'][$doc_id] = array('term_frequency' => 0);
            }

            $dictionary[$term]['postings'][$doc_id]['term_frequency']++;
        }

        //from http://phpir.com/simple-search-the-vector-space-model/

    }

    return array('doc_count' => $doc_count, 'dictionary' => $dictionary);
}

function get_similar_documents($query='', $corpus=array(), $separator=' '){

    $similar_documents=array();

    if($query!=''&&!empty($corpus)){

        $words=explode($separator,$query);

        $corpus=get_corpus_index($corpus, $separator);

        $doc_count=count($corpus['doc_count']);

        foreach($words as $word) {

            if(isset($corpus['dictionary'][$word])){

                $entry = $corpus['dictionary'][$word];


                foreach($entry['postings'] as $doc_id => $posting) {

                    //get term frequency–inverse document frequency
                    $score=$posting['term_frequency'] * log($doc_count + 1 / $entry['document_frequency'] + 1, 2);

                    if(isset($similar_documents[$doc_id])){

                        $similar_documents[$doc_id]+=$score;

                    }
                    else{

                        $similar_documents[$doc_id]=$score;

                    }
                }
            }
        }

        // length normalise
        foreach($similar_documents as $doc_id => $score) {

            $similar_documents[$doc_id] = $score/$corpus['doc_count'][$doc_id];

        }

        // sort from  high to low

        arsort($similar_documents);

    }   

    return $similar_documents;
}

$variables = $argv;
if(count($variables) == 3) {
	$company = $variables[1];
	$organisationID = $variables[2];
	if((int)$organisationID > 0) {	
        $listAllAssets = array();
		$companiesData = array();
        $companyAddress = array();
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


                            /**
                             * Company Address
                             */
                            $queryAddress = "SELECT * FROM address WHERE representative_id =".$representative->representative_id;
                            $resultAddress = $orgConnect->query($queryAddress);
                            if($resultAddress && $resultAddress->num_rows > 0) {
                                while($representativeAddress = $resultAddress->fetch_object()){
                                    array_push($companyAddress, $representativeAddress);
                                }
                            }
                        }
                    }
                }
            }
        }

        if(count($companiesData) > 0) {
            /**
             * Security End Date
             */
            $year = 1997;
            $releaseIDs = array();

            $queryReleaseIDs = "SELECT apt.release_rf_id AS rf_id, FROM ".$dbApplication.".activity_parties_transactions AS apt WHERE  apt.activity_id IN (5,12) AND date_format(apt.exec_dt, '%Y') > ".$year." AND release_rf_id <> '' GROUP BY apt.release_rf_id";

            $resultReleaseIDs = $con->query($queryReleaseIDs);
            if($resultReleaseIDs && $resultReleaseIDs->num_rows > 0) {
                while($rowReleaseIDs = $resultReleaseIDs->fetch_object()){
                    array_push($releaseIDs, (int)$rowReleaseIDs->rf_id);
                }
            }
            foreach($companiesData as $company) {	
                $companyID = $company['representative_id'];
                $companyAllAssignorAndAssigneeIDs = array();

                $queryFindCompanyRepresentative = "SELECT representative_id FROM ".$dbUSPTO.".representative WHERE representative_name = '".$con->real_escape_string($company['name'])."' ORDER BY representative_id DESC LIMIT 1";
                //echo $queryFindCompanyRepresentative;
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
                //echo  $queryAssignorAndAssigneeIDs;
                $resultCompanyAssignorAndAssigneeIDs = $con->query($queryAssignorAndAssigneeIDs);	
                $companyAssignorAndAssigneeIDs = array();
                if($resultCompanyAssignorAndAssigneeIDs->num_rows > 0) {
                    while($companyAssignorAssigneeRow = $resultCompanyAssignorAndAssigneeIDs->fetch_object()) {
                        array_push($companyAssignorAndAssigneeIDs, $companyAssignorAssigneeRow->assignor_and_assignee_id);
                    }
                }
                //print_r($companyAssignorAndAssigneeIDs);
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
                //echo "COUNT: ".count($listAllAssets);
                /**
                 * Remove expired assets
                 */
                $expiredAssets = array();
                if(count($listAllAssets) > 0) {
                    $queryExpiredAssets = "SELECT appno_doc_num FROM ".$dbUSPTO.".application_status WHERE appno_doc_num IN (".implode(',', $listAllAssets).") AND (`status` LIKE '%abandoned%' OR `status` LIKE '%expired%' OR `status` LIKE '%final rejection%')";
                    //echo $queryExpiredAssets;
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
                
                
                //echo "COUNT2: ".count($listAllAssets);

                //echo implode(',', $expiredAssets);

                $implodeAssetsList = implode(',', $listAllAssets);
/*
                $con->query("DELETE FROM ".$dbApplication.".owned_assets WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);

                $con->query("INSERT IGNORE INTO ".$dbApplication.".owned_assets(appno_doc_num, company_id, organisation_id) SELECT appno_doc_num, company_id, organisation_id FROM ".$dbApplication.".assets WHERE appno_doc_num IN (".$implodeAssetsList.") AND company_id = ".$companyID." AND organisation_id = ".$organisationID." AND layout_id = 15");
                    */


                







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
                    
                    foreach($listAllAssets as $asset) {
                        $querySecurity = "SELECT apt.rf_id AS rf_id, exec_dt,  ( SELECT GROUP_CONCAT(DISTINCT dd.appno_doc_num) FROM ".$dbUSPTO.".documentid AS dd WHERE dd.rf_id = apt.rf_id) AS totalAssets FROM ".$dbApplication.".activity_parties_transactions AS apt
                        WHERE apt.organisation_id = ".$organisationID." AND apt.rf_id IN (
                            SELECT rf_id FROM ".$dbUSPTO.".documentid WHERE appno_doc_num = ".$asset." AND date_format(appno_date, '%Y') > ".$year." GROUP BY rf_id
                        ) AND apt.activity_id IN (5,12) AND date_format(apt.exec_dt, '%Y') > ".$year." AND release_rf_id IS NULL GROUP BY apt.rf_id ORDER BY exec_dt ASC ";
                        
                        $securities = [];
                        $resultSecurity = $con->query($querySecurity);
                        if($resultSecurity && $resultSecurity->num_rows > 0) {
                            while($rowSecurity = $resultSecurity->fetch_object()){
                                array_push($securities, array('rf_id'=> $rowSecurity->rf_id, 'exec_dt'=>$rowSecurity->exec_dt, 'total'=> $rowSecurity->totalAssets));
                            }
                        }

                        $queryRelease = "SELECT apt.rf_id AS rf_id, exec_dt,  ( SELECT GROUP_CONCAT(DISTINCT dd.appno_doc_num) FROM ".$dbUSPTO.".documentid AS dd WHERE dd.rf_id = apt.rf_id) AS totalAssets FROM ".$dbApplication.".activity_parties_transactions AS apt
                        WHERE apt.organisation_id = ".$organisationID." AND apt.rf_id IN (
                            SELECT rf_id FROM ".$dbUSPTO.".documentid WHERE appno_doc_num = ".$asset." AND date_format(appno_date, '%Y') > ".$year." GROUP BY rf_id
                        ) AND apt.activity_id IN (13, 11) AND date_format(apt.exec_dt, '%Y') > ".$year." GROUP BY apt.rf_id ORDER BY exec_dt ASC ";
                       
                        $releases = [];
                        $resultRelease = $con->query($queryRelease);
                        if($resultRelease && $resultRelease->num_rows > 0) {
                            while($rowSecurity = $resultRelease->fetch_object()){
                                array_push($releases, array('rf_id'=> $rowSecurity->rf_id, 'exec_dt'=>$rowSecurity->exec_dt,'total'=> $rowSecurity->totalAssets));
                            }
                        }                        
                        if(count($securities) > 0 && count($releases) > 0) {                           
                            foreach($securities as $security) {
                                $allSecuritiesAssets = explode(',', $security['total']);
                                $entry = false;
                                foreach($releases as $release) {
                                    if($entry === false) {
                                        $allReleaseAssets = explode(',', $release['total']);
                                        $remainingArray = array_diff($allSecuritiesAssets, $allReleaseAssets);
                                        if(count($remainingArray) == 0 &&  strtotime($release['exec_dt']) > strtotime($security['exec_dt'])) {
                                            $checkRelease = false;
                                            if(count($releaseIDs) > 0 && in_array((int)$release['rf_id'], $releaseIDs)){
                                                $checkRelease = true;
                                            }
                                            if($checkRelease  === false) {
                                                if(count($releaseIDs) > 0) {
                                                    print_r($releaseIDs);
                                                    echo (int)$release['rf_id'];
                                                    
                                                }
                                                
                                                /**
                                                 * All security assets released
                                                 */
                                                $updateQuery = "UPDATE ".$dbApplication.".activity_parties_transactions SET release_rf_id = '" .$release['rf_id']. "', release_exec_dt = '" .$release['exec_dt']. "'  WHERE rf_id =".$security['rf_id'];
        
                                                echo $updateQuery."<br/>";
        
                                                array_push($releaseIDs, (int)$release['rf_id']);
                                                $con->query($updateQuery);
                                                $entry = true;
                                                break;
                                            }                                            
                                        }
                                    }                                    
                                }
                            }
                        }
                    }

                    

                    

                    /**
                     * Non-Expired Patents
                    */

                    /**
                     * Patents Acquired (Acquisition and MergerIn)
                     */
                    $type = 31;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $acquiredAcitivityID = implode(',', array(1,6));
                    $queryPatentAcquired = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", '', d.appno_doc_num, 0, 0 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE d.grant_doc_num <> '' AND date_format(d.appno_date, '%Y') > ".$year." AND d.appno_doc_num IN (".$implodeAssetsList.") AND apt.activity_id IN (".$acquiredAcitivityID.") AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID." GROUP BY d.appno_doc_num";
                    $con->query($queryPatentAcquired);
                    
                    /**
                     * Patents Invented
                     */
                    $type = 32;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $queryPatentsInvented = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", '', d.appno_doc_num, 0, 0 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = d.rf_id WHERE ass.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") AND date_format(d.appno_date, '%Y') > ".$year."  AND d.appno_doc_num IN (".$implodeAssetsList.") AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID." AND (rac.convey_ty = 'employee' OR rac.employer_assign = 1) GROUP BY d.appno_doc_num";
                    $con->query($queryPatentsInvented);
                    /**
                     * Un Maintained Patents
                     */
                    $type = 33;

                    /**
                     * Pending Applications
                     */
                    $type = 34;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $queryPendingApplications = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", '', d.appno_doc_num, 0, 0 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE d.grant_doc_num = '' AND date_format(d.appno_date, '%Y') > ".$year."  AND d.appno_doc_num IN (".$implodeAssetsList.") AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." GROUP BY d.appno_doc_num";
                    $con->query($queryPendingApplications);
                    /**
                     * Filed Application
                     */
                    $type = 35;
                    /**
                     * Application Acquired (Acquisition and MergerIn)
                     */
                    $type = 36;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $acquiredAcitivityID = implode(',', array(1,6));
                    $queryApplicationsAcquired = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", '', d.appno_doc_num, 0, 0 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE d.grant_doc_num = '' AND date_format(d.appno_date, '%Y') > ".$year."  AND d.appno_doc_num IN (".$implodeAssetsList.") AND apt.activity_id IN (".$acquiredAcitivityID.") AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID." GROUP BY d.appno_doc_num";
                    
                    
                    /**
                     *  Maintainance Budget
                     */
                    $type = 37;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $queryMaintainenceBudget = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, event_code, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", '', emf.appno_doc_num, 0, emf.event_code, CASE WHEN emf.event_code = 'M1554' THEN 500 WHEN emf.event_code = 'M2554' THEN 250 WHEN emf.event_code = 'M3554' THEN 125 WHEN emf.event_code = 'M1555' THEN 500 WHEN emf.event_code = 'M2555' THEN 250 WHEN emf.event_code = 'M3555' THEN 125  WHEN emf.event_code = 'M1556' THEN 500 WHEN emf.event_code = 'M2556' THEN 250 WHEN emf.event_code = 'M3556' THEN 125 WHEN emf.event_code = 'M1558' THEN 2100 WHEN emf.event_code = 'M2558' THEN 1050 WHEN emf.event_code = 'M3558' THEN 525  WHEN emf.event_code = 'M1551' THEN 2000 WHEN emf.event_code = 'M2551' THEN 1000 WHEN emf.event_code = 'M3551' THEN 500  WHEN emf.event_code = 'M1552' THEN 3760 WHEN emf.event_code = 'M2552' THEN 1880 WHEN emf.event_code = 'M3552' THEN 940  WHEN emf.event_code = 'M1553' THEN 7700 WHEN emf.event_code = 'M2553' THEN 3850 WHEN emf.event_code = 'M3553' THEN 1925  ELSE 0 END AS amount   
                    FROM db_patent_maintainence_fee.event_maintainence_fees AS emf 
                    WHERE emf.appno_doc_num IN (".$implodeAssetsList.") AND date_format(emf.filling_date, '%Y') > ".$year." 
                    AND emf.event_code IN ('M1551', 'M2551', 'M3551', 'M1552', 'M2552', 'M3552', 'M1553', 'M2553', 'M3553', 'M1554', 'M2554', 'M3554', 'M1555', 'M2555', 'M3555', 'M1556', 'M2556', 'M3556',  'M1558', 'M2558', 'M3558') GROUP BY emf.appno_doc_num, emf.event_code";
                    $con->query($queryMaintainenceBudget);
                    /**
                     * Top non-US Members
                     */
                    $type = 38;
                    
                    /**
                     * Top Proliferate Inventors
                     */
                    $type = 39;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID); 
                    
                    /*
                    SELECT inventorName, SUM(app) AS appNo FROM (SELECT aaa.assignor_and_assignee_id, IF(aaa.representative_id <> '', r.representative_name, aaa.name) AS inventorName, COUNT(application) AS app FROM (SELECT aor.assignor_and_assignee_id, d.appno_doc_num AS application FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".assignor AS aor ON aor.rf_id = d.rf_id WHERE ass.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") AND d.appno_doc_num IN (".$implodeAssetsList.")  AND date_format(d.appno_date, '%Y') > ".$year." AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." AND (rac.convey_ty = 'employee' OR rac.employer_assign = 1) GROUP BY d.appno_doc_num, aor.assignor_and_assignee_id) AS temp INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = temp.assignor_and_assignee_id LEFT JOIN ".$dbUSPTO.".representative AS r ON r.representative_id = aaa.representative_id GROUP BY aaa.assignor_and_assignee_id) AS temp1 GROUP BY inventorName
                    */
                    $queryTopInventor = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", aor.assignor_and_assignee_id, ".$type.", '', d.appno_doc_num, 0, 0 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".assignor AS aor ON aor.rf_id = d.rf_id WHERE ass.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") AND d.appno_doc_num IN (".$implodeAssetsList.")  AND date_format(d.appno_date, '%Y') > ".$year." AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." AND (rac.convey_ty = 'employee' OR rac.employer_assign = 1) GROUP BY d.appno_doc_num, aor.assignor_and_assignee_id";                    
                    $con->query($queryTopInventor);
                    /**
                     * Top Law Firms
                     */
                    $type = 40;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $queryLawFirms = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total, lawfirm) SELECT  ".$organisationID.", ".$companyID.", 0, ".$type.", '', d.appno_doc_num, 0, 0,  a.caddress_1 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".assignment AS a ON a.rf_id = apt.rf_id WHERE d.appno_doc_num IN (".$implodeAssetsList.") AND date_format(d.appno_date, '%Y') > ".$year."  AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." AND a.caddress_1 <> '' GROUP BY d.appno_doc_num, a.caddress_1";
                    $con->query($queryLawFirms);

                    /**
                     * Top Lenders
                     */
                    $type = 41;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                    /* $queryLenders = "SELECT lendorName, SUM(app) AS appNo FROM (
                        SELECT temp.assignor_and_assignee_id, IF(aaa.representative_id <> '', r.representative_name, aaa.name) AS lendorName, COUNT(appno_doc_num) AS app FROM (
                            SELECT aor.assignor_and_assignee_id, d.appno_doc_num FROM ".$dbUSPTO.".documentid AS d 
                            JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id
                            INNER JOIN ".$dbUSPTO.".assignor AS aor ON aor.rf_id = d.rf_id 
                            INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = d.rf_id 
                            INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = aor.rf_id  
                            WHERE ass.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") 
                            AND d.appno_doc_num IN (".$implodeAssetsList.")  AND date_format(d.appno_date, '%Y') > ".$year." AND rac.convey_ty = 'release' AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID."
                            GROUP BY aor.assignor_and_assignee_id, d.appno_doc_num
                        ) AS temp 
                        INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = temp.assignor_and_assignee_id 
                        LEFT JOIN ".$dbUSPTO.".representative AS r ON r.representative_id = aaa.representative_id 
                        GROUP BY temp.appno_doc_num, temp.assignor_and_assignee_id
                    ) AS temp1 
                    GROUP BY lendorName 
                        UNION
                    SELECT lendorName, SUM(app) AS appNo FROM (
                        SELECT temp.assignor_and_assignee_id, IF(aaa.representative_id <> '', r.representative_name, aaa.name) AS lendorName, COUNT(appno_doc_num) AS app FROM (
                            SELECT ass.assignor_and_assignee_id, d.appno_doc_num FROM ".$dbUSPTO.".documentid AS d 
                            JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id
                            INNER JOIN ".$dbUSPTO.".assignor AS aor ON aor.rf_id = d.rf_id 
                            INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = d.rf_id 
                            INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = aor.rf_id  
                            WHERE aor.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") 
                            AND d.appno_doc_num IN (".$implodeAssetsList.") AND date_format(d.appno_date, '%Y') > ".$year."  AND rac.convey_ty IN ('security', 'restatedsecurity') AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID."
                            GROUP BY ass.assignor_and_assignee_id, d.appno_doc_num
                        ) AS temp 
                        INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = temp.assignor_and_assignee_id 
                        LEFT JOIN ".$dbUSPTO.".representative AS r ON r.representative_id = aaa.representative_id 
                        GROUP BY temp.appno_doc_num, temp.assignor_and_assignee_id
                    ) AS temp1 
                    GROUP BY lendorName"; */
                    $queryLenders = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT  ".$organisationID.", ".$companyID.", aor.assignor_and_assignee_id, ".$type.", '',  d.appno_doc_num, 0, 0 FROM ".$dbUSPTO.".documentid AS d 
                            JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id
                            INNER JOIN ".$dbUSPTO.".assignor AS aor ON aor.rf_id = d.rf_id 
                            INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = d.rf_id 
                            INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = aor.rf_id  
                            WHERE ass.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") 
                            AND d.appno_doc_num IN (".$implodeAssetsList.")  AND date_format(d.appno_date, '%Y') > ".$year." AND rac.convey_ty = 'release' AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID."
                            GROUP BY aor.assignor_and_assignee_id, d.appno_doc_num                        
                            UNION
                            SELECT ".$organisationID.", ".$companyID.", ass.assignor_and_assignee_id, ".$type.", '',  d.appno_doc_num, 0, 0  FROM ".$dbUSPTO.".documentid AS d 
                            JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id
                            INNER JOIN ".$dbUSPTO.".assignor AS aor ON aor.rf_id = d.rf_id 
                            INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = d.rf_id 
                            INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = aor.rf_id  
                            WHERE aor.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") 
                            AND d.appno_doc_num IN (".$implodeAssetsList.") AND date_format(d.appno_date, '%Y') > ".$year."  AND rac.convey_ty IN ('security', 'restatedsecurity') AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID."
                            GROUP BY ass.assignor_and_assignee_id, d.appno_doc_num";

                    $con->query($queryLenders);
                    
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
                        WHERE appno_doc_num IN (".$implodeAssetsList.")
                        GROUP BY rf_id                                
                    ) as temp1) AS total FROM (
                        SELECT apt.recorded_assignor_and_assignee_id, MAX(appno_doc_num) AS appno, MAX(appno_date) AS appnoDt, MAX(grant_doc_num) AS grantNo, MAX(grant_date) AS grantDt,  rac.rf_id, aaa.name AS name,
                                            (SELECT representative_name FROM db_uspto.representative WHERE representative_id = aaa.representative_id) AS representative_name  FROM db_new_application.activity_parties_transactions AS apt
                        INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = apt.rf_id
                        INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id 
                        INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = rac.rf_id 
                        INNER JOIN db_uspto.conveyance AS con ON con.convey_name = rac.convey_ty AND con.is_ota = 1 
                        INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = ass.assignor_and_assignee_id
                        WHERE apt.company_id = ".$companyID." AND apt.organisation_id = ".$organisationID." AND appno_doc_num IN (".$implodeAssetsList.")
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
                    WHERE d.appno_doc_num IN (".$implodeAssetsList.") AND aor.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).")                  
                    GROUP BY d.appno_doc_num";
                    
                    $con->query($queryEncumbrances);


                    /**
                     * Wrong Addresses
                     */
                    if(count($companyAddress) > 0) {
                        $type = 19;
                        $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
                        $findCompanyAddress = array();
                        foreach($companyAddress as $address) {
                            if((int)$address->representative_id === (int)$companyID){
                                array_push($findCompanyAddress, $address);
                            }
                        }
                        if(count($findCompanyAddress) > 0) {
                            $queryTransactionAddress = "SELECT ass.rf_id, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country FROM db_new_application.activity_parties_transactions AS apt INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = apt.rf_id INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = ass.rf_id WHERE apt.organisation_id = ".$organisationID. " AND apt.company_id IN (".$companyID.") AND ass.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") AND doc.appno_doc_num IN (".$implodeAssetsList.") AND apt.activity_id IN (1, 6, 10) GROUP BY ass.rf_id";

                            

                            $resultTransactionAddress = $con->query($queryTransactionAddress);

                            $totalTransactionAddress = 0;
                            if($resultTransactionAddress && $resultTransactionAddress->num_rows > 0) {
                                $totalTransactionAddress = $resultTransactionAddress->num_rows ;
                                $wrongAddressCount = array();
                                while($transactionAddress = $resultTransactionAddress->fetch_object()) {
                                    $address1 = removeDoubleSpace( $transactionAddress->ee_address_1 );
                                    $address1 = strReplace( $address1 );
                                    $address2 = removeDoubleSpace( $transactionAddress->ee_address_2 );
                                    $address2 = strReplace( $address2 );
                                    if($address1 === '' && $address2 === ''){
                                        array_push($wrongAddressCount, $transactionAddress->rf_id);
                                    } else {
                                        $matched = false;
                                        foreach($findCompanyAddress as $address) {
                                            $streetAddress = removeDoubleSpace( $address->street_address );
                                            $streetAddress = strReplace( $streetAddress );
                                            $match_results = get_similar_documents(strtolower($address1), array(strtolower($streetAddress)));
                                            if(count($match_results) == 0) {
                                                $match_results = get_similar_documents(strtolower($address2), array(strtolower($streetAddress)));
                                            }
                                            if(count($match_results) > 0) {
                                                if($transactionAddress->ee_city != '' && strtolower($transactionAddress->ee_city) == strtolower($address->city)) {
                                                    if($transactionAddress->ee_postcode == $address->zip_code){
                                                        $matched = true;
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                        if($matched === false) {
                                            array_push($wrongAddressCount, $transactionAddress->rf_id);
                                        }
                                    }
                                }

                                if(count($wrongAddressCount) > 0) {
                                    $queryWrongAddress = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, aor.assignor_and_assignee_id, ".$type.", '', '', ass.rf_id, ".$totalTransactionAddress."  FROM db_uspto.assignee AS ass 
                                    INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = ass.rf_id 
                                    WHERE ass.rf_id IN (".implode(',', $wrongAddressCount).")                  
                                    GROUP BY d.appno_doc_num";
                                    echo $queryWrongAddress;
                                    $con->query($queryWrongAddress);
                                }
                            }
                        }
                    }



                    

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
                    AND tawb.appno_doc_num IN (".$implodeAssetsList.")
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
                        WHERE doc.appno_doc_num IN (".$implodeAssetsList.")
                        GROUP BY apt.rf_id                                
                    ) as temp1) AS total FROM ( SELECT rac.rf_id AS rf_id, 0 AS assignor_id
                    FROM ".$dbApplication.".activity_parties_transactions AS apt 
                    INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id
                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id
                    WHERE apt.company_id = ".$companyID." 
                    AND apt.organisation_id = ".$organisationID." 
                    AND doc.appno_doc_num IN (".$implodeAssetsList.")
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
                        WHERE appno_doc_num IN (".$implodeAssetsList.")
                        GROUP BY rf_id                                
                    ) as temp1) AS total FROM (SELECT temp_exec_dt.rf_id, DATEDIFF(ass.record_dt, temp_exec_dt.exec_dt) AS noOfDays   
                    FROM db_new_application.assets as tawb
                    INNER JOIN (
                        SELECT appno_doc_num, rf_id FROM db_uspto.documentid
                        WHERE appno_doc_num IN (".$implodeAssetsList.")
                        GROUP BY appno_doc_num, rf_id
                    ) AS doc ON doc.appno_doc_num = tawb.appno_doc_num
                    INNER JOIN db_uspto.assignment AS ass ON ass.rf_id = doc.rf_id
                    INNER JOIN LATERAL (
                        SELECT aor.rf_id, aor.exec_dt FROM db_uspto.assignor AS aor
                        INNER JOIN (
                            SELECT appno_doc_num, rf_id FROM db_uspto.documentid
                            WHERE appno_doc_num IN (".$implodeAssetsList.")
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