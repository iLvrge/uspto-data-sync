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
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);


$variables = $argv;

if(count($variables) == 4) {
	$organisationID = $variables[1];
	$representativeName = $variables[2];
	$orgRun = $variables[3];
	if((int)$organisationID > 0) {		
		$query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID;	
		$result = $con->query($query);
		if($result && $result->num_rows > 0) {  
			while($row = $result->fetch_object()) {
				try {
					$orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
					$companiesData = [];
					if($orgConnect) {
						$queryRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 0"; 
						if($representativeName != '') {
							$queryRepresentative .= " AND (representative_name = '".$orgConnect->real_escape_string($representativeName)."' OR original_name = '".$orgConnect->real_escape_string($representativeName)."')";
						} else {
							$queryRepresentative .= " AND parent_id = 0 ";
						} 				
						$queryRepresentative .= " GROUP BY representative_name ORDER BY status DESC";	
						
						
						$resultRepresentative = $orgConnect->query($queryRepresentative);	
						if($resultRepresentative && $resultRepresentative->num_rows > 0) {
							while($representative = $resultRepresentative->fetch_object()){
								array_push($companiesData, array('representative_id'=>$representative->representative_id, 'name'=>$representative->representative_name)); 
							}
						}
	
						if($representativeName == "") {
							$queryRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 1 AND parent_id = 0";	
							$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
					
							if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {					
								while($getGroup = $resultRepresentativeParentCompany->fetch_object()) {
									$queryGroupRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 0 AND parent_id = ".$getGroup->representative_id.' ORDER BY status DESC';
									
									$resultRepresentativeGroupParentCompany = $orgConnect->query($queryGroupRepresentative);
									if($resultRepresentativeGroupParentCompany && $resultRepresentativeGroupParentCompany->num_rows > 0) {
										
										while($getCompanyRow = $resultRepresentativeGroupParentCompany->fetch_object()) {
											array_push($companiesData, array('representative_id'=>$getCompanyRow->representative_id, 'name'=>$getCompanyRow->representative_name));
										}
									}
								}
							}
						}
					}
					$date = new DateTime();
					$date->modify('-24 year');
					$YEAR =  $date->format('Y');
					$YEARBETWEEN_1 = 1998;
					$YEARBETWEEN_2 = 2001;
					$LAYOUTID = 15;
					print_r($companiesData); 
					if(count($companiesData) > 0) {
						$uniqueCompaniesNames = [];
						$companyIDs = [];
						foreach($companiesData as $company) {
							if(!in_array($company['name'], $uniqueCompaniesNames)) {
								array_push($companyIDs, $company['representative_id']);
								array_push($uniqueCompaniesNames, $company['name']);
							}
						}

						$con->query("DELETE FROM summary WHERE organisation_id = ". $organisationID." AND company_id = ".$companiesData[0]['representative_id']);

						foreach($companiesData as $company) {  
							$companyID = $company['representative_id'];
							print_r($companyID);
							$queryCompanyAssets = "SELECT appno_doc_num FROM db_new_application.assets WHERE organisation_id = ".(int)$organisationID." AND company_id IN (".$companyID.") AND date_format(appno_date, '%Y') > ".$YEAR." AND layout_id = ".$LAYOUTID." GROUP BY appno_doc_num";

							$allCompanyAssets = [];
							$resultAssets = $con->query($queryCompanyAssets);
							if($resultAssets && $resultAssets->num_rows > 0) {
								while($getAssetRow = $resultAssets->fetch_object()) {
									array_push($allCompanyAssets, '"'.$getAssetRow->appno_doc_num.'"');
								}
							}


							$assetsTill2001 = array();
							$queryCompany2001Assets = "SELECT appno_doc_num FROM db_new_application.assets WHERE organisation_id = ".(int)$organisationID." AND company_id IN (".$companyID.") AND date_format(appno_date, '%Y') BETWEEN ".$YEARBETWEEN_1." AND ".$YEARBETWEEN_2." AND layout_id = ".$LAYOUTID." GROUP BY appno_doc_num";

							$resultAssets = $con->query($queryCompany2001Assets);
							if($resultAssets && $resultAssets->num_rows > 0) {
								while($getAssetRow = $resultAssets->fetch_object()) {
									array_push($assetsTill2001, '"'.$getAssetRow->appno_doc_num.'"');
								}
							}

							$employeeName = array();
							if(count($assetsTill2001) > 0) {
								$queryEmployees = "SELECT aaa.name FROM assignor_and_assignee AS aaa INNER JOIN assignor AS aor ON aor.assignor_and_assignee_id = aaa.assignor_and_assignee_id INNER JOIN documentid AS doc ON doc.rf_id = aor.rf_id INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id AND (rac.convey_ty = 'employee' or rac.employer_assign = 1) WHERE doc.appno_doc_num IN (".implode(',', $assetsTill2001).") GROUP BY aaa.name ";

								$resultEmployees = $con->query($queryEmployees);
								if($resultEmployees && $resultEmployees->num_rows > 0) {
									while($getRow = $resultEmployees->fetch_object()) {
										array_push($employeeName,  $getRow->name );
									}
								}
							}


							if(count($allCompanyAssets) > 0) {

								$queryEmployees = "SELECT name FROM (SELECT name, appno_doc_num FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $allCompanyAssets).") GROUP BY name, appno_doc_num  UNION  SELECT name, appno_doc_num FROM db_patent_grant_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $allCompanyAssets).") AND appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $allCompanyAssets).")) GROUP BY name, appno_doc_num) AS tempEmployees ";


								if(count($employeeName) > 0) {
									$queryEmployees .= " WHERE name NOT IN (".implode(',', $employeeName).") GROUP BY name";
								} else {
									$queryEmployees .= " GROUP BY name";
								} 
								$totalEmployees = 0;
								$resultEmployees = $con->query($queryEmployees);
								if($resultEmployees && $resultEmployees->num_rows > 0) {
									$totalEmployees = $resultEmployees->num_rows;

									if(count($employeeName) > 0) {
										$totalEmployees = $totalEmployees + count($employeeName);
									}
								}

								if($totalEmployees == 0 && count($employeeName) > 0) {
									$totalEmployees = count($employeeName);
								}
								/**
								 *      SELECT aaa.assignor_and_assignee_id FROM assignor_and_assignee AS aaa INNER JOIN assignee AS a ON a.assignor_and_assignee_id = aaa.assignor_and_assignee_id INNER JOIN documentid AS doc ON doc.rf_id = a.rf_id WHERE doc.appno_doc_num IN (".implode(',', $allCompanyAssets).") GROUP BY aaa.assignor_and_assignee_id UNION SELECT aaa.assignor_and_assignee_id FROM assignor_and_assignee AS aaa INNER JOIN assignor AS a ON a.assignor_and_assignee_id = aaa.assignor_and_assignee_id INNER JOIN documentid AS doc ON doc.rf_id = a.rf_id WHERE doc.appno_doc_num IN (".implode(',', $allCompanyAssets).") GROUP BY aaa.assignor_and_assignee_id )
								 */
								$queryParties = "SELECT count(*) as countParties FROM (  SELECT apt.assignor_and_assignee_id FROM db_new_application.activity_parties_transactions AS apt WHERE activity_id <> 10 AND organisation_id = ".(int)$organisationID." AND company_id = ".$companyID." GROUP BY apt.assignor_and_assignee_id ) AS temp ";
								$totalParties = 0;
								$resultParties = $con->query($queryParties);
	
								if($resultParties && $resultParties->num_rows > 0) {
									$rowParties = $resultParties->fetch_object();
									$totalParties = $rowParties->countParties; 
								}


								$queryRFID = "SELECT doc.rf_id FROM documentid AS doc 
								INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id AND (rac.convey_ty <> 'employee' or (rac.convey_ty <> 'assignment' and rac.employer_assign <> 1))
								WHERE doc.appno_doc_num IN (".implode(',', $allCompanyAssets).")
								GROUP BY doc.rf_id"; 
								$resultRFID = $con->query($queryRFID);
								$allRfIDs = array();
								if($resultRFID && $resultRFID->num_rows > 0) {
									while($row = $resultRFID->fetch_object()) {
										array_push($allRfIDs, $row->rf_id);
									}
								}  

								/* $queryAllRfIDs = " SELECT rf_id FROM documentid WHERE appno_doc_num IN (".implode(',', $allCompanyAssets).") ";
								$resultAllRFID = $con->query($queryAllRfIDs);
								$all3rdPartyRfIDs = array();
								if($resultAllRFID && $resultAllRFID->num_rows > 0) {
									while($row = $resultRFID->fetch_object()) {
										array_push($all3rdPartyRfIDs, $row->rf_id);
									}
								} */

								$total3rdParties = 0;
								echo "ALL RFID: ".count($allRfIDs);
								if(count($allRfIDs) > 0) {

									$query3rdParties = "SELECT COUNT(*) AS tempCount FROM (SELECT assignor_and_assignee_id FROM (SELECT assignor_and_assignee_id FROM assignee WHERE rf_id IN (".implode(',', $allRfIDs).") AND  assignor_and_assignee_id NOT IN (SELECT inventors.assignor_and_assignee_id FROM inventors) AND  assignor_and_assignee_id NOT IN (SELECT recorded_assignor_and_assignee_id FROM db_new_application.activity_parties_transactions WHERE organisation_id = ".(int)$organisationID." AND company_id = ".$companyID."  AND rf_id IN (".implode(',', $allRfIDs).") GROUP BY recorded_assignor_and_assignee_id)  /* AND rf_id NOT IN (SELECT apt.rf_id FROM db_new_application.activity_parties_transactions AS apt WHERE activity_id <> 10 AND organisation_id = ".(int)$organisationID." AND company_id = ".$companyID." GROUP BY apt.rf_id) */ GROUP BY assignor_and_assignee_id UNION SELECT assignor_and_assignee_id FROM assignor WHERE rf_id IN (".implode(',', $allRfIDs).") /* AND  rf_id NOT IN (SELECT apt.rf_id FROM db_new_application.activity_parties_transactions AS apt WHERE activity_id <> 10 AND organisation_id = ".(int)$organisationID." AND company_id = ".$companyID." GROUP BY apt.rf_id) */ AND  assignor_and_assignee_id NOT IN (SELECT inventors.assignor_and_assignee_id FROM inventors) AND  assignor_and_assignee_id NOT IN (SELECT recorded_assignor_and_assignee_id FROM db_new_application.activity_parties_transactions WHERE organisation_id = ".(int)$organisationID." AND company_id = ".$companyID."  AND rf_id IN (".implode(',', $allRfIDs).") GROUP BY recorded_assignor_and_assignee_id) GROUP BY assignor_and_assignee_id ) AS temp GROUP BY assignor_and_assignee_id) AS temp1";

									//echo $query3rdParties;
									/* echo $query3rdParties = "SELECT assignor_and_assignee_id FROM ( SELECT aaa.assignor_and_assignee_id FROM assignor_and_assignee AS aaa INNER JOIN assignee AS a ON a.assignor_and_assignee_id = aaa.assignor_and_assignee_id WHERE a.rf_id IN (".implode(',', $allRfIDs).") GROUP BY aaa.assignor_and_assignee_id UNION SELECT aaa.assignor_and_assignee_id FROM assignor_and_assignee AS aaa INNER JOIN assignor AS a ON a.assignor_and_assignee_id = aaa.assignor_and_assignee_id WHERE a.rf_id IN (".implode(',', $allRfIDs).") GROUP BY aaa.assignor_and_assignee_id ) AS temp GROUP BY assignor_and_assignee_id"; */

									$result3rdParties = $con->query($query3rdParties); 
									if($result3rdParties && $result3rdParties->num_rows > 0) {
										$row3rdParties = $result3rdParties->fetch_object();
										$total3rdParties = $row3rdParties->tempCount;
									}
								}
	
								/* 
								$queryParties = "SELECT assignor_and_assignee_id FROM db_new_application.activity_parties_transactions AS apt WHERE organisation_id = ".(int)$organisationID." AND company_id IN (".$company['representative_id'].") GROUP BY assignor_and_assignee_id";
								$totalParties = 0;
								$resultParties = $con->query($queryParties);
	
								if($resultParties && $resultParties->num_rows > 0) {
									$totalParties = $resultParties->num_rows;
								} */
	
								$queryTransactions = "SELECT count(*) AS counTransactions FROM (SELECT rf_id FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $allCompanyAssets).") GROUP BY rf_id) AS temp";
								$totalTransactions = 0;
								$resultTransactions = $con->query($queryTransactions);
	
								if($resultTransactions && $resultTransactions->num_rows > 0) {
									$rowTransactions = $resultTransactions->fetch_object();
									$totalTransactions = $rowTransactions->counTransactions;
								}
	
								$queryArrows = "SELECT SUM(arrows) AS totalArrows FROM assignment_arrows WHERE rf_id IN (SELECT rf_id FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $allCompanyAssets).") GROUP BY rf_id)";
								$totalArrows = 0;
								$resultArrows = $con->query($queryArrows);
	
								if($resultArrows && $resultArrows->num_rows > 0) {
									$rowArrow = $resultArrows->fetch_object();
									$totalArrows = $rowArrow->totalArrows;
								}

	
								$queryReport = "INSERT IGNORE INTO summary (organisation_id, company_id, companies, activities, entities, parties, employees, transactions, assets, arrows) SELECT ".$organisationID." AS organisation_id, ".$companyID." AS company_id, 1 as company_count, COUNT(DISTINCT activity) AS noOfActivities, ".$total3rdParties." AS noOfEntities, ".$totalParties." AS noOfParties, ".$totalEmployees." AS noOfEmployees, ".$totalTransactions." AS noOfTransactions, ".count($allCompanyAssets)." as noOfAssets, ".$totalArrows." AS noOfArrows FROM (SELECT CASE WHEN activity_id = 11 THEN 5 WHEN activity_id = 12 THEN 5 WHEN activity_id = 13 THEN 5 WHEN activity_id = 16 THEN 5 ELSE activity_id END AS activity FROM db_new_application.activity_parties_transactions WHERE organisation_id = ".(int)$organisationID." AND company_id = ".$companyID." GROUP BY organisation_id, activity_id ) AS temp";
								//echo $queryReport.'<br/>';
								$con->query($queryReport);
							}
						} 
						if($orgRun == '1') {
							print_r("adasdsdsadasdasdada");
							$con->query("DELETE FROM summary WHERE organisation_id = ". $organisationID." AND company_id = 0");

							$queryAssets = "SELECT appno_doc_num FROM db_new_application.assets WHERE organisation_id = ".(int)$organisationID." AND company_id IN (".implode(',', $companyIDs).") AND date_format(appno_date, '%Y') > ".$YEAR." AND layout_id = ".$LAYOUTID." GROUP BY appno_doc_num";

							$allAssets = [];
							$resultAssets = $con->query($queryAssets);
							if($resultAssets && $resultAssets->num_rows > 0) {
								while($getAssetRow = $resultAssets->fetch_object()) {
									array_push($allAssets, '"'.$getAssetRow->appno_doc_num.'"');
								}
							}
							echo "COUNT: ".count($allAssets);
							if(count($allAssets) > 0) {

								$assetsTill2001 = array();
                                $queryCompany2001Assets = "SELECT appno_doc_num FROM db_new_application.assets WHERE organisation_id = ".(int)$organisationID." AND company_id IN (".implode(',', $companyIDs).")  AND date_format(appno_date, '%Y') BETWEEN ".$YEARBETWEEN_1." AND ".$YEARBETWEEN_2." AND layout_id = ".$LAYOUTID." GROUP BY appno_doc_num";
 
								$resultAssets = $con->query($queryCompany2001Assets);
								if($resultAssets && $resultAssets->num_rows > 0) {
									while($getAssetRow = $resultAssets->fetch_object()) {
										array_push($assetsTill2001, '"'.$getAssetRow->appno_doc_num.'"');
									}
								}

								$employeeName = array();
								if(count($assetsTill2001) > 0) {
									$queryEmployees = "SELECT aaa.name FROM assignor_and_assignee AS aaa INNER JOIN assignor AS aor ON aor.assignor_and_assignee_id = aaa.assignor_and_assignee_id INNER JOIN documentid AS doc ON doc.rf_id = aor.rf_id INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id AND (rac.convey_ty = 'employee' or rac.employer_assign = 1) WHERE doc.appno_doc_num IN (".implode(',', $assetsTill2001).") GROUP BY aaa.name ";

									$resultEmployees = $con->query($queryEmployees);
									if($resultEmployees && $resultEmployees->num_rows > 0) {
										while($getRow = $resultEmployees->fetch_object()) {
											array_push($employeeName,  $getRow->name );
										}
									}
								}



								$queryEmployees = "SELECT name FROM (SELECT name, appno_doc_num FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $allAssets).") GROUP BY name, appno_doc_num  UNION  SELECT name, appno_doc_num FROM db_patent_grant_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $allAssets).") AND appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num IN (".implode(',', $allAssets).")) GROUP BY name, appno_doc_num) AS tempEmployees  ";

								if(count($employeeName) > 0) {
									$queryEmployees .= " WHERE name NOT IN (".implode(',', $employeeName).") GROUP BY name";
								} else {
									$queryEmployees .= " GROUP BY name";
								} 

								$totalEmployees = 0;
								$resultEmployees = $con->query($queryEmployees);
								if($resultEmployees && $resultEmployees->num_rows > 0) {
									$totalEmployees = $resultEmployees->num_rows;

									if(count($employeeName) > 0) {
										$totalEmployees = $totalEmployees + count($employeeName);
									}
								}

								if($totalEmployees == 0 && count($employeeName) > 0) {
									$totalEmployees = count($employeeName);
								} 


								$queryParties = "SELECT count(*) as countParties FROM ( SELECT apt.assignor_and_assignee_id FROM db_new_application.activity_parties_transactions AS apt WHERE activity_id <> 10 AND organisation_id = ".(int)$organisationID." AND company_id IN (".implode(',', $companyIDs).") GROUP BY apt.assignor_and_assignee_id ) AS temp ";
								$totalParties = 0;
								$resultParties = $con->query($queryParties);

								if($resultParties && $resultParties->num_rows > 0) {
									$rowParties = $resultParties->fetch_object();
									$totalParties = $rowParties->countParties; 
								}

								
								/* $queryRFID = "SELECT doc.rf_id FROM documentid AS doc 
								INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id AND (rac.convey_ty <> 'employee' or rac.employer_assign <> 1)
								WHERE doc.appno_doc_num IN (".implode(',', $allAssets).") AND doc.rf_id NOT IN (SELECT apt.rf_id FROM db_new_application.activity_parties_transactions AS apt WHERE activity_id <> 10 AND organisation_id = ".(int)$organisationID." GROUP BY apt.rf_id )
								GROUP BY doc.rf_id"; */


								$queryRFID = "SELECT doc.rf_id FROM documentid AS doc 
								INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id AND (rac.convey_ty <> 'employee' or (rac.convey_ty <> 'assignment' and rac.employer_assign <> 1))
								WHERE doc.appno_doc_num IN (".implode(',', $allAssets).")  GROUP BY doc.rf_id";
 

								$resultRFID = $con->query($queryRFID);
								$allRfIDs = array();
								if($resultRFID && $resultRFID->num_rows > 0) {
									while($row = $resultRFID->fetch_object()) {
										array_push($allRfIDs, $row->rf_id);
									}
								}
								echo "RFID: ".count($allRfIDs);
								$total3rdParties = 0;

								if(count($allRfIDs) > 0) {
									$query3rdParties = "SELECT COUNT(*) AS tempCount FROM (SELECT assignor_and_assignee_id FROM (SELECT assignor_and_assignee_id FROM assignee WHERE rf_id IN (".implode(',', $allRfIDs).") AND  assignor_and_assignee_id NOT IN (SELECT inventors.assignor_and_assignee_id FROM inventors) AND  assignor_and_assignee_id NOT IN (SELECT recorded_assignor_and_assignee_id FROM db_new_application.activity_parties_transactions WHERE organisation_id = ".(int)$organisationID." AND company_id IN (".implode(',', $companyIDs).")  AND rf_id IN (".implode(',', $allRfIDs).") GROUP BY recorded_assignor_and_assignee_id)  /* AND rf_id NOT IN (SELECT apt.rf_id FROM db_new_application.activity_parties_transactions AS apt WHERE activity_id <> 10 AND organisation_id = ".(int)$organisationID." AND company_id IN (".implode(',', $companyIDs).") GROUP BY apt.rf_id) */ GROUP BY assignor_and_assignee_id UNION SELECT assignor_and_assignee_id FROM assignor WHERE rf_id IN (".implode(',', $allRfIDs).") /* AND  rf_id NOT IN (SELECT apt.rf_id FROM db_new_application.activity_parties_transactions AS apt WHERE activity_id <> 10 AND organisation_id = ".(int)$organisationID." AND company_id IN (".implode(',', $companyIDs).")  GROUP BY apt.rf_id) */ AND  assignor_and_assignee_id NOT IN (SELECT inventors.assignor_and_assignee_id FROM inventors) AND  assignor_and_assignee_id NOT IN (SELECT recorded_assignor_and_assignee_id FROM db_new_application.activity_parties_transactions WHERE organisation_id = ".(int)$organisationID." AND company_id IN (".implode(',', $companyIDs).") AND rf_id IN (".implode(',', $allRfIDs).") GROUP BY recorded_assignor_and_assignee_id) GROUP BY assignor_and_assignee_id ) AS temp GROUP BY assignor_and_assignee_id) AS temp1";

									//echo $query3rdParties;
									
	
									$result3rdParties = $con->query($query3rdParties); 
									if($result3rdParties && $result3rdParties->num_rows > 0) {
										$row3rdParties = $result3rdParties->fetch_object();
										$total3rdParties = $row3rdParties->tempCount;
									}
								}
								echo "Entities: ".$total3rdParties;
								$queryTransactions = "SELECT count(*) AS counTransactions FROM (SELECT rf_id FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $allAssets).") GROUP BY rf_id) AS temp";
								$totalTransactions = 0;
								$resultTransactions = $con->query($queryTransactions);
	
								if($resultTransactions && $resultTransactions->num_rows > 0) {
									$rowTransactions = $resultTransactions->fetch_object();
									$totalTransactions = $rowTransactions->counTransactions;
								}
	
								$queryArrows = "SELECT SUM(arrows) AS totalArrows FROM assignment_arrows WHERE rf_id IN (SELECT rf_id FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $allAssets).") GROUP BY rf_id)";
								$totalArrows = 0;
								$resultArrows = $con->query($queryArrows);
	
								if($resultArrows && $resultArrows->num_rows > 0) {
									$rowArrow = $resultArrows->fetch_object();
									$totalArrows = $rowArrow->totalArrows;
								}

								$queryReport = "INSERT IGNORE INTO summary (organisation_id, company_id, companies, activities, entities,  parties, employees, transactions, assets, arrows) SELECT ".$organisationID." AS organisation_id, 0 AS company_id, ".count($companyIDs)." as company_count, COUNT(DISTINCT activity) AS noOfActivities, ".$total3rdParties." AS noOfEntities, ".$totalParties." AS noOfParties, ".$totalEmployees." AS noOfEmployees, ".$totalTransactions." AS noOfTransactions, ".count($allAssets)." as noOfAssets, ".$totalArrows." AS noOfArrows FROM (SELECT CASE WHEN activity_id = 11 THEN 5 WHEN activity_id = 12 THEN 5 WHEN activity_id = 13 THEN 5 WHEN activity_id = 16 THEN 5 ELSE activity_id END AS activity FROM db_new_application.activity_parties_transactions WHERE organisation_id = ".(int)$organisationID." GROUP BY organisation_id, activity_id) AS temp";
								//echo $queryReport."<br/>";
							   	$con->query($queryReport); 
							}

						}
					}
                } catch (Exception $e){
					echo "<pre>";
					print_r($e);
				}
            }
        }
    }
}