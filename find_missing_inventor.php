<?php 
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbApplication);

$variables = $argv;
/*OrganisationID, RepresentativeID*/

if(count($variables) == 3) {
	$organisationID = $variables[1];
	$representativeID = $variables[2];
	echo $organisationID.",".$representativeID."<br/>";
	if((int)$organisationID > 0) {
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		/*echo $queryOrganisation."<br/>";*/
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				if($representativeID != "" && $representativeID > 0) {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE representative_id = '".$representativeID."' AND parent_id = 0";
				} else {
					$queryRepresentative = "SELECT representative_id FROM representative WHERE (original_name = '".$con->real_escape_string($orgRow->name)."' OR representative_name = '".$con->real_escape_string($orgRow->name)."') AND parent_id = 0";
				}
				
				//echo $queryRepresentative."<br/>";
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
				
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
					if($representativeID == "") {
						$rowData = $resultRepresentativeParentCompany->fetch_object();
						$representativeID = $rowData->representative_id;
					}
					$findRepresentativeName = "Select original_name, representative_name FROM representative WHERE representative_id = '".$representativeID."' OR parent_id = '".$representativeID."'";
					//echo $findRepresentativeName."<br/>";
					$resultRepresentativeCompanies = $orgConnect->query($findRepresentativeName);
					
					$allCompanies = array();
					
					while($row = $resultRepresentativeCompanies->fetch_object()){
						$name = $row->representative_name;
						if($name == null || $name == "") {
							$name = $row->original_name;
						}
						array_push($allCompanies, $name);
					}
					
					if(count($allCompanies) > 0) {
						$errorInList = array();
						$queryAssignee = 'SELECT rf_id FROM `db_application`.`assignee` as ac WHERE assignor_and_assignee_id IN ( SELECT assignor_and_assignee_id FROM `db_application`.`assignor_and_assignee` as aa LEFT JOIN `db_application`.`representative` as r1 ON r1.representative_id = aa.representative_id WHERE ( ';
						
						foreach($allCompanies as $company) {
							$queryAssignee .= 'aa.name = "'.$con->real_escape_string($company).'" OR r1.representative_name="'.$con->real_escape_string($company).'" OR ';
						}
						
						$queryAssignee = substr($queryAssignee, 0, -3);
						
						$queryAssignee .= ' ) ) GROUP BY rf_id ';
						/*echo $queryAssignee."<br/>";*/
						$result = $con->query($queryAssignee);
						$rfIDs = [];
						/*echo $result->num_rows."<br/>";*/
						if($result->num_rows > 0) {	
							while($row = $result->fetch_object()){
								array_push($rfIDs, $row->rf_id);
							}
						}
						
						$queryAssignor = 'SELECT rf_id FROM `db_application`.`assignor` as ac WHERE assignor_and_assignee_id IN ( SELECT assignor_and_assignee_id FROM `db_application`.`assignor_and_assignee` as aa LEFT JOIN `db_application`.`representative` as r1 ON r1.representative_id = aa.representative_id WHERE ( ';
						
						foreach($allCompanies as $company) {
							$queryAssignor .= ' aa.name = "'.$con->real_escape_string($company).'" OR r1.representative_name="'.$con->real_escape_string($company).'" OR ';
						}		

						$queryAssignor = substr($queryAssignor, 0, -3);
						
						$queryAssignor .= ') ) GROUP BY rf_id';
						/*echo $queryAssignor."<br/>";*/
						$result = $con->query($queryAssignor);
						
						/*echo $result->num_rows."<br/>";*/
						if($result->num_rows > 0) {	
							while($row = $result->fetch_object()){
								array_push($rfIDs, $row->rf_id);
							}
						}
						
						/*echo "<pre>";
						print_r($rfIDs);*/
						
						$queryFindCorrectRFIDs = 'SELECT appno_doc_num FROM db_application.documentid WHERE appno_doc_num <> "" AND  rf_id IN ('.implode(',', $rfIDs).') GROUP BY appno_doc_num';
						
						$resultIDs = $con->query($queryFindCorrectRFIDs);
						$appNo = array(); 
						if($resultIDs && $resultIDs->num_rows > 0) {
							$appNo = array(); 
							while($row = $resultIDs->fetch_object()){
								array_push($appNo, $row->appno_doc_num);
							}
						}
						
						if(count($appNo) > 0) {
							foreach($appNo as $app) {
								
								$queryInventorCheck = "SELECT ac.rf_id FROM db_application.assignment_conveyance as ac INNER JOIN db_application.documentid as d ON d.rf_id = ac.rf_id WHERE d.appno_doc_num = '".$app."' AND ac.employer_assign = 1";
								//echo $queryInventorCheck."<br/>";
								$resultInventor = $con->query($queryInventorCheck);
								//echo "<pre>";
								//print_r($resultInventor);
								$queryInventorFromPatentApplication = "SELECT * FROM db_patent_application_bibliographic.inventor WHERE appno_doc_num = '".$app."'";
								
								$resultPatentApplicationInventor = $con->query($queryInventorFromPatentApplication);
								//echo $queryInventorFromPatentApplication."<br/>";
								
								//print_r($resultPatentApplicationInventor);
								//echo $app."@@".$resultPatentApplicationInventor->num_rows."@@".$resultInventor->num_rows."<br/>";
								if(($resultPatentApplicationInventor->num_rows > 0 && $resultPatentApplicationInventor->num_rows > $resultInventor->num_rows) || ($resultPatentApplicationInventor->num_rows == 0 && $resultInventor->num_rows == 0 || $resultInventor->num_rows == 0 ) ) {
									$errorInList[] = $app;
								}
							}
						}
						/*echo "<pre>";
						print_r($errorInList);*/
						if(count($errorInList) > 0) {	
							$con->query("DELETE FROM db_application.error WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID );
							$queryInsertError = "INSERT INTO db_application.error(organisation_id, representative_id, appno_doc_num) VALUES ";
							foreach($errorInList as $err) {
								$queryInsertError .= "(".$orgRow->organisation_id.",".$representativeID.",'".$err."'), ";
							}
							$queryInsertError = substr($queryInsertError, 0, -2);
							//echo $queryInsertError;
							$con->query($queryInsertError);
						}
						
						
						
						/*End Errors*/		
						/*Patent and assets*/
						
						$allNames = "";
						
						foreach($allCompanies as $company) {
							$allNames .= ' aaa.name = "'.$con->real_escape_string($company).'" OR r.representative_name="'.$con->real_escape_string($company).'" OR ';
						}
						
						$allNames = substr($allNames, 0, -3);
						
						$queryAssets = 'SELECT appno_doc_num, grant_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee`
						INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id 
						LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
						LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
						WHERE ( '.$allNames.' ) AND ac.convey_ty IN ("assignment","namechg","merger","employee")) AND 
						appno_doc_num NOT IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`
						INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id 
						LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
						LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
						WHERE ( '.$allNames.' ) 
						AND ac.convey_ty IN ("assignment","namechg","merger","employee")) GROUP BY appno_doc_num)
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
						
						
						if(count($allAppNo) > 0) {	
							
							foreach($allAppNo as $app) {
								
								$querySecurity = 'SELECT count(*) as countRecords FROM documentid as d INNER JOIN assignment_conveyance as ac ON ac.rf_id = d.rf_id INNER JOIN assignors as a ON a.rf_id = ac.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT representative as r ON r.representative_id = aaa.representative_id  WHERE d.appno_doc_num = "'.$app.'" AND ac.convey_ty = "security" AND ('.$allNames.')';
								
								$resultSecurity = $con->query($querySecurity);
								
								$queryRelease = 'SELECT count(*) as countRecords FROM documentid as d INNER JOIN assignment_conveyance as ac ON ac.rf_id = d.rf_id INNER JOIN assignee as a ON a.rf_id = ac.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = a.assignor_and_assignee_id LEFT representative as r ON r.representative_id = aaa.representative_id  WHERE d.appno_doc_num = "'.$app.'" AND ac.convey_ty = "release" AND ('.$allNames.')';
								
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
								//echo $app.": ".$security."@@". $release."<br/>";
								if($security > 0 && $security > $release) {
									//echo "ENTERED<br/>";
									$encumberedS++;
								}				
							}							
						}
						
						$con->query("DELETE FROM db_application.validity WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID );	
						
						$con->query("INSERT INTO db_application.validity (organisation_id, representative_id, application, patent, encumbered) VALUES (".$orgRow->organisation_id.",".$representativeID.",".$applicationS.",".$patentS.",".$encumberedS.")");
						
						$buy = 0;
						$sale = 0;
						$security = 0;
						$release = 0;
						$licenseIn = 0;
						$licenseOut = 0;
			
						$queryAcquired = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ac.employer_assign = "1" AND ac.convey_ty IN ("assignment", "employee")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
						//echo $queryAcquired."<br/>";
						$resultAcquired = $con->query($queryAcquired);

						if($resultAcquired) {
							$row = $resultAcquired->fetch_object();
							$buy = $row->totalRecords;
						}
						
						$querySold = 'SELECT count(*) as totalRecords FROM (Select ee.* from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "employee", "merger")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
						//echo $querySold."<br/>";
						$resultSold = $con->query($querySold);

						if($resultSold) {
							$row = $resultSold->fetch_object();
							$sale = $row->totalRecords;
						}
						
						$querySecurity = 'SELECT count(*) as totalRecords FROM (Select ee.* from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ac.employer_assign = "0" AND ac.convey_ty IN ("security")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
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
						
						$queryLicenseIn = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND a.convey_text LIKE "%license%" AND ac.employer_assign = "0" AND ac.convey_ty IN ("other")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
						//echo $queryLicenseIn."<br/>";
						/*echo $queryLicenseIn;*/
						
						$resultLicenseIn = $con->query($queryLicenseIn);
						
						if($resultLicenseIn) {
							$row = $resultLicenseIn->fetch_object();
							$licenseIn = $row->totalRecords;
						}
						
						$queryLicenseOut = 'SELECT count(*) as totalRecords FROM (Select ee.* from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' )AND a.convey_text LIKE "%license%" AND ac.employer_assign = "0" AND ac.convey_ty IN ("other")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
						
						//echo $queryLicenseOut."<br/>";
						$resultLicenseOut = $con->query($queryLicenseOut);
						
						if($resultLicenseOut) {
							$row = $resultLicenseOut->fetch_object();
							$licenseOut = $row->totalRecords;
						}			
						$con->query("DELETE FROM db_application.transaction WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID );
						/*echo "DELETE FROM db_application.transaction WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID."<br/>";
						echo "INSERT INTO db_application.`transaction`(organisation_id,representative_id,buy,sale,security,release,license_in,license_out,transaction_list) VALUES (".$orgRow->organisation_id.", ".$representativeID.", ".$buy.", ".$sale.",".$security.",".$release.",".$licenseIn.",".$licenseOut.")<br/>";*/
						$con->query("INSERT INTO db_application.`transaction`(organisation_id,representative_id,buy,sale,`security`,`release`,license_in,license_out,transaction_list) VALUES (".$orgRow->organisation_id.", ".$representativeID.", ".$buy.", ".$sale.",".$security.",".$release.",".$licenseIn.",".$licenseOut.", null)");
						
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
							
							$queryWeeklyTransaction = "SELECT count(rf_id) as counter FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format('record_dt', '%Y-%m-%d') BETWEEN '".$weekDay."' AND '".$todayDay."'";
							
							$resultWeeklyTrans = $con->query($queryWeeklyTransaction);
							if($resultWeeklyTrans) {
								$row = $resultWeeklyTrans->fetch_object();
								$weeklyTransCounter = $row->counter;
							}
							
							$queryWeeklyAssets = "SELECT count(appno_doc_num) as counter FROM documentid WHERE rf_id IN (SELECT rf_id FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format('record_dt', '%Y-%m-%d') BETWEEN '".$weekDay."' AND '".$todayDay."') AND appno_doc_num <> ''";
							
							$resultWeeklyTrans = $con->query($queryWeeklyAssets);
							if($resultWeeklyTrans) {
								$row = $resultWeeklyTrans->fetch_object();
								$weeklyAssetsCounter = $row->counter;
							}
							
							$queryMonthTransaction = "SELECT count(rf_id) as counter FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format('record_dt', '%Y-%m-%d') BETWEEN '".$lastMonth."' AND '".$todayDay."'";
							
							$resultMonthTrans = $con->query($queryMonthTransaction);
							if($resultMonthTrans) {
								$row = $resultMonthTrans->fetch_object();
								$lastMonthTransCounter = $row->counter;
							}
							
							$queryMonthAssets = "SELECT count(appno_doc_num) as counter FROM documentid WHERE rf_id IN (SELECT rf_id FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format('record_dt', '%Y-%m-%d') BETWEEN '".$lastMonth."' AND '".$todayDay."')";
							
							$resultMonthTrans = $con->query($queryMonthAssets);
							if($resultMonthTrans) {
								$row = $resultMonthTrans->fetch_object();
								$lastMonthAssetsCounter = $row->counter;
							}
							
							$queryQuaterlyTransaction = "SELECT count(rf_id) as counter FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format('record_dt', '%Y-%m-%d') BETWEEN '".$quaterlyDate."' AND '".$todayDay."'";
							
							$resultQuaterlyTrans = $con->query($queryQuaterlyTransaction);
							if($resultQuaterlyTrans) {
								$row = $resultQuaterlyTrans->fetch_object();
								$quaterlyTransCounter = $row->counter;
							}
							
							$queryQuaterlyAssets = "SELECT count(appno_doc_num) as counter FROM documentid WHERE rf_id IN (SELECT count(rf_id) as counter FROM db_application.assignment WHERE rf_id IN (".implode(',', $rfIDs).") AND date_format('record_dt', '%Y-%m-%d') BETWEEN '".$quaterlyDate."' AND '".$todayDay."')";
							
							$resultQuaterlyTrans = $con->query($queryQuaterlyTransaction);
							if($resultQuaterlyTrans) {
								$row = $resultQuaterlyTrans->fetch_object();
								$quaterlyAssetsCounter = $row->counter;
							}							
						}
						
						
						$con->query("INSERT INTO db_application.`update` (organisation_id,representative_id, weekly_transactions, weekly_applications, monthly_transactions,montly_applications,quaterly_transactions,quaterly_applications,update_transaction_list,update_application_list) VALUES (".$orgRow->organisation_id.", ".$representativeID.", ".$weeklyTransCounter.", ".$weeklyAssetsCounter.", ".$lastMonthTransCounter.", ".$lastMonthAssetsCounter.", ".$quaterlyTransCounter.", ".$quaterlyAssetsCounter.", NULL, NULL)");
					}
				}				
			}
		}
	}
}
?>