<?php 
ignore_user_abort(true);
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);

require_once 'config/db_central.php';

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

$expiredStatuses = [
    "'Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362'", 
    "'Provisional Application Expired'", 
    "'Final Rejection Mailed'", 
    "'Expressly Abandoned  --  During Publication Process'", 
    "'Expressly Abandoned  --  During Examination'", 
    "'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision'", 
    "'Abandoned  --  Failure to Pay Issue Fee'", 
    "'Abandoned  --  File-Wrapper-Continuation Parent Application'",
    "'Abandoned  --  Failure to Respond to an Office Action'",  
    "'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)'",
    "'Abandoned  --  Incomplete Application (Pre-examination)'", 
    "'Abandonment for Failure to Correct Drawings/Oath/NonPub Request'"
];

$variables = $argv;
if(count($variables) == 3) {
	$company = $variables[1];
	$organisationID = $variables[2]; 

	if((int)$organisationID > 0) {	

		include 'config/db_client.php';

	 	$orgConnect = $GLOBALS['orgConnect'];

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

            if(count($companiesData) > 0) {
            
	            /**
	             * Security End Date
	             */
	            $time = new DateTime('now');
	            $year =  $time->modify('-24 year')->format('Y'); 
	            $time = new DateTime('now');
	            $YEAR =   $time->modify('-21 year')->format('Y'); 
	            $releaseIDs = array();

	            $yearStart = $year . '-01-01';
	            $queryReleaseIDs = "
					SELECT DISTINCT apt.release_rf_id AS rf_id
					FROM ".$dbApplication.".activity_parties_transactions AS apt
					WHERE apt.activity_id IN (5,12) 
					AND apt.exec_dt >= '".$yearStart."'
					AND apt.release_rf_id IS NOT NULL 
					AND apt.release_rf_id <> 0";

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
	                	$applicantAssignorAndAssigneeIDsList = implode(',', array_map('intval', $applicantAssignorAndAssigneeIDs));
						$companyAssignorAndAssigneeIDsList = implode(',', array_map('intval', $companyAssignorAndAssigneeIDs));

						$queryApplicantAssets = "
						    SELECT DISTINCT appno_doc_num
						    FROM (
						        SELECT appno_doc_num
						        FROM db_patent_grant_bibliographic.applicant
						        WHERE assignor_and_assignee_id IN ($applicantAssignorAndAssigneeIDsList)

						        UNION ALL

						        SELECT appno_doc_num
						        FROM db_patent_application_bibliographic.applicant
						        WHERE assignor_and_assignee_id IN ($applicantAssignorAndAssigneeIDsList)

						        UNION ALL

						        SELECT appno_doc_num
						        FROM db_patent_grant_bibliographic.assignee
						        WHERE assignor_and_assignee_id IN ($applicantAssignorAndAssigneeIDsList)

						        UNION ALL

						        SELECT appno_doc_num
						        FROM db_patent_application_bibliographic.assignee
						        WHERE assignor_and_assignee_id IN ($applicantAssignorAndAssigneeIDsList)

						        UNION ALL

						        SELECT DISTINCT appno_doc_num
						        FROM {$dbUSPTO}.table_b
						        WHERE company_id = $companyID
						          AND organisation_id = $organisationID
						          AND appno_doc_num IN (
						            SELECT DISTINCT appno_doc_num
						            FROM {$dbUSPTO}.documentid
						            WHERE rf_id IN (
						                SELECT DISTINCT rf_id
						                FROM {$dbApplication}.activity_parties_transactions
						                WHERE company_id = $companyID
						                  AND organisation_id = $organisationID
						                  AND activity_id = 10
						                  AND recorded_assignor_and_assignee_id IN ($companyAssignorAndAssigneeIDsList)
						            )
						        )
						    ) AS temp
						";
						$resultApplicantAssetsList = $con->query($queryApplicantAssets);
	                    if($resultApplicantAssetsList && $resultApplicantAssetsList->num_rows > 0) {
	                        while($rowAsset = $resultApplicantAssetsList->fetch_object()) {
	                            array_push($applicantAssets, '"'.$rowAsset->appno_doc_num.'"');
	                        }
	                    } 
	                }
	                $originalApplicantAssets = $applicantAssets;
	                /**
	                 * 
	                 * Find company OTA Assets minus Sold Assets AND merger Out
	                 */


	                $companyAssignorAndAssigneeIDsList = implode(',', array_map('intval', $companyAssignorAndAssigneeIDs));

					$queryAllAssetsList = "
					    SELECT DISTINCT t.appno_doc_num
					    FROM {$dbUSPTO}.table_b t
					    INNER JOIN {$dbUSPTO}.documentid d ON d.appno_doc_num = t.appno_doc_num
					    INNER JOIN {$dbApplication}.activity_parties_transactions a ON a.rf_id = d.rf_id
					    WHERE t.company_id = $companyID
					      AND t.organisation_id = $organisationID
					      AND a.company_id = $companyID
					      AND a.organisation_id = $organisationID
					      AND a.activity_id IN (1,6)
					      AND a.recorded_assignor_and_assignee_id IN ($companyAssignorAndAssigneeIDsList)
					";

					$resultAllAssetsList = $con->query($queryAllAssetsList);
	                if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
	                    while($rowAsset = $resultAllAssetsList->fetch_object()) {
	                        array_push($listAllAssets, '"'.$rowAsset->appno_doc_num.'"');
	                    }
	                } 

	                $originalList = $listAllAssets;

	               	$companyAllAssets = array();

	               	$queryAllAssetsList = "
					    SELECT DISTINCT t.appno_doc_num
					    FROM {$dbUSPTO}.table_b t
					    INNER JOIN {$dbUSPTO}.documentid d ON d.appno_doc_num = t.appno_doc_num
					    INNER JOIN {$dbApplication}.activity_parties_transactions a ON a.rf_id = d.rf_id
					    WHERE t.company_id = $companyID
					      AND t.organisation_id = $organisationID
					      AND a.company_id = $companyID
					      AND a.organisation_id = $organisationID
					      AND a.recorded_assignor_and_assignee_id IN ($companyAssignorAndAssigneeIDsList)
					";
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
	                $querySoldAssetsList = "
					    SELECT DISTINCT d.appno_doc_num
					    FROM {$dbUSPTO}.documentid d
					    INNER JOIN {$dbApplication}.activity_parties_transactions a ON a.rf_id = d.rf_id
					    WHERE a.company_id = $companyID
					      AND a.organisation_id = $organisationID
					      AND a.activity_id IN (2, 7)
					";

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
	                f(count($listAllAssets) > 0 || count($applicantAssets) > 0) {
	                	$designAssetsFirstPart = array();
	                    $designAssetsSecondPart = array();
	                    $expiredAssets = array(); 
	                    $grantApplications = array();

	                    if(count($listAllAssets) > 0) {
	                    	$queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid AS doc  WHERE appno_doc_num IN ( ".implode(',', $listAllAssets).")  AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num";

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
	                        $queryAllAssetsList = "
								SELECT DISTINCT appno_doc_num
								FROM {$dbUSPTO}.documentid AS doc  
								WHERE appno_doc_num IN (" . implode(',', $listAllAssets) . ")
								  AND grant_doc_num LIKE 'd%'
								  AND grant_date < '2015-05-13'
								  AND YEAR(appno_date) > {$YEAR}";

							$resultAllAssetsList = $con->query($queryAllAssetsList);
	                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
	                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
	                                array_push($designAssetsFirstPart, '"'.$rowAsset->appno_doc_num.'"');
	                            }
	                        } 

	                        $queryAllAssetsList = "
								SELECT DISTINCT appno_doc_num 
								FROM {$dbUSPTO}.documentid AS doc  
								WHERE appno_doc_num IN (" . implode(',', $listAllAssets) . ")
								  AND grant_doc_num LIKE 'd%'
								  AND grant_date >= '2015-05-13'
								  AND YEAR(appno_date) > {$YEAR}";

							$resultAllAssetsList = $con->query($queryAllAssetsList);
	                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
	                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
	                                array_push($designAssetsSecondPart, '"'.$rowAsset->appno_doc_num.'"');
	                            }
	                        }

	                        $findPatentsAssets = "
								SELECT DISTINCT appno_doc_num 
								FROM db_uspto.documentid 
								WHERE grant_doc_num <> '' 
								AND appno_doc_num IN (".implode(',', $listAllAssets).")";

	                        $resultGrantApplications = $con->query($findPatentsAssets); 
	                        if($resultGrantApplications && $resultGrantApplications->num_rows > 0) {
	                            while($rowAsset = $resultGrantApplications->fetch_object()) {
	                                array_push($grantApplications, '"'.$rowAsset->appno_doc_num.'"');
	                            }
	                        }

	                    }


	                    if(count($grantApplications) > 0) {
	                        $queryExpiredStatusAssets = "
								SELECT DISTINCT appno_doc_num AS application 
								FROM db_uspto.application_status  
								WHERE status = 'Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362'  
								AND appno_doc_num IN (".implode(',', $grantApplications).")
								";

	                    
	                        $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
	                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
	                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
	                                array_push($expiredAssets, '"'.$rowAsset->application.'"');
	                            }
	                        }
	                    }	

	                    if(count($listAllAssets) > 0) {
	                        $queryExpiredStatusAssets = "
								SELECT DISTINCT appno_doc_num AS application 
								FROM db_uspto.application_status  
								WHERE status IN (".implode(',', $expiredStatuses).") 
								AND appno_doc_num IN (".implode(',', $listAllAssets).") 
							";

							if(count($grantApplications) > 0) {
							    $queryExpiredStatusAssets .= " AND appno_doc_num NOT IN (".implode(',', $grantApplications).") ";
							}

	                        //echo $queryExpiredStatusAssets;
	                        
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
	                    $queryExpiredMaintainenceAssets = "
							SELECT DISTINCT appno_doc_num AS application
							FROM db_patent_maintainence_fee.event_maintainence_fees
							WHERE event_code IN ('EXP', 'EXP.')
							AND appno_doc_num IN (".implode(',', $allAssets).")";

	                    
	                    $resultAllExpiredAssetsList = $con->query($queryExpiredMaintainenceAssets); 
	                    if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
	                        while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
	                            array_push($expiredAssets, '"'.$rowAsset->application.'"');
	                        }
	                    }


	                    $allAssetsList = implode(',', $allAssets);
						$designAssetsExclude = [];
						if (count($designAssetsFirstPart) > 0) {
						    $designAssetsExclude[] = implode(',', $designAssetsFirstPart);
						}
						if (count($designAssetsSecondPart) > 0) {
						    $designAssetsExclude[] = implode(',', $designAssetsSecondPart);
						}

						$excludeClause = '';
						if (!empty($designAssetsExclude)) {
						    // Combine multiple excludes with AND NOT IN clauses
						    foreach ($designAssetsExclude as $excludeSet) {
						        $excludeClause .= " AND ap.appno_doc_num NOT IN ($excludeSet) ";
						    }
						}

						$currentDateStr = $currentDate->format('Y-m-d');


						$queryExpiredDateAssets = "
							SELECT application, yearDiffer FROM (
							    SELECT 
							        application,
							        CASE 
							            WHEN extendedDate > '$currentDateStr' THEN 0
							            ELSE yearDiffer
							        END AS yearDiffer
							    FROM (
							        SELECT 
							            application, extendedDate, '$currentDateStr' AS currentDate,
							            IF(
							                extendedDate <> '', 
							                TIMESTAMPDIFF(YEAR, appno_date, extendedDate), 
							                TIMESTAMPDIFF(YEAR, appno_date, '$currentDateStr')
							            ) AS yearDiffer
							        FROM (
							            SELECT ap.appno_doc_num AS application, appno_date,
							                IF(
							                    ge.extension <> '', 
							                    DATE_ADD(DATE_ADD(appno_date, INTERVAL 20 YEAR), INTERVAL ge.extension DAY), 
							                    ''
							                ) AS extendedDate
							            FROM db_patent_grant_bibliographic.application_publication ap
							            LEFT JOIN db_patent_application_bibliographic.grant_extension ge
							                ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci
							            WHERE ap.appno_doc_num IN ($allAssetsList)
							            GROUP BY ap.appno_doc_num

							            UNION ALL

							            SELECT ap.appno_doc_num AS application, appno_date,
							                IF(
							                    ge.extension <> '', 
							                    DATE_ADD(DATE_ADD(appno_date, INTERVAL 20 YEAR), INTERVAL ge.extension DAY), 
							                    ''
							                ) AS extendedDate
							            FROM db_patent_application_bibliographic.application_grant ap
							            LEFT JOIN db_patent_application_bibliographic.grant_extension ge
							                ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci
							            WHERE ap.appno_doc_num IN ($allAssetsList)
							            GROUP BY ap.appno_doc_num

							            UNION ALL

							            SELECT ap.appno_doc_num AS application, appno_date,
							                IF(
							                    ge.extension <> '', 
							                    DATE_ADD(DATE_ADD(appno_date, INTERVAL 20 YEAR), INTERVAL ge.extension DAY), 
							                    ''
							                ) AS extendedDate
							            FROM db_uspto.documentid ap
							            LEFT JOIN db_patent_application_bibliographic.grant_extension ge
							                ON ge.appno_doc_num = ap.appno_doc_num
							            WHERE ap.appno_doc_num IN ($allAssetsList)
							            $excludeClause
							            GROUP BY ap.appno_doc_num
							        ) AS tempAll
							    ) AS tempWithYearDiff
							) AS tempFinal
							GROUP BY application
							HAVING yearDiffer > 19
						";

	                    $resultAllExpiredAssetsList = $con->query($queryExpiredDateAssets); 
	                    if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
	                        while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
	                            array_push($expiredAssets, '"'.$rowAsset->application.'"');
	                        }
	                    }
	                    $expiredAssets = array_unique($expiredAssets);


	                    if(count($applicantAssets) > 0) {

	                        $applicantAssetsList = implode(',', $applicantAssets);
							$queryAllAssetsList = "
							    SELECT appno_doc_num
							    FROM db_patent_application_bibliographic.application_grant AS doc
							    WHERE appno_doc_num IN ($applicantAssetsList)
							      AND grant_doc_num LIKE 'D%'
							      AND grant_date < '2015-05-13'
							      AND YEAR(appno_date) > $YEAR
							    GROUP BY appno_doc_num
							";
	                         
	                        $resultAllAssetsList = $con->query($queryAllAssetsList);
	                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
	                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
	                                array_push($designAssetsFirstPart, '"'.$rowAsset->appno_doc_num.'"');
	                            }
	                        } 
	    
	                        $queryAllAssetsList = "
							    SELECT appno_doc_num
							    FROM db_patent_application_bibliographic.application_grant AS doc
							    WHERE appno_doc_num IN ($applicantAssetsList)
							      AND grant_doc_num LIKE 'D%'
							      AND grant_date >= '2015-05-13'
							      AND YEAR(appno_date) > $YEAR
							    GROUP BY appno_doc_num
							";
	                         
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
	                        $designAssetsList = implode(',', $designAssetsFirstPart);
							$currentDateStr = $currentDate->format('Y-m-d');

							$queryExpiredDateDesignAssets = "
								SELECT application, yearDiffer FROM (
								    SELECT application, 
								           IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer
								    FROM (
								        SELECT application, extendedDate, currentDate, 
								               IF(extendedDate <> '', 
								                  TIMESTAMPDIFF(YEAR, grant_date, extendedDate), 
								                  TIMESTAMPDIFF(YEAR, grant_date, currentDate)
								               ) AS yearDiffer
								        FROM (
								            SELECT ap.appno_doc_num AS application, 
								                   grant_date, 
								                   '$currentDateStr' AS currentDate,
								                   IF(ge.extension <> '', 
								                      DATE_ADD(DATE_ADD(grant_date, INTERVAL 14 YEAR), INTERVAL ge.extension DAY), 
								                      ''
								                   ) AS extendedDate
								            FROM db_patent_application_bibliographic.application_grant AS ap
								            LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge 
								                ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci
								            WHERE ap.appno_doc_num IN ($designAssetsList)
								            GROUP BY ap.appno_doc_num
								            
								            UNION
								            
								            SELECT ap.appno_doc_num AS application, 
								                   grant_date, 
								                   '$currentDateStr' AS currentDate,
								                   IF(ge.extension <> '', 
								                      DATE_ADD(DATE_ADD(grant_date, INTERVAL 14 YEAR), INTERVAL ge.extension DAY), 
								                      ''
								                   ) AS extendedDate
								            FROM db_uspto.documentid AS ap
								            LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge 
								                ON ge.appno_doc_num = ap.appno_doc_num
								            WHERE ap.appno_doc_num IN ($designAssetsList)
								            GROUP BY ap.appno_doc_num
								        ) AS temp
								    ) AS temp2
								) AS temp3
								GROUP BY application
								HAVING yearDiffer >= 13
							";
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
	                        $designAssetsList = implode(',', $designAssetsSecondPart);
							$currentDateStr = $currentDate->format('Y-m-d');

							$queryExpiredDateDesignAssets = "
							SELECT application, yearDiffer FROM (
							    SELECT application, 
							           IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer
							    FROM (
							        SELECT application, extendedDate, currentDate,
							               IF(extendedDate <> '',
							                  TIMESTAMPDIFF(YEAR, grant_date, extendedDate),
							                  TIMESTAMPDIFF(YEAR, grant_date, currentDate)
							               ) AS yearDiffer
							        FROM (
							            SELECT ap.appno_doc_num AS application,
							                   grant_date,
							                   '$currentDateStr' AS currentDate,
							                   IF(ge.extension <> '',
							                      DATE_ADD(DATE_ADD(grant_date, INTERVAL 15 YEAR), INTERVAL ge.extension DAY),
							                      ''
							                   ) AS extendedDate
							            FROM db_patent_application_bibliographic.application_grant AS ap
							            LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge
							              ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci
							            WHERE ap.appno_doc_num IN ($designAssetsList)
							            GROUP BY ap.appno_doc_num

							            UNION

							            SELECT ap.appno_doc_num AS application,
							                   grant_date,
							                   '$currentDateStr' AS currentDate,
							                   IF(ge.extension <> '',
							                      DATE_ADD(DATE_ADD(grant_date, INTERVAL 15 YEAR), INTERVAL ge.extension DAY),
							                      ''
							                   ) AS extendedDate
							            FROM db_uspto.documentid AS ap
							            LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge
							              ON ge.appno_doc_num = ap.appno_doc_num
							            WHERE ap.appno_doc_num IN ($designAssetsList)
							            GROUP BY ap.appno_doc_num
							        ) AS combined_results
							    ) AS year_diff_calc
							) AS final_results
							GROUP BY application
							HAVING yearDiffer >= 14
							";


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
                     		$findApplicantPatentsAssets = "
								SELECT MAX(appno_doc_num) AS appno_doc_num
								FROM db_patent_application_bibliographic.application_grant
								WHERE grant_doc_num <> ''
								  AND appno_doc_num IN (".implode(',', $applicantAssets).")
								GROUP BY appno_doc_num
							";
							$resultApplicantGrantApplications = $con->query($findApplicantPatentsAssets); 
	                        if($resultApplicantGrantApplications && $resultApplicantGrantApplications->num_rows > 0) {
	                            while($rowAsset = $resultApplicantGrantApplications->fetch_object()) {
	                                array_push($grantApplicantApplications, '"'.$rowAsset->appno_doc_num.'"');
	                            }
	                        }
	                        if(count($grantApplicantApplications) > 0) {
	                            $queryExpiredStatusAssets = "
									SELECT DISTINCT appno_doc_num AS application 
									FROM db_uspto.application_status  
									WHERE status = 'Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362'  
									  AND appno_doc_num IN (".implode(',', $grantApplicantApplications).")
								";
	    
	                        
	                            $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
	                            if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
	                                while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
	                                    array_push($expiredAssets, '"'.$rowAsset->application.'"');
	                                }
	                            }
	                        }
	                        $queryExpiredStatusAssets = "
								SELECT DISTINCT appno_doc_num AS application
								FROM db_uspto.application_status
								WHERE status IN (
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
								    'Abandonment for Failure to Correct Drawings/Oath/NonPub Request'
								)
								AND appno_doc_num IN (".implode(',', $applicantAssets).")
							";

							// Add NOT IN only if needed
							if (count($grantApplicantApplications) > 0) {
							    $queryExpiredStatusAssets .= " AND appno_doc_num NOT IN (".implode(',', $grantApplicantApplications).") ";
							}

	                         
	                        $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
	                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
	                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
	                                $appLicationNo = '"'.$rowAsset->application.'"'; 
	                                if(!in_array($appLicationNo, $expiredAssets)) { 
	                                    array_push($expiredAssets, $appLicationNo);
	                                }
	                            }
	                        }
	                        $inClause = implode(',', $applicantAssets);
							$currentDateFormatted = $currentDate->format('Y-m-d');

							$queryExpiredDateAssets = "
								SELECT 
								    application, 
								    CASE 
								        WHEN extendedDate > '$currentDateFormatted' THEN 0 
								        ELSE TIMESTAMPDIFF(YEAR, appno_date, COALESCE(extendedDate, '$currentDateFormatted')) 
								    END AS yearDiffer
								FROM (
								    SELECT 
								        ap.appno_doc_num AS application,
								        ap.appno_date,
								        IF(ge.extension IS NOT NULL AND ge.extension <> '', 
								            DATE_ADD(DATE_ADD(ap.appno_date, INTERVAL 20 YEAR), INTERVAL ge.extension DAY),
								            DATE_ADD(ap.appno_date, INTERVAL 20 YEAR)
								        ) AS extendedDate
								    FROM (
								        SELECT appno_doc_num, appno_date FROM db_patent_grant_bibliographic.application_publication WHERE appno_doc_num IN ($inClause)
								        UNION ALL
								        SELECT appno_doc_num, appno_date FROM db_patent_application_bibliographic.application_grant WHERE appno_doc_num IN ($inClause)
								        UNION ALL
								        SELECT appno_doc_num, appno_date FROM db_uspto.documentid WHERE appno_doc_num IN ($inClause)
								    ) AS ap
								    LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num
								) AS combined
								HAVING yearDiffer > 19
							";
	                        
	                        
	                        $resultAllExpiredAssetsList = $con->query($queryExpiredDateAssets); 
	                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
	                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
	                                $appLicationNo = '"'.$rowAsset->application.'"'; 
	                                if(!in_array($appLicationNo, $expiredAssets)) { 
	                                    array_push($expiredAssets, $appLicationNo);
	                                }
	                            }
	                        }
                     	} /* line no 683*/
                     	if(count($expiredAssets) > 0) {
                     		$con->query("DROP TEMPORARY TABLE IF EXISTS temp_expired_assets");
							$con->query("CREATE TEMPORARY TABLE temp_expired_assets (appno_doc_num VARCHAR(50) PRIMARY KEY) ENGINE=MEMORY");
							$values = [];
							foreach ($expiredAssets as $asset) {
							    $values[] = "('" . mysqli_real_escape_string($con, $asset) . "')";
							}
							$chunks = array_chunk($values, 1000);
							foreach ($chunks as $chunk) {
							    $query = "INSERT IGNORE INTO temp_expired_assets (appno_doc_num) VALUES " . implode(',', $chunk);
							    if (!$con->query($query)) {
							        echo $con->error;
							    }
							}
							$queryExpiredWithDate = "
							    INSERT IGNORE INTO {$dbApplication}.assets_with_bank_expired_status
							    (appno_doc_num, status_date, expire_date, company_id, organisation_id)
							    SELECT 
							        t.appno_doc_num, 
							        MAX(t.status_date) AS sDate, 
							        MAX(t.expiry_date) AS exDate, 
							        {$companyID}, 
							        {$organisationID}
							    FROM (
							        SELECT 
							            a.appno_doc_num, 
							            MAX(a.status_date) AS status_date, 
							            '' AS expiry_date
							        FROM db_uspto.application_status a
							        JOIN temp_expired_assets e ON e.appno_doc_num = a.appno_doc_num
							        GROUP BY a.appno_doc_num

							        UNION ALL

							        SELECT 
							            d.appno_doc_num, 
							            '' AS status_date, 
							            DATE_ADD(d.appno_date, INTERVAL 20 YEAR) AS expiry_date
							        FROM db_uspto.documentid d
							        JOIN temp_expired_assets e ON e.appno_doc_num = d.appno_doc_num
							        GROUP BY d.appno_doc_num
							    ) AS t
							    GROUP BY t.appno_doc_num
							";
	                        $con->query($queryExpiredWithDate);
	                    }

	                } /* line no 711*/

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
	                	$queryAllEmployeeAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND appno_doc_num IN (".implode(',', $listAllAssets).")  AND appno_doc_num IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND organisation_id = ".$organisationID." AND activity_id IN ( 10 ) ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";

                		$queryAllEmployeeAssetsList ="
	                		SELECT DISTINCT b.appno_doc_num
								FROM dbUSPTO.table_b b
								JOIN dbUSPTO.documentid d ON b.appno_doc_num = d.appno_doc_num
								JOIN dbApplication.activity_parties_transactions a ON d.rf_id = a.rf_id
								WHERE b.company_id = $companyID
								  AND b.organisation_id = $organisationID
								  AND b.appno_doc_num IN (".implode(',', $listAllAssets).")
								  AND a.company_id = $companyID
								  AND a.organisation_id = $organisationID
								  AND a.activity_id = 10
						";
                    
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


	                $implodeAssetsList = implode(',', $listAllAssets);

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

                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE type IN (".implode(',', $allTypes).") AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);

                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items_count WHERE type IN (".implode(',', $allTypes).") AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);

                    $con->query("DELETE FROM ".$dbApplication.".owned_assets WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);

                    if(count($originalList) > 0  || count($expiredAssets) > 0 || count($soldAssets) > 0 || count($applicantAssets) > 0 ) {  
                    	/**
	                     * Assets Acquired 
	                     */
	                    $type = 32;
	                    $acquiredAcitivityID = implode(',', array(1,6));

                    	echo "AAA: ".count($patentedAssetsStatus)."asd";
	                    if(count($patentedAssetsStatus) > 0) {
	                        
                        	$queryPatentAcquired = "
	                        	INSERT IGNORE INTO dbApplication.dashboard_items
								  (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)
								SELECT
								  $organisationID AS organisation_id,
								  $companyID AS representative_id,
								  0 AS assignor_id,
								  $type AS type,
								  MAX(d.grant_doc_num) AS patent,
								  d.appno_doc_num AS application,
								  0 AS rf_id,
								  0 AS total
								FROM dbUSPTO.documentid AS d
								WHERE d.appno_date >= STR_TO_DATE(CONCAT($YEAR + 1, '-01-01'), '%Y-%m-%d')
								  AND d.appno_doc_num IN ($implodePatentedAssetsList)
								GROUP BY d.appno_doc_num
							";

	                        echo $queryPatentAcquired ; 
	                    
	                        $con->query($queryPatentAcquired); 
	                    }

	                    /**
	                     * Filled Assets Applicant
	                     */
                    
                    	if(count($applicantAssets) > 0 || count($employeeAssets) > 0) {
                    		$type = 31;
                        	$applicantAndEmployee = array_merge($applicantAssets, $employeeAssets);  
                        	$queryApplicantPatent = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT DISTINCT ".$organisationID.", ".$companyID.", 0, ".$type.", d.grant_doc_num, d.appno_doc_num, 0, 0 FROM db_patent_application_bibliographic.application_grant AS d  WHERE d.appno_date >= STR_TO_DATE(CONCAT({$YEAR} + 1, '-01-01'), '%Y-%m-%d') AND d.appno_doc_num IN (".implode(',', $applicantAndEmployee).") ";

                            if(count($soldAssets) > 0) {
                                $queryApplicantPatent .= " AND d.appno_doc_num NOT IN (".implode(',', $soldAssets).") ";
                            }
    
                            if(count($expiredAssets) > 0) {
                                $queryApplicantPatent .= " AND d.appno_doc_num NOT IN (".implode(',', $expiredAssets).") ";
                            }
                                
                            echo $queryApplicantPatent."<br/>";
                          
                            $con->query($queryApplicantPatent); 


                            $queryEmployeePatent = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT DISTINCT ".$organisationID.", ".$companyID.", 0, ".$type.", d.grant_doc_num, d.appno_doc_num, 0, 0 FROM db_uspto.documentid AS d  WHERE d.appno_date >= STR_TO_DATE(CONCAT({$YEAR} + 1, '-01-01'), '%Y-%m-%d') AND d.appno_doc_num IN (".implode(',', $applicantAndEmployee).") AND d.appno_doc_num NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = ".$type.")";

                            if(count($soldAssets) > 0) {
                                $queryEmployeePatent .= " AND d.appno_doc_num NOT IN (".implode(',', $soldAssets).") ";
                            }
    
                            if(count($expiredAssets) > 0) {
                                $queryEmployeePatent .= " AND d.appno_doc_num NOT IN (".implode(',', $expiredAssets).") ";
                            }
                            
                            $con->query($queryEmployeePatent); 
                        
 
                            $queryApplicantApplication = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) 
	                            SELECT DISTINCT
								  {$organisationID},
								  {$companyID},
								  0,
								  {$type},
								  '',
								  d.appno_doc_num,
								  0,
								  0
								FROM db_patent_grant_bibliographic.application_publication AS d  
								WHERE d.appno_date >= STR_TO_DATE(CONCAT({$YEAR} + 1, '-01-01'), '%Y-%m-%d') 
									AND d.appno_doc_num IN (".implode(',', $applicantAndEmployee).")  
									AND d.appno_doc_num 
	                            	AND NOT EXISTS (
									    SELECT 1
									    FROM dbApplication.dashboard_items AS dash
									    WHERE dash.organisation_id = {$organisationID}
									      AND dash.representative_id = {$companyID}
									      AND dash.type = {$type}
									      AND dash.application = d.appno_doc_num
									)  
	                        ";

                            if(count($soldAssets) > 0) {
                                $queryApplicantApplication .= " AND d.appno_doc_num NOT IN (".implode(',', $soldAssets).") ";
                            }

                            if(count($expiredAssets) > 0) {
                                $queryApplicantApplication .= " AND d.appno_doc_num NOT IN (".implode(',', $expiredAssets).") ";
                            }
                            
                            
                            echo $queryApplicantApplication."<br/>";
                            $con->query($queryApplicantApplication);  

                    	} /* line no 991 */

                    	/**
	                     * Owned Patents
	                    */
	                    $type = 30; 

						$queryOwnedPatent = "
							INSERT IGNORE INTO {$dbApplication}.dashboard_items
							  (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)
							SELECT DISTINCT
							  {$organisationID}, {$companyID}, 0, {$type}, patent, application, 0, 0
							FROM {$dbApplication}.dashboard_items
							WHERE type IN (31, 32)
							  AND organisation_id = {$organisationID}
							  AND representative_id = {$companyID}
						";

						$con->query($queryOwnedPatent);

						if (count($listAllAssets) > 0) {
						    $queryOwnedAssets = "
							    INSERT IGNORE INTO {$dbApplication}.owned_assets (appno_doc_num, company_id, organisation_id)
							    SELECT DISTINCT application, {$companyID}, {$organisationID}
							    FROM {$dbApplication}.dashboard_items
							    WHERE representative_id = {$companyID}
							      AND organisation_id = {$organisationID}
							      AND type = {$type}
						    ";
						    $con->query($queryOwnedAssets);
						}
						/**
	                     * Assets Divested
	                     */
	                    if(count($soldAssets) > 0) {
	                    	$type = 33;
	                    	$yearThreshold = ($YEAR + 1)."-01-01";
	                    	$soldAssetsList = implode(',', $soldAssets);

	                        $querySoldAsset = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", MAX(d.grant_doc_num), MAX(d.appno_doc_num), 0, 0 FROM ".$dbUSPTO.".documentid AS d WHERE d.appno_date >= '{$yearThreshold}' AND d.appno_doc_num IN (".$soldAssetsList.")  GROUP BY d.appno_doc_num";
	                        $con->query($querySoldAsset);

	                        $querySoldAsset = "
							    INSERT IGNORE INTO {$dbApplication}.dashboard_items 
							    (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)
							    SELECT 
							        {$organisationID}, 
							        {$companyID}, 
							        0, 
							        {$type}, 
							        d.grant_doc_num, 
							        d.appno_doc_num, 
							        0, 
							        0
							    FROM db_patent_application_bibliographic.application_grant AS d
							    LEFT JOIN {$dbApplication}.dashboard_items AS di
							        ON d.appno_doc_num = di.application
							        AND di.organisation_id = {$organisationID}
							        AND di.representative_id = {$companyID}
							        AND di.type = {$type}
							    WHERE 
							        d.appno_date >= '{$yearThreshold}'
							        AND d.appno_doc_num IN ({$soldAssetsList})
							        AND di.application IS NULL
							";

							$con->query($querySoldAsset);

							$querySoldAsset = "
							    INSERT IGNORE INTO {$dbApplication}.dashboard_items 
							    (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)
							    SELECT 
							        {$organisationID}, 
							        {$companyID}, 
							        0, 
							        {$type}, 
							        d.grant_doc_num, 
							        d.appno_doc_num, 
							        0, 
							        0
							    FROM db_patent_grant_bibliographic.application_publication AS d
							    LEFT JOIN {$dbApplication}.dashboard_items AS di
							        ON d.appno_doc_num = di.application
							        AND di.organisation_id = {$organisationID}
							        AND di.representative_id = {$companyID}
							        AND di.type = 33
							    WHERE 
							        d.appno_date >= '{$yearThreshold}'
							        AND d.appno_doc_num IN ({$soldAssetsList})
							        AND di.application IS NULL
							";

							$con->query($querySoldAsset);

	                    } /* line no 1067 */

	                    $yearThreshold = ($YEAR + 1)."-01-01";
	                    /**
	                     *  Assets Abandoned
	                     */
	                    if(count($expiredAssets) > 0) { 
	                        $mergeFilledAndOTAAssets = array_merge($originalApplicantAssets, $ownedAfterSold); 


	                        $grantApplications = array();

	                        if(count($mergeFilledAndOTAAssets) > 0) {
	                            $implodeAssets = implode(',', $mergeFilledAndOTAAssets);

							    $findPatentsAssets = "
							        SELECT MAX(appno_doc_num) AS appno_doc_num 
							        FROM db_uspto.documentid 
							        WHERE grant_doc_num <> '' 
							          AND appno_doc_num IN ($implodeAssets)
							        GROUP BY appno_doc_num 

							        UNION

							        SELECT MAX(appno_doc_num) AS appno_doc_num 
							        FROM db_patent_application_bibliographic.application_grant 
							        WHERE appno_doc_num IN ($implodeAssets)
							        GROUP BY appno_doc_num
							    ";

							    $resultGrantApplications = $con->query($findPatentsAssets);

							    if ($resultGrantApplications && $resultGrantApplications->num_rows > 0) {
							        $grantApplications = [];
							        while ($rowAsset = $resultGrantApplications->fetch_object()) {
							            $grantApplications[] = '"' . $rowAsset->appno_doc_num . '"';
							        }
							    }
	                        }

	                        $remainingAssets = array();
	                        if(count($grantApplications) > 0) {
	                            $queryExpiredStatusAssets = "SELECT DISTINCT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362') AND appno_doc_num IN (".implode(',', $grantApplications).") ";
	    
	                        
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
	                        
	                        'Abandonment for Failure to Correct Drawings/Oath/NonPub Request') GROUP BY  appno_doc_num) AS temp ";
	                        
	                        $resultRemainingAssetsList = $con->query($queryAbandonedAssets);
	                        
	                        if($resultRemainingAssetsList && $resultRemainingAssetsList->num_rows > 0) {
	                            while($rowAsset = $resultRemainingAssetsList->fetch_object()) {
	                                array_push($remainingAssets, '"'.$rowAsset->appno_doc_num.'"');
	                            }
	                        } 
	                        
	                        if(count($remainingAssets) > 0) {
	                            $type = 36;
	                            $remainingAssetsList = implode(',', $remainingAssets);
	                            $queryPendingApplications = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, 0  FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE d.appno_date >= ".$yearThreshold."  AND d.appno_doc_num IN ({$remainingAssetsList}) AND apt.organisation_id = ".$organisationID." AND company_id = ".$companyID." GROUP BY d.appno_doc_num ";
								
	                            $con->query($queryPendingApplications);



	                            $publicationAndGrantQuery = "
							        INSERT IGNORE INTO {$dbApplication}.dashboard_items 
							            (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)
							        SELECT 
							            {$organisationID}, {$companyID}, 0, {$type}, grant_doc_num, appno_doc_num, 0, 0
							        FROM (
							            SELECT grant_doc_num, appno_doc_num 
							            FROM db_patent_application_bibliographic.application_grant 
							            WHERE appno_date > {$yearThreshold} 
							              AND appno_doc_num IN ({$remainingAssetsList})
							            UNION
							            SELECT grant_doc_num, appno_doc_num 
							            FROM db_patent_application_bibliographic.application_publication 
							            WHERE appno_date > {$yearThreshold} 
							              AND appno_doc_num IN ({$remainingAssetsList})
							        ) AS combined
							        WHERE appno_doc_num NOT IN (
							            SELECT application 
							            FROM {$dbApplication}.dashboard_items 
							            WHERE organisation_id = {$organisationID} 
							              AND representative_id = {$companyID} 
							              AND type = {$type}
							        )
							        GROUP BY appno_doc_num
							    ";
							    $con->query($publicationAndGrantQuery);
	                        }
	                    }

	                    /**
	                     * Top Proliferate Inventors
	                     */
	                    $type = 39;
	                    $implodeOriginalAssets = implode(',', $originalApplicantAssets);
	                    $queryTopInventor ="INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, name, assignor_id, type, patent, application ) SELECT ".$organisationID.", ".$companyID.", IF(representative_name <> '' , representative_name, aaa.name), aaa.assignor_and_assignee_id,  ".$type.", ag.grant_doc_num, ag.appno_doc_num FROM db_patent_application_bibliographic.inventor AS inv 
		                    INNER JOIN db_patent_application_bibliographic.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = inv.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id
		                    INNER JOIN db_patent_application_bibliographic.application_grant AS ag ON BINARY ag.appno_doc_num = BINARY inv.appno_doc_num 
		                    WHERE inv.appno_doc_num IN (".$implodeOriginalAssets.") GROUP BY aaa.name, ag.appno_doc_num ";
		                     
	                    $con->query($queryTopInventor);



	                    $queryTopInventor ="INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, name, assignor_id, type, patent, application ) SELECT ".$organisationID.", ".$companyID.", IF(representative_name <> '' , representative_name, aaa.name), aaa.assignor_and_assignee_id, ".$type.", '' AS patent, ap.appno_doc_num FROM db_patent_grant_bibliographic.inventor_new AS inv INNER JOIN db_patent_application_bibliographic.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = inv.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id
	                    INNER JOIN db_patent_application_bibliographic.application_publication AS ap ON ap.appno_doc_num = inv.appno_doc_num
	                    WHERE inv.appno_doc_num IN (".$implodeOriginalAssets.") AND inv.appno_doc_num NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = ".$type." GROUP BY application) GROUP BY aaa.name, ap.appno_doc_num ";
	                     
	                    $con->query($queryTopInventor);


	                    $implodeAssignorAndAssigneeIDs = implode(',', $companyAssignorAndAssigneeIDs);
	                    /**
	                     * Top Law Firms
	                     */
	                    $type = 40;
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
	                        where ass.assignor_and_assignee_id IN (".$implodeAssignorAndAssigneeIDs.") AND apt.organisation_id = ".$organisationID." and apt.company_id = ".$companyID." AND apt.exec_dt >= '2000-01-01' AND c.cname <> ''
	                        GROUP BY apt.rf_id) AS temp GROUP BY rf_id, lawfirm";
	                    
	                    $con->query($queryLawFirms);


                    	/**
	                     * Top Lenders
	                     */
	                    $type = 41;
	                    $queryLenders = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT  ".$organisationID.", ".$companyID.", apt.assignor_and_assignee_id, ".$type.", d.grant_doc_num,  d.appno_doc_num, apt.rf_id, 0 FROM ".$dbUSPTO.".documentid AS d 
	                            JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id
	                            WHERE apt.recorded_assignor_and_assignee_id IN (".$implodeAssignorAndAssigneeIDs.") 
	                            AND d.appno_doc_num IN (".$implodePatentedAssetsList.") AND d.appno_date >= ".$yearThreshold."  AND apt.activity_id IN (5, 12) AND apt.organisation_id = ".$organisationID." AND apt.company_id = ".$companyID."
	                            GROUP BY apt.rf_id, apt.assignor_and_assignee_id";

	                    $con->query($queryLenders);

                     	/**
                 		 * Collateralized
	                     * Show all the assets which are not released yet
	                     */
	                    $type = 34;
	                    $securityActivityID = implode(',', array(5, 12));

	                    // Step 1: Get all client-owned assets (type = 30)
						$clientOwnedQuery = "
						    SELECT DISTINCT application 
						    FROM {$dbApplication}.dashboard_items
						    WHERE organisation_id = {$organisationID}
						      AND representative_id = {$companyID}
						      AND type = 30
						";

						$resultClientOwned = $con->query($clientOwnedQuery);
						$clientOwnedAssets = [];
						if ($resultClientOwned && $resultClientOwned->num_rows > 0) {
						    while ($row = $resultClientOwned->fetch_object()) {
						        $clientOwnedAssets[] = "'" . $con->real_escape_string($row->application) . "'";
						    }
						}

						if(count($clientOwnedAssets) > 0) {
							$implodedOWNEDAssetsLIST = implode(',', $clientOwnedAssets);
							// Step 2: Find secured applications that haven't been fully released
							/*$seQuery = "SELECT tempSecurity.appno_doc_num FROM (SELECT appNo AS appno_doc_num, eeName, count(rf_id) AS counter, 'security' as type FROM (
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
		                        ) as tempRelease ON tempRelease.appno_doc_num = tempSecurity.appno_doc_num AND tempRelease.eeName = tempSecurity.eeName WHERE (tempRelease.eeName IS NULL AND tempSecurity.eeName <> '') OR (tempSecurity.counter > tempRelease.counter) GROUP BY tempSecurity.appno_doc_num"; */

		                    $seQuery = "
								SELECT
								    sec.appno_doc_num
								FROM
								    (
								        SELECT
								            doc.appno_doc_num,
								            IF(r.representative_id <> '', r.representative_name, aaa.name) AS eeName,
								            COUNT(*) AS counter
								        FROM
								            assignee AS ee
								            INNER JOIN db_new_application.activity_parties_transactions AS apt ON apt.rf_id = ee.rf_id
								            INNER JOIN documentid AS doc ON doc.rf_id = ee.rf_id
								            INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id
								            INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
								            LEFT JOIN representative AS r ON r.representative_id = aaa.representative_id
								        WHERE
								            apt.organisation_id = {$organisationID}
								            AND rac.convey_ty IN ('security', 'restatedsecurity')
								            AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
								        GROUP BY
								            doc.appno_doc_num, eeName
								    ) AS sec
								LEFT JOIN
								    (
								        SELECT
								            doc.appno_doc_num,
								            IF(r.representative_id <> '', r.representative_name, aaa.name) AS eeName,
								            COUNT(*) AS counter
								        FROM
								            assignor AS aor
								            INNER JOIN db_new_application.activity_parties_transactions AS apt ON apt.rf_id = aor.rf_id
								            INNER JOIN documentid AS doc ON doc.rf_id = aor.rf_id
								            INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id
								            INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id
								            LEFT JOIN representative AS r ON r.representative_id = aaa.representative_id
								        WHERE
								            apt.organisation_id = {$organisationID}
								            AND rac.convey_ty IN ('release', 'partialrelease')
								            AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
								        GROUP BY
								            doc.appno_doc_num, eeName
								    ) AS rel
								    ON sec.appno_doc_num = rel.appno_doc_num AND sec.eeName = rel.eeName
								WHERE
								    (rel.eeName IS NULL AND sec.eeName <> '') OR (sec.counter > rel.counter)
								GROUP BY
								    sec.appno_doc_num
							";
							$resultseQuery = $con->query($seQuery) ;

		                    $collaterializedAssets = array();
		                    if($resultseQuery && $resultseQuery->num_rows > 0) {
		                        while($row = $resultseQuery->fetch_object()) {
		                            array_push($collaterializedAssets, '"'.$row->appno_doc_num.'"');
		                        }
		                    } 

		                    if(count($collaterializedAssets) > 0) {
		                    	$yearThreshold = ($YEAR + 1)."-01-01";
		                    	$queryCollateralized = "
							        INSERT IGNORE INTO {$dbApplication}.dashboard_items (
							            organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total
							        )
							        SELECT
							            {$organisationID},
							            {$companyID},
							            0,
							            {$type},
							            MAX(d.grant_doc_num) AS patent,
							            d.appno_doc_num AS application,
							            0,
							            0
							        FROM
							            {$dbUSPTO}.documentid AS d
							        WHERE
							            d.appno_date > $yearThreshold ;
							            AND d.appno_doc_num IN ({$collaterializedAssets})
							        GROUP BY
							            d.appno_doc_num
							    ";
							    $con->query($queryCollateralized);
		                    }

		                    /**
	                     	*  Maintainance Budget
		                     */
		                    $type = 35;
		                    $currentDate = new DateTime('now');
		                    $FORMAT = 'Y-m-d';
		                    $graceEndDate = $currentDate->modify('+6 month')->format($FORMAT);
		                    $currentDate = new DateTime('now');
		                    $dueDate = $currentDate->modify('-6 month')->format($FORMAT);
		                    $currentDate = new DateTime('now');
		                    $formatCurrentDate = $currentDate->format($FORMAT);

		                    $defaultEntityStatus = 'N';

		                    $queryMaintainenceBudget = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, event_code, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, tempAll.event_code, emcf.fees_amount  FROM ( SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, CASE WHEN fees = '3.5' AND status = 'N' THEN 'M1551' WHEN fees = '3.5' AND status = 'Y' THEN 'M2551'  WHEN fees = '3.5' AND status = 'M' THEN 'M3551'  WHEN fees = '7.5' AND status = 'N' THEN 'M1552'  WHEN fees = '7.5' AND status = 'Y' THEN 'M2552'  WHEN fees = '7.5' AND status = 'M' THEN 'M3552'  WHEN fees = '11.5' AND status = 'N' THEN 'M1553'  WHEN fees = '11.5' AND status = 'Y' THEN 'M2553' WHEN fees = '11.5' AND status = 'M' THEN 'M3553' ELSE '' END AS event_code FROM (SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '3.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 42 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 54 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
		                        WHERE appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND event_code IN ('M1551','M2551', 'M3551', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '7.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 90 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 102 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate  FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
		                        WHERE appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND event_code IN ('M1552','M2552', 'M3552', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '11.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 138 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 150 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
		                        WHERE appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND event_code IN ('M1553','M2553', 'M3553', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND  currentDate >= due_date AND currentDate <= grace_end_date ) AS temp1) AS tempAll INNER JOIN db_patent_maintainence_fee.event_maintainence_code_fees AS emcf ON emcf.event_code = tempAll.event_code";
		                        //echo $queryMaintainenceBudget;
    
                        
	                        $con->query($queryMaintainenceBudget);


	                        $queryMaintainenceBudget = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, event_code, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, tempAll.event_code, emcf.fees_amount  FROM ( SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, CASE WHEN fees = '3.5' AND status = 'N' THEN 'M1551' WHEN fees = '3.5' AND status = 'Y' THEN 'M2551'  WHEN fees = '3.5' AND status = 'M' THEN 'M3551'  WHEN fees = '7.5' AND status = 'N' THEN 'M1552'  WHEN fees = '7.5' AND status = 'Y' THEN 'M2552'  WHEN fees = '7.5' AND status = 'M' THEN 'M3552'  WHEN fees = '11.5' AND status = 'N' THEN 'M1553'  WHEN fees = '11.5' AND status = 'Y' THEN 'M2553' WHEN fees = '11.5' AND status = 'M' THEN 'M3553' ELSE '' END AS event_code FROM (SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '3.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 42 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 54 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_patent_application_bibliographic.application_grant AS doc WHERE doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
	                        WHERE appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND event_code IN ('M1551','M2551', 'M3551', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '7.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 90 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 102 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate  FROM db_patent_application_bibliographic.application_grant AS doc WHERE doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
	                        WHERE appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND event_code IN ('M1552','M2552', 'M3552', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '11.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 138 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 150 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_patent_application_bibliographic.application_grant AS doc WHERE doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
	                        WHERE appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND event_code IN ('M1553','M2553', 'M3553', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND  currentDate >= due_date AND currentDate <= grace_end_date ) AS temp1) AS tempAll INNER JOIN db_patent_maintainence_fee.event_maintainence_code_fees AS emcf ON emcf.event_code = tempAll.event_code WHERE appno_doc_num NOT IN (SELECT application FROM db_new_application.dashboard_items
	                        where organisation_id = ".$organisationID." AND representative_id = ".$companyID." AND type = ".$type.")";

                        
	                        $con->query($queryMaintainenceBudget);



	                        $con->query("DELETE FROM db_new_application.maintainence_assets WHERE organisation_id = ".$organisationID." AND company_id = ".$companyID);

	                        $con->query("INSERT IGNORE INTO db_new_application.maintainence_assets(organisation_id, company_id, asset, asset_type, grant_doc_num, appno_doc_num, grant_date, payment_due, payment_grace, fee_code, fee_amount, type, fee_code_surcharge, fee_surcharge)
	                        SELECT ".$organisationID.", ".$companyID.", grant_doc_num, 0, grant_doc_num, appno_doc_num, grant_date, due_date, grace_end_date, CASE WHEN tempAll.event_code = 'M1551' THEN 1551 WHEN tempAll.event_code = 'M2551' THEN 2551 WHEN tempAll.event_code = 'M3551' THEN 3551 WHEN tempAll.event_code = 'M1552' THEN 1552 WHEN tempAll.event_code = 'M2552' THEN 2552 WHEN tempAll.event_code = 'M3552' THEN 3552 WHEN tempAll.event_code = 'M1553' THEN 1553  WHEN tempAll.event_code = 'M2553' THEN 2553 WHEN tempAll.event_code = 'M3553' THEN 3553 ELSE 0 END, emcf.fees_amount, CASE WHEN tempAll.event_code = 'M1551' THEN 1 WHEN tempAll.event_code = 'M2551' THEN 1 WHEN tempAll.event_code = 'M3551' THEN 1 WHEN tempAll.event_code = 'M1552' THEN 2 WHEN tempAll.event_code = 'M2552' THEN 2 WHEN tempAll.event_code = 'M3552' THEN 2 WHEN tempAll.event_code = 'M1553' THEN 3  WHEN tempAll.event_code = 'M2553' THEN 3 WHEN tempAll.event_code = 'M3553' THEN 3 ELSE 0 END AS type, CASE WHEN tempAll.event_code = 'M1551' THEN 1554 WHEN tempAll.event_code = 'M2551' THEN 2554 WHEN tempAll.event_code = 'M3551' THEN 3554 WHEN tempAll.event_code = 'M1552' THEN 1555 WHEN tempAll.event_code = 'M2552' THEN 2555 WHEN tempAll.event_code = 'M3552' THEN 3555 WHEN tempAll.event_code = 'M1553' THEN 1556  WHEN tempAll.event_code = 'M2553' THEN 2556 WHEN tempAll.event_code = 'M3553' THEN 3556 ELSE 0 END AS surcharge_code,
	                        CASE WHEN tempAll.event_code = 'M1551' THEN 500 WHEN tempAll.event_code = 'M2551' THEN 250 WHEN tempAll.event_code = 'M3551' THEN 125 WHEN tempAll.event_code = 'M1552' THEN 500 WHEN tempAll.event_code = 'M2552' THEN 250 WHEN tempAll.event_code = 'M3552' THEN 125 WHEN tempAll.event_code = 'M1553' THEN 500  WHEN tempAll.event_code = 'M2553' THEN 250 WHEN tempAll.event_code = 'M3553' THEN 125 ELSE 0 END AS surcharge_amount  FROM ( SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, CASE WHEN fees = '3.5' AND status = 'N' THEN 'M1551' WHEN fees = '3.5' AND status = 'Y' THEN 'M2551'  WHEN fees = '3.5' AND status = 'M' THEN 'M3551'  WHEN fees = '7.5' AND status = 'N' THEN 'M1552'  WHEN fees = '7.5' AND status = 'Y' THEN 'M2552'  WHEN fees = '7.5' AND status = 'M' THEN 'M3552'  WHEN fees = '11.5' AND status = 'N' THEN 'M1553'  WHEN fees = '11.5' AND status = 'Y' THEN 'M2553' WHEN fees = '11.5' AND status = 'M' THEN 'M3553' ELSE '' END AS event_code, due_date, grace_end_date FROM (SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status, due_date, grace_end_date FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '3.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 42 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 54 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
	                        WHERE appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND event_code IN ('M1551','M2551', 'M3551', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status, due_date, grace_end_date FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '7.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 90 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 102 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate  FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
	                        WHERE appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND event_code IN ('M1552','M2552', 'M3552', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status, due_date, grace_end_date FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '11.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 138 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 150 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
	                        WHERE appno_doc_num IN (".$implodedOWNEDAssetsLIST.") AND event_code IN ('M1553','M2553', 'M3553', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND  currentDate >= due_date AND currentDate <= grace_end_date ) AS temp1) AS tempAll INNER JOIN db_patent_maintainence_fee.event_maintainence_code_fees AS emcf ON emcf.event_code = tempAll.event_code");

							
							$sql = "
							    DELETE FROM db_uspto.temp_application_inventor_count 
							    WHERE company_id = {$companyID} AND organisation_id = {$organisationID};

							    DELETE FROM db_uspto.temp_application_employee_count 
							    WHERE company_id = {$companyID} AND organisation_id = {$organisationID};

							    DELETE FROM db_new_application.assets 
							    WHERE layout_id = 1 AND company_id = {$companyID} AND organisation_id = {$organisationID};

							    DELETE FROM db_new_application.dashboard_items 
							    WHERE type = 1 AND representative_id = {$companyID} AND organisation_id = {$organisationID};
							";

							if ($con->multi_query($sql)) {
							    do {
							        if ($result = $con->store_result()) {
							            $result->free();
							        }
							    } while ($con->more_results() && $con->next_result());
							} else {
							    $con->query("DELETE FROM  `db_uspto`.`temp_application_inventor_count` WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);
			                    $con->query("DELETE FROM  `db_uspto`.`temp_application_employee_count` WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);
			                    $con->query("DELETE FROM db_new_application.assets WHERE layout_id = 1 AND company_id = ".$companyID." AND organisation_id = ".$organisationID);
			                    $con->query("DELETE FROM db_new_application.dashboard_items WHERE type = 1 AND representative_id = ".$companyID." AND organisation_id = ".$organisationID);
							}
							
							echo "OWNED APPLICATION";
							
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
	                        $queryBrokenChain = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)SELECT ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type." AS type, grant_doc_num, appno_doc_num, 0 AS rf_id, ".count($clientOwnedAssets)."  FROM db_new_application.assets AS assets  WHERE assets.appno_date >= '2000-01-01' AND assets.layout_id = 1 AND assets.company_id = ".$companyID." AND assets.organisation_id = ".$organisationID." AND appno_doc_num IN (".implode(',', $clientOwnedAssets).") GROUP BY appno_doc_num";

	                        $con->query($queryBrokenChain); 
							
		                     /**
		                     * Names
		                     */
		                    $type = 17;
		                    $queryIncorrectNames = "
								INSERT IGNORE INTO {$dbApplication}.dashboard_items (
								    organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total
								)
								SELECT 
								    {$organisationID} AS organisation_id,
								    {$companyID} AS representative_id,
								    apt.recorded_assignor_and_assignee_id AS assignor_id,
								    {$type} AS type,
								    MAX(doc.grant_doc_num) AS patent,
								    MAX(doc.appno_doc_num) AS application,
								    rac.rf_id,
								    ".count($clientOwnedAssets)." AS total
								FROM db_new_application.activity_parties_transactions AS apt
								INNER JOIN db_uspto.documentid AS doc 
								    ON doc.rf_id = apt.rf_id
								INNER JOIN db_uspto.representative_assignment_conveyance AS rac 
								    ON rac.rf_id = apt.rf_id
								INNER JOIN db_uspto.assignee AS ass 
								    ON ass.rf_id = rac.rf_id
								INNER JOIN db_uspto.conveyance AS con 
								    ON con.convey_name = rac.convey_ty AND con.is_ota = 1
								INNER JOIN db_uspto.assignor_and_assignee AS aaa 
								    ON aaa.assignor_and_assignee_id = ass.assignor_and_assignee_id
								INNER JOIN db_uspto.representative AS rep 
								    ON rep.representative_id = aaa.representative_id
								WHERE 
								    apt.company_id = {$companyID}
								    AND apt.organisation_id = {$organisationID}
								    AND doc.appno_doc_num IN (".$implodedOWNEDAssetsLIST.")
								    AND rep.representative_name <> ''
								    AND LOWER(aaa.name) <> LOWER(rep.representative_name)
								GROUP BY 
								    apt.recorded_assignor_and_assignee_id, 
								    doc.appno_doc_num, 
								    rac.rf_id
								";
							$con->query($queryIncorrectNames);

							/**
		                     * Encumbrances
		                    */   
		                    $type = 18;
		                    $encumberedAssets = array();
		                    $queryEncumbrances = "
								INSERT IGNORE INTO {$dbApplication}.dashboard_items (
								    organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total
								)
								SELECT 
								    {$organisationID} AS organisation_id,
								    {$companyID} AS representative_id,
								    aor.assignor_and_assignee_id,
								    {$type} AS type,
								    d.grant_doc_num,
								    d.appno_doc_num,
								    rac.rf_id,
								    ".count($clientOwnedAssets)." AS total
								FROM db_uspto.documentid AS d
								INNER JOIN db_uspto.representative_assignment_conveyance AS rac 
								    ON rac.rf_id = d.rf_id 
								    AND rac.convey_ty IN (
								        'license', 'courtappointment', 'courtorder', 'govern', 'option', 'other'
								    )
								INNER JOIN db_new_application.activity_parties_transactions AS aor 
								    ON aor.rf_id = rac.rf_id 
								WHERE 
								    d.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
								    AND aor.recorded_assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).")
								GROUP BY 
								    d.appno_doc_num
								";
							$con->query($queryEncumbrances);

							/**
		                     * Addresses
		                     */
		                    $type = 19;
		                    $con->query("DELETE FROM ".$dbApplication.".dashboard_items WHERE mode = 1 AND type = ".$type." AND representative_id = ".$companyID);
		                    $queryCollateralized = "
								INSERT IGNORE INTO {$dbApplication}.dashboard_items (
								    mode, representative_id, assignor_id, type, patent, application, rf_id, total
								)
								SELECT 
								    1 AS mode,
								    {$companyID} AS representative_id,
								    0 AS assignor_id,
								    {$type} AS type,
								    patent_number,
								    application_number,
								    '' AS rf_id,
								    " . count($clientOwnedAssets) . " AS total
								FROM {$dbApplication}.dashboard_items
								WHERE 
								    representative_id = {$companyID}
								    AND type = 30
								    AND mode = 0
								    AND application_number NOT IN (
								        SELECT application_number 
								        FROM {$dbApplication}.dashboard_items 
								        WHERE representative_id = {$companyID} AND type = 34
								    )
								";
							$con->query($queryCollateralized);

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

		                    $yearThreshold = ($YEAR + 1)."-01-01";

		                    $queryCPCSection = "
								SELECT section FROM (
								    -- Section from patent applications with granted numbers
								    SELECT cpc.section 
								    FROM db_patent_application_bibliographic.patent_cpc AS cpc
								    INNER JOIN (
								        SELECT doc.appno_doc_num 
								        FROM db_uspto.documentid AS doc 
								        WHERE doc.appno_date >= {$yearThreshold} 
								          AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
								          AND doc.grant_doc_num <> ''
								        GROUP BY doc.appno_doc_num
								    ) AS granted 
								    ON granted.appno_doc_num = cpc.application_number 
								    WHERE cpc.type = 0 
								    GROUP BY granted.appno_doc_num, cpc.section

								    UNION

								    -- Section from patent applications without granted numbers
								    SELECT cpc.section 
								    FROM db_patent_grant_bibliographic.application_cpc AS cpc
								    INNER JOIN (
								        SELECT doc.appno_doc_num 
								        FROM db_uspto.documentid AS doc 
								        WHERE doc.appno_date >= {$yearThreshold}
								          AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
								          AND doc.grant_doc_num = ''
								        GROUP BY doc.appno_doc_num
								    ) AS not_granted 
								    ON not_granted.appno_doc_num = cpc.application_number 
								    WHERE cpc.type = 0 
								    GROUP BY not_granted.appno_doc_num, cpc.section
								) AS sections
								GROUP BY section
								ORDER BY section ASC
							";

		                    $allSections = array();
		                    $resultAllSections = $con->query($queryCPCSection);
		                    if($resultAllSections && $resultAllSections->num_rows > 0) {
		                        while($rowAsset = $resultAllSections->fetch_object()) {
		                            array_push($allSections, '"'.$rowAsset->section.'"');
		                        }
		                    } 

		                    $unNecessarySection = [];

							$queryUnnecessarySections = "SELECT 
							    section,
							    SUM(CASE WHEN app_year BETWEEN {$pastYear3} AND {$currentYear} THEN 1 ELSE 0 END) AS count_3_years,
							    SUM(CASE WHEN app_year BETWEEN {$pastYear5} AND {$currentYear} THEN 1 ELSE 0 END) AS count_5_years
							FROM (
							    SELECT cpc.section, YEAR(doc.appno_date) AS app_year
							    FROM db_patent_application_bibliographic.patent_cpc AS cpc
							    INNER JOIN db_uspto.documentid AS doc 
							        ON doc.appno_doc_num = cpc.application_number
							    WHERE cpc.type = 0 AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})

							    UNION ALL

							    SELECT cpc.section, YEAR(doc.appno_date) AS app_year
							    FROM db_patent_grant_bibliographic.application_cpc AS cpc
							    INNER JOIN db_uspto.documentid AS doc 
							        ON doc.appno_doc_num = cpc.application_number
							    WHERE cpc.type = 0 AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
							) AS section_year_data
							GROUP BY section
							HAVING count_3_years = 0 OR count_5_years <= 5
							ORDER BY section";

							$resultUnnecessary = $con->query($queryUnnecessarySections);
							if ($resultUnnecessary && $resultUnnecessary->num_rows > 0) {
							    while ($row = $resultUnnecessary->fetch_object()) {
							        $unNecessarySection[] = '"' . $row->section . '"';
							    }
							}

		                    if(count($unNecessarySection) > 0) {
		                    	$sectionList = implode(',', $unNecessarySection);
		                    	$totalAssets = count($clientOwnedAssets); 

		                        $queryUnNecessaryPatents = "
							        INSERT IGNORE INTO {$dbApplication}.dashboard_items 
							            (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)
							        SELECT 
							            {$organisationID} AS organisation_id,
							            {$companyID} AS representative_id,
							            0 AS assignor_id,
							            {$type} AS type,
							            temp.grant_doc_num AS patent,
							            temp.appno_doc_num AS application,
							            '' AS rf_id,
							            {$totalAssets} AS total
							        FROM (
							            -- Patents with grant numbers (granted applications)
							            SELECT 
							                cpc.application_number,
							                doc.grant_doc_num,
							                doc.appno_doc_num
							            FROM db_uspto.documentid AS doc
							            INNER JOIN db_patent_application_bibliographic.patent_cpc AS cpc
							                ON doc.appno_doc_num = cpc.application_number
							            WHERE 
							                doc.appno_date >= {$yearThreshold}
							                AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
							                AND doc.grant_doc_num <> ''
							                AND cpc.type = 0
							                AND cpc.section IN ({$sectionList})
							            GROUP BY doc.appno_doc_num

							            UNION

							            -- Applications not yet granted (no grant_doc_num)
							            SELECT 
							                cpc.application_number,
							                '' AS grant_doc_num,
							                doc.appno_doc_num
							            FROM db_uspto.documentid AS doc
							            INNER JOIN db_patent_grant_bibliographic.application_cpc AS cpc
							                ON doc.appno_doc_num = cpc.application_number
							            WHERE 
							                doc.appno_date >= {$yearThreshold}
							                AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
							                AND doc.grant_doc_num = ''
							                AND cpc.type = 0
							                AND cpc.section IN ({$sectionList})
							            GROUP BY doc.appno_doc_num
							        ) AS temp
							        GROUP BY temp.application_number
							    ";

						    	$con->query($queryUnNecessaryPatents);
		                    }

						} /*OWNED ASSETS LIST*/



	                     /**
	                     * To Record
	                     */
                     	if (count($applicantAssets) > 0) {
						    $type = 22;

						    // Step 1: Fetch all current type-31 assets for the company/org
						    $queryApplicantAssets = "
						        SELECT application, patent 
						        FROM {$dbApplication}.dashboard_items 
						        WHERE type = 31 
						          AND organisation_id = {$organisationID} 
						          AND representative_id = {$companyID}
						        GROUP BY application
						    ";

						    $resultApplicant = $con->query($queryApplicantAssets);

						    if ($resultApplicant && $resultApplicant->num_rows > 0) {
						        $applicantAssetsList = [];
						        $patentList = [];
						        $assetRows = [];

						        while ($row = $resultApplicant->fetch_object()) {
						            $applicantAssetsList[] = '"' . $row->application . '"';
						            $patentList[] = '"' . $row->patent . '"';
						            $assetRows[] = $row;
						        }

						        // Step 2: Find which applications exist in documentid table
						        $queryDocs = "
						            SELECT appno_doc_num 
						            FROM {$dbUSPTO}.documentid 
						            WHERE appno_doc_num IN (" . implode(',', $applicantAssetsList) . ") 
						            GROUP BY appno_doc_num
						        ";
						        $resultDocs = $con->query($queryDocs);

						        $documentAssets = [];
						        if ($resultDocs && $resultDocs->num_rows > 0) {
						            while ($doc = $resultDocs->fetch_object()) {
						                $documentAssets[] = '"' . $doc->appno_doc_num . '"';
						            }
						        }

						        // Step 3: Find remaining assets not present in documentid
						        $remainingApplicantAssets = array_diff($applicantAssetsList, $documentAssets);

						        // Step 4: Check patents that exist in documentid but applications don’t
						        if (count($remainingApplicantAssets) > 0) {
						            $queryGrantPatents = "
						                SELECT grant_doc_num 
						                FROM {$dbUSPTO}.documentid 
						                WHERE grant_doc_num IN (" . implode(',', $patentList) . ") 
						                  AND appno_doc_num NOT IN (" . implode(',', $documentAssets) . ") 
						                GROUP BY grant_doc_num
						            ";
						            $resultPatents = $con->query($queryGrantPatents);

						            $documentPatentAssets = [];
						            if ($resultPatents && $resultPatents->num_rows > 0) {
						                while ($row = $resultPatents->fetch_object()) {
						                    $documentPatentAssets[] = '"' . $row->grant_doc_num . '"';
						                }
						            }

						            // Step 5: Match patents with known assets to recover missing apps
						            foreach ($documentPatentAssets as $patentAsset) {
						                foreach ($assetRows as $asset) {
						                    if ($patentAsset == '"' . $asset->patent . '"') {
						                        $documentAssets[] = '"' . $asset->application . '"';
						                        break;
						                    }
						                }
						            }

						            // Step 6: Final missing assets (not found via documentid or patent)
						            $remainingApplicantAssets = array_diff($applicantAssetsList, $documentAssets);

						            // Step 7: Filter out expired assets
						            if (count($expiredAssets) > 0 && count($remainingApplicantAssets) > 0) {
						                $remainingApplicantAssets = array_diff($remainingApplicantAssets, $expiredAssets);
						            }

						            // Step 8: Insert recovered unassigned assets (type 22)
						            if (count($remainingApplicantAssets) > 0) {
						                $queryInsert = "
						                    INSERT IGNORE INTO {$dbApplication}.dashboard_items 
						                        (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total)
						                    SELECT 
						                        {$organisationID} AS organisation_id,
						                        {$companyID} AS representative_id,
						                        assignor_id,
						                        {$type} AS type,
						                        patent,
						                        application,
						                        rf_id,
						                        " . count($applicantAssetsList) . " AS total
						                    FROM (
						                        SELECT * 
						                        FROM {$dbApplication}.dashboard_items 
						                        WHERE type = 31 
						                          AND organisation_id = {$organisationID}
						                          AND representative_id = {$companyID}
						                          AND application IN (" . implode(',', $remainingApplicantAssets) . ") 
						                        GROUP BY application
						                    ) AS temp
						                ";
						                $con->query($queryInsert);

						                // Step 9: Delete broken chain-of-title records (type 1)
						                $queryDelete = "
						                    DELETE FROM {$dbApplication}.dashboard_items 
						                    WHERE type = 1 
						                      AND organisation_id = {$organisationID}
						                      AND representative_id = {$companyID}
						                      AND application IN (
						                          SELECT * FROM (
						                              SELECT application 
						                              FROM {$dbApplication}.dashboard_items 
						                              WHERE type = {$type}
						                                AND organisation_id = {$organisationID}
						                                AND representative_id = {$companyID}
						                          ) AS temp
						                      )
						                ";

						                echo $queryDelete . " -- chain delete to record\n";
						                $con->query($queryDelete);
						            }
						        }
						    }
						}
						

		                    

						

                    } /* line no 2258*/
	            }
	        }
        }
	}
}
