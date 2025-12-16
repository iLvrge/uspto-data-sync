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
                    $queryRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 0"; 
                    
                    if($company != "") {
                        $queryRepresentative .= " AND representative_id = ".$company;
                    } else {
                        $queryRepresentative .= " AND parent_id = 0 ";
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

                    if($company == "") {
                        $queryRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 1 AND parent_id = 0";	
                        $resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
				
                        if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {					
                            while($getGroup = $resultRepresentativeParentCompany->fetch_object()) {
                                $queryGroupRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 0 AND parent_id = ".$getGroup->representative_id;
                                
                                $resultRepresentativeGroupParentCompany = $orgConnect->query($queryGroupRepresentative);
                                if($resultRepresentativeGroupParentCompany && $resultRepresentativeGroupParentCompany->num_rows > 0) {
                                    
                                    while($getCompanyRow = $resultRepresentativeGroupParentCompany->fetch_object()) {
                                        array_push($companiesData, array('representative_id'=>$getCompanyRow->representative_id, 'name'=>$getCompanyRow->representative_name));
                                    }
                                }
                            }
                        }
                    }

                    if(count($companiesData) == 0 && $company != "") {
                        $queryGroupRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 1 AND parent_id = 0 AND representative_id = ".$company; 
                        
                        $resultGroupRepresentative = $orgConnect->query($queryGroupRepresentative);	
                        if($resultGroupRepresentative && $resultGroupRepresentative->num_rows > 0) {
                            while($representativeGroup = $resultGroupRepresentative->fetch_object()){

                                $queryRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 0 AND parent_id = ".$representativeGroup->representative_id; 
                               
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
                }
            }
        }
       
        if(count($companiesData) > 0) {
            
            /**
             * Security End Date
             */
            $time = new DateTime('now');
            $year =  $time->modify('-24 year')->format('Y'); 
            $time = new DateTime('now');
            $YEAR =   $time->modify('-21 year')->format('Y'); 
            $releaseIDs = array();

            $queryReleaseIDs = "SELECT apt.release_rf_id AS rf_id, FROM ".$dbApplication.".activity_parties_transactions AS apt WHERE  apt.activity_id IN (5,12) AND date_format(apt.exec_dt, '%Y') > ".$year." AND release_rf_id <> '' AND release_rf_id <> 0 GROUP BY apt.release_rf_id";

            $resultReleaseIDs = $con->query($queryReleaseIDs);
            if($resultReleaseIDs && $resultReleaseIDs->num_rows > 0) {
                while($rowReleaseIDs = $resultReleaseIDs->fetch_object()){
                    array_push($releaseIDs, (int)$rowReleaseIDs->rf_id);
                }
            }

            $abandonedStatus = array(
                'Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                'Provisional Application Expired', 
                'Final Rejection Mailed', 
                'Expressly Abandoned  --  During Publication Process', 
                'Expressly Abandoned  --  During Examination', 
                'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                'Abandoned  --  Failure to Pay Issue Fee', 
                'Abandoned  --  File-Wrapper-Continuation Parent Application',
                'Abandoned  --  Failure to Respond to an Office Action',  
                'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                'Abandoned  --  Incomplete Application (Pre-examination)', 
                'Abandonment for Failure to Correct Drawings/Oath/NonPub Request');

               

            foreach($companiesData as $company) {	
                $companyID = $company['representative_id'];

                
                $companyAllAssignorAndAssigneeIDs = array();
                $listAllAssets = array();
                $queryFindCompanyRepresentative = "SELECT representative_id FROM ".$dbUSPTO.".representative WHERE representative_name = '".$con->real_escape_string($company['name'])."' ORDER BY representative_id DESC LIMIT 1";
                echo $queryFindCompanyRepresentative;
                $resultCompanyRepresentative = $con->query($queryFindCompanyRepresentative);	
                $representativeID = 0;
                if($resultCompanyRepresentative->num_rows > 0) {
                    $representativeRow = $resultCompanyRepresentative->fetch_object();
                    $representativeID = $representativeRow->representative_id;
                }

                $allCompanyNames = array();

                $queryAssignorAndAssigneeIDs = "SELECT assignor_and_assignee_id, name FROM ".$dbUSPTO.".assignor_and_assignee WHERE name = '".$con->real_escape_string($company['name'])."' ";

                if($representativeID > 0) {
                    $queryAssignorAndAssigneeIDs .= "  OR representative_id = ".$representativeID." GROUP BY assignor_and_assignee_id";
                }
                echo  $queryAssignorAndAssigneeIDs;
                $resultCompanyAssignorAndAssigneeIDs = $con->query($queryAssignorAndAssigneeIDs);	
                $companyAssignorAndAssigneeIDs = array();
                if($resultCompanyAssignorAndAssigneeIDs->num_rows > 0) {
                    while($companyAssignorAssigneeRow = $resultCompanyAssignorAndAssigneeIDs->fetch_object()) {
                        array_push($companyAssignorAndAssigneeIDs, $companyAssignorAssigneeRow->assignor_and_assignee_id);
                        array_push($allCompanyNames, '"'.$con->real_escape_string($companyAssignorAssigneeRow->name).'"');
                    }
                }

                $applicantsIDs = [];

                $queryApplicant = "SELECT assignor_and_assignee_id FROM db_patent_application_bibliographic.assignor_and_assignee WHERE name = '".$con->real_escape_string($company['name'])."' ";

                if($representativeID > 0) {
                    $queryApplicant .= "  OR representative_id = ".$representativeID." GROUP BY assignor_and_assignee_id";
                }
                echo $queryApplicant;
                $resultApplicantAssignorAndAssigneeIDs = $con->query($queryApplicant);	
                $applicantAssignorAndAssigneeIDs = array();
                if($resultApplicantAssignorAndAssigneeIDs->num_rows > 0) {
                    while($ApplicantAssignorAssigneeRow = $resultApplicantAssignorAndAssigneeIDs->fetch_object()) {
                        array_push($applicantAssignorAndAssigneeIDs, $ApplicantAssignorAssigneeRow->assignor_and_assignee_id);
                    }
                }

                $applicantAssets = [];
                $originalApplicantAssets = [];
                if(count($applicantAssignorAndAssigneeIDs) > 0) {
                    $queryApplicantAssets = "SELECT appno_doc_num FROM ( SELECT appno_doc_num  FROM db_patent_grant_bibliographic.applicant 
                    WHERE assignor_and_assignee_id IN (".implode(',', $applicantAssignorAndAssigneeIDs).")
                    UNION 
                    SELECT appno_doc_num FROM db_patent_application_bibliographic.applicant 
                    WHERE assignor_and_assignee_id IN (".implode(',', $applicantAssignorAndAssigneeIDs).")
                    UNION
                    SELECT appno_doc_num  FROM db_patent_grant_bibliographic.assignee 
                    WHERE assignor_and_assignee_id IN (".implode(',', $applicantAssignorAndAssigneeIDs).")
                    UNION
                    SELECT appno_doc_num FROM db_patent_application_bibliographic.assignee 
                    WHERE assignor_and_assignee_id IN (".implode(',', $applicantAssignorAndAssigneeIDs).")
                    ) AS temp GROUP BY appno_doc_num
                    UNION 
                    SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND appno_doc_num IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 10 ) AND recorded_assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).")) GROUP BY appno_doc_num) GROUP BY appno_doc_num";

                   /*  echo $queryApplicantAssets = "SELECT appno_doc_num FROM ( SELECT appno_doc_num  FROM db_patent_grant_bibliographic.applicant 
                    WHERE assignor_and_assignee_id IN (".implode(',', $applicantAssignorAndAssigneeIDs).")
                    UNION 
                    SELECT appno_doc_num FROM db_patent_application_bibliographic.applicant 
                    WHERE assignor_and_assignee_id IN (".implode(',', $applicantAssignorAndAssigneeIDs).")
                    ) AS temp GROUP BY appno_doc_num"; */
                    echo $queryApplicantAssets;
                    $resultApplicantAssetsList = $con->query($queryApplicantAssets);
                    if($resultApplicantAssetsList && $resultApplicantAssetsList->num_rows > 0) {
                        while($rowAsset = $resultApplicantAssetsList->fetch_object()) {
                            array_push($applicantAssets, '"'.$rowAsset->appno_doc_num.'"');
                        }
                    } 
                }

                $originalApplicantAssets = $applicantAssets;
               
                //print_r($companyAssignorAndAssigneeIDs);
                echo "COMPANYID: ".$companyID."<br/>";
                /*$queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbApplication.".assets WHERE company_id = ".$companyID." AND date_format(assets.appno_date, '%Y') > 1999 AND organisation_id = ".$organisationID." AND assets.layout_id = 15 GROUP BY appno_doc_num";*/
                /**
                 * 
                 * Find company OTA Assets minus Sold Assets AND merger Out
                 */
                $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND appno_doc_num IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 1, 6 ) AND recorded_assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";

                echo $queryAllAssetsList;
                
                $resultAllAssetsList = $con->query($queryAllAssetsList);
                if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                    while($rowAsset = $resultAllAssetsList->fetch_object()) {
                        array_push($listAllAssets, '"'.$rowAsset->appno_doc_num.'"');
                    }
                } 

                $originalList = $listAllAssets;

               
               $companyAllAssets = array();

               $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND appno_doc_num IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID."  AND recorded_assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";

                echo $queryAllAssetsList;
                
                $resultAllAssetsList = $con->query($queryAllAssetsList);
                if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                    while($rowAsset = $resultAllAssetsList->fetch_object()) {
                        array_push($companyAllAssets, '"'.$rowAsset->appno_doc_num.'"');
                    }
                } 
                
                
                

                /**
                 * 
                 */

                $soldAssets = [];

                $querySoldAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 2, 7 ) ) GROUP BY appno_doc_num";

                
                
                $resultSoldAssetsList = $con->query($querySoldAssetsList);
                if($resultSoldAssetsList && $resultSoldAssetsList->num_rows > 0) {
                    while($rowAsset = $resultSoldAssetsList->fetch_object()) {
                        array_push($soldAssets, '"'.$rowAsset->appno_doc_num.'"');
                    }
                } 

                
                if(count($soldAssets) > 0) {
                    $listAllAssets = array_diff($listAllAssets, $soldAssets);

                    $applicantAssets = array_diff($applicantAssets, $soldAssets);
                }
               

  
                $ownedAfterSold = $listAllAssets ;
                $expiredAssets = array();
                $onlyExpiredAssets = array();
                $currentDate = new DateTime('now');
                if(count($listAllAssets) > 0 || count($applicantAssets) > 0) {
                    $designAssetsFirstPart = array();
                    $designAssetsSecondPart = array();
                    $expiredAssets = array(); 
                    $grantApplications = array();
                    if(count($listAllAssets) > 0) {
                        $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid AS doc  WHERE appno_doc_num IN ( ".implode(',', $listAllAssets).")  AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num";
    
    
                        //$listAllAssets = array();
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) { 
                                array_push($listAllAssets, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 
    
                        $listAllAssets = array_unique($listAllAssets);
                        /**
                         * ApplicationTypeCategory : Design
                         * in PED XMLS
                         */
    
                        
    
                        $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid AS doc  WHERE appno_doc_num IN ( ".implode(',', $listAllAssets).") AND grant_doc_num LIKE 'd%' AND date_format(grant_date, '%Y-%m-%d') < '2015-05-13' AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num"; 
                         
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
                                array_push($designAssetsFirstPart, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 
    
                        $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid AS doc  WHERE appno_doc_num IN ( ".implode(',', $listAllAssets).") AND grant_doc_num LIKE 'd%' AND date_format(grant_date, '%Y-%m-%d') >= '2015-05-13' AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num"; 
                         
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
                                array_push($designAssetsSecondPart, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 

                        $findPatentsAssets = "SELECT MAX(appno_doc_num) AS appno_doc_num FROM db_uspto.documentid WHERE grant_doc_num <> '' AND grant_doc_num <> '' AND appno_doc_num IN (".implode(',', $listAllAssets).") GROUP BY appno_doc_num ";


                        $resultGrantApplications = $con->query($findPatentsAssets); 
                        if($resultGrantApplications && $resultGrantApplications->num_rows > 0) {
                            while($rowAsset = $resultGrantApplications->fetch_object()) {
                                array_push($grantApplications, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        }
                    }

                    
 
                    if(count($grantApplications) > 0) {
                        $queryExpiredStatusAssets = "SELECT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362')  AND appno_doc_num IN (".implode(',', $grantApplications).") GROUP BY appno_doc_num ";

                    
                        $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                array_push($expiredAssets, '"'.$rowAsset->application.'"');
                            }
                        }
                    }


                    if(count($listAllAssets) > 0) {
                        $queryExpiredStatusAssets = "SELECT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                        'Provisional Application Expired', 
                        'Final Rejection Mailed', 
                        'Expressly Abandoned  --  During Publication Process', 
                        'Expressly Abandoned  --  During Examination', 
                        'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                        'Abandoned  --  Failure to Pay Issue Fee', 
                        'Abandoned  --  File-Wrapper-Continuation Parent Application',
                        'Abandoned  --  Failure to Respond to an Office Action',  
                        'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                        'Abandoned  --  Incomplete Application (Pre-examination)', 
                        
                        'Abandonment for Failure to Correct Drawings/Oath/NonPub Request')  AND appno_doc_num IN (".implode(',', $listAllAssets).") ";
                        
                        if(count($grantApplications) > 0) {
                            $queryExpiredStatusAssets .= " AND appno_doc_num NOT IN (".implode(',', $grantApplications).") ";
                        }
                        
                        $queryExpiredStatusAssets .= " GROUP BY appno_doc_num ";

                        echo $queryExpiredStatusAssets;
                        
                        //die;

                        
                        $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                array_push($expiredAssets, '"'.$rowAsset->application.'"');
                            }
                        }
                    }

                    /**
                     * From Maintainence
                     */
                    $allAssets = array_merge($listAllAssets, $applicantAssets);
                    $queryExpiredMaintainenceAssets = "SELECT appno_doc_num AS application FROM db_patent_maintainence_fee.event_maintainence_fees  WHERE event_code IN ('EXP', 'EXP.')  AND appno_doc_num IN (".implode(',', $allAssets).") GROUP BY appno_doc_num ";

                    
                    $resultAllExpiredAssetsList = $con->query($queryExpiredMaintainenceAssets); 
                    if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                        while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                            array_push($expiredAssets, '"'.$rowAsset->application.'"');
                        }
                    }

                    

                    $queryExpiredDateAssets = "SELECT application, yearDiffer FROM ( SELECT application, IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer FROM ( SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_grant_bibliographic.application_publication AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci WHERE ap.appno_doc_num IN (".implode(',', $allAssets).") GROUP BY ap.appno_doc_num) AS temp GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci WHERE ap.appno_doc_num IN (".implode(',', $allAssets).") GROUP BY ap.appno_doc_num) AS temp1 GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_uspto.documentid AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num  = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $allAssets).") ";  

                    if(count($designAssetsFirstPart) > 0) {
                        $queryExpiredDateAssets .= " AND ap.appno_doc_num NOT IN (".implode(',', $designAssetsFirstPart).") ";
                    }

                    if(count($designAssetsSecondPart) > 0) {
                        $queryExpiredDateAssets .= " AND ap.appno_doc_num NOT IN (".implode(',', $designAssetsSecondPart).") ";
                    }
                    
                    
                    $queryExpiredDateAssets .= " GROUP BY ap.appno_doc_num) AS temp2 GROUP BY application ) AS temp3 ) AS temp4 GROUP BY application HAVING yearDiffer > 19 ";

                    
                     
                    /* $queryExpiredDateAssets = "SELECT application FROM ( SELECT appno_doc_num AS application, timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer FROM db_patent_grant_bibliographic.application_publication AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $listAllAssets).") GROUP BY appno_doc_num HAVING yearDiffer > 19 UNION SELECT appno_doc_num AS application, timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $listAllAssets).") GROUP BY appno_doc_num HAVING yearDiffer > 19  UNION SELECT MAX(doc.appno_doc_num) AS application, timestampdiff(YEAR, doc.appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer FROM db_uspto.documentid AS doc  AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = doc.appno_doc_num  WHERE doc.appno_doc_num IN (".implode(',', $listAllAssets).") GROUP BY appno_doc_num HAVING yearDiffer > 19) AS temp GROUP BY application"; */ 
                    $resultAllExpiredAssetsList = $con->query($queryExpiredDateAssets); 
                    if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                        while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                            array_push($expiredAssets, '"'.$rowAsset->application.'"');
                            /* $appLicationNo = '"'.$rowAsset->application.'"'; 
                            if(!in_array($appLicationNo, $expiredAssets)) { 
                                array_push($expiredAssets, $appLicationNo);
                            } */
                        }
                    }
                    $expiredAssets = array_unique($expiredAssets);
                   /*  echo "Query";
                    print_r($queryExpiredDateAssets);
                    print_r($expiredAssets);
                    die; */


                    if(count($applicantAssets) > 0) {

                        $queryAllAssetsList = "SELECT appno_doc_num FROM db_patent_application_bibliographic.application_grant AS doc  WHERE appno_doc_num IN ( ".implode(',', $applicantAssets).") AND grant_doc_num LIKE 'D%' AND date_format(grant_date, '%Y-%m-%d') < '2015-05-13' AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num"; 
                         
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
                                array_push($designAssetsFirstPart, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 
    
                        $queryAllAssetsList = "SELECT appno_doc_num FROM db_patent_application_bibliographic.application_grant AS doc  WHERE appno_doc_num IN ( ".implode(',', $applicantAssets).") AND grant_doc_num LIKE 'D%' AND date_format(grant_date, '%Y-%m-%d') >= '2015-05-13' AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num"; 
                         
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
                                array_push($designAssetsSecondPart, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 

                    }



                    /**
                     * Design Assets
                     * Expired in 14 years if the data before May 13, 2015 otherwise its 15 years
                     */
                    if(count($designAssetsFirstPart) > 0) {
                        $queryExpiredDateDesignAssets = "SELECT application, yearDiffer FROM ( SELECT application, IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer FROM ( SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, grant_date, extendedDate), timestampdiff(YEAR, grant_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, grant_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(grant_date, INTERVAL 14 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci WHERE ap.appno_doc_num IN (".implode(',', $designAssetsFirstPart).") GROUP BY ap.appno_doc_num) AS temp1 GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, grant_date, extendedDate), timestampdiff(YEAR, grant_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, grant_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(grant_date, INTERVAL 14 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_uspto.documentid AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $designAssetsFirstPart).") GROUP BY ap.appno_doc_num) AS temp2 GROUP BY application ) AS temp3 ) AS temp4 GROUP BY application HAVING yearDiffer >= 13 ";
                        echo $queryExpiredDateDesignAssets;
                        //die;
                        $resultAllExpiredAssetsList = $con->query($queryExpiredDateDesignAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                $appLicationNo = '"'.$rowAsset->application.'"'; 
                                if(!in_array($appLicationNo, $expiredAssets)) { 
                                    array_push($expiredAssets, $appLicationNo);
                                }
                            }
                        }
                    } 
                    if(count($designAssetsSecondPart) > 0) {
                        $queryExpiredDateDesignAssets = "SELECT application, yearDiffer FROM ( SELECT application, IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer FROM ( SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, grant_date, extendedDate), timestampdiff(YEAR, grant_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, grant_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(grant_date, INTERVAL 15 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci WHERE ap.appno_doc_num IN (".implode(',', $designAssetsSecondPart).") GROUP BY ap.appno_doc_num) AS temp1 GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, grant_date, extendedDate), timestampdiff(YEAR, grant_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, grant_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(grant_date, INTERVAL 15 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_uspto.documentid AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $designAssetsSecondPart).") GROUP BY ap.appno_doc_num) AS temp2 GROUP BY application ) AS temp3 ) AS temp4 GROUP BY application HAVING yearDiffer >= 14 ";

                        $resultAllExpiredAssetsList = $con->query($queryExpiredDateDesignAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                $appLicationNo = '"'.$rowAsset->application.'"'; 
                                if(!in_array($appLicationNo, $expiredAssets)) { 
                                    array_push($expiredAssets, $appLicationNo);
                                }
                            }
                        }
                    } 

                    $grantApplicantApplications = array();

                    if(count($applicantAssets) > 0) {

                        $findApplicantPatentsAssets = "SELECT MAX(appno_doc_num) AS appno_doc_num FROM db_patent_application_bibliographic.application_grant WHERE grant_doc_num <> '' AND grant_doc_num <> '' AND appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num ";
    
                        
                        $resultApplicantGrantApplications = $con->query($findApplicantPatentsAssets); 
                        if($resultApplicantGrantApplications && $resultApplicantGrantApplications->num_rows > 0) {
                            while($rowAsset = $resultApplicantGrantApplications->fetch_object()) {
                                array_push($grantApplicantApplications, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        }
    
                        if(count($grantApplicantApplications) > 0) {
                            $queryExpiredStatusAssets = "SELECT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362')  AND appno_doc_num IN (".implode(',', $grantApplicantApplications).") GROUP BY appno_doc_num ";
    
                        
                            $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                            if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                                while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                    array_push($expiredAssets, '"'.$rowAsset->application.'"');
                                }
                            }
                        }

                        $queryExpiredStatusAssets = "SELECT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                        'Provisional Application Expired', 
                        'Final Rejection Mailed', 
                        'Expressly Abandoned  --  During Publication Process', 
                        'Expressly Abandoned  --  During Examination', 
                        'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                        'Abandoned  --  Failure to Pay Issue Fee', 
                        'Abandoned  --  File-Wrapper-Continuation Parent Application',
                        'Abandoned  --  Failure to Respond to an Office Action',  
                        'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                        'Abandoned  --  Incomplete Application (Pre-examination)', 
                        
                        'Abandonment for Failure to Correct Drawings/Oath/NonPub Request')  AND appno_doc_num IN (".implode(',', $applicantAssets).") " ;
                        
                        if(count($grantApplicantApplications) > 0) {
                            $queryExpiredStatusAssets .= " AND appno_doc_num NOT IN (".implode(',', $grantApplicantApplications).") ";

                        }
                        
                        $queryExpiredStatusAssets .= " GROUP BY appno_doc_num ";

                         
                        $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                $appLicationNo = '"'.$rowAsset->application.'"'; 
                                if(!in_array($appLicationNo, $expiredAssets)) { 
                                    array_push($expiredAssets, $appLicationNo);
                                }
                            }
                        }

                        /* $queryExpiredDateAssets = "SELECT application FROM ( SELECT appno_doc_num AS application, timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer FROM db_patent_grant_bibliographic.application_publication AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num HAVING yearDiffer > 19 UNION SELECT appno_doc_num AS application, timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num HAVING yearDiffer > 19   UNION SELECT MAX(doc.appno_doc_num) AS application, timestampdiff(YEAR, doc.appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer FROM db_uspto.documentid AS doc  AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = doc.appno_doc_num  WHERE doc.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num HAVING yearDiffer > 19) AS temp GROUP BY application"; */

                        $queryExpiredDateAssets = "SELECT application, yearDiffer FROM ( SELECT application, IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer FROM ( SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_grant_bibliographic.application_publication AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY ap.appno_doc_num) AS temp GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY ap.appno_doc_num) AS temp1 GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_uspto.documentid AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY ap.appno_doc_num) AS temp2 GROUP BY application ) AS temp3 ) AS temp4 GROUP BY application HAVING yearDiffer > 19 ";
                        echo "Query";
                        
                        $resultAllExpiredAssetsList = $con->query($queryExpiredDateAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                $appLicationNo = '"'.$rowAsset->application.'"'; 
                                if(!in_array($appLicationNo, $expiredAssets)) { 
                                    array_push($expiredAssets, $appLicationNo);
                                }
                            }
                        }
                    }  
                    /* $queryExpiredAssets = "SELECT application FROM ( SELECT MAX(doc.appno_doc_num) AS application, doc.grant_doc_num AS patent, doc.appno_date, timestampdiff(YEAR, doc.appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer,  IF (temp_expired.status <> '', 1, 0) AS expiredStatus  FROM  db_uspto.documentid AS doc LEFT JOIN ( SELECT appno_doc_num, status FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                    'Provisional Application Expired', 
                    'Final Rejection Mailed', 
                    'Expressly Abandoned  --  During Publication Process', 
                    'Expressly Abandoned  --  During Examination', 
                    'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                    'Abandoned  --  Failure to Pay Issue Fee', 
                    'Abandoned  --  File-Wrapper-Continuation Parent Application',
                    'Abandoned  --  Failure to Respond to an Office Action',  
                    'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                    'Abandoned  --  Incomplete Application (Pre-examination)', 
                    
                    'Abandonment for Failure to Correct Drawings/Oath/NonPub Request')  AND appno_doc_num IN (".implode(',', $listAllAssets).") ) AS temp_expired ON temp_expired.appno_doc_num = doc.appno_doc_num  WHERE doc.appno_doc_num IN (".implode(',', $listAllAssets).")   GROUP BY  doc.appno_doc_num) AS temp WHERE yearDiffer > 19 OR expiredStatus = 1";
                    //echo $queryExpiredAssets ;
                    $resultAllExpiredAssetsList = $con->query($queryExpiredAssets);
                    $expiredAssets = array();
                    if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                        while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                            array_push($expiredAssets, '"'.$rowAsset->application.'"');
                        }
                    } */

                    if(count($expiredAssets) > 0) {
                        $queryExpiredWithDate = "INSERT IGNORE INTO ".$dbApplication.".assets_with_bank_expired_status(appno_doc_num, status_date, expire_date, company_id, organisation_id) SELECT appno_doc_num, MAX(status_date) AS sDate, MAX(expiry_date) AS exDate, ".$companyID.", ".$organisationID." FROM ( SELECT appno_doc_num, MAX(status_date) AS status_date, '' AS expiry_date FROM db_uspto.application_status WHERE appno_doc_num IN (".implode(',', $expiredAssets).")  GROUP BY appno_doc_num UNION ALL SELECT appno_doc_num, '' AS status_date, DATE_ADD(appno_date, INTERVAL 20 YEAR) AS expiry_date FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $expiredAssets).") GROUP BY appno_doc_num ) AS temp GROUP BY appno_doc_num";
                        //echo $queryExpiredWithDate;
                        $con->query($queryExpiredWithDate);
                    }
                }

                
               
                
                /*
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


                
                if(count($listAllAssets) > 0) {
                    $queryExpiredAssets = "SELECT appno_doc_num FROM ".$dbUSPTO.".application_status WHERE appno_doc_num IN (".implode(',', $listAllAssets).") AND (`status` LIKE '%expired%')";
                    //echo $queryExpiredAssets;
                    $resultExpiredAssetsList = $con->query($queryExpiredAssets);
                    if($resultExpiredAssetsList && $resultExpiredAssetsList->num_rows > 0) {
                        while($rowAsset = $resultExpiredAssetsList->fetch_object()) {
                            array_push($onlyExpiredAssets, '"'.$rowAsset->appno_doc_num.'"');
                        }
                    } 
                }
                */
                /**
                 * In assignment db acquired
                 */
                $allAssets = $originalList;

               
                if(count($expiredAssets) > 0 && count($listAllAssets) > 0) {
                    $listAllAssets = array_diff($listAllAssets, $expiredAssets);
                    $allAssets = array_diff($allAssets, $expiredAssets);
                    
                }

                if(count($expiredAssets) > 0 && count($applicantAssets) > 0) {
                    $applicantAssets = array_diff($applicantAssets, $expiredAssets);
                }

                

                echo "listAllAssets Assets";
                print_r($listAllAssets); 


                echo "SOLD: ";
                print_r($soldAssets);
                echo "EXPIRED: ";
                print_r($expiredAssets); 
                
                /**
                 * Asset Last Status 
                 */
                $patentedAssetsStatus = array();
                $employeeAssets = array();
                if(count($listAllAssets) > 0) {
                    
                    /* $queryPatentedAssets = "SELECT appno_doc_num FROM (SELECT appno_doc_num, status, MAX(status_date) FROM ".$dbUSPTO.".application_status WHERE appno_doc_num IN (".implode(',', $listAllAssets).") AND status = 'Patented Case' GROUP BY  appno_doc_num) AS temp GROUP BY  appno_doc_num";
                    //echo $queryExpiredAssets;
                    $resultPatentedAssetsList = $con->query($queryPatentedAssets);
                    if($resultPatentedAssetsList && $resultPatentedAssetsList->num_rows > 0) {
                        while($rowAsset = $resultPatentedAssetsList->fetch_object()) {
                            array_push($patentedAssetsStatus, '"'.$rowAsset->appno_doc_num.'"');
                        }
                    }  */

                    //Remove Employee Assets 
                    /* echo $queryAllEmployeeAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND appno_doc_num IN (".implode(',', $listAllAssets).")  AND appno_doc_num NOT IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 10 ) ) GROUP BY appno_doc_num) GROUP BY appno_doc_num"; */

                    echo $queryAllEmployeeAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND appno_doc_num IN (".implode(',', $listAllAssets).")  AND appno_doc_num IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 10 ) ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";

                
                    
                    $resultAllEmployeeAssetsList = $con->query($queryAllEmployeeAssetsList);
                    if($resultAllEmployeeAssetsList && $resultAllEmployeeAssetsList->num_rows > 0) {
                        while($rowAsset = $resultAllEmployeeAssetsList->fetch_object()) {
                            array_push($employeeAssets, '"'.$rowAsset->appno_doc_num.'"');
                        }
                    } 
                } 

                echo "REmaining: ".count($listAllAssets); 
                echo implode(',', $listAllAssets);
                echo "Applicant: ";
                print_r($applicantAssets); 
                echo "Employee: ";
                print_r($employeeAssets);

               
                
                
                //echo "COUNT2: ".count($listAllAssets);

                //echo implode(',', $expiredAssets);

                $implodeAssetsList = implode(',', $listAllAssets);
                //$implodePatentedAssetsList = implode(',', $patentedAssetsStatus);

                if(count($employeeAssets) > 0 && count($listAllAssets) > 0) {
                    if(count($soldAssets) > 0 && count($employeeAssets) > 0) {
                        $employeeAssets = array_diff($employeeAssets, $soldAssets);
                    }
                    if(count($expiredAssets) > 0 && count($employeeAssets) > 0) {
                        $employeeAssets = array_diff($employeeAssets, $expiredAssets);
                    }  
                } 

                if(count($allAssets) > 0 && count($employeeAssets) > 0) {
                    $patentedAssetsStatus = array_diff($allAssets, $employeeAssets);
                } else {
                    $patentedAssetsStatus = $allAssets;
                }

                
                if(count($applicantAssets) > 0 && count($patentedAssetsStatus) > 0) {
                    $applicantAssets = array_diff($applicantAssets, $patentedAssetsStatus);
                }


               
 
                if(count($soldAssets) > 0 && count($patentedAssetsStatus) > 0) {
                    $patentedAssetsStatus = array_diff($patentedAssetsStatus, $soldAssets);
                }

                if(count($expiredAssets) > 0 && count($patentedAssetsStatus) > 0) {
                    $patentedAssetsStatus = array_diff($patentedAssetsStatus, $expiredAssets);
                }
                

                $implodePatentedAssetsList = implode(',', $patentedAssetsStatus); 

                //$implodePatentedAssetsList = implode(',', $listAllAssets);

               

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


                $currentDate = new DateTime('now');
                $FORMAT = 'Y-m-d';
                $graceEndDate = $currentDate->modify('+6 month')->format($FORMAT);
                $currentDate = new DateTime('now');
                $dueDate = $currentDate->modify('-6 month')->format($FORMAT);
                $currentDate = new DateTime('now');
                $formatCurrentDate = $currentDate->format($FORMAT);

                $queryEntityStatus = "SELECT MAX(entity_status) AS entity_status FROM db_patent_maintainence_fee.event_maintainence_fees WHERE appno_doc_num IN (".$implodeAssetsList.") LIMIT 1";

                $defaultEntityStatus = 'N';

                $resultEntityStatus = $con->query($queryEntityStatus);
                if($resultEntityStatus && $resultEntityStatus->num_rows > 0) {
                    $rowEntityStatus = $resultEntityStatus->fetch_object();
                    $defaultEntityStatus =  $rowEntityStatus->entity_status;
                } 

                echo "ASSSSSSSSS";

                echo count($originalList)."%".count($expiredAssets)."%".count($soldAssets)."%".count($applicantAssets)."%".count($patentedAssetsStatus).'%'.count($employeeAssets)."%".count($listAllAssets)."%".count($allAssets);
                
                $allTypes = [30,31,32,33,34,35,36,37,38,39,40,41,1,17,18,19,20,21,22,23,24,25,26,27];
                    
                    $deleteQuery = "DELETE FROM ".$dbApplication.".dashboard_items WHERE type IN (".implode(',', $allTypes).") AND representative_id = ".$companyID." AND organisation_id = ".$organisationID;

                    $con->query($deleteQuery);

                    $deleteQuery = "DELETE FROM ".$dbApplication.".dashboard_items_count WHERE type IN (".implode(',', $allTypes).") AND representative_id = ".$companyID." AND organisation_id = ".$organisationID;

                    $con->query($deleteQuery);
                    
                    

                    $con->query("DELETE FROM ".$dbApplication.".owned_assets WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);

                if(count($originalList) > 0  || count($expiredAssets) > 0 || count($soldAssets) > 0 || count($applicantAssets) > 0 ) {  

                   
                   
                
                    
                     /**
                     * Assets Acquired 
                     */
                    $type = 32;
                    $acquiredAcitivityID = implode(',', array(1,6));

                    /* $queryPatentAcquired = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", d.grant_doc_num, d.appno_doc_num, 0, 0 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE date_format(d.appno_date, '%Y') > ".$year." AND d.appno_doc_num IN (".$implodePatentedAssetsList.") AND apt.activity_id IN (".$acquiredAcitivityID.") AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID." GROUP BY d.appno_doc_num"; */
                    echo "AAA: ".count($patentedAssetsStatus)."asd";
                    if(count($patentedAssetsStatus) > 0) {
                        
                        echo $queryPatentAcquired = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", MAX(d.grant_doc_num), MAX(d.appno_doc_num), 0, 0 FROM ".$dbUSPTO.".documentid AS d WHERE date_format(d.appno_date, '%Y') > ".$YEAR." AND d.appno_doc_num IN (".$implodePatentedAssetsList.") GROUP BY d.appno_doc_num";

                        echo $queryPatentAcquired ; 
                    
                        $con->query($queryPatentAcquired); 
                    }  
                    

                    /**
                     * Filled Assets Applicant
                     */
                    
                    if(count($applicantAssets) > 0 || count($employeeAssets) > 0) {
                        $type = 31;
                        $applicantAndEmployee = array_merge($applicantAssets, $employeeAssets);  

                            $queryApplicantPatent = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", d.grant_doc_num, d.appno_doc_num, 0, 0 FROM db_patent_application_bibliographic.application_grant AS d  WHERE date_format(d.appno_date, '%Y') > ".$YEAR." AND d.appno_doc_num IN (".implode(',', $applicantAndEmployee).") ";

                            if(count($soldAssets) > 0) {
                                $queryApplicantPatent .= " AND d.appno_doc_num NOT IN (".implode(',', $soldAssets).") ";
                            }
    
                            if(count($expiredAssets) > 0) {
                                $queryApplicantPatent .= " AND d.appno_doc_num NOT IN (".implode(',', $expiredAssets).") ";
                            }
                            
                            
                            $queryApplicantPatent .= "GROUP BY d.appno_doc_num";
    
                            echo $queryApplicantPatent."<br/>";
                          
                            $con->query($queryApplicantPatent); 
                        
 
                            $queryEmployeePatent = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", d.grant_doc_num, d.appno_doc_num, 0, 0 FROM db_uspto.documentid AS d  WHERE date_format(d.appno_date, '%Y') > ".$YEAR." AND d.appno_doc_num IN (".implode(',', $applicantAndEmployee).") AND d.appno_doc_num NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = ".$type.")";

                            if(count($soldAssets) > 0) {
                                $queryEmployeePatent .= " AND d.appno_doc_num NOT IN (".implode(',', $soldAssets).") ";
                            }
    
                            if(count($expiredAssets) > 0) {
                                $queryEmployeePatent .= " AND d.appno_doc_num NOT IN (".implode(',', $expiredAssets).") ";
                            }
                            
                            
                            $queryEmployeePatent .= "GROUP BY d.appno_doc_num";
    
                            echo $queryEmployeePatent."<br/>";
                          
                            $con->query($queryEmployeePatent); 
                        
 
                            $queryApplicantApplication = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", '', d.appno_doc_num, 0, 0 FROM db_patent_grant_bibliographic.application_publication AS d  WHERE date_format(d.appno_date, '%Y') > ".$YEAR." AND d.appno_doc_num IN (".implode(',', $applicantAndEmployee).")  AND d.appno_doc_num NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = ".$type.")  ";

                            if(count($soldAssets) > 0) {
                                $queryApplicantApplication .= " AND d.appno_doc_num NOT IN (".implode(',', $soldAssets).") ";
                            }

                            if(count($expiredAssets) > 0) {
                                $queryApplicantApplication .= " AND d.appno_doc_num NOT IN (".implode(',', $expiredAssets).") ";
                            }
                            
                            $queryApplicantApplication .= " GROUP BY d.appno_doc_num";
                            echo $queryApplicantApplication."<br/>";
                            $con->query($queryApplicantApplication); 
                    } 
                    /**
                     * Owned Patents
                    */
                    $type = 30;
                    
                    $queryOwnedPatent = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", patent, application, 0, 0 FROM ".$dbApplication.".dashboard_items  WHERE type IN (31, 32) AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." GROUP BY application";

                    $con->query($queryOwnedPatent);


                    if(count($listAllAssets) > 0) {
                        $con->query("INSERT IGNORE INTO ".$dbApplication.".owned_assets(appno_doc_num, company_id, organisation_id) SELECT application, ".$companyID.", ".$organisationID." FROM ".$dbApplication.".dashboard_items WHERE   representative_id = ".$companyID." AND organisation_id = ".$organisationID." AND type = 30 GROUP BY application");
                    }
                    //exec('php -f /var/www/html/trash/add_collateralize.php '.$companyID.'  '.$organisationID);
                    
                    /**
                     * Assets Divested
                     */
                    if(count($soldAssets) > 0) {

                        $type = 33;
                        $querySoldAsset = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", MAX(d.grant_doc_num), MAX(d.appno_doc_num), 0, 0 FROM ".$dbUSPTO.".documentid AS d WHERE date_format(d.appno_date, '%Y') > ".$YEAR." AND d.appno_doc_num IN (".implode(',', $soldAssets).")  GROUP BY d.appno_doc_num";
                        $con->query($querySoldAsset);


                        


                        $querySoldAsset = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", MAX(d.grant_doc_num), MAX(d.appno_doc_num), 0, 0 FROM db_patent_application_bibliographic.application_grant AS d  WHERE date_format(d.appno_date, '%Y') > ".$YEAR." AND d.appno_doc_num IN (".implode(',', $soldAssets).") AND d.appno_doc_num NOT IN (SELECT application FROM db_new_application.dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = 33 GROUP BY application)  GROUP BY d.appno_doc_num";
                        $con->query($querySoldAsset);

                        $querySoldAsset = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", MAX(d.grant_doc_num), MAX(d.appno_doc_num), 0, 0 FROM db_patent_grant_bibliographic.application_publication AS d  WHERE date_format(d.appno_date, '%Y') > ".$YEAR." AND d.appno_doc_num IN (".implode(',', $soldAssets).") AND d.appno_doc_num NOT IN (SELECT application FROM db_new_application.dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = 33 GROUP BY application)  GROUP BY d.appno_doc_num";
                        $con->query($querySoldAsset);
                        
                        

                        /* $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND appno_doc_num IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 1, 6 ) ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";

                        $acquiredRawAssets = array();
                        $divestedAssets = array();
                        
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
                                array_push($acquiredRawAssets, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 

                        if(count($acquiredRawAssets) > 0) {
                            $querySoldAcquiredAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 2, 7 ) ) AND appno_doc_num IN (".implode(', ', $acquiredRawAssets).") GROUP BY appno_doc_num";

                            $resultSoldAssetsList = $con->query($querySoldAcquiredAssetsList);
                            if($resultSoldAssetsList && $resultSoldAssetsList->num_rows > 0) {
                                while($rowAsset = $resultSoldAssetsList->fetch_object()) {
                                    array_push($divestedAssets, '"'.$rowAsset->appno_doc_num.'"');
                                }
                            } 
                        }


                        if(count($originalApplicantAssets) > 0) {
                            $querySoldAcquiredAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 2, 7 ) ) AND appno_doc_num IN (".implode(', ', $originalApplicantAssets).") GROUP BY appno_doc_num";

                            $resultSoldAssetsList = $con->query($querySoldAcquiredAssetsList);
                            if($resultSoldAssetsList && $resultSoldAssetsList->num_rows > 0) {
                                while($rowAsset = $resultSoldAssetsList->fetch_object()) {
                                    array_push($divestedAssets, '"'.$rowAsset->appno_doc_num.'"');
                                }
                            } 
                        }

                        $divestedAssets = array_unique($divestedAssets);  */


                        
                    }


                    /**
                     * Collateralized
                     * Show all the assets which are not released yet
                     */
                    $type = 34;
                    $securityActivityID = implode(',', array(5, 12));
                    //$queryCollateralized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", d.grant_doc_num, d.appno_doc_num, 0, 0 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE date_format(d.appno_date, '%Y') > ".$year." AND apt.activity_id IN (".$securityActivityID.") AND (apt.release_rf_id IS NULL OR apt.release_rf_id  = 0 OR full_match = 0) AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." GROUP BY d.appno_doc_num";
                   

                    $clientOwnedQuery = "SELECT application FROM db_new_application.dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = 30 GROUP BY application";
                    
                    $clientOwnedAssets = array();
                    $resultClientOwned = $con->query($clientOwnedQuery);
                    if($resultClientOwned && $resultClientOwned->num_rows > 0) {
                        while($rowAsset = $resultClientOwned->fetch_object()) {
                            array_push($clientOwnedAssets, '"'.$rowAsset->application.'"');
                        }
                    } 

                    $seQuery = "SELECT tempSecurity.appno_doc_num FROM (SELECT appNo AS appno_doc_num, eeName, count(rf_id) AS counter, 'security' as type FROM (
                        Select doc.appno_doc_num AS appNo, ee.rf_id, IF(r.representative_id <> '', r.representative_name, aaa.name) AS eeName FROM assignee AS ee
                        INNER JOIN db_new_application.activity_parties_transactions AS apt ON apt.rf_id = ee.rf_id 
                        INNER JOIN documentid AS doc ON doc.rf_id = ee.rf_id
                        INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id
                        INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
                        LEFT JOIN representative AS r ON r.representative_id = aaa.representative_id
                        WHERE apt.organisation_id = ".$organisationID."   AND rac.convey_ty IN ('security', 'restatedsecurity') AND appno_doc_num IN (".implode(',', $clientOwnedAssets).")) AS temp GROUP BY appNo, eeName HAVING counter > 0 ) as tempSecurity 
                        LEFT JOIN (
                            SELECT appNo AS appno_doc_num, eeName, count(rf_id) AS counter FROM (
                                Select doc.appno_doc_num AS appNo, aor.rf_id, IF(r.representative_id <> '', r.representative_name, aaa.name) AS eeName FROM assignor AS aor
                                INNER JOIN db_new_application.activity_parties_transactions AS apt ON apt.rf_id = aor.rf_id 
                                INNER JOIN documentid AS doc ON doc.rf_id = aor.rf_id
                                INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id
                                INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id
                                LEFT JOIN representative AS r ON r.representative_id = aaa.representative_id
                                WHERE  apt.organisation_id = ".$organisationID."  AND rac.convey_ty IN ('release', 'partialrelease') AND appno_doc_num IN (".implode(',', $clientOwnedAssets).")
                            ) AS temp
                            GROUP BY appNo, eeName HAVING counter > 0
                        ) as tempRelease ON tempRelease.appno_doc_num = tempSecurity.appno_doc_num AND tempRelease.eeName = tempSecurity.eeName WHERE (tempRelease.eeName IS NULL AND tempSecurity.eeName <> '') OR (tempSecurity.counter > tempRelease.counter) GROUP BY tempSecurity.appno_doc_num";  
                    
                    echo "COLLATERALISATION";
                    echo $seQuery;
                    $resultseQuery = $con->query($seQuery) ;

                    $collaterializedAssets = array();
                    if($resultseQuery && $resultseQuery->num_rows > 0) {
                        while($row = $resultseQuery->fetch_object()) {
                            array_push($collaterializedAssets, '"'.$row->appno_doc_num.'"');
                        }
                    } 

                    print_r($collaterializedAssets);

                    if(count($collaterializedAssets) > 0) {
                        $queryCollateralized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", patent, application, 0, 0 FROM ( SELECT MAX(d.grant_doc_num) as patent, d.appno_doc_num as application FROM ".$dbUSPTO.".documentid AS d  WHERE date_format(d.appno_date, '%Y') > ".$YEAR." AND d.appno_doc_num IN (".implode(',', $collaterializedAssets).") GROUP BY d.appno_doc_num) AS temp GROUP BY application";
                        echo $queryCollateralized;
                        $con->query($queryCollateralized);

                        echo 'AFFECTED ROWS: '.$con->affected_rows;
                    } 

                    /* const maintainenanceHeading = document.querySelector('a[name="Patent%20Maintenance%20Fee"]')
                    const allFeesWithCodes = []
                    if(maintainenanceHeading != null) {
                        const maintainanceGrandParentNode = maintainenanceHeading.parentNode.parentNode.parentNode.parentNode
                        if(maintainanceGrandParentNode != null) {
                            const allFeesList = maintainanceGrandParentNode.nextSibling.querySelectorAll('tr')
                            if(allFeesList.length > 0) {
                                const fees = []
                                allFeesList.forEach((tr, index) => {
                                    const findAllCols = tr.querySelectorAll('td')
                                    if(findAllCols.length > 0) {
                                        const codesWithFees = {
                                            code: '',
                                            fees3: 0,
                                            fees4: 0,
                                            fees5: 0
                                        }
                                        findAllCols.forEach((td, index) => {
                                            if(index == 0) {
                                                codesWithFees.code = td.innerText
                                            } else if(index > 2) {
                                                codesWithFees[`fees${index}`] = td.innerText
                                            }
                                        })
                                        allFeesWithCodes.push(codesWithFees)
                                    }
                                })
                            }
                        }
                    } */
                    
                   
                    /**
                     *  Maintainance Budget
                     */
                    $type = 35;
                    /* $queryMaintainenceBudget = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, event_code, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", '', emf.appno_doc_num, 0, emf.event_code, CASE WHEN emf.event_code = 'M1554' THEN 500 WHEN emf.event_code = 'M2554' THEN 250 WHEN emf.event_code = 'M3554' THEN 125 WHEN emf.event_code = 'M1555' THEN 500 WHEN emf.event_code = 'M2555' THEN 250 WHEN emf.event_code = 'M3555' THEN 125  WHEN emf.event_code = 'M1556' THEN 500 WHEN emf.event_code = 'M2556' THEN 250 WHEN emf.event_code = 'M3556' THEN 125 WHEN emf.event_code = 'M1558' THEN 2100 WHEN emf.event_code = 'M2558' THEN 1050 WHEN emf.event_code = 'M3558' THEN 525  WHEN emf.event_code = 'M1551' THEN 2000 WHEN emf.event_code = 'M2551' THEN 1000 WHEN emf.event_code = 'M3551' THEN 500  WHEN emf.event_code = 'M1552' THEN 3760 WHEN emf.event_code = 'M2552' THEN 1880 WHEN emf.event_code = 'M3552' THEN 940  WHEN emf.event_code = 'M1553' THEN 7700 WHEN emf.event_code = 'M2553' THEN 3850 WHEN emf.event_code = 'M3553' THEN 1925  ELSE 0 END AS amount   
                    FROM db_patent_maintainence_fee.event_maintainence_fees AS emf 
                    WHERE emf.appno_doc_num IN (".$implodeAssetsList.") AND date_format(emf.filling_date, '%Y') > ".$year." 
                    AND emf.event_code IN ('M1551', 'M2551', 'M3551', 'M1552', 'M2552', 'M3552', 'M1553', 'M2553', 'M3553', 'M1554', 'M2554', 'M3554', 'M1555', 'M2555', 'M3555', 'M1556', 'M2556', 'M3556',  'M1558', 'M2558', 'M3558') GROUP BY emf.appno_doc_num, emf.event_code"; */

                    $currentDate = new DateTime('now');
                    $FORMAT = 'Y-m-d';
                    $graceEndDate = $currentDate->modify('+6 month')->format($FORMAT);
                    $currentDate = new DateTime('now');
                    $dueDate = $currentDate->modify('-6 month')->format($FORMAT);
                    $currentDate = new DateTime('now');
                    $formatCurrentDate = $currentDate->format($FORMAT);

                    $queryEntityStatus = "SELECT MAX(entity_status) AS entity_status FROM db_patent_maintainence_fee.event_maintainence_fees WHERE appno_doc_num IN (".$implodeAssetsList.") LIMIT 1";

                    $defaultEntityStatus = 'N';

                    /* $resultEntityStatus = $con->query($queryEntityStatus);
                    if($resultEntityStatus && $resultEntityStatus->num_rows > 0) {
                        $rowEntityStatus = $resultEntityStatus->fetch_object();
                        $defaultEntityStatus =  $rowEntityStatus->entity_status;
                    }  */


                    

                    
                    if(count($clientOwnedAssets) > 0) {
                        $implodeOwned = implode(',', $clientOwnedAssets);
                        $queryMaintainenceBudget = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, event_code, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, tempAll.event_code, emcf.fees_amount  FROM ( SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, CASE WHEN fees = '3.5' AND status = 'N' THEN 'M1551' WHEN fees = '3.5' AND status = 'Y' THEN 'M2551'  WHEN fees = '3.5' AND status = 'M' THEN 'M3551'  WHEN fees = '7.5' AND status = 'N' THEN 'M1552'  WHEN fees = '7.5' AND status = 'Y' THEN 'M2552'  WHEN fees = '7.5' AND status = 'M' THEN 'M3552'  WHEN fees = '11.5' AND status = 'N' THEN 'M1553'  WHEN fees = '11.5' AND status = 'Y' THEN 'M2553' WHEN fees = '11.5' AND status = 'M' THEN 'M3553' ELSE '' END AS event_code FROM (SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '3.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 42 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 54 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodeOwned.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$implodeOwned.") AND event_code IN ('M1551','M2551', 'M3551', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '7.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 90 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 102 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate  FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodeOwned.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$implodeOwned.") AND event_code IN ('M1552','M2552', 'M3552', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '11.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 138 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 150 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodeOwned.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$implodeOwned.") AND event_code IN ('M1553','M2553', 'M3553', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND  currentDate >= due_date AND currentDate <= grace_end_date ) AS temp1) AS tempAll INNER JOIN db_patent_maintainence_fee.event_maintainence_code_fees AS emcf ON emcf.event_code = tempAll.event_code";
                        //echo $queryMaintainenceBudget;
    
                        
                        $con->query($queryMaintainenceBudget);


                        $queryMaintainenceBudget = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, event_code, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, tempAll.event_code, emcf.fees_amount  FROM ( SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, CASE WHEN fees = '3.5' AND status = 'N' THEN 'M1551' WHEN fees = '3.5' AND status = 'Y' THEN 'M2551'  WHEN fees = '3.5' AND status = 'M' THEN 'M3551'  WHEN fees = '7.5' AND status = 'N' THEN 'M1552'  WHEN fees = '7.5' AND status = 'Y' THEN 'M2552'  WHEN fees = '7.5' AND status = 'M' THEN 'M3552'  WHEN fees = '11.5' AND status = 'N' THEN 'M1553'  WHEN fees = '11.5' AND status = 'Y' THEN 'M2553' WHEN fees = '11.5' AND status = 'M' THEN 'M3553' ELSE '' END AS event_code FROM (SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '3.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 42 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 54 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_patent_application_bibliographic.application_grant AS doc WHERE doc.appno_doc_num IN (".$implodeOwned.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$implodeOwned.") AND event_code IN ('M1551','M2551', 'M3551', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '7.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 90 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 102 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate  FROM db_patent_application_bibliographic.application_grant AS doc WHERE doc.appno_doc_num IN (".$implodeOwned.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$implodeOwned.") AND event_code IN ('M1552','M2552', 'M3552', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '11.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 138 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 150 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_patent_application_bibliographic.application_grant AS doc WHERE doc.appno_doc_num IN (".$implodeOwned.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$implodeOwned.") AND event_code IN ('M1553','M2553', 'M3553', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND  currentDate >= due_date AND currentDate <= grace_end_date ) AS temp1) AS tempAll INNER JOIN db_patent_maintainence_fee.event_maintainence_code_fees AS emcf ON emcf.event_code = tempAll.event_code WHERE appno_doc_num NOT IN (SELECT application FROM db_new_application.dashboard_items
                        where organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = ".$type.")";

                        
                        $con->query($queryMaintainenceBudget);



                        $con->query("DELETE FROM db_new_application.maintainence_assets WHERE organisation_id = ".$organisationID." AND company_id = ".$companyID);

                        $con->query("INSERT IGNORE INTO db_new_application.maintainence_assets(organisation_id, company_id, asset, asset_type, grant_doc_num, appno_doc_num, grant_date, payment_due, payment_grace, fee_code, fee_amount, type, fee_code_surcharge, fee_surcharge)
                        SELECT ".$organisationID.", ".$companyID.", grant_doc_num, 0, grant_doc_num, appno_doc_num, grant_date, due_date, grace_end_date, CASE WHEN tempAll.event_code = 'M1551' THEN 1551 WHEN tempAll.event_code = 'M2551' THEN 2551 WHEN tempAll.event_code = 'M3551' THEN 3551 WHEN tempAll.event_code = 'M1552' THEN 1552 WHEN tempAll.event_code = 'M2552' THEN 2552 WHEN tempAll.event_code = 'M3552' THEN 3552 WHEN tempAll.event_code = 'M1553' THEN 1553  WHEN tempAll.event_code = 'M2553' THEN 2553 WHEN tempAll.event_code = 'M3553' THEN 3553 ELSE 0 END, emcf.fees_amount, CASE WHEN tempAll.event_code = 'M1551' THEN 1 WHEN tempAll.event_code = 'M2551' THEN 1 WHEN tempAll.event_code = 'M3551' THEN 1 WHEN tempAll.event_code = 'M1552' THEN 2 WHEN tempAll.event_code = 'M2552' THEN 2 WHEN tempAll.event_code = 'M3552' THEN 2 WHEN tempAll.event_code = 'M1553' THEN 3  WHEN tempAll.event_code = 'M2553' THEN 3 WHEN tempAll.event_code = 'M3553' THEN 3 ELSE 0 END AS type, CASE WHEN tempAll.event_code = 'M1551' THEN 1554 WHEN tempAll.event_code = 'M2551' THEN 2554 WHEN tempAll.event_code = 'M3551' THEN 3554 WHEN tempAll.event_code = 'M1552' THEN 1555 WHEN tempAll.event_code = 'M2552' THEN 2555 WHEN tempAll.event_code = 'M3552' THEN 3555 WHEN tempAll.event_code = 'M1553' THEN 1556  WHEN tempAll.event_code = 'M2553' THEN 2556 WHEN tempAll.event_code = 'M3553' THEN 3556 ELSE 0 END AS surcharge_code,
                        CASE WHEN tempAll.event_code = 'M1551' THEN 500 WHEN tempAll.event_code = 'M2551' THEN 250 WHEN tempAll.event_code = 'M3551' THEN 125 WHEN tempAll.event_code = 'M1552' THEN 500 WHEN tempAll.event_code = 'M2552' THEN 250 WHEN tempAll.event_code = 'M3552' THEN 125 WHEN tempAll.event_code = 'M1553' THEN 500  WHEN tempAll.event_code = 'M2553' THEN 250 WHEN tempAll.event_code = 'M3553' THEN 125 ELSE 0 END AS surcharge_amount  FROM ( SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, CASE WHEN fees = '3.5' AND status = 'N' THEN 'M1551' WHEN fees = '3.5' AND status = 'Y' THEN 'M2551'  WHEN fees = '3.5' AND status = 'M' THEN 'M3551'  WHEN fees = '7.5' AND status = 'N' THEN 'M1552'  WHEN fees = '7.5' AND status = 'Y' THEN 'M2552'  WHEN fees = '7.5' AND status = 'M' THEN 'M3552'  WHEN fees = '11.5' AND status = 'N' THEN 'M1553'  WHEN fees = '11.5' AND status = 'Y' THEN 'M2553' WHEN fees = '11.5' AND status = 'M' THEN 'M3553' ELSE '' END AS event_code, due_date, grace_end_date FROM (SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status, due_date, grace_end_date FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '3.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 42 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 54 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodeOwned.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$implodeOwned.") AND event_code IN ('M1551','M2551', 'M3551', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status, due_date, grace_end_date FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '7.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 90 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 102 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate  FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodeOwned.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$implodeOwned.") AND event_code IN ('M1552','M2552', 'M3552', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status, due_date, grace_end_date FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '11.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 138 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 150 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodeOwned.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$implodeOwned.") AND event_code IN ('M1553','M2553', 'M3553', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND  currentDate >= due_date AND currentDate <= grace_end_date ) AS temp1) AS tempAll INNER JOIN db_patent_maintainence_fee.event_maintainence_code_fees AS emcf ON emcf.event_code = tempAll.event_code");

                    }
                    



                    



                    
                    /*$queryCalOnFly = "SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num,  0, event_code, 0 AS fee_amount FROM (SELECT appno_doc_num, grant_doc_num, grant_date,  date_format(DATE_ADD(grant_date, INTERVAL 42 MONTH), '%Y-%m-%d') as payment_due, date_format(DATE_ADD(grant_date, INTERVAL 54 MONTH), '%Y-%m-%d') as payment_grace, entity_status, CASE WHEN entity_status = 'N' THEN 'M1551' WHEN entity_status = 'Y' THEN 'M2551' WHEN entity_status = 'M' THEN 'M3551' ELSE '?' END AS event_code FROM db_patent_maintainence_fee.event_maintainence_fees WHERE appno_doc_num IN (".$implodeAssetsList.") GROUP BY appno_doc_num, event_code UNION ALL SELECT appno_doc_num, grant_doc_num, grant_date,  date_format(DATE_ADD(grant_date, INTERVAL 90 MONTH), '%Y-%m-%d') as payment_due, date_format(DATE_ADD(grant_date, INTERVAL 102 MONTH), '%Y-%m-%d') as payment_grace, entity_status, CASE WHEN entity_status = 'N' THEN 'M1552' WHEN entity_status = 'Y' THEN 'M2552' WHEN entity_status = 'M' THEN 'M3552' ELSE '?' END AS event_code FROM db_patent_maintainence_fee.event_maintainence_fees WHERE appno_doc_num IN (".$implodeAssetsList.") GROUP BY appno_doc_num, event_code UNION ALL SELECT appno_doc_num, grant_doc_num, grant_date,  date_format(DATE_ADD(grant_date, INTERVAL 138 MONTH), '%Y-%m-%d') as payment_due, date_format(DATE_ADD(grant_date, INTERVAL 150 MONTH), '%Y-%m-%d') as payment_grace, entity_status, CASE WHEN entity_status = 'N' THEN 'M1553' WHEN entity_status = 'Y' THEN 'M2553' WHEN entity_status = 'M' THEN 'M3553' ELSE '?' END AS event_code FROM db_patent_maintainence_fee.event_maintainence_fees WHERE appno_doc_num IN (".$implodeAssetsList.") GROUP BY appno_doc_num, event_code) AS temp 
                    INNER JOIN db_uspto.documentid AS doc ON doc.appno_doc_num = temp.appno_doc_num WHERE doc.appno_doc_num IN (".$implodeAssetsList.") AND ((payment_due BETWEEN '".$dueDate."' AND '".$formatCurrentDate."') OR (payment_grace BETWEEN '".$formatCurrentDate ."' AND '".$graceEndDate."') ) AND  temp.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_application.assets_transfer WHERE appno_doc_num <> '' AND status = 0 AND layout_id = 3 AND organisation_id = ".$organisationID.") AND doc.grant_doc_num NOT IN (SELECT grant_doc_num FROM db_application.assets_transfer WHERE appno_doc_num = '' AND grant_doc_num <> '' AND status = 0 AND layout_id = 3 AND organisation_id = ".$organisationID.") GROUP BY doc.grant_doc_num, doc.appno_doc_num ";




                    $queryMaintainenceBudget = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, event_code, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num,  0, fee_code, fee_amount FROM ".$dbApplication.".maintainence_assets WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND ((payment_due BETWEEN '".$dueDate."' AND '".$formatCurrentDate."') OR (payment_grace BETWEEN '".$formatCurrentDate ."' AND '".$graceEndDate."') ) AND appno_doc_num IN (".$implodeAssetsList.") AND appno_doc_num NOT IN (SELECT appno_doc_num FROM db_application.assets_transfer WHERE appno_doc_num <> '' AND status = 0 AND layout_id = 3 AND organisation_id = ".$organisationID.") AND grant_doc_num NOT IN (SELECT grant_doc_num FROM db_application.assets_transfer WHERE appno_doc_num = '' AND grant_doc_num <> '' AND status = 0 AND layout_id = 3 AND organisation_id = ".$organisationID.") GROUP BY grant_doc_num, appno_doc_num, company_id";
                    echo $queryMaintainenceBudget;
                    $con->query($queryMaintainenceBudget);*/

                    /**
                     *  Assets Abandoned
                     */
                    if(count($expiredAssets) > 0) { 
                        $mergeFilledAndOTAAssets = array_merge($originalApplicantAssets, $ownedAfterSold); 


                        $grantApplications = array();

                        if(count($mergeFilledAndOTAAssets) > 0) {
                            $findPatentsAssets = "SELECT MAX(appno_doc_num) AS appno_doc_num FROM db_uspto.documentid WHERE grant_doc_num <> '' AND grant_doc_num <> '' AND appno_doc_num IN (".implode(',', $mergeFilledAndOTAAssets).") GROUP BY appno_doc_num ";
    
    
                            $resultGrantApplications = $con->query($findPatentsAssets); 
                            if($resultGrantApplications && $resultGrantApplications->num_rows > 0) {
                                while($rowAsset = $resultGrantApplications->fetch_object()) {
                                    array_push($grantApplications, '"'.$rowAsset->appno_doc_num.'"');
                                }
                            }

                            $findPatentsAssets = "SELECT MAX(appno_doc_num) AS appno_doc_num FROM db_patent_application_bibliographic.application_grant WHERE appno_doc_num IN (".implode(',', $mergeFilledAndOTAAssets).") GROUP BY appno_doc_num ";
    
    
                            $resultGrantApplications = $con->query($findPatentsAssets); 
                            if($resultGrantApplications && $resultGrantApplications->num_rows > 0) {
                                while($rowAsset = $resultGrantApplications->fetch_object()) {
                                    array_push($grantApplications, '"'.$rowAsset->appno_doc_num.'"');
                                }
                            }
                        }

                        $remainingAssets = array();
                        if(count($grantApplications) > 0) {
                            $queryExpiredStatusAssets = "SELECT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362') AND appno_doc_num IN (".implode(',', $grantApplications).") GROUP BY appno_doc_num ";
    
                        
                            $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                            if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                                while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                    array_push($remainingAssets, '"'.$rowAsset->application.'"');
                                }
                            }
                        } 

                        $queryAbandonedAssets = "SELECT appno_doc_num FROM (SELECT appno_doc_num, status, MAX(status_date) FROM ".$dbUSPTO.".application_status WHERE appno_doc_num IN (".implode(',', $mergeFilledAndOTAAssets).") ";

                        if(count($grantApplications) > 0) {
                            $queryAbandonedAssets .= " AND appno_doc_num NOT IN (".implode(',', $grantApplications).") ";
                        }
                        
                        
                        
                        $queryAbandonedAssets .= " AND status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                        'Provisional Application Expired', 
                        'Final Rejection Mailed', 
                        'Expressly Abandoned  --  During Publication Process', 
                        'Expressly Abandoned  --  During Examination', 
                        'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                        'Abandoned  --  Failure to Pay Issue Fee', 
                        'Abandoned  --  File-Wrapper-Continuation Parent Application',
                        'Abandoned  --  Failure to Respond to an Office Action',  
                        'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                        'Abandoned  --  Incomplete Application (Pre-examination)', 
                        
                        'Abandonment for Failure to Correct Drawings/Oath/NonPub Request') GROUP BY  appno_doc_num) AS temp GROUP BY  appno_doc_num";
                        echo "ABABABABABABABBAABABABABBAB";
                        echo $queryAbandonedAssets;
                        $resultRemainingAssetsList = $con->query($queryAbandonedAssets);
                        
                        if($resultRemainingAssetsList && $resultRemainingAssetsList->num_rows > 0) {
                            while($rowAsset = $resultRemainingAssetsList->fetch_object()) {
                                array_push($remainingAssets, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 
                        
                        if(count($remainingAssets) > 0) {
                            $type = 36;
                            $queryPendingApplications = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, 0  FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE date_format(d.appno_date, '%Y') > ".$YEAR."  AND d.appno_doc_num IN (".implode(',', $remainingAssets).") AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." GROUP BY d.appno_doc_num ";
echo $queryPendingApplications."<br/>";
                            $con->query($queryPendingApplications);
                            echo "1ABABABABABABABBAABABABABBAB";
                            $queryPendingApplications = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, 0  FROM db_patent_application_bibliographic.application_grant AS d   WHERE date_format(d.appno_date, '%Y') > ".$YEAR."  AND d.appno_doc_num IN (".implode(',', $remainingAssets).") AND d.appno_doc_num NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = ".$type.") GROUP BY d.appno_doc_num ";
                            echo "2ABABABABABABABBAABABABABBAB";
                            $con->query($queryPendingApplications);
                            echo $queryPendingApplications."<br/>";
                            $queryPendingApplications = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, 0  FROM db_patent_application_bibliographic.application_publication AS d WHERE date_format(d.appno_date, '%Y') > ".$YEAR."  AND d.appno_doc_num IN (".implode(',', $remainingAssets).") AND d.appno_doc_num NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = ".$type.") GROUP BY d.appno_doc_num ";
                            echo "3ABABABABABABABBAABABABABBAB";
                            echo $queryPendingApplications."<br/>";
                            $con->query($queryPendingApplications); 
                        }
                    }
                    
                     /**
                     * Pending Applications
                     */
                    

                    /*$queryRemainingStatusAssets = "SELECT appno_doc_num FROM (SELECT appno_doc_num, status, MAX(status_date) FROM ".$dbUSPTO.".application_status WHERE appno_doc_num IN (".$implodeAssetsList.") AND status NOT IN (
                        'Patented Case', 
                        'Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                        'Provisional Application Expired', 
                        'Final Rejection Mailed', 
                        'Expressly Abandoned  --  During Publication Process', 
                        'Expressly Abandoned  --  During Examination', 
                        'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                        'Abandoned  --  Failure to Pay Issue Fee', 
                        'Abandoned  --  File-Wrapper-Continuation Parent Application',
                        'Abandoned  --  Failure to Respond to an Office Action',  
                        'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                        'Abandoned  --  Incomplete Application (Pre-examination)', 
                        
                        'Abandonment for Failure to Correct Drawings/Oath/NonPub Request') GROUP BY  appno_doc_num) AS temp GROUP BY  appno_doc_num";*/
                    
                    /* $resultRemainingAssetsList = $con->query($queryRemainingStatusAssets);
                    $remainingAssets = array();
                    if($resultRemainingAssetsList && $resultRemainingAssetsList->num_rows > 0) {
                        while($rowAsset = $resultRemainingAssetsList->fetch_object()) {
                            array_push($remainingAssets, '"'.$rowAsset->appno_doc_num.'"');
                        }
                    } 

                    if(count($remainingAssets) > 0) {
                        $queryPendingApplications = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", '', appno_doc_num, 0, 0  FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE date_format(d.appno_date, '%Y') > ".$year."  AND d.appno_doc_num IN (".implode(',', $remainingAssets).") AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." GROUP BY d.appno_doc_num ";
                        $con->query($queryPendingApplications);
                    } */


                    /**
                     * Un Maintained Patents
                     * All patents with flag Expired for non paid maintenance fee
                     */
                    

                   
                    /**
                     * Filed Application
                     */
                    //$type = 36;
                    /**
                     * Application Acquired (Acquisition and MergerIn)
                     */
                    //$type = 37;
                    
                    
                    
                    /**
                     * Top non-US Members
                     */
                    //$type = 38;

                    
                    /**
                     * Top Proliferate Inventors
                     */
                    $type = 39;


                    $queryTopInventor ="INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, name, assignor_id, type, patent, application ) SELECT ".$organisationID.", ".$companyID.", IF(representative_name <> '' , representative_name, aaa.name), aaa.assignor_and_assignee_id,  ".$type.", ag.grant_doc_num, ag.appno_doc_num FROM db_patent_application_bibliographic.inventor AS inv 
                    INNER JOIN db_patent_application_bibliographic.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = inv.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id
                    INNER JOIN db_patent_application_bibliographic.application_grant AS ag ON ag.appno_doc_num COLLATE utf8mb4_general_ci = inv.appno_doc_num COLLATE utf8mb4_general_ci
                    WHERE inv.appno_doc_num IN (".implode(',', $originalApplicantAssets).") GROUP BY aaa.name, ag.appno_doc_num ";
                     
                    $con->query($queryTopInventor);



                    $queryTopInventor ="INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, name, assignor_id, type, patent, application ) SELECT ".$organisationID.", ".$companyID.", IF(representative_name <> '' , representative_name, aaa.name), aaa.assignor_and_assignee_id, ".$type.", '' AS patent, ap.appno_doc_num FROM db_patent_grant_bibliographic.inventor_new AS inv INNER JOIN db_patent_application_bibliographic.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = inv.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id
                    INNER JOIN db_patent_application_bibliographic.application_publication AS ap ON ap.appno_doc_num = inv.appno_doc_num
                    WHERE inv.appno_doc_num IN (".implode(',', $originalApplicantAssets).") AND inv.appno_doc_num NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = ".$type." GROUP BY application) GROUP BY aaa.name, ap.appno_doc_num ";
                     
                    $con->query($queryTopInventor);

                    /* $queryTopInventor = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", aor.assignor_and_assignee_id, ".$type.", d.grant_doc_num,  d.appno_doc_num, apt.rf_id, 0 FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".assignor AS aor ON aor.rf_id = d.rf_id WHERE ass.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") AND d.appno_doc_num IN (".$implodeAssetsList.")  AND date_format(d.appno_date, '%Y') > ".$year." AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." AND (rac.convey_ty = 'employee' OR rac.employer_assign = 1) GROUP BY apt.rf_id, aor.assignor_and_assignee_id";                    
                    $con->query($queryTopInventor); */
                    /**
                     * Top Law Firms
                     */
                    /* $type = 40;
                    $queryLawFirms = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total, lawfirm) SELECT  ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, rf_id, 0,  lawfirm FROM ( SELECT d.grant_doc_num,  d.appno_doc_num, apt.rf_id, IF(rlf.representative_name <> '', rlf.representative_name, lf.name) AS lawfirm FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".assignment AS a ON a.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = apt.rf_id AND ass.assignor_and_assignee_id = apt.recorded_assignor_and_assignee_id INNER JOIN ".$dbUSPTO.".law_firm AS lf ON lf.law_firm_id = a.law_firm_id LEFT JOIN  ".$dbUSPTO.".representative_law_firm AS rlf = rfl.representative_id = lf.representative_id WHERE d.appno_doc_num IN (".$implodeAssetsList.") AND date_format(d.appno_date, '%Y') > ".$year."  AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." AND a.caddress_1 <> '' GROUP BY apt.rf_id) GROUP BY rf_id, lawfirm";
                    $con->query($queryLawFirms); */

                    $type = 40;
                    /* $queryLawFirms = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total, lawfirm) SELECT  ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, rf_id, 0,  lawfirm FROM ( SELECT d.grant_doc_num,  d.appno_doc_num, apt.rf_id, IF(rlf.representative_name <> '', rlf.representative_name, lf.name) AS lawfirm FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id INNER JOIN ".$dbUSPTO.".correspondent AS a ON a.rf_id = apt.rf_id INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = apt.rf_id AND ass.assignor_and_assignee_id = apt.recorded_assignor_and_assignee_id INNER JOIN ".$dbUSPTO.".law_firm AS lf ON a.cname = lf.name LEFT JOIN  ".$dbUSPTO.".representative_law_firm AS rlf ON rlf.representative_id = lf.representative_id WHERE d.appno_doc_num IN (".$implodeAssetsList.") AND date_format(d.appno_date, '%Y') > ".$year."  AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." AND a.cname <> '' GROUP BY apt.rf_id) AS temp GROUP BY rf_id, lawfirm"; */

                    $queryLawFirms = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total, lawfirm, lawfirm_id) SELECT  ".$organisationID.", ".$companyID.", 0, ".$type.", patent, application, rf_id, 0,
                    (CASE WHEN representative_name <> '' THEN representative_name 
                        WHEN lfName <> '' THEN lfName
                        ELSE cName END) AS lawfirm, law_firm_id FROM (
                        SELECT c.rf_id, c.cname as cName, lf.law_firm_id,  lf.name as lfName, rlf.representative_name, MAX(doc.grant_doc_num) AS patent, MAX(doc.appno_doc_num) AS application from db_new_application.activity_parties_transactions AS apt
                        INNER JOIN ".$dbUSPTO.".correspondent AS c ON c.rf_id = apt.rf_id
                        INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = apt.rf_id
                        LEFT JOIN ".$dbUSPTO.".law_firm  as lf ON c.cname = lf.name
                        LEFT JOIN ".$dbUSPTO.".representative_law_firm AS rlf ON rlf.representative_id = lf.representative_id
                        INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id 
                        where ass.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") AND apt.organisation_id = ".$organisationID." and apt.company_id = ".$companyID." AND date_format(apt.exec_dt, '%Y') > 1999 AND c.cname <> ''
                        GROUP BY apt.rf_id) AS temp GROUP BY rf_id, lawfirm";
                    
                    $con->query($queryLawFirms);

                    /**
                     * Top Lenders
                     */
                    $type = 41;
                    $queryLenders = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT  ".$organisationID.", ".$companyID.", apt.assignor_and_assignee_id, ".$type.", d.grant_doc_num,  d.appno_doc_num, apt.rf_id, 0 FROM ".$dbUSPTO.".documentid AS d 
                            JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id
                            WHERE apt.recorded_assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") 
                            AND d.appno_doc_num IN (".$implodePatentedAssetsList.") AND date_format(d.appno_date, '%Y') > ".$YEAR."  AND apt.activity_id IN (5, 12) AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID."
                            GROUP BY apt.rf_id, apt.assignor_and_assignee_id";

                    $con->query($queryLenders);




                    
                    $con->query("DELETE FROM  `db_uspto`.`temp_application_inventor_count` WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $con->query("DELETE FROM  `db_uspto`.`temp_application_employee_count` WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $con->query("DELETE FROM db_new_application.assets WHERE layout_id = 1 AND company_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $con->query("DELETE FROM db_new_application.dashboard_items WHERE type = 1 AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
 

                    echo "OWNED APPLICATION";
                    
                    print_r($clientOwnedAssets);



                    
                    if(count($clientOwnedAssets) > 0) {
                        echo "INNNNNNNNNNNN";
                        $queryFillingInventor = "INSERT IGNORE INTO `db_uspto`.`temp_application_inventor_count` (appno_doc_num, counter, company_id, organisation_id)  SELECT appno_doc_num, inventor_count, ".$companyID.", ".$organisationID." FROM ( SELECT inventor.appno_doc_num, COUNT(DISTINCT inventor.name) AS inventor_count 
                        FROM db_patent_application_bibliographic.inventor AS inventor WHERE inventor.appno_doc_num IN (".implode(',', $clientOwnedAssets).") GROUP BY inventor.appno_doc_num UNION SELECT inventor.appno_doc_num, COUNT(DISTINCT inventor.name) AS inventor_count FROM db_patent_grant_bibliographic.inventor AS inventor WHERE inventor.appno_doc_num IN (".implode(',', $clientOwnedAssets).") GROUP BY inventor.appno_doc_num) AS temp1";
                        echo $queryFillingInventor;
                        $con->query($queryFillingInventor); 

                        $queryAssignmentEmployee = "INSERT IGNORE INTO `db_uspto`.`temp_application_employee_count` (appno_doc_num, counter, company_id, organisation_id) Select appno_doc_num, COUNT(DISTINCT name) AS employee_count, ".$companyID.", ".$organisationID." FROM ( Select doc.appno_doc_num, IF(r.representative_name <> '', r.representative_name, aaa.name) AS name FROM db_uspto.documentid AS doc INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = doc.rf_id INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id LEFT JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = aor.rf_id Where doc.appno_doc_num IN (".implode(',', $clientOwnedAssets).") AND (rac.convey_ty = 'employee' OR (rac.convey_ty = 'assignment' AND rac.employer_assign = 1) OR (rac.convey_ty = 'correct' AND rac.employer_assign = 1))) AS temp GROUP BY appno_doc_num";
                        
                        echo $queryAssignmentEmployee;
                        $con->query($queryAssignmentEmployee);


                        $queryBroken = " Select taic.appno_doc_num FROM db_uspto.temp_application_inventor_count AS taic INNER JOIN db_uspto.temp_application_employee_count AS taec ON taec.appno_doc_num = taic.appno_doc_num WHERE taic.company_id = ".$companyID." AND taic.organisation_id = ".$organisationID." AND taec.company_id = ".$companyID." AND taec.organisation_id = ".$organisationID." AND (taic.counter > taec.counter)GROUP BY taic.appno_doc_num";
                        echo $queryBroken;
                        $resultBroken = $con->query($queryBroken);
                        $applicationBroken = array();
                        if($resultBroken && $resultBroken->num_rows > 0) {  
                            while($row = $resultBroken->fetch_object()) {
                                array_push($applicationBroken, '"'.$row->appno_doc_num.'"');
                            }
                        }

                        

                        $queryBroken = " Select taec.appno_doc_num FROM db_uspto.temp_application_employee_count AS taec WHERE taec.counter = 0 AND company_id = ".$companyID." AND organisation_id = ".$organisationID." GROUP BY taec.appno_doc_num";
                        echo $queryBroken;
                        $resultBroken = $con->query($queryBroken); 
                        if($resultBroken && $resultBroken->num_rows > 0) {  
                            while($row = $resultBroken->fetch_object()) {
                                array_push($applicationBroken, '"'.$row->appno_doc_num.'"');
                            }
                        }

                        $queryBroken = " Select taic.appno_doc_num FROM db_uspto.temp_application_inventor_count AS taic LEFT JOIN db_uspto.temp_application_employee_count AS taec ON taec.appno_doc_num = taic.appno_doc_num INNER JOIN db_uspto.documentid AS doc ON doc.appno_doc_num = taic.appno_doc_num  WHERE taic.company_id = ".$companyID." AND taic.organisation_id = ".$organisationID." AND taec.counter IS NULL GROUP BY taic.appno_doc_num";


                        $resultBroken = $con->query($queryBroken); 
                        if($resultBroken && $resultBroken->num_rows > 0) {  
                            while($row = $resultBroken->fetch_object()) {
                                array_push($applicationBroken, '"'.$row->appno_doc_num.'"');
                            }
                        }


                        if(count($applicationBroken) > 0) {
                            echo "APPLICATION BROKEN START";
                            $queryBrokenInsert = " INSERT IGNORE INTO db_new_application.assets (appno_doc_num, appno_date, grant_doc_num, grant_date, layout_id, company_id, organisation_id) SELECT appno_doc_num, appno_date, grant_doc_num, grant_date, 1, ".$companyID.", ".$organisationID." FROM db_patent_application_bibliographic.application_grant WHERE appno_doc_num IN (".implode(',', $applicationBroken).") GROUP BY appno_doc_num";
                            echo $queryBrokenInsert;
                            $con->query($queryBrokenInsert);

                            $queryBrokenInsert = " INSERT IGNORE INTO db_new_application.assets (appno_doc_num, appno_date, grant_doc_num, grant_date, layout_id, company_id, organisation_id) SELECT MAX(appno_doc_num), MAX(appno_date), MAX(grant_doc_num), MAX(grant_date), 1, ".$companyID.", ".$organisationID." FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $applicationBroken).") GROUP BY appno_doc_num";
                            echo $queryBrokenInsert;
                            $con->query($queryBrokenInsert);

                            $queryBrokenInsert = " INSERT IGNORE INTO db_new_application.assets (appno_doc_num, appno_date, layout_id, company_id, organisation_id) SELECT MAX(appno_doc_num), MAX(appno_date), 1, ".$companyID.", ".$organisationID." FROM db_patent_grant_bibliographic.application_publication WHERE appno_doc_num IN (".implode(',', $applicationBroken).") AND appno_doc_num NOT IN (SELECT appno_doc_num FROM db_new_application.assets WHERE layout_id = 1 AND company_id = ".$companyID." AND organisation_id = ".$organisationID." GROUP BY appno_doc_num ) GROUP BY appno_doc_num";
                            echo $queryBrokenInsert;
                            $con->query($queryBrokenInsert);


                            $queryBrokedAssets = " Select appno_doc_num FROM db_new_application.assets WHERE layout_id = 1 AND company_id = ".$companyID." AND organisation_id = ".$organisationID." GROUP BY appno_doc_num  ";

                            $applicationBroked = array();
                            $resultBrokedAssets = $con->query($queryBrokedAssets); 
                            if($resultBrokedAssets && $resultBrokedAssets->num_rows > 0) {  
                                while($row = $resultBrokedAssets->fetch_object()) {
                                    array_push($applicationBroken, '"'.$row->appno_doc_num.'"');
                                }
                            }  
                        }

                        

                        $brokedNonInventorAssets = array();
                        foreach($clientOwnedAssets as $ownAsset) {
                            if(!in_array($ownAsset, $applicationBroken)) { 
                                $queryFindNonInventorLevel = "Select assigneeNames FROM (
                                    SELECT assigneeNames FROM (
                                    Select IF(r.representative_name <> '', r.representative_name, aaa.name) AS assigneeNames from documentid  AS doc
                                    INNER JOIN assignee AS aee ON aee.rf_id = doc.rf_id
                                    INNER JOIN assignor AS aor ON aor.rf_id = aee.rf_id 
                                    INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aee.assignor_and_assignee_id
                                    LEFT JOIN representative AS r On r.representative_id = aaa.representative_id
                                    INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = aee.rf_id
                                    INNER JOIN conveyance AS c ON c.convey_name = rac.convey_ty
                                    where appno_doc_num = ".$ownAsset." AND (c.is_ota = 1 OR (rac.convey_ty = 'correct' AND rac.employer_assign = 1)) AND aor.exec_dt <= (SELECT MAX(exec_dt) FROM assignor WHERE rf_id IN (
                                        SELECT assignee.rf_id FROM documentid  
                                        INNER JOIN assignee on assignee.rf_id = documentid.rf_id
                                        where appno_doc_num = ".$ownAsset." AND assignee.assignor_and_assignee_id IN(".implode(',', $companyAssignorAndAssigneeIDs).") 
                                    )) AND aee.rf_id NOT IN (Select ee.rf_id from documentid as doc
                                    INNER JOIN assignee as ee ON ee.rf_id = doc.rf_id
                                    INNER JOIN assignor as aor ON aor.rf_id = doc.rf_id
                                    where appno_doc_num = ".$ownAsset." and ee.assignor_and_assignee_id IN(".implode(',', $companyAssignorAndAssigneeIDs).") HAVING MAX(aor.exec_dt))
                                    ORDER BY aor.exec_dt ASC
                                    ) AS temp
                                    WHERE assigneeNames NOT IN (".implode(',', $allCompanyNames).")
                                    GROUP BY assigneeNames) AS temp1
                                    LEFT JOIN (
                                    
                                    SELECT assignorNames FROM (
                                    Select IF(r.representative_name <> '', r.representative_name, aaa.name) AS assignorNames from documentid  AS doc
                                    INNER JOIN assignor AS aor ON aor.rf_id = doc.rf_id 
                                    INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id
                                    LEFT JOIN representative AS r On r.representative_id = aaa.representative_id
                                    INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = aor.rf_id
                                    INNER JOIN conveyance AS c ON c.convey_name = rac.convey_ty
                                    where appno_doc_num = ".$ownAsset." AND (c.is_ota = 1 OR (rac.convey_ty = 'correct' AND rac.employer_assign = 1)) AND aor.exec_dt <= (SELECT MAX(exec_dt) FROM assignor WHERE rf_id IN (
                                        SELECT assignee.rf_id FROM documentid  
                                        INNER JOIN assignee on assignee.rf_id = documentid.rf_id
                                        where appno_doc_num = ".$ownAsset." AND assignee.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") 
                                    ))
                                    ORDER BY aor.exec_dt ASC
                                    ) AS temp 
                                    GROUP BY assignorNames) AS temp2 ON temp2.assignorNames = temp1.assigneeNames
                                    WHERE temp2.assignorNames IS NULL";
                                echo $queryFindNonInventorLevel;
                                 
                                $resultBrokednonInventorLevel = $con->query($queryFindNonInventorLevel); 
                                if($resultBrokednonInventorLevel && $resultBrokednonInventorLevel->num_rows > 0) {  
                                    $row = $resultBrokednonInventorLevel->fetch_object();
                                    echo $queryFindNonInventorLevel;
                                    print_r($row);


                                    array_push($brokedNonInventorAssets, $ownAsset);
                                }
                            }
                        } 
                        if(count($brokedNonInventorAssets) > 0) {
                            $queryBrokenInsert = " INSERT IGNORE INTO db_new_application.assets (appno_doc_num, appno_date, grant_doc_num, grant_date, layout_id, company_id, organisation_id) SELECT MAX(appno_doc_num), MAX(appno_date), MAX(grant_doc_num), MAX(grant_date), 1, ".$companyID.", ".$organisationID." FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $brokedNonInventorAssets).") GROUP BY appno_doc_num";
                            echo $queryBrokenInsert;
                            $con->query($queryBrokenInsert);
                        }


                        

                        echo "End Of Chain Of Title";
                        
                        /**s
                         * Broken Chain of Title
                         */
                        $type = 1;
                        $queryBrokenChain = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type." AS type, grant_doc_num, appno_doc_num, 0 AS rf_id, ".count($clientOwnedAssets)."  FROM db_new_application.assets AS assets  WHERE date_format(assets.appno_date, '%Y') > 1999 AND assets.layout_id = 1 AND assets.company_id = ".$companyID." AND assets.organisation_id = ".$organisationID." AND appno_doc_num IN (".implode(',', $clientOwnedAssets).") GROUP BY appno_doc_num";

                        $con->query($queryBrokenChain); 
                    }  
                    /* $con->query("DELETE FROM  `db_uspto`.`temp_application_inventor_count` WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);
                    $con->query("DELETE FROM  `db_uspto`.`temp_application_employee_count` WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID); */


                   
                    //die;
                    /**
                     * Names
                     */
                    $type = 17;
                    $queryIncorrectNames = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, recorded_assignor_and_assignee_id AS assignor_id, ".$type." AS type, grantNo, appno, rf_id, ".count($clientOwnedAssets)." AS total FROM (
                        SELECT apt.recorded_assignor_and_assignee_id, MAX(appno_doc_num) AS appno, MAX(appno_date) AS appnoDt, MAX(grant_doc_num) AS grantNo, MAX(grant_date) AS grantDt,  rac.rf_id, aaa.name AS name,
                                            (SELECT representative_name FROM db_uspto.representative WHERE representative_id = aaa.representative_id) AS representative_name  FROM db_new_application.activity_parties_transactions AS apt
                        INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = apt.rf_id
                        INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id 
                        INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = rac.rf_id 
                        INNER JOIN db_uspto.conveyance AS con ON con.convey_name = rac.convey_ty AND con.is_ota = 1 
                        INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = ass.assignor_and_assignee_id
                        WHERE apt.company_id = ".$companyID." AND apt.organisation_id = ".$organisationID." AND appno_doc_num IN (".implode(',', $clientOwnedAssets).")
                        GROUP BY apt.recorded_assignor_and_assignee_id, appno_doc_num, rac.rf_id
                        ) AS temp
                        WHERE representative_name <> '' AND LOWER(name) <> LOWER(representative_name)";
                    
                        $con->query($queryIncorrectNames);
                    /**
                     * Encumbrances
                    */   
                    $type = 18;
                    /* $queryEncumbrances = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, aor.assignor_and_assignee_id, ".$type.", d.grant_doc_num, d.appno_doc_num, rac.rf_id, ".count($listAllAssets)."  FROM db_uspto.documentid AS d 
                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = d.rf_id AND rac.convey_ty NOT IN ('namechg', 'licenseend', 'release', 'addresschg', 'correspondchange')  
                    INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = rac.rf_id 
                    WHERE d.appno_doc_num IN (".$implodeAssetsList.") AND aor.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).")                  
                    GROUP BY d.appno_doc_num, rac.rf_id"; */

                    
                    $encumberedAssets = array();
                    //$encumberedConveyance = array('license', 'courtappointment', 'courtorder', 'govern', 'option', 'other');
                    /* foreach($clientOwnedAssets as $ownAsset) { 
                        $queryFindNonInventorLevel = "Select assigneeNames FROM (
                            SELECT assigneeNames FROM (
                            Select IF(r.representative_name <> '', r.representative_name, aaa.name) AS assigneeNames from documentid  AS doc
                            INNER JOIN assignee AS aee ON aee.rf_id = doc.rf_id
                            INNER JOIN assignor AS aor ON aor.rf_id = aee.rf_id 
                            INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aee.assignor_and_assignee_id
                            LEFT JOIN representative AS r On r.representative_id = aaa.representative_id
                            INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = aee.rf_id
                            INNER JOIN conveyance AS c ON c.convey_name = rac.convey_ty
                            where appno_doc_num = ".$ownAsset." AND c.convey_name IN ('license', 'courtappointment', 'courtorder', 'govern', 'option', 'other') AND aor.exec_dt <= (SELECT MAX(exec_dt) FROM assignor WHERE rf_id IN (
                                SELECT assignee.rf_id FROM documentid  
                                INNER JOIN assignee on assignee.rf_id = documentid.rf_id
                                where appno_doc_num = ".$ownAsset." AND assignee.assignor_and_assignee_id IN(".implode(',', $companyAssignorAndAssigneeIDs).") 
                            ))
                            ORDER BY aor.exec_dt ASC
                            ) AS temp
                            WHERE assigneeNames NOT IN (".implode(',', $allCompanyNames).")
                            GROUP BY assigneeNames) AS temp1
                            LEFT JOIN (
                            
                            SELECT assignorNames FROM (
                            Select IF(r.representative_name <> '', r.representative_name, aaa.name) AS assignorNames from documentid  AS doc
                            INNER JOIN assignor AS aor ON aor.rf_id = doc.rf_id 
                            INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id
                            LEFT JOIN representative AS r On r.representative_id = aaa.representative_id
                            INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = aor.rf_id
                            INNER JOIN conveyance AS c ON c.convey_name = rac.convey_ty
                            where appno_doc_num = ".$ownAsset." AND aor.exec_dt <= (SELECT MAX(exec_dt) FROM assignor WHERE rf_id IN (
                                SELECT assignee.rf_id FROM documentid  
                                INNER JOIN assignee on assignee.rf_id = documentid.rf_id
                                where appno_doc_num = ".$ownAsset." AND assignee.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") 
                            ))
                            ORDER BY aor.exec_dt ASC
                            ) AS temp 
                            GROUP BY assignorNames) AS temp2 ON temp2.assignorNames = temp1.assigneeNames
                            WHERE temp2.assignorNames IS NULL";
                        echo $queryFindNonInventorLevel;
                        $resultBrokednonInventorLevel = $con->query($queryFindNonInventorLevel); 
                        if($resultBrokednonInventorLevel && $resultBrokednonInventorLevel->num_rows > 0) {  
                            array_push($encumberedAssets, $ownAsset);
                        } 
                    } */


                    if(count($clientOwnedAssets) > 0) {
                        $queryEncumbrances = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, aor.assignor_and_assignee_id, ".$type.", d.grant_doc_num, d.appno_doc_num, rac.rf_id, ".count($clientOwnedAssets)."  FROM db_uspto.documentid AS d 
                        INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = d.rf_id AND rac.convey_ty IN ('license', 'courtappointment', 'courtorder', 'govern', 'option', 'other')  
                        INNER JOIN db_new_application.activity_parties_transactions AS aor ON aor.rf_id = rac.rf_id 
                        WHERE d.appno_doc_num IN (".implode(',', $clientOwnedAssets).") AND aor.recorded_assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).")                  
                        GROUP BY d.appno_doc_num";
                        $con->query($queryEncumbrances);
                        
                    }

                    
                    


                    /**
                     * Addresses
                     */
                    $type = 19;
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE mode = 1 AND type = ".$type." AND representative_id = ".$companyID);
                    $queryCollateralized =  "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (mode, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT 1, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.",  patent_number, application_number, '' AS rf_id, ".count($clientOwnedAssets)." FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." and type = 30 
                    and mode = 0 AND application NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." and type = 34) ";
                    $con->query($queryCollateralized);

                    /* if(count($companyAddress) > 0) {
                        $type = 19;
                        $findCompanyAddress = array();
                        foreach($companyAddress as $address) {
                            if((int)$address->representative_id === (int)$companyID){
                                array_push($findCompanyAddress, $address);
                            }
                        }
                        if(count($findCompanyAddress) > 0) {
                            $queryTransactionAddress = "SELECT ass.rf_id, ee_address_1, ee_address_2, ee_city, ee_state, ee_postcode, ee_country FROM db_new_application.activity_parties_transactions AS apt INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = apt.rf_id INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = ass.rf_id WHERE apt.organisation_id = ".$organisationID. " AND apt.company_id IN (".$companyID.") AND ass.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") AND doc.appno_doc_num IN (".implode(',', $clientOwnedAssets).") AND apt.activity_id IN (1, 6, 10) GROUP BY ass.rf_id";

                            

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

                                    $queryWrongAddress = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, ee.assignor_and_assignee_id, ".$type.", d.grant_doc_num, d.appno_doc_num, ee.rf_id, ".count($clientOwnedAssets)."  FROM db_uspto.documentid AS d   
                                    INNER JOIN db_uspto.assignee AS ee ON ee.rf_id = d.rf_id 
                                    WHERE d.appno_doc_num IN (".implode(',', $clientOwnedAssets).") AND ee.rf_id IN (".implode(',', $wrongAddressCount).")   
                                    GROUP BY d.appno_doc_num";
                                   
                                    $con->query($queryWrongAddress);
                                }
                            }
                        }
                    } */
                                    
                    /* $queryWrongAddress = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, aor.assignor_and_assignee_id, ".$type.", '', '', ass.rf_id, ".$totalTransactionAddress."  FROM db_uspto.assignee AS ass 
                    INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = ass.rf_id 
                    WHERE ass.rf_id IN (".implode(',', $wrongAddressCount).")                  
                    GROUP BY ass.rf_id"; */

                    /**
                     * To Divest
                     * the company does not have any patents in that letter in the most recent 3 years.
                     * OR
                     * the company has no more than 5 patents in the past 5 years.
                     */
                    $type = 21;
                    $allUnnecessaryAssets = array();
                    $currentDate = new DateTime();
                    $currentYear = $currentDate->format('Y');
                    $pastYear3 = $currentDate->modify('-3 year')->format('Y');
                    $pastYear5 = $currentDate->modify('-2 year')->format('Y');
                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);

                    $queryCPCSection = "SELECT section FROM (SELECT section FROM db_patent_application_bibliographic.patent_cpc AS application_cpc  INNER JOIN (SELECT documentid.appno_doc_num, documentid.grant_doc_num, documentid.appno_date FROM db_uspto.documentid AS documentid WHERE date_format(documentid.appno_date, '%Y') >".$YEAR."  AND documentid.appno_doc_num IN(".implode(',', $clientOwnedAssets).") AND documentid.grant_doc_num <> ''  GROUP BY documentid.appno_doc_num) AS temp ON temp.appno_doc_num = application_cpc.application_number WHERE application_cpc.type = 0  GROUP BY temp.appno_doc_num UNION SELECT section FROM db_patent_grant_bibliographic.application_cpc  INNER JOIN (SELECT documentid.appno_doc_num, documentid.grant_doc_num, documentid.appno_date FROM db_uspto.documentid AS documentid WHERE date_format(documentid.appno_date, '%Y') >".$YEAR."  AND documentid.appno_doc_num IN(".implode(',', $clientOwnedAssets).") AND documentid.grant_doc_num = ''  GROUP BY documentid.appno_doc_num) AS temp ON temp.appno_doc_num = application_cpc.application_number WHERE application_cpc.type = 0  GROUP BY temp.appno_doc_num) temp2 GROUP BY section ORDER BY section ASC";

                    $allSections = array();
                    $resultAllSections = $con->query($queryCPCSection);
                    if($resultAllSections && $resultAllSections->num_rows > 0) {
                        while($rowAsset = $resultAllSections->fetch_object()) {
                            array_push($allSections, '"'.$rowAsset->section.'"');
                        }
                    } 

                    $unNecessarySection = array();
                    
                    if(count($allSections) > 0) {
                        foreach( $allSections as $section) {
                            $queryPast3Years = "SELECT application_number  FROM (SELECT application_number FROM db_patent_application_bibliographic.patent_cpc AS application_cpc INNER JOIN (SELECT documentid.appno_doc_num, documentid.grant_doc_num, documentid.appno_date FROM db_uspto.documentid AS documentid WHERE date_format(documentid.appno_date, '%Y') BETWEEN '".$pastYear3."' AND '".$currentYear."' AND documentid.appno_doc_num IN(".implode(',', $clientOwnedAssets).") AND documentid.grant_doc_num <> ''  GROUP BY documentid.appno_doc_num)AS temp ON temp.appno_doc_num = application_cpc.application_number WHERE application_cpc.type = 0 AND application_cpc.section = ".$section."  GROUP BY temp.appno_doc_num   UNION SELECT application_number FROM db_patent_grant_bibliographic.application_cpc INNER JOIN (SELECT documentid.appno_doc_num, documentid.grant_doc_num, documentid.appno_date FROM db_uspto.documentid AS documentid WHERE date_format(documentid.appno_date, '%Y') BETWEEN '".$pastYear3."' AND '".$currentYear."' AND documentid.appno_doc_num IN(".implode(',', $clientOwnedAssets).") AND documentid.grant_doc_num = ''  GROUP BY documentid.appno_doc_num)AS temp ON temp.appno_doc_num = application_cpc.application_number WHERE application_cpc.type = 0  AND application_cpc.section = ".$section."  GROUP BY temp.appno_doc_num ) as temp2  GROUP BY application_number";

                            $resultPastYear3 = $con->query($queryPast3Years);
                            
                            if($resultPastYear3 && $resultPastYear3->num_rows == 0) { 
                                if(!in_array($section, $unNecessarySection)) {
                                    array_push( $unNecessarySection, $section);
                                }
                            }


                            $queryPast5Years = "SELECT application_number FROM (SELECT application_number FROM db_patent_application_bibliographic.patent_cpc AS application_cpc INNER JOIN (SELECT documentid.appno_doc_num, documentid.grant_doc_num, documentid.appno_date FROM db_uspto.documentid AS documentid WHERE date_format(documentid.appno_date, '%Y') BETWEEN '".$pastYear5."' AND '".$currentYear."' AND documentid.appno_doc_num IN(". implode(',', $clientOwnedAssets).") AND documentid.grant_doc_num <> ''  GROUP BY documentid.appno_doc_num)AS temp ON temp.appno_doc_num = application_cpc.application_number WHERE application_cpc.type = 0 AND application_cpc.section = ".$section."  GROUP BY temp.appno_doc_num   UNION SELECT application_number FROM db_patent_grant_bibliographic.application_cpc INNER JOIN (SELECT documentid.appno_doc_num, documentid.grant_doc_num, documentid.appno_date FROM db_uspto.documentid AS documentid WHERE date_format(documentid.appno_date, '%Y') BETWEEN '".$pastYear5."' AND '".$currentYear."' AND documentid.appno_doc_num IN(".implode(',', $clientOwnedAssets).") AND documentid.grant_doc_num = ''  GROUP BY documentid.appno_doc_num)AS temp ON temp.appno_doc_num = application_cpc.application_number WHERE application_cpc.type = 0  AND application_cpc.section = ".$section."  GROUP BY temp.appno_doc_num ) as temp2 GROUP BY application_number";

                            $resultLast5Years = $con->query($queryPast5Years);
                            
                            if($resultLast5Years && $resultLast5Years->num_rows <= 5 ) {
                                if(!in_array($section, $unNecessarySection)) {
                                    array_push( $unNecessarySection, $section);
                                }
                            }
                        }
                    }

                    if(count($unNecessarySection) > 0) {
                        $queryUnNecessaryPatents = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.",  patent_number, application_number, '' AS rf_id, ".count($clientOwnedAssets)." FROM (SELECT application_number, temp.grant_doc_num AS patent_number FROM db_patent_application_bibliographic.patent_cpc AS application_cpc INNER JOIN (SELECT documentid.appno_doc_num, documentid.grant_doc_num, documentid.appno_date FROM db_uspto.documentid AS documentid WHERE date_format(documentid.appno_date, '%Y') >".$YEAR."  AND documentid.appno_doc_num IN (".implode(',', $clientOwnedAssets).") AND documentid.grant_doc_num <> ''  GROUP BY documentid.appno_doc_num)AS temp ON temp.appno_doc_num = application_cpc.application_number WHERE application_cpc.type = 0 AND application_cpc.section IN (".implode(',', $unNecessarySection).")  GROUP BY temp.appno_doc_num   UNION SELECT application_number, '' AS patent_number FROM db_patent_grant_bibliographic.application_cpc INNER JOIN (SELECT documentid.appno_doc_num, documentid.grant_doc_num, documentid.appno_date FROM db_uspto.documentid AS documentid WHERE date_format(documentid.appno_date, '%Y')  >".$YEAR."  AND documentid.appno_doc_num IN (".implode(',', $clientOwnedAssets).") AND documentid.grant_doc_num = ''  GROUP BY documentid.appno_doc_num)AS temp ON temp.appno_doc_num = application_cpc.application_number WHERE application_cpc.type = 0  AND application_cpc.section IN (".implode(',', $unNecessarySection).")  GROUP BY temp.appno_doc_num ) as temp2 GROUP BY application_number";
                        $con->query($queryUnNecessaryPatents);
                    }

                    /**
                     * To Record
                     */

                    
                    /* if(count($expiredAssets) > 0) {
                        $queryMissedMonitization = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.",  patent_number, '' AS application_number,  '' AS rf_id, ".count($expiredAssets)."  FROM (SELECT cp.patent_number, COUNT(cp.assignee_id) AS counter FROM ".$dbApplication.".cited_patents AS cp WHERE cp.patent_number IN (SELECT grant_doc_num FROM ".$dbUSPTO.".documentid WHERE appno_doc_num IN (".implode(',', $expiredAssets).") AND grant_doc_num <> '' AND date_format(appno_date, '%Y') > ".$year." ) GROUP BY cp.patent_number) AS temp WHERE counter > 10";
                        $con->query($queryMissedMonitization);
                    } */
                    if(count($applicantAssets) > 0) {
                        $type = 22;
                        $queryApplicantAssets = "SELECT application, patent FROM ".$dbApplication.".dashboard_items WHERE type = 31 AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." GROUP BY application";

                        $resultApplicant = $con->query($queryApplicantAssets);
                            
                        if($resultApplicant && $resultApplicant->num_rows > 0) { 
                            $applicantAssets = array();
                            $patent = array();
                            $assetRows = array();
                            while($rowApplicant = $resultApplicant->fetch_object()) {
                                array_push( $applicantAssets, '"'.$rowApplicant->application.'"');
                                array_push( $patent, '"'.$rowApplicant->patent.'"');
                                array_push( $assetRows, $rowApplicant);
                            }


                            $queryDocumenttAssets = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num";
                            

                            $resultDocument = $con->query($queryDocumenttAssets);

                            $documentAssets = array();
                            $remainingApplicantAssets = array();
                            
                            if($resultDocument && $resultDocument->num_rows > 0) { 
                                while($rowDoc = $resultDocument->fetch_object()) {
                                    array_push( $documentAssets, '"'.$rowDoc->appno_doc_num.'"');
                                }
                            }

                            $remainingApplicantAssets = array_diff($applicantAssets, $documentAssets);

                            if(count($remainingApplicantAssets) > 0) {

                                $queryDocumenttAssets = "SELECT grant_doc_num FROM ".$dbUSPTO.".documentid WHERE grant_doc_num IN (".implode(',', $patent).") AND appno_doc_num NOT IN (".implode(',', $documentAssets).") GROUP BY grant_doc_num";

                                $resultDocument = $con->query($queryDocumenttAssets);

                                $documentPatentAssets = array();
                                //$remainingApplicantAssets = array();
                                
                                if($resultDocument && $resultDocument->num_rows > 0) { 
                                    while($rowDoc = $resultDocument->fetch_object()) {
                                        array_push( $documentPatentAssets, '"'.$rowDoc->grant_doc_num.'"');
                                    }
                                }


                                if(count($documentPatentAssets) > 0) {
                                    foreach($documentPatentAssets as $pAsset) {
                                        foreach($assetRows as $aRows) {
                                            if($pAsset == '"'.$aRows->patent.'"') {
                                                array_push($documentAssets, '"'.$aRows->application.'"');
                                                break;
                                            }
                                        }
                                    }
                                }

                                $remainingApplicantAssets = array_diff($applicantAssets, $documentAssets);

                                if(count($expiredAssets) > 0 && count($remainingApplicantAssets) > 0) { 
                                    $remainingApplicantAssets = array_diff($remainingApplicantAssets, $expiredAssets);
                                }
                                

                                if(count($remainingApplicantAssets) > 0) {
                                    $queryUnAssignedAssets = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.",  patent, application,  rf_id, ".count($applicantAssets)."  FROM (SELECT * FROM ".$dbApplication.".dashboard_items  WHERE type = 31 AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND application IN  (".implode(',', $remainingApplicantAssets).") GROUP BY application) AS temp";
                                    $con->query($queryUnAssignedAssets);

                                    /**
                                     * Delete To record assets from broken chain of title
                                    */

                                     $queryToRecordFromDelete = "DELETE FROM ".$dbApplication.".dashboard_items WHERE type = 1 AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND application IN (SELECT * FROM ( SELECT application FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." ) AS temp )";

                                    echo $queryToRecordFromDelete."chain delete to record";

                                    $con->query($queryToRecordFromDelete); 
                                }
                            }
                        }
                    }

                    /**
                     * To be Monetized
                     * The list of Divest assets, identify those with over 10 F. Citations  
                     * From the list of Owned assets identify those with filing year over 12 years ago, AND have over 10 F. Citations
                     */
                    $type = 20;
                    $currentDate = new DateTime('now');
                    $pastYear12 = $currentDate->modify('-12 year')->format('Y');

                    $queryDivestFCitation = " SELECT cp.patent_number, COUNT(cp.assignee_id) AS counter FROM ".$dbApplication.".citing_patents_with_assignee AS cp WHERE cp.patent_number IN (SELECT patent FROM ".$dbApplication.".dashboard_items AS di WHERE di.type = 21 AND patent <> '' AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." GROUP BY patent) GROUP BY cp.patent_number HAVING counter > 10 ";

                    echo "DIVESTED QUEREYYYYY: ".$queryDivestFCitation."<br/>";
                    $resultCitations = $con->query($queryDivestFCitation);
                    $divestAssets = array();
                        
                    if($resultCitations && $resultCitations->num_rows > 0) { 
                        while($rowDoc = $resultCitations->fetch_object()) {
                            array_push( $divestAssets, '"'.$rowDoc->patent_number.'"');
                        }
                    }

                    $clientOwnedQuery = "SELECT patent FROM db_new_application.dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = 30 AND patent <> '' GROUP BY patent";
                    
                    $clientOwnedPatents = array();
                    $resultClientOwned = $con->query($clientOwnedQuery);
                    if($resultClientOwned && $resultClientOwned->num_rows > 0) {
                        while($rowAsset = $resultClientOwned->fetch_object()) {
                            array_push($clientOwnedPatents, '"'.$rowAsset->patent.'"');
                        }
                    } 
                    

                    $queryCitationAssets = "SELECT cp.patent_number, COUNT(DISTINCT cp.assignee_id) AS counter FROM ".$dbApplication.".citing_patents_with_assignee AS cp WHERE cp.patent_number IN (".implode(',', $clientOwnedPatents).") 
                        GROUP BY cp.patent_number
                        HAVING counter > 10 ";

                    echo "queryCitationAssets QUEREYYYYY: ".$queryCitationAssets."<br/>";

                    $resultCitations = $con->query($queryCitationAssets);
                    
                    $citationAssets = array();
                    if($resultCitations && $resultCitations->num_rows > 0) { 
                        while($rowDoc = $resultCitations->fetch_object()) {
                            array_push( $citationAssets, '"'.$rowDoc->patent_number.'"');
                        }
                    }


                    echo "DIVESTED: ".count($divestAssets)."<br/>";
                    echo "citationAssets: ".count($citationAssets)."<br/>";

                    $divestedCounter = "SELECT patent FROM ".$dbApplication.".dashboard_items AS di WHERE di.type = 21 AND patent <> '' AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." GROUP BY patent";
                    $resultDivestedCounter = $con->query($divestedCounter);

                    $totalDivested = 0;
                    if($resultDivestedCounter && $resultDivestedCounter->num_rows > 0) { 
                        $totalDivested = $resultDivestedCounter->num_rows;
                    }

                    $countQuery = count($clientOwnedPatents) + $totalDivested;

                    if(count($divestAssets) > 0) {
                        
                        $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application,  total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", grant_doc_num, appno_doc_num,  ".$countQuery."  FROM ( SELECT grant_doc_num, appno_doc_num FROM db_patent_application_bibliographic.application_grant WHERE grant_doc_num IN  (".implode(',', $divestAssets).") GROUP BY appno_doc_num ) AS temp GROUP BY appno_doc_num";
                        $con->query($queryMonetized);

                        echo $queryMonetized."<br/>";
                        $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application,  total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", patent, application,    ".$countQuery."  FROM ( SELECT MAX(grant_doc_num) AS patent, MAX(appno_doc_num) AS application FROM db_uspto.documentid WHERE grant_doc_num IN (".implode(',', $divestAssets).") AND grant_doc_num NOT IN ( SELECT patent FROM db_new_application.dashboard_item WHERE type = ".$type." AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." GROUP BY patent ) GROUP BY appno_doc_num ) AS temp GROUP BY application";
                        $con->query($queryMonetized);

                        echo $queryMonetized."<br/>";
                    }

                    if(count($citationAssets) > 0) {
                         
                        $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application,   total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", grant_doc_num, appno_doc_num,  ".$countQuery."  FROM ( SELECT grant_doc_num, appno_doc_num FROM db_patent_application_bibliographic.application_grant WHERE grant_doc_num IN  (".implode(',', $citationAssets).") AND  date_format(appno_date, '%Y') <= ".$pastYear12." ) AS temp GROUP BY appno_doc_num";
                        $con->query($queryMonetized);

                        echo $queryMonetized."<br/>";

                        $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", patent, application, ".$countQuery."  FROM ( SELECT MAX(grant_doc_num) AS patent, MAX(appno_doc_num) AS application FROM db_uspto.documentid WHERE grant_doc_num IN (".implode(',', $citationAssets).") AND  date_format(appno_date, '%Y') <= ".$pastYear12."  AND grant_doc_num NOT IN ( SELECT patent FROM db_new_application.dashboard_item WHERE type = ".$type." AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." GROUP BY patent ) GROUP BY appno_doc_num ) AS temp GROUP BY application";
                        $con->query($queryMonetized);

                        echo $queryMonetized."<br/>"; 
                    } 
                    /* $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", grant_doc_num, appno_doc_num,  '' AS rf_id, ".count($clientOwnedAssets)."  FROM ( SELECT patent AS grant_doc_num, application AS appno_doc_num FROM ".$dbApplication.".dashboard_items AS di WHERE di.type = 21 AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND di.application IN  (".implode(',', $clientOwnedAssets).") GROUP BY application UNION SELECT grant_doc_num, appno_doc_num FROM ".$dbUSPTO.".documentid AS doc WHERE doc.grant_doc_num IN (SELECT patent_number FROM (SELECT cp.patent_number, COUNT(cp.assignee_id) AS counter FROM ".$dbApplication.".cited_patents AS cp WHERE cp.patent_number IN (SELECT grant_doc_num FROM ".$dbUSPTO.".documentid WHERE appno_doc_num IN  (".implode(',', $clientOwnedAssets).") AND grant_doc_num <> '' AND date_format(appno_date, '%Y') > ".$year." ) GROUP BY cp.patent_number) AS temp WHERE counter > 10) AND date_format(doc.appno_date, '%Y') <= ".$pastYear12.") AS temp3 GROUP BY appno_doc_num"; */


                    /* $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", grant_doc_num, appno_doc_num,  '' AS rf_id, ".count($clientOwnedAssets)."  FROM ( SELECT patent AS grant_doc_num, application AS appno_doc_num FROM ".$dbApplication.".dashboard_items AS di WHERE di.type = 21 AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND di.application IN  (".implode(',', $clientOwnedAssets).") GROUP BY application UNION SELECT grant_doc_num, appno_doc_num FROM ".$dbUSPTO.".documentid AS doc WHERE doc.grant_doc_num IN (SELECT patent_number FROM (SELECT cp.patent_number, COUNT(cp.assignee_id) AS counter FROM ".$dbApplication.".cited_patents AS cp WHERE cp.patent_number IN (SELECT grant_doc_num FROM ".$dbUSPTO.".documentid WHERE appno_doc_num IN  (".implode(',', $clientOwnedAssets).") AND grant_doc_num <> '' AND date_format(appno_date, '%Y') > ".$year." ) GROUP BY cp.patent_number) AS temp WHERE counter > 10) AND date_format(doc.appno_date, '%Y') <= ".$pastYear12.") AS temp3 GROUP BY appno_doc_num"; */

                    /* $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", grant_doc_num, appno_doc_num,  '' AS rf_id, ".count($clientOwnedAssets)."  FROM (
                        SELECT grant_doc_num, appno_doc_num FROM ".$dbUSPTO.".documentid AS doc WHERE doc.grant_doc_num IN (
                            SELECT patentNumber FROM (
                            SELECT cp.patent_number AS patentNumber, COUNT(cp.assignee_id) AS counter FROM ".$dbApplication.".cited_patents AS cp WHERE cp.patent_number IN (
                                SELECT patent AS appno_doc_num FROM ".$dbApplication.".dashboard_items AS di WHERE di.type = 21 AND organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND patent <> '' GROUP BY patent
                            )
                            GROUP BY cp.patent_number
                            HAVING counter > 10) AS temp
                        ) AND date_format(doc.appno_date, '%Y') <= ".$pastYear12."
                        GROUP BY appno_doc_num
                    ) AS temp3 GROUP BY appno_doc_num */

                    
                    

                    /**
                    * Late Maintainence (Owned Assets)  
                    */
                    $type = 23;
                    $queryLateMaintainence = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", emf.grant_doc_num, tawb.appno_doc_num, '' AS rf_id, ".count($clientOwnedAssets)."                               
                    FROM db_new_application.assets as tawb
                    INNER JOIN db_patent_maintainence_fee.event_maintainence_fees AS emf ON emf.appno_doc_num = tawb.appno_doc_num
                    WHERE company_id  = ".$companyID."
                    AND organisation_id = ".$organisationID."
                    AND tawb.appno_doc_num IN  (".implode(',', $clientOwnedAssets).")
                    AND emf.event_code IN ('F176', 'M1554', 'M1555', 'M1556', 'M1557', 'M1558', 'M176', 'M177', 'M178', 'M181', 'M182', 'M186', 'M187', 'M188', 'M2554', 'M2555', 'M2556', 'M2558', 'M277', 'M281', 'M282', 'M286', 'M3554', 'M3555', 'M3556', 'M3557', 'M3558') GROUP BY tawb.appno_doc_num";
                    
                    $con->query($queryLateMaintainence);

                    /**
                    * Incorrect Recordings
                    */
                    $type = 24;
                    /* $queryIncorrectRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.", '' AS patent, '' AS application, rf_id, (SELECT COUNT(transactions) FROM ( 
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
                    GROUP BY apt.rf_id) AS temp"; */

                    $queryIncorrectRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, assignor_id, ".$type.",   patent,  application, rf_id, ".count($companyAllAssets)."  AS total FROM ( SELECT rac.rf_id AS rf_id, doc.grant_doc_num AS patent, doc.appno_doc_num AS application, 0 AS assignor_id
                    FROM ".$dbApplication.".activity_parties_transactions AS apt 
                    INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id
                    INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = apt.rf_id
                    INNER JOIN ".$dbUSPTO.".assignee AS ee ON ee.rf_id = doc.rf_id AND ee.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).")
                    WHERE apt.company_id = ".$companyID." 
                    AND apt.organisation_id = ".$organisationID." 
                    AND doc.appno_doc_num IN (".implode(',', $companyAllAssets).")
                    AND rac.convey_ty = 'correct'
                    GROUP BY doc.appno_doc_num) AS temp";
                    $con->query($queryIncorrectRecording);

                        echo $queryIncorrectRecording;

                    

                    /**
                    * Late Recordings
                    */  
                    $days = 90;
                    $type = 25; 
                    $queryLateRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0, ".$type.", patent, application, rf_id,  ".count($companyAllAssets)." AS total FROM ( SELECT d.rf_id, d.appno_doc_num AS application, d.grant_doc_num AS patent FROM ".$dbUSPTO.".documentid AS d INNER JOIN (SELECT temp_exec_dt.rf_id, DATEDIFF(ass.record_dt, temp_exec_dt.exec_dt) AS noOfDays   
                    FROM db_new_application.assets as tawb
                    INNER JOIN (
                        SELECT appno_doc_num, rf_id FROM ".$dbUSPTO.".documentid
                        WHERE appno_doc_num IN (".implode(',', $companyAllAssets).")
                        GROUP BY appno_doc_num, rf_id
                    ) AS doc ON doc.appno_doc_num = tawb.appno_doc_num
                    INNER JOIN ".$dbUSPTO.".assignment AS ass ON ass.rf_id = doc.rf_id
                    INNER JOIN LATERAL (
                        SELECT aor.rf_id, MAX(aor.exec_dt) as exec_dt FROM ".$dbUSPTO.".assignor AS aor
                        INNER JOIN (
                            SELECT appno_doc_num, rf_id FROM ".$dbUSPTO.".documentid
                            WHERE appno_doc_num IN (".implode(',', $companyAllAssets).")
                            GROUP BY appno_doc_num, rf_id
                        ) AS doc1 ON doc1.rf_id = aor.rf_id
                        INNER JOIN db_new_application.assets AS tawb1 ON tawb1.appno_doc_num = doc1.appno_doc_num
                        WHERE company_id = ".$companyID." 
                        AND organisation_id = ".$organisationID." 
                        GROUP BY aor.rf_id
                    ) AS temp_exec_dt ON  temp_exec_dt.rf_id = ass.rf_id
                    INNER JOIN ".$dbUSPTO.".assignee AS ee ON ee.rf_id = ass.rf_id AND ee.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).")
                    WHERE company_id = ".$companyID." 
                    AND organisation_id = ".$organisationID." 
                    AND date_format(temp_exec_dt.exec_dt, '%Y') > ".$year."
                    GROUP BY temp_exec_dt.rf_id
                    HAVING noOfDays > ".$days.") AS temp ON temp.rf_id = d.rf_id  GROUP BY d.appno_doc_num) AS temp1";  

                    $con->query($queryLateRecording);  


                    /**
                     * Deflated Collateral
                     */
                    $type = 26;
                    $currentDate = new DateTime();
                    /**
                     * $queryDeflatedCollateral = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0, ".$type.",  patent, application, rfID, ".count($clientOwnedAssets)." AS total FROM ( SELECT apt.rf_id as rfID, doc.appno_doc_num AS application, doc.grant_doc_num AS patent, doc.appno_date, release_rf_id,  timestampdiff(YEAR, doc.appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer,  IF (temp_expired.status <> '', 1, 0) AS expiredStatus, COUNT(*) OVER() AS total FROM db_new_application.activity_parties_transactions AS apt INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = apt.rf_id  LEFT JOIN ( SELECT appno_doc_num, status FROM db_uspto.application_status  WHERE (`status` LIKE '%abandoned%' OR `status` LIKE '%expired%' OR `status` LIKE '%final rejection%')  AND appno_doc_num IN (".implode(',', $clientOwnedAssets).") ) AS temp_expired ON temp_expired.appno_doc_num = doc.appno_doc_num  WHERE doc.appno_doc_num IN (".implode(',', $clientOwnedAssets).") AND apt.activity_id IN (5, 12) AND (release_rf_id IS NULL OR apt.release_rf_id = 0) AND apt.organisation_id = ".$organisationID."  AND company_id = ".$companyID."  GROUP BY apt.rf_id, doc.appno_doc_num) AS temp WHERE yearDiffer > 19 OR expiredStatus = 1 GROUP BY application ";
                     */
                    $queryDeflatedCollateral = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0, ".$type.",  patent, application, rfID, ".count($clientOwnedAssets)." AS total FROM ( SELECT apt.rf_id as rfID, doc.appno_doc_num AS application, MAX(doc.grant_doc_num) AS patent, doc.appno_date, release_rf_id,  timestampdiff(YEAR, doc.appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer,  1 AS expiredStatus, COUNT(*) OVER() AS total FROM db_new_application.activity_parties_transactions AS apt INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = apt.rf_id  WHERE (doc.appno_doc_num IN (".implode(',', $expiredAssets).") OR doc.appno_doc_num IN (SELECT application FROM db_new_application.dashboard_items WHERE type = 33 AND organisation_id = ".$organisationID."  AND representative_id = ".$companyID." GROUP BY application )) AND apt.activity_id IN (5, 12) AND (release_rf_id IS NULL OR apt.release_rf_id = 0) AND apt.organisation_id = ".$organisationID."  AND company_id = ".$companyID."  GROUP BY apt.rf_id, doc.appno_doc_num) AS temp  GROUP BY application ";
                    echo "DEFLATED COLLATERAL";
                    echo $queryDeflatedCollateral;
                    $con->query($queryDeflatedCollateral);  

                    foreach($allTypes as $type) {
                        if($type == 35) {   
                            /**
                             * Maintainence
                             */

                            $queryInsertMaintainence = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, organisation_id, representative_id, assignor_id, type,  total) SELECT SUM(total) AS number, ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0, ".$type.", COUNT(IF(patent <> '', patent, null)) AS total FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID."  AND representative_id = ".$companyID." AND type = ".$type;
                            echo $queryInsertMaintainence;
                            $con->query($queryInsertMaintainence);  

                        } else if($type == 38) {
                            /**
                             * Family
                             */
                            $queryFamily = "SELECT cwc.name AS name, COUNT(application_country) AS number FROM (
                                    SELECT grant_doc_num, application_number, application_country FROM db_uspto.assets_family AS af WHERE grant_doc_num IN (
                                        SELECT grant_doc_num FROM db_uspto.documentid AS di WHERE appno_doc_num IN (".implode(',', $clientOwnedAssets).")                                     
                                        GROUP BY grant_doc_num
                                    ) AND application_country NOT IN ('WO', 'US') GROUP BY application_number) AS temp 
                                    INNER JOIN db_uspto.country_with_codes AS cwc ON cwc.country_code = temp.application_country 
                                    GROUP BY application_country ORDER BY number DESC, name ASC ";
                            $patentFamily = array();
                            $resultFamily = $con->query($queryFamily);
                            if($resultFamily && $resultFamily->num_rows > 0) {
                                while($rowAsset = $resultFamily->fetch_object()) {
                                    array_push($patentFamily, $rowAsset);
                                }
                            }             
                            
                            $queryInsertFamily = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, organisation_id, representative_id, assignor_id, type,  total, other) VALUES ( 0, ".$organisationID.", ".$companyID.", 0, ".$type.", 0, '".json_encode($patentFamily)."')";
                             
                            $con->query($queryInsertFamily); 

                        }  else if( $type == 39 ) {
                            /**
                             * Inventor
                             */

                            $queryInventor = "SELECT name, COUNT(application) AS number FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID."  AND representative_id = ".$companyID." AND type = ".$type." GROUP BY name ORDER BY number DESC, name ASC LIMIT 20"; 

                            $allInventor = array();
                            
                            $resultInventor = $con->query($queryInventor);
                            if($resultInventor && $resultInventor->num_rows > 0) {
                                while($rowAsset = $resultInventor->fetch_object()) {
                                    $name = preg_replace("/'/", "", $rowAsset->name);
                                    array_push($allInventor, array('name'=>$name, 'number'=>$rowAsset->number)); 
                                }
                            }   

                            $queryInsertParties = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, organisation_id, representative_id, assignor_id, type,  total, other) VALUES ( 0, ".$organisationID.", ".$companyID.", 0, ".$type.", 0, '".json_encode($allInventor)."')";
                            
                            $con->query($queryInsertParties); 

                        } else if($type == 41) {
                            /**
                             * Inventor, Lender
                             */

                            $queryLI = "SELECT inventorName AS name, COUNT(rf_id) AS number FROM (SELECT  IF(aaa.representative_id <> '', r.representative_name, aaa.name) AS inventorName, rf_id FROM ".$dbApplication.".dashboard_items AS di INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = di.assignor_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id WHERE di.type = ".$type." AND di.organisation_id = ".$organisationID." AND di.representative_id = ".$companyID." ) AS temp GROUP BY inventorName ORDER BY number DESC, name ASC  LIMIT 20"; 

                            
                            $parties = array();
                            $resultParties = $con->query($queryLI);
                            if($resultParties && $resultParties->num_rows > 0) {
                                while($rowAsset = $resultParties->fetch_object()) {
                                    $name = preg_replace("/'/", "", $rowAsset->name);
                                    array_push($parties, array('name'=>$name, 'number'=>$rowAsset->number));
                                }
                            }   
                           
                            $queryInsertParties = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, organisation_id, representative_id, assignor_id, type,  total, other) VALUES ( 0, ".$organisationID.", ".$companyID.", 0, ".$type.", 0, '".json_encode($parties)."')";
                            
                            $con->query($queryInsertParties); 

                        }  else if($type == 40) {
                            /**
                             * LawFirm
                             */
                            $queryLawFirm = "SELECT lawfirm AS name, COUNT(rf_id) AS number FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID."  AND representative_id = ".$companyID." AND type = ".$type." GROUP BY lawfirm ORDER BY number DESC, name ASC  LIMIT 20"; 

                            $allLawfirms = array();
                            
                            $resultLawFirm = $con->query($queryLawFirm);
                            if($resultLawFirm && $resultLawFirm->num_rows > 0) {
                                while($rowAsset = $resultLawFirm->fetch_object()) {
                                    $name = preg_replace("/'/", "", $rowAsset->name);
                                    array_push($allLawfirms, array('name'=>$name, 'number'=>$rowAsset->number)); 
                                }
                            }  
                            
                            $queryInsertLawFirms = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, organisation_id, representative_id, assignor_id, type,  total, other) VALUES ( 0, ".$organisationID.", ".$companyID.", 0, ".$type.", 0, '".json_encode($allLawfirms)."')";
                            
                            $con->query($queryInsertLawFirms); 
                            
                        } else if ($type < 28 && $type != 20) {
                            $queryInsertCounter = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, other_number,  total, organisation_id, representative_id, assignor_id, type) SELECT SUM(number + other_number) AS num, 0, total,  organisation_id, representative_id, assignor_id, type FROM (SELECT COUNT(IF(patent <> '', patent, null)) AS number, COUNT(IF(patent = '', application, null)) AS other_number,  total, ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type." AS type FROM ( SELECT * FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID."  AND representative_id = ".$companyID." AND type = ".$type." GROUP BY application ) AS temp2 ) AS temp";
                            
                            $con->query($queryInsertCounter);  
                        }  else if($type == 34) {
                            $queryInsertCounter = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, other_number,  total, organisation_id, representative_id, assignor_id, type) SELECT COUNT(IF(patent <> '', patent, null)) AS number, COUNT(IF(patent = '', application, null)) AS other_number,  total, ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0, ".$type." FROM ( SELECT application, patent, total FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID."  AND representative_id = ".$companyID." AND type = ".$type." GROUP BY application ) AS temp";
                            
                            $con->query($queryInsertCounter);  
                        } else if($type != 37) {
                            /* $queryInsertCounter = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, other_number,  total, organisation_id, representative_id, assignor_id, type) SELECT COUNT(IF(patent <> '', patent, null)) AS number, COUNT(IF(patent = '', application, null)) AS other_number,  total, ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0, ".$type." FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID."  AND representative_id = ".$companyID." AND type = ".$type;
                            
                            $con->query($queryInsertCounter);   */
                            $queryInsertCounter = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, other_number,  total, organisation_id, representative_id, assignor_id, type) SELECT COUNT(IF(patent <> '', patent, null)) AS number, COUNT(IF(patent = '', application, null)) AS other_number,  total, ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0, ".$type." FROM ( SELECT application, patent, total FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID."  AND representative_id = ".$companyID." AND type = ".$type." GROUP BY application ) AS temp";
                            
                            $con->query($queryInsertCounter);  
                        }
                    }
                }   
            }
        }
    }
}