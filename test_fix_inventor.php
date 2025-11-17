<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);*/
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbApplication);

$variables = $argv;
//$variables = $_GET;
if(count($variables) == 3) {
//if(count($variables) > 0) {
	$organisationID = $variables[1];
	$representativeID = $variables[2];
	/*$organisationID = $variables['o'];
	$representativeID = $variables['r'];*/
	echo $organisationID.",".$representativeID."<br/>";
	if((int)$organisationID > 0) {
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				/*Check from client database */
				if($representativeID != "" && $representativeID > 0) {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE representative_id = '".$representativeID."' AND parent_id = 0";
				} else {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE parent_id = 0";
				}				
				
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
				echo $resultRepresentativeParentCompany->num_rows."<br/>";
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
					while($getCompanyRow = $resultRepresentativeParentCompany->fetch_object()) {
						$allCompanies = array();
						$findRepresentativeName = "Select representative_id, original_name, representative_name, parent_id FROM representative WHERE representative_id = '".$getCompanyRow->representative_id."' OR parent_id = '".$getCompanyRow->representative_id."'";
					
						$resultRepresentativeCompanies = $orgConnect->query($findRepresentativeName);
						
						while($row = $resultRepresentativeCompanies->fetch_object()){
							if($row->parent_id == 0) {
								$representativeID =  $row->representative_id;
							}
							$name = $row->original_name;
							array_push($allCompanies, $name);
						}
						
						
						$rfIDs = [];
						
						$queryFindAllRFIDs = "SELECT rf_id FROM db_uspto.representative_transactions WHERE organisation_id = ".$organisationID." AND representative_id =" .$representativeID;
						
						$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
						
						if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
							while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
								array_push($rfIDs, $rowRepresentativeRF->rf_id);
							}
						}
						
						
						//echo "FINDING FOR ".$representativeID."<br/>";
						//print_r($allCompanies);
						if(count($rfIDs) > 0) {
							
						
							/*All application of the Representative company*/
							
							
							
							$queryFindCorrectRFIDs = 'SELECT appno_doc_num FROM db_application.documentid WHERE appno_doc_num <> "" AND  rf_id IN ('.implode(',', $rfIDs).')  GROUP BY appno_doc_num';
							
							$resultIDs = $con->query($queryFindCorrectRFIDs);
							$appNo = array(); 
							if($resultIDs && $resultIDs->num_rows > 0) {
								while($row = $resultIDs->fetch_object()){
									array_push($appNo, $row->appno_doc_num);
								}
							}
							/*Patent and assets*/
							echo "Patents & Assets & Encumbered<br/>";
							$allNames = "";
							
							foreach($allCompanies as $company) {
								$allNames .= ' aaa.name = "'.$con->real_escape_string($company).'" OR r.representative_name="'.$con->real_escape_string($company).'" OR ';
							}
							
							$allNames = substr($allNames, 0, -3);
						
							 $queryAssets = 'SELECT appno_doc_num, grant_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee`
							INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id 
							LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
							LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
							WHERE ( '.$allNames.' ) AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $rfIDs).') ) AND 
							appno_doc_num NOT IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`
							INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id 
							LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
							LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
							WHERE ( '.$allNames.' ) 
							AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $rfIDs).')) GROUP BY appno_doc_num)
							GROUP BY appno_doc_num';
						
						/*echo $queryAssets."<br/>";*/
						
						$resultAssets = $con->query($queryAssets);
						$allAppNo = array();
						$applicationS = 0;
						$patentS = 0;
						$encumberedS = 0;
						if($resultAssets) {
							while($row = $resultAssets->fetch_object()){
								if($row->grant_doc_num != "" && $row->grant_doc_num != null) {
									$patentS++;
								} else if($row->appno_doc_num != "" && $row->appno_doc_num != null  && $row->appno_doc_num > 0) {
									$applicationS++;
								}
								
								if($row->appno_doc_num != "" && $row->appno_doc_num != null  && $row->appno_doc_num > 0) {
									array_push($allAppNo, $row->appno_doc_num);
								}
							}
						}
						
						/*License or Government, or Option, or Security, or RestatedSecurity, or Other, or Missing*/
						$encumberedlist = array();
						if(count($allAppNo) > 0) {
							foreach($allAppNo as $app) {
								$querySecurity = 'SELECT count(*) as countRecords FROM (SELECT a.rf_id, a.assignor_and_assignee_id FROM documentid as d INNER JOIN assignment_conveyance as ac ON ac.rf_id = d.rf_id INNER JOIN assignor as a ON a.rf_id = ac.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id  WHERE d.appno_doc_num = "'.$app.'" AND ac.convey_ty IN ("security", "restatedsecurity", "license", "govern", "option", "other", "missing") AND ('.$allNames.') GROUP BY a.rf_id, a.assignor_and_assignee_id) as temp';
								
								$resultSecurity = $con->query($querySecurity);
								
								$queryRelease = 'SELECT count(*) as countRecords FROM (SELECT a.rf_id, a.assignor_and_assignee_id FROM documentid as d INNER JOIN assignment_conveyance as ac ON ac.rf_id = d.rf_id INNER JOIN assignee as a ON a.rf_id = ac.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id  WHERE d.appno_doc_num = "'.$app.'" AND ac.convey_ty IN ("release", "licenseend") AND ('.$allNames.') GROUP BY a.rf_id, a.assignor_and_assignee_id) as temp';
								
								$resultRelease = $con->query($queryRelease);
								
								$security = 0;
								$release = 0;
								if($resultSecurity) {
									$row = $resultSecurity->fetch_object();
									$security = $row->countRecords;
								}
								
								if($resultRelease) {
									$row = $resultRelease->fetch_object();
									$release = $row->countRecords;
								}
								
								if($security > 0 && $security > $release) {
									echo "ENTERED<br/>";
									$encumberedS++;
									array_push($encumberedlist, $app);
								}								
							}							
						}
						print_r($allAppNo);
						//print_r($encumberedlist);
						echo $encumberedS;
						
						
						$con->query("DELETE FROM db_application.validity WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID );	
						
						$con->query("INSERT INTO db_application.validity (organisation_id, representative_id, application, patent, encumbered) VALUES (".$orgRow->organisation_id.",".$representativeID.",".$applicationS.",".$patentS.",".$encumberedS.")");
						
						
						//echo "FIND ERROR LIST<br/>";
						//echo "<pre>";
						//print_r($allAppNo);
						$errorInList = array();
						if(count($allAppNo) > 0) {
							foreach($allAppNo as $app) {								
								$queryInventorCheck = "SELECT ac.rf_id FROM db_application.assignment_conveyance as ac INNER JOIN db_application.documentid as d ON d.rf_id = ac.rf_id WHERE d.appno_doc_num = '".$app."' AND ac.employer_assign = 1";
								$resultInventor = $con->query($queryInventorCheck);
								$queryInventorFromPatentApplication = "SELECT * FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num = '".$app."'";
								
								$resultPatentApplicationInventor = $con->query($queryInventorFromPatentApplication);
								/*if(($resultPatentApplicationInventor->num_rows > 0 && $resultPatentApplicationInventor->num_rows > $resultInventor->num_rows) || ($resultPatentApplicationInventor->num_rows == 0 && $resultInventor->num_rows == 0 || $resultInventor->num_rows == 0 ) ) {
									$errorInList[] = $app;
								}*/
								
								/*if(($resultPatentApplicationInventor->num_rows > 0 && $resultPatentApplicationInventor->num_rows > $resultInventor->num_rows) || ($resultPatentApplicationInventor->num_rows == 0 && $resultInventor->num_rows == 0 ) ) {
									$errorInList[] = $app;
								}*/
								if(($resultPatentApplicationInventor->num_rows == 0 && $resultInventor->num_rows == 0 ) ) {
									$errorInList[] = $app;
								}
							}
						}
						
						
						/*Error Table*/
						if(count($errorInList) > 0) {	
							$con->query("DELETE FROM db_application.error WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID ." AND type = 0" );
							$queryInsertError = "INSERT INTO db_application.error(organisation_id, representative_id, appno_doc_num) VALUES ";
							foreach($errorInList as $err) {
								$queryInsertError .= "(".$orgRow->organisation_id.",".$representativeID.",'".$err."'), ";
							}
							$queryInsertError = substr($queryInsertError, 0, -2);	
							//echo $queryInsertError;
							$con->query($queryInsertError);
						}
						
						
						/*End Errors*/	
						
						
						
						//print_r($allCompanies);
						$errorInList = array();
						/*Error Title*/
						if(count($allAppNo) > 0) {
							foreach($allAppNo as $app) {
								$selectDocumentRFIDs = "SELECT rf_id FROM documentid WHERE appno_doc_num = '".$app."'";
								
								$resultDocument = $con->query($selectDocumentRFIDs);
								
								if($resultDocument && $resultDocument->num_rows) {
									$allRFIDs = array();
									
									while($rowDocument = $resultDocument->fetch_object()) {
										array_push($allRFIDs, $rowDocument->rf_id);
									}
									
									$queryAssignees = "SELECT r.representative_name, aa.name FROM assignor_and_assignee as aa INNER JOIN assignee as a ON a.assignor_and_assignee_id = aa.assignor_and_assignee_id INNER JOIN assignment_conveyance as ac ON a.rf_id = ac.rf_id LEFT JOIN representative as r ON r.representative_id = aa.representative_id WHERE ac.convey_ty IN ('assignment', 'partialassignment', 'merger', 'namechg', 'courtorder', 'courtappointment') AND a.rf_id IN (".implode(',', $allRFIDs).")";
									
									$resultAssignee = $con->query($queryAssignees);
									
									$assigneeList = array();
									
									if($resultAssignee && $resultAssignee->num_rows > 0) {
										while($rowAssignee = $resultAssignee->fetch_object()){
											$name = $rowAssignee->representative_name;
											
											if($name == null || $name == ''){
												$name = $rowAssignee->name;
											}
											array_push($assigneeList, $name);
										}
									}
									
									
									$queryAssignors = "SELECT r.representative_name, aa.name FROM assignor_and_assignee as aa INNER JOIN assignor as a ON a.assignor_and_assignee_id = aa.assignor_and_assignee_id INNER JOIN assignment_conveyance as ac ON a.rf_id = ac.rf_id LEFT JOIN representative as r ON r.representative_id = aa.representative_id WHERE ac.convey_ty IN ('assignment', 'partialassignment', 'merger', 'namechg', 'courtorder', 'courtappointment') AND a.rf_id IN (".implode(',', $allRFIDs).")";
									
									$resultAssignor = $con->query($queryAssignors);
									
									$assignorList = array();
									
									if($resultAssignor && $resultAssignor->num_rows > 0) {
										while($rowAssignor = $resultAssignor->fetch_object()){
											$name = $rowAssignor->representative_name;
											
											if($name == null || $name == ''){
												$name = $rowAssignor->name;
											}
											array_push($assignorList, $name);
										}
									}									
									//print_r($assignorList);
									//print_r($assigneeList);
									$resultCompanies = array_diff($assigneeList, $assignorList );
									//print_r($resultCompanies);
									if(count($resultCompanies) > 0) {
										$findCompany = false;
										foreach($resultCompanies as $company){
											if(in_array($company, $allCompanies)){
												$findCompany = true;
												break;
											}
										}
										
										if($findCompany === false) {
											/*Error Title*/
											//print_r("YES");
											array_push($errorInList, $app);
										}	
									}
								}
							}
						}
						
						
						
						if(count($errorInList) > 0) {	
							$con->query("DELETE FROM db_application.error WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID ." AND type = 1" );
							$queryInsertError = "INSERT INTO db_application.error(organisation_id, representative_id, appno_doc_num, type) VALUES ";
							foreach($errorInList as $err) {
								$queryInsertError .= "(".$orgRow->organisation_id.",".$representativeID.",'".$err."', 1), ";
							}
							$queryInsertError = substr($queryInsertError, 0, -2);	
							//echo $queryInsertError;
							$con->query($queryInsertError);
						}
						
						
						echo "TRANSACTIONS<br/>";
						$buy = 0;
						$sale = 0;
						$security = 0;
						$release = 0;
						$licenseIn = 0;
						$licenseOut = 0;
			
						$queryAcquired = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "partialassignment", "merger")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
						//echo $queryAcquired."<br/>";
						$resultAcquired = $con->query($queryAcquired);

						if($resultAcquired) {
							$row = $resultAcquired->fetch_object();
							$buy = $row->totalRecords;
						}
						
						$querySold = 'SELECT count(*) as totalRecords FROM (Select ee.* from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' )  AND ac.convey_ty IN ("assignment", "partialassignment", "merger")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
						//echo $querySold."<br/>";
						$resultSold = $con->query($querySold);

						if($resultSold) {
							$row = $resultSold->fetch_object();
							$sale = $row->totalRecords;
						}
						
						
						
						$querySecurity = 'SELECT count(*) as totalRecords FROM (Select ee.* from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ac.employer_assign = "0" AND ac.convey_ty IN ("security", "restatedsecurity")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
						//echo $querySecurity."<br/>";
						$resultSecurity = $con->query($querySecurity);

						if($resultSecurity) {
							$row = $resultSecurity->fetch_object();
							$security = $row->totalRecords;
						}
						
						$queryRelease = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ac.employer_assign = "0" AND ac.convey_ty IN ("release")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
						//echo $queryRelease."<br/>";
						$resultRelease = $con->query($queryRelease);

						if($resultRelease) {
							$row = $resultRelease->fetch_object();
							$release = $row->totalRecords;
						}
						
						$queryLicenseIn = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND a.convey_text LIKE "%license%" AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "govern")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
						//echo $queryLicenseIn."<br/>";
						/*echo $queryLicenseIn;*/
						
						$resultLicenseIn = $con->query($queryLicenseIn);
						
						if($resultLicenseIn) {
							$row = $resultLicenseIn->fetch_object();
							$licenseIn = $row->totalRecords;
						}
						
						$queryLicenseOut = 'SELECT count(*) as totalRecords FROM (Select ee.* from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' )AND a.convey_text LIKE "%license%" AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "govern")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
						
						//echo $queryLicenseOut."<br/>";
						$resultLicenseOut = $con->query($queryLicenseOut);
						
						if($resultLicenseOut) {
							$row = $resultLicenseOut->fetch_object();
							$licenseOut = $row->totalRecords;
						}			
						$con->query("DELETE FROM db_application.transaction WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID );
						
						$con->query("INSERT INTO db_application.`transaction`(organisation_id,representative_id,buy,sale,`security`,`release`,license_in,license_out,transaction_list) VALUES (".$orgRow->organisation_id.", ".$representativeID.", ".$buy.", ".$sale.",".$security.",".$release.",".$licenseIn.",".$licenseOut.", null)");
						echo "UPDATE<br/>";
						$con->query("DELETE FROM db_application.update WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID );
						$weeklyTransCounter = 0;
						$weeklyAssetsCounter = 0;
						$lastMonthTransCounter = 0;
						$lastMonthAssetsCounter = 0;
						$quaterlyTransCounter = 0;
						$quaterlyAssetsCounter = 0;
						if(count($rfIDs) > 0) {
							$date = new DateTime("now");
							$todayDay = $date->format('Y-m-d'); 
							$date = new DateTime("now");
							$week = $date->modify('-1 week');
							$weekDay = $week->format('Y-m-d'); 
							$date = new DateTime("now");
							$last = $date->modify('-1 month');
							$lastMonth = $last->format('Y-m-d'); 
							$date = new DateTime("now");
							$quaterly = $date->modify('-100 days');
							$quaterlyDate = $date->format('Y-m-d');
							
							$queryWeeklyTransaction = "SELECT count(rf_id) as counter FROM (SELECT rf_id FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format(record_dt, '%Y-%m-%d') BETWEEN '".$weekDay."' AND '".$todayDay."' GROUP BY rf_id) as temp";
							
							$resultWeeklyTrans = $con->query($queryWeeklyTransaction);
							if($resultWeeklyTrans) {
								$row = $resultWeeklyTrans->fetch_object();
								$weeklyTransCounter = $row->counter;
							}
							
							$queryWeeklyAssets = "SELECT count(appno_doc_num) as counter FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT rf_id FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format('record_dt', '%Y-%m-%d') BETWEEN '".$weekDay."' AND '".$todayDay."' GROUP BY rf_id) AND appno_doc_num <> '' GROUP BY appno_doc_num) as temp";
							
							$resultWeeklyTrans = $con->query($queryWeeklyAssets);
							if($resultWeeklyTrans) {
								$row = $resultWeeklyTrans->fetch_object();
								$weeklyAssetsCounter = $row->counter;
							}
							
							$queryMonthTransaction = "SELECT count(rf_id) as counter FROM (SELECT rf_id FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format(record_dt, '%Y-%m-%d') BETWEEN '".$lastMonth."' AND '".$todayDay."' GROUP BY rf_id) as temp";
							
							$resultMonthTrans = $con->query($queryMonthTransaction);
							if($resultMonthTrans) {
								$row = $resultMonthTrans->fetch_object();
								$lastMonthTransCounter = $row->counter;
							}
							
							$queryMonthAssets = "SELECT count(appno_doc_num) as counter FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT rf_id FROM documentid WHERE rf_id IN (SELECT rf_id FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format('record_dt', '%Y-%m-%d') BETWEEN '".$lastMonth."' AND '".$todayDay."' GROUP BY rf_id) AND appno_doc_num <> '' GROUP BY appno_doc_num) as temp";
							
							$resultMonthTrans = $con->query($queryMonthAssets);
							if($resultMonthTrans) {
								$row = $resultMonthTrans->fetch_object();
								$lastMonthAssetsCounter = $row->counter;
							}
							
							$queryQuaterlyTransaction = "SELECT count(*) as counter FROM (SELECT rf_id FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format(record_dt, '%Y-%m-%d') BETWEEN '".$quaterlyDate."' AND '".$todayDay."' GROUP BY rf_id) as temp";
							
							$resultQuaterlyTrans = $con->query($queryQuaterlyTransaction);
							if($resultQuaterlyTrans) {
								$row = $resultQuaterlyTrans->fetch_object();
								$quaterlyTransCounter = $row->counter;
							}
							
							$queryQuaterlyAssets = "SELECT count(appno_doc_num) as counter FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT count(rf_id) as counter FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format('record_dt', '%Y-%m-%d') BETWEEN '".$quaterlyDate."' AND '".$todayDay."' GROUP BY rf_id) AND appno_doc_num <> '' GROUP BY appno_doc_num) as temp";
							
							$resultQuaterlyTrans = $con->query($queryQuaterlyTransaction);
							if($resultQuaterlyTrans) {
								$row = $resultQuaterlyTrans->fetch_object();
								$quaterlyAssetsCounter = $row->counter;
							}							
						}
						
						
						$con->query("INSERT INTO db_application.`update` (organisation_id,representative_id, weekly_transactions, weekly_applications, monthly_transactions,montly_applications,quaterly_transactions,quaterly_applications,update_transaction_list,update_application_list) VALUES (".$orgRow->organisation_id.", ".$representativeID.", ".$weeklyTransCounter.", ".$weeklyAssetsCounter.", ".$lastMonthTransCounter.", ".$lastMonthAssetsCounter.", ".$quaterlyTransCounter.", ".$quaterlyAssetsCounter.", NULL, NULL)");
						
						/*End of Insert in Application, Transaction, and Update table*/
						
						/*Tree*/
						echo "Tree<br/>";
								
								
							$allNames = array();
							
							foreach($allCompanies as $company) {
								array_push($allNames, "'".$con->real_escape_string($company)."'");
							}
							
							$con->query("DELETE FROM tree WHERE organisation_id = ".$orgRow->organisation_id." AND representative_id = ".$representativeID);
							
							/*Acquisition*/
							
							/*Purchase*/
							$queryPurchase = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "0" as type, "0" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("assignment", "partialassignment") AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							echo $queryPurchase."<br/>";
							$con->query($queryPurchase);
							
							/*Sale*/
							$querySale = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "1" as type, "1" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment") AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
							echo $querySale."<br/>";
							$con->query($querySale);
							
							
							/*License-In*/
							$queryLicenseIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "2" as type, "2" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("license", "licenseend", "govern") AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							echo $queryLicenseIn."<br/>";
							$con->query($queryLicenseIn);
							
							/*License-Out*/
							$queryLicenseOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "3" as type, "3" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("license", "licenseend", "govern") AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
							echo $queryLicenseOut."<br/>";
							$con->query($queryLicenseOut);
							
							/*SecurityOut*/
							$querySecurityOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "4" as type, "4" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("security", "restatedsecurity") AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
							echo $querySecurityOut."<br/>";
							$con->query($querySecurityOut);
							
							/*ReleaseIn*/
							$queryReleaseIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "5" as type, "4" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as `ee` INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("release", "restatedsecurity") AND ass.employer_assign = 0 AND  (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
							echo $queryReleaseIn."<br/>";
							$con->query($queryReleaseIn);
							
							/*MergerIn*/
							$queryMergerIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "6" as type, "5" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							echo $queryMergerIn."<br/>";
							$con->query($queryMergerIn);
							
							/*MergerOut*/
							$queryMergerOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "7" as type, "6" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							echo $queryMergerOut."<br/>";
							$con->query($queryMergerOut);
							
							
							
							/*Option*/
							$queryOptionIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "8" as type, "7" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "option" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							echo $queryOptionIn."<br/>";
							$con->query($queryOptionIn);
							
							$queryOptionOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "9" as type, "7" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "option" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							echo $queryOptionOut."<br/>";
							$con->query($queryOptionOut);
							
							/*CourtOrders*/
							$queryCourtOrderIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "10" as type, "8" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "courtorder" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							echo $queryCourtOrderIn."<br/>";
							$con->query($queryCourtOrderIn);
							
							$queryCourtOrderOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "11" as type, "8" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "courtorder" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							echo $queryCourtOrderOut."<br/>";
							$con->query($queryCourtOrderOut);
							
							$customEmployeeQuery = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent,"12" as type, "9" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM db_uspto.assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.employer_assign = 1 AND ass.convey_ty IN ("assignment", "partialassignment", "employee") AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							echo $customEmployeeQuery."<br/>";						
							$con->query($customEmployeeQuery);
															
							/*Missing*/
							$queryMissingChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "13" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "missing" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
							echo $queryMissingChange."<br/>";
							$con->query($queryMissingChange);
							
							$queryMissingChangeAssignor = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "14" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "missing" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							echo $queryMissingChangeAssignor."<br/>";
							$con->query($queryMissingChangeAssignor);
							
							/*Other*/
							$queryOtherChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "15" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "other" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
							echo $queryOtherChange."<br/>";
							$con->query($queryOtherChange);
							
							$queryOtherChangeAssignor = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "16" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "other" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							echo $queryOtherChangeAssignor."<br/>";
							$con->query($queryOtherChangeAssignor);
							
							/*NameChange*/
							$queryNameChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "17" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "namechg" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
							echo $queryNameChange."<br/>";
							$con->query($queryNameChange);
							
							$queryNameChangeAssignor = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "18" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "namechg" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							echo $queryNameChangeAssignor."<br/>";
							$con->query($queryNameChangeAssignor);
							 
							/*Timeline Table*/
							echo "Timeline<br/>";
							$con->query("DELETE FROM `db_application`.`timeline` WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID);
							
							/*Purchase*/
							try{
								$queryPurchase = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "0" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment") AND ac.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $queryPurchase."<br/>";
								$con->query($queryPurchase);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Sale*/
							try{
								$querySale = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "1" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment") AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								echo $querySale."<br/>";
								$con->query($querySale);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*License-In*/
							try{
								$queryLicenseIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "2" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("license", "licenseend", "govern") AND ac.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $queryLicenseIn."<br/>";
								$con->query($queryLicenseIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*License-Out*/
							try{
								$queryLicenseOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "3" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("license", "licenseend", "govern") AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								echo $queryLicenseOut."<br/>";
								$con->query($queryLicenseOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*SecurityOut*/
							try{
								$querySecurityOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "4" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("security", "restatedsecurity") AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								echo $querySecurityOut."<br/>";
								$con->query($querySecurityOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*ReleaseIn*/
							try{
								$queryReleaseIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "4" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("release", "restatedsecurity") AND ac.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $queryReleaseIn."<br/>";
								$con->query($queryReleaseIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*MergerIn*/
							try{
								$queryMergeIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "5" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "merger" AND ac.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $queryMergeIn."<br/>";
								$con->query($queryMergeIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*MergerOut*/
							try{
								$queryMergerOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "6" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "merger" AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								echo $queryMergerOut."<br/>";
								$con->query($queryMergerOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Option*/
							try{
								$queryOptionIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "7" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "option" AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								echo $queryOptionIn."<br/>";
								$con->query($queryOptionIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*OptionOut*/
							try{
								$queryOptionOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "7" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "option" AND ac.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $queryOptionOut."<br/>";
								$con->query($queryOptionOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*CourtOrders*/
							try{
								$queryCourtOrderIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "8" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "courtorder" AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								echo $queryCourtOrderIn."<br/>";
								$con->query($queryCourtOrderIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*CourtOrderOut*/
							try{
								$queryCourtOrderOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "8" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "courtorder" AND ac.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $queryCourtOrderOut."<br/>";
								$con->query($queryCourtOrderOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Employer*/
							try{
								$customEmployeeQuery = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "9" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment", "employee") AND ac.employer_assign = 1 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $customEmployeeQuery."<br/>";
								$con->query($customEmployeeQuery);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Missing*/
							try{
								$queryMissingChange = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "missing" AND ac.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $queryMissingChange."<br/>";
								$con->query($queryMissingChange);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Missing*/
							try{
								$queryMissingChangeAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "missing" AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								echo $queryMissingChangeAssignee."<br/>";
								$con->query($queryMissingChangeAssignee);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Other*/
							try{
								$queryOtherChange = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "other" AND ac.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $queryOtherChange."<br/>";
								$con->query($queryOtherChange);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*NameChange*/
							try{
								$queryOtherChangeAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "other" AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								echo $queryOtherChangeAssignee."<br/>";
								$con->query($queryOtherChangeAssignee);
							}catch(Exception $e){
								print_r($e);
							}
							
							
							/*NameChange*/
							try{
								$queryNameChange = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "namechg" AND ac.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								echo $queryNameChange."<br/>";
								$con->query($queryNameChange);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*NameChange*/
							try{
								$queryNameChangeAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "namechg" AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								echo $queryNameChangeAssignee."<br/>";
								$con->query($queryNameChangeAssignee);
							}catch(Exception $e){
								print_r($e);
							}				
						}
					}
				}
			}
		}
	}
}
function removeDoubleSpace($string) {
	return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return trim(strtolower($string));
}

/**
 * A slightly more readable, non-regex solution.
 */
function remove_if_trailing($haystack, $needle)
{
    // The length of the needle as a negative number is where it would appear in the haystack
    $needle_position = strlen($needle) * -1;  
	$lp = 0;
    // If the last N letters match $needle
    if (substr(strtolower($haystack), $needle_position) == strtolower($needle)) {
         // Then remove the last N letters from the string
        $haystack = substr($haystack, 0, $needle_position);
		if(strtolower($needle) == "company"){
			$haystack .= " co";
		} else if(strtolower($needle) == "incorporated"){
			$haystack .= " inc";
		} else if(strtolower($needle) == "limited"){
			$haystack .= " ltd";
		} else if(strtolower($needle) == "corporation"){
			$haystack .= " corp";
		}
		$lp = 1;
    }
    return array(trim(ucwords(strtolower($haystack))), $lp);
}