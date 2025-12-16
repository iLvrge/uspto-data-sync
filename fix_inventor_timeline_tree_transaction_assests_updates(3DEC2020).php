<?php 
ignore_user_abort(true);
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);*/
$con = new mysqli("167.172.195.92","db_user_all","wDv%5tgn0O0kMkM","db_application");
 
$variables = $argv;
$grandEntered = false;
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
				$nameRepresentaitve = "";
				$rfIDUpdated = array();
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
								$nameRepresentaitve = $row->representative_name;
								if($nameRepresentaitve == null ) {
									$nameRepresentaitve = $row->original_name;
								}
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
						
						$currentYearRFID = array();
						$previousYearRFID = array();
						
						/*Current Year*/
						$now   = new DateTime;
						$clone = clone $now;   
						$clone->modify( '-1 year' );
						$startDate = $clone->format( 'Y-m-d' );
						$endDate = $now->format( 'Y-m-d' );
						/*End Current Year*/
						
						/*Prev Year*/
						$clone->modify( '-1 day' );
						$endPrevDate = $clone->format( 'Y-m-d' );
						$clone->modify( '-1 year' );
						$startPrevDate = $clone->format( 'Y-m-d' );
						
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
							//echo "Patents & Assets & Encumbered<br/>";
							$allNames = array();
							
							foreach($allCompanies as $company) {
								array_push($allNames, '"'.$con->real_escape_string($company).'"');
							}
							
							$allNames = 'aaa.name IN ('.implode(",", $allNames ).')';
						
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
							
							$errorInList = array();
							if(count($allAppNo) > 0) {
								foreach($allAppNo as $app) {								
									$queryInventorCheck = "SELECT ac.rf_id FROM db_application.assignment_conveyance as ac INNER JOIN db_application.documentid as d ON d.rf_id = ac.rf_id WHERE d.appno_doc_num = '".$app."' AND ac.employer_assign = 1";
									$resultInventor = $con->query($queryInventorCheck);
									if($resultInventor->num_rows == 0 ) {
										array_push($errorInList, $app);
									}
								}
							}
							echo "countERROR<br/>";
							/*print_r($errorInList);*/
							$con->query("DELETE FROM db_application.error WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID ." AND type = 0" );
							/*Error Inventor Table*/
							if(count($errorInList) > 0) {
								$queryInsertError = "INSERT IGNORE INTO db_application.error(organisation_id, representative_id, type, appno_doc_num, record_dt, cname, caddress_1 ) SELECT ".$orgRow->organisation_id.", ".$representativeID.", 0 as type, d.appno_doc_num as appno_doc_num, d.appno_date as record_dt, ass.cname as cname, ass.caddress_1 as caddress_1 FROM db_application.documentid as d LEFT JOIN db_application.assignment as ass ON ass.rf_id = d.rf_id WHERE d.appno_doc_num IN(".implode(',', $errorInList).") GROUP BY d.appno_doc_num ";
								$con->query($queryInsertError);
							}
							/*End Errors Inventor*/
						
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
										
										$queryAssignees = "SELECT r.representative_name, aa.name FROM assignor_and_assignee as aa INNER JOIN assignee as a ON a.assignor_and_assignee_id = aa.assignor_and_assignee_id INNER JOIN assignment_conveyance as ac ON a.rf_id = ac.rf_id LEFT JOIN representative as r ON r.representative_id = aa.representative_id WHERE (ac.convey_ty IN ('assignment', 'partialassignment', 'merger', 'namechg', 'courtorder', 'courtappointment') OR (ac.convey_ty IN ('assignment', 'employee') AND ac.employer_assign = 1)) AND a.rf_id IN (".implode(',', $allRFIDs).")";
										
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
										$resultCompanies = array_diff($assigneeList, $assignorList );
										if(count($resultCompanies) == 1){
											foreach($resultCompanies as $company){
												if(in_array($company, $allCompanies)){
													$findCompany = true;
													break;
												}
											}
											if($findCompany === false) {											
												array_push($errorInList, $app);
											}
										} else if(count($resultCompanies) == 0){
											$findCompany = true;
										} else {
											array_push($errorInList, $app);
										}
									}
								}
							}
							echo "countERRORTitle<br/>";
							if(count($errorInList) > 0) {	
								$con->query("DELETE FROM db_application.error WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID ." AND type = 1" );
								$queryInsertError = "INSERT IGNORE INTO db_application.error(organisation_id, representative_id, type, appno_doc_num, record_dt, cname, caddress_1) SELECT ".$orgRow->organisation_id." as organisation_id, ".$representativeID." as representative_id, 1 as type, d.appno_doc_num as appno_doc_num, d.appno_date as record_dt, ass.cname as cname, ass.caddress_1 as caddress_1 FROM db_application.documentid as d LEFT JOIN db_application.assignment as ass ON ass.rf_id = d.rf_id WHERE d.appno_doc_num IN(".implode(',', $errorInList).") GROUP BY d.appno_doc_num";
								$con->query($queryInsertError);
							}
							
							
							
							
							
							
							
							
							
							
							
							/*Update for Organisation*/
							if($grandEntered === false){
								$queryAllRepresentative = "SELECT representative_id, original_name FROM representative";
								$resultAllRepresentative = $orgConnect->query($queryAllRepresentative);
								if($resultAllRepresentative && $resultAllRepresentative->num_rows > 0) {
									$allRepresentativeNames = array();
									$allRepresentativeIDs = array();
									$allRepresentativeRFIDs = array();
									
									while($rowRepresentativeName = $resultAllRepresentative->fetch_object()) {
										array_push($allRepresentativeNames, $rowRepresentativeName->original_name);
										array_push($allRepresentativeIDs, $rowRepresentativeName->representative_id);
									}
									
									if(count($allRepresentativeIDs) > 0) {
										$queryAllRFIDS = "SELECT rf_id FROM db_uspto.representative_transactions WHERE representative_id IN (".implode(',', $allRepresentativeIDs).") GROUP BY rf_id";
										
										$resultRepresentativeAllRFIDs = $con->query($queryAllRFIDS);
						
										if($resultRepresentativeAllRFIDs && $resultRepresentativeAllRFIDs->num_rows > 0) {
											while($allRFIDs = $resultRepresentativeAllRFIDs->fetch_object()) {
												array_push($allRepresentativeRFIDs, $allRFIDs->rf_id);
											}
										}
									}
									
									if(count($allRepresentativeRFIDs) > 0) {
										$currentAllRFIDs = array();
										$previousAllRFIDs = array();
										$queryCurrentYearAllRFIDs = 'SELECT rf_id FROM assignor WHERE rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND date_format(exec_dt,"%Y-%m-%d") BETWEEN "'.$startDate.'" AND "'.$endDate.'" GROUP BY rf_id';
							
										$resultCurrentAllRFIDs = $con->query($queryCurrentYearAllRFIDs);
									
										if($resultCurrentAllRFIDs && $resultCurrentAllRFIDs->num_rows > 0) {
											while($rowRF = $resultCurrentAllRFIDs->fetch_object()) {
												array_push($currentAllRFIDs, $rowRF->rf_id);
											}
										}
										
										$queryPreviousYearAllRFIDs = 'SELECT rf_id FROM assignor WHERE rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND date_format(exec_dt,"%Y-%m-%d") BETWEEN "'.$startPrevDate.'" AND "'.$endPrevDate.'" GROUP BY rf_id';
										
										$resultPreviousAllRFIDs = $con->query($queryPreviousYearAllRFIDs);
									
										if($resultPreviousAllRFIDs && $resultPreviousAllRFIDs->num_rows > 0) {
											while($rowRF = $resultPreviousAllRFIDs->fetch_object()) {
												array_push($previousAllRFIDs, $rowRF->rf_id);
											}
										}
										
										$allRFNames = array();
							
										foreach($allRepresentativeNames as $company) {
											array_push($allRFNames, '"'.$con->real_escape_string($company).'"');
										}
										
										$allRFNames = 'aaa.name IN ('.implode(",", $allRFNames ).')';
										
										$queryAssets = 'SELECT appno_doc_num, grant_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee`
										INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id 
										LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
										LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
										WHERE ( '.$allRFNames.' ) AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $allRepresentativeRFIDs).') ) AND 
										appno_doc_num NOT IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`
										INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id 
										LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
										LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
										WHERE ( '.$allRFNames.' ) 
										AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $allRepresentativeRFIDs).')) GROUP BY appno_doc_num)
										GROUP BY appno_doc_num';
										
										
										$resultAssets = $con->query($queryAssets);
										
										$allApplicationS = 0;
										$allPatentS = 0;
										
										if($resultAssets) {
											while($row = $resultAssets->fetch_object()){
												if($row->grant_doc_num != "" && $row->grant_doc_num != null) {
													$allPatentS++;
												} else if($row->appno_doc_num != "" && $row->appno_doc_num != null  && $row->appno_doc_num > 0) {
													$allApplicationS++;
												}
											}
										}
									
										$currentAllPatentTotal = 0;
										$currentAllApplicationTotal = 0;
										$prevAllPatentTotal = 0;
										$prevAllApplicationTotal = 0;
										$diffAllPatent = 0;
										$diffAllApplication = 0;
									
										if(count($currentAllRFIDs) > 0){
											$queryCurrentAssetsA = 'SELECT count(*) as countAssets FROM (SELECT grant_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee` INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
											LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
											WHERE ( '.$allRFNames.' )  AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $currentAllRFIDs).') ) AND (appno_doc_num <> "" ) AND (grant_doc_num <> "" ) GROUP BY grant_doc_num) as temp';
											
											$resultCurrentAssetsA = $con->query($queryCurrentAssetsA);
											
											$queryCurrentAssetsB = 'SELECT count(*) as countAssets FROM (SELECT grant_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
											LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
											WHERE ( '.$allRFNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $currentAllRFIDs).')) AND (appno_doc_num <> "") AND (grant_doc_num <> "") GROUP BY grant_doc_num) as temp ';
											//echo $queryCurrentAssets."<br/>";	
											$resultCurrentAssetsB = $con->query($queryCurrentAssetsB);
											
											$totalB = 0;
											$totalA = 0;
											
											if($resultCurrentAssetsB){
												$totalB = $resultCurrentAssetsB->fetch_object()->countAssets;
											}
											
											if($resultCurrentAssetsA){
												$totalA = $resultCurrentAssetsA->fetch_object()->countAssets;
											}
											
											$currentAllPatentTotal = $totalB - $totalA;
											
											
											$queryCurrentAssetsA = 'SELECT count(*) as countAssets FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee` INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
											LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
											WHERE ( '.$allRFNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $currentAllRFIDs).') ) AND (appno_doc_num <> "" ) AND (grant_doc_num = "") GROUP BY appno_doc_num) as temp';
											
											$resultCurrentAssetsA = $con->query($queryCurrentAssetsA);
											
											$queryCurrentAssetsB = 'SELECT count(*) as countAssets FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
											LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
											WHERE ( '.$allRFNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $currentAllRFIDs).')) AND (appno_doc_num <> "") AND (grant_doc_num = "") GROUP BY appno_doc_num) as temp ';
											//echo $queryCurrentAssets."<br/>";	
											$resultCurrentAssetsB = $con->query($queryCurrentAssetsB);
											
											$totalB = 0;
											$totalA = 0;
											
											if($resultCurrentAssetsB){
												$totalB = $resultCurrentAssetsB->fetch_object()->countAssets;
											}
											
											if($resultCurrentAssetsA){
												$totalA = $resultCurrentAssetsA->fetch_object()->countAssets;
											}
											
											$currentAllApplicationTotal = $totalB - $totalA;
										}	
									
									
									
										if(count($previousAllRFIDs) > 0){
											$queryPrevAssetsA = 'SELECT count(*) as countAssets FROM (SELECT grant_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee` INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
											LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
											WHERE ( '.$allRFNames.' )  AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $previousAllRFIDs).') ) AND (appno_doc_num <> "" ) AND (grant_doc_num <> "") GROUP BY grant_doc_num) as temp';
											
											$resultPrevAssetsA = $con->query($queryPrevAssetsA);
											
											$queryPrevAssetsB = 'SELECT count(*) as countAssets FROM (SELECT grant_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
											LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
											WHERE ( '.$allRFNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $previousAllRFIDs).')) AND (appno_doc_num <> "") AND (grant_doc_num <> "") GROUP BY grant_doc_num) as temp ';
											//echo $queryCurrentAssets."<br/>";	
											$resultPrevAssetsB = $con->query($queryPrevAssetsB);
											
											$totalB = 0;
											$totalA = 0;
											
											if($resultPrevAssetsB){
												$totalB = $resultPrevAssetsB->fetch_object()->countAssets;
											}
											
											if($resultPrevAssetsA){
												$totalA = $resultPrevAssetsA->fetch_object()->countAssets;
											}
											
											$prevAllPatentTotal = $totalB - $totalA;
											
											
											$queryPrevAssetsA = 'SELECT count(*) as countAssets FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee` INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
											LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
											WHERE ( '.$allRFNames.' )  AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $previousAllRFIDs).') ) AND (appno_doc_num <> "") AND (grant_doc_num = "" ) GROUP BY appno_doc_num) as temp';
											
											$resultPrevAssetsA = $con->query($queryPrevAssetsA);
											
											$queryPrevAssetsB = 'SELECT count(*) as countAssets FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
											LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
											WHERE ( '.$allRFNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $previousAllRFIDs).')) AND (appno_doc_num <> "") AND (grant_doc_num = "") GROUP BY appno_doc_num) as temp ';
											//echo $queryCurrentAssets."<br/>";	
											$resultPrevAssetsB = $con->query($queryPrevAssetsB);
											
											$totalB = 0;
											$totalA = 0;
											
											if($resultPrevAssetsB){
												$totalB = $resultPrevAssetsB->fetch_object()->countAssets;
											}
											
											if($resultPrevAssetsA){
												$totalA = $resultPrevAssetsA->fetch_object()->countAssets;
											}
											
											$prevAllApplicationTotal = $totalB - $totalA;
										}
									
										$diffAllpatent = findDiffInPercentage($currentAllPatentTotal, $prevAllPatentTotal);
										$diffAllapplication = findDiffInPercentage($currentAllApplicationTotal, $prevAllApplicationTotal);
										
										
										
										$con->query("DELETE FROM db_application.validity WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = 0");	
										
										$con->query("INSERT INTO db_application.validity (organisation_id, representative_id, application, patent, encumbered, current_patent_year, current_application_year, previous_patent_year, previous_application_year, difference_patent, difference_application) VALUES (".$orgRow->organisation_id.",0,".$allApplicationS.",".$allPatentS.",0, ".$currentAllPatentTotal.", ".$currentAllApplicationTotal.", ".$prevAllPatentTotal.", ".$prevAllApplicationTotal.", ".$diffAllpatent.", ".$diffAllapplication.")");
										
										
										echo "TRANSACTIONS<br/>";
										$allBuy = 0;
										$allBuyPatents = 0;
										$allBuyPatentsCurrent = 0;
										$allBuyPatentsPrev = 0;
										$diffAllBuyPatents = 0;
										
										$allSale = 0;
										$allSalePatents = 0;
										$allSalePatentsCurrent = 0;
										$allSalePatentsPrev = 0;
										$diffAllSalePatents = 0;
										
										$allSecurity = 0;
										$allSecurityPatents = 0;
										$allSecurityPatentsCurrent = 0;
										$allSecurityPatentsPrev = 0;
										$diffAllSecurityPatents = 0;
										
										$allRelease = 0;
										$allReleasePatents = 0;
										$allReleasePatentsCurrent = 0;
										$allReleasePatentsPrev = 0;						
										$diffAllReleasePatents = 0;						
										
										$allLicenseIn = 0;
										$allLicenseInPatents = 0;
										$allLicenseInPatentsCurrent = 0;
										$allLicenseInPatentsPrev = 0;
										$diffallLicenseIn = 0;
										
										$allLicenseOut = 0;
										$allLicenseOutPatents = 0;
										$allLicenseOutPatentsCurrent = 0;
										$allLicenseOutPatentsPrev = 0;
										$diffallLicenseOut = 0;
							
										$queryAcquired = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
										//echo $queryAcquired."<br/>";
										$resultAcquired = $con->query($queryAcquired);

										if($resultAcquired) {
											$allBuy = $resultAcquired->fetch_object()->totalRecords;
										}
										
										$queryAcquiredPatent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
										
										$resultAcquiredPatent = $con->query($queryAcquiredPatent);

										if($resultAcquiredPatent) {
											$allBuyPatents = $resultAcquiredPatent->fetch_object()->totalRecords;
										}
										
										if(count($currentAllRFIDs) > 0) {
											$queryAcquiredPatentCurrent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND  ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $currentAllRFIDs).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											//echo $queryAcquiredPatentCurrent."<br/>";
											$resultAcquiredPatentCurrent = $con->query($queryAcquiredPatentCurrent);

											if($resultAcquiredPatentCurrent) {
												$allBuyPatentsCurrent = $resultAcquiredPatentCurrent->fetch_object()->totalRecords;
											}
										}
										
										if(count($previousAllRFIDs) > 0) {						
											$queryAcquiredPatentPrev = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $previousAllRFIDs).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											
											
											//echo $queryAcquiredPatentPrev."<br/>";
											
											$resultAcquiredPatentPrev = $con->query($queryAcquiredPatentPrev);

											if($resultAcquiredPatentPrev) {
												$allBuyPatentsPrev = $resultAcquiredPatentPrev->fetch_object()->totalRecords;
											}
										}
										
										$diffAllBuyPatents = findDiffInPercentage($allBuyPatentsCurrent, $allBuyPatentsPrev);
										
										$querySold = 'SELECT count(*) as totalRecords FROM (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' )  AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = 0 AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
										echo $querySold."<br/>";
										$resultSold = $con->query($querySold);

										if($resultSold) {
											$allSale = $resultSold->fetch_object()->totalRecords;
										}
										
										$querySoldPatents = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' )  AND ac.employer_assign = 0 AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).')  AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id)  GROUP BY appno_doc_num, grant_doc_num) as temp';
										
										$resultSoldPatents = $con->query($querySoldPatents);

										if($resultSoldPatents) {
											$allSalePatents = $resultSoldPatents->fetch_object()->totalRecords;
										}
										
										if(count($currentAllRFIDs) > 0) {
											$querySoldPatentsCurrent = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' )  AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = 0 AND ac.convey_ty IN ("assignment", "partialassignment") AND or.rf_id IN('.implode(',', $currentAllRFIDs).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											//echo $querySoldPatentsCurrent."<br/>";
											$resultSoldPatentsCurrent = $con->query($querySoldPatentsCurrent);

											if($resultSoldPatentsCurrent) {
												$allSalePatentsCurrent = $resultSoldPatentsCurrent->fetch_object()->totalRecords;
											}
										}
										
										if(count($previousAllRFIDs) > 0) {
											$querySoldPatentsPrev = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' )  AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = 0 AND ac.convey_ty IN ("assignment", "partialassignment") AND or.rf_id IN('.implode(',', $previousAllRFIDs).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											
											//echo $querySoldPatentsPrev."<br/>";
											$resultSoldPatentsPrev = $con->query($querySoldPatentsPrev);

											if($resultSoldPatentsPrev) {
												$allSalePatentsPrev = $resultSoldPatentsPrev->fetch_object()->totalRecords;
											}
										}
										
										$diffAllSalePatents = findDiffInPercentage($allSalePatentsCurrent, $allSalePatentsPrev);
										
										$querySecurity = 'SELECT count(*) as totalRecords FROM (Select ee.* from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("security", "restatedsecurity")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
										//echo $querySecurity."<br/>";
										$resultSecurity = $con->query($querySecurity);

										if($resultSecurity) {
											$allSecurity = $resultSecurity->fetch_object()->totalRecords;
										}
										
										$querySecurityPatent = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("security", "restatedsecurity")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id)  GROUP BY appno_doc_num, grant_doc_num) as temp';
										//echo $querySecurity."<br/>";
										$resultSecurityPatent = $con->query($querySecurityPatent);

										if($resultSecurityPatent) {
											$allSecurityPatents = $resultSecurityPatent->fetch_object()->totalRecords;
										}
										if(count($currentAllRFIDs) > 0) {
											$querySecurityPatentCurrent = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("security", "restatedsecurity") AND or.rf_id IN('.implode(',', $currentAllRFIDs).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id)  GROUP BY appno_doc_num, grant_doc_num) as temp';
											//echo $querySecurityPatentCurrent."<br/>";
											
											$resultSecurityPatentCurrent = $con->query($querySecurityPatentCurrent);

											if($resultSecurityPatentCurrent) {
												$allSecurityPatentsCurrent = $resultSecurityPatentCurrent->fetch_object()->totalRecords;
											}
										}
										
										if(count($previousAllRFIDs) > 0) {
											$querySecurityPatentPrev = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("security", "restatedsecurity")  AND or.rf_id IN('.implode(',', $previousAllRFIDs).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id)  GROUP BY appno_doc_num, grant_doc_num) as temp';
											
											//echo $querySecurityPatentPrev."<br/>";
											$resultSecurityPatentPrev = $con->query($querySecurityPatentPrev);

											if($resultSecurityPatentPrev) {
												$allSecurityPatentsPrev = $resultSecurityPatentPrev->fetch_object()->totalRecords;
											}
										}
										
										$diffAllSecurityPatents = findDiffInPercentage($allSecurityPatentsCurrent, $allSecurityPatentsPrev);
										
										$queryRelease = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("release", "restatedsecurity")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
										//echo $queryRelease."<br/>";
										$resultRelease = $con->query($queryRelease);

										if($resultRelease) {
											$allRelease = $resultRelease->fetch_object()->totalRecords;
										}
										
										$queryReleasePatents = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("release", "restatedsecurity")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
										//echo $queryRelease."<br/>";
										$resultReleasePatents = $con->query($queryReleasePatents);

										if($resultReleasePatents) {
											$allReleasePatents = $resultReleasePatents->fetch_object()->totalRecords;
										}
										if(count($currentAllRFIDs) > 0) {
											$queryReleasePatentsCurrent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("release", "restatedsecurity")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $currentAllRFIDs).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											//echo $queryReleasePatentsCurrent."<br/>";
											$resultReleasePatentsCurrent = $con->query($queryReleasePatentsCurrent);

											if($resultReleasePatentsCurrent) {
												$allReleasePatentsCurrent = $resultReleasePatentsCurrent->fetch_object()->totalRecords;
											}
										}
										if(count($previousAllRFIDs) > 0) {
											$queryReleasePatentsPrev = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("release", "restatedsecurity")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $previousAllRFIDs).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											
											//echo $queryReleasePatentsPrev."<br/>";
											$resultReleasePatentsPrev = $con->query($queryReleasePatentsPrev);

											if($resultReleasePatentsPrev) {
												$allReleasePatentsPrev = $resultReleasePatentsPrev->fetch_object()->totalRecords;
											}
										}
										
										$diffAllReleasePatents = findDiffInPercentage($allReleasePatentsCurrent, $allReleasePatentsPrev);
										
										$queryLicenseIn = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
																
										$resultLicenseIn = $con->query($queryLicenseIn);
										
										if($resultLicenseIn) {
											$allLicenseIn = $resultLicenseIn->fetch_object()->totalRecords;
										}
										
										$queryLicenseInPatents = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											
										$resultLicenseInPatents = $con->query($queryLicenseInPatents);
										
										if($resultLicenseInPatents) {
											$allLicenseInPatents = $resultLicenseInPatents->fetch_object()->totalRecords;
										}
										if(count($currentAllRFIDs) > 0) {
											$queryLicenseInPatentsCurrent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $currentAllRFIDs).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											//echo $queryLicenseInPatentsCurrent."<br/>";	
											$resultLicenseInPatentsCurrent = $con->query($queryLicenseInPatentsCurrent);
											
											if($resultLicenseInPatentsCurrent) {
												$allLicenseInPatentsCurrent = $resultLicenseInPatentsCurrent->fetch_object()->totalRecords;
											}
										}
										if(count($previousAllRFIDs) > 0) {
											$queryLicenseInPatentsPrev = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND ee.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $previousAllRFIDs).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											
											//echo $queryLicenseInPatentsPrev."<br/>";	
											$resultLicenseInPatentsPrev = $con->query($queryLicenseInPatentsPrev);
											
											if($resultLicenseInPatentsPrev) {
												$allLicenseInPatentsPrev = $resultLicenseInPatentsPrev->fetch_object()->totalRecords;
											}
										}
										$diffAllLicenseIn = findDiffInPercentage($allLicenseInPatentsCurrent, $allLicenseInPatentsPrev);
										
										$queryLicenseOut = 'SELECT count(*) as totalRecords FROM (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
										
										//echo $queryLicenseOut."<br/>";
										$resultLicenseOut = $con->query($queryLicenseOut);
										
										if($resultLicenseOut) {
											$allLicenseOut = $resultLicenseOut->fetch_object()->totalRecords;
										}			
										
										$queryLicenseOutPatents = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
										
										//echo $queryLicenseOut."<br/>";
										$resultLicenseOutPatents = $con->query($queryLicenseOutPatents);
										
										if($resultLicenseOutPatents) {
											$allLicenseOutPatents = $resultLicenseOutPatents->fetch_object()->totalRecords;
										}	
										
										if(count($currentAllRFIDs) > 0) {
											$queryLicenseOutPatentsCurrent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' ) AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern") AND or.rf_id IN('.implode(',', $currentAllRFIDs).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											
											//echo $queryLicenseOutPatentsCurrent."<br/>";
											$resultLicenseOutPatentsCurrent = $con->query($queryLicenseOutPatentsCurrent);
											
											if($resultLicenseOutPatentsCurrent) {
												$allLicenseOutPatentsCurrent = $resultLicenseOutPatentsCurrent->fetch_object()->totalRecords;
											}
										}
										
										if(count($previousAllRFIDs) > 0) {
											$queryLicenseOutPatentsPrev = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allRFNames.' )AND or.rf_id IN ('.implode(',', $allRepresentativeRFIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern") AND or.rf_id IN('.implode(',', $previousAllRFIDs).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
											
											//echo $queryLicenseOutPatentsPrev."<br/>";	
											//echo $queryLicenseOut."<br/>";
											$resultLicenseOutPatentsPrev = $con->query($queryLicenseOutPatentsPrev);
											
											if($resultLicenseOutPatentsPrev) {
												$allLicenseOutPatentsPrev = $resultLicenseOutPatentsPrev->fetch_object()->totalRecords;
											}
										}
										$diffAllLicenseOut = findDiffInPercentage($allLicenseOutPatentsCurrent, $allLicenseOutPatentsPrev);
										
										$con->query( "DELETE FROM db_application.transaction WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = 0" );
										
										$con->query("INSERT INTO db_application.`transaction`(organisation_id,representative_id,buy, buy_patent, diff_buy_patent,sale, sale_patent, diff_sale_patent, `security`, security_patent, diff_security_patent, `release`, release_patent, diff_release_patent,license_in, license_in_patent, diff_license_in_patent, license_out, license_out_patent, diff_license_out_patent,transaction_list) VALUES (".$orgRow->organisation_id.", 0, ".$allBuy.", ".$allBuyPatents.", ".$diffAllBuyPatents.", ".$allSale.", ".$allSalePatents.", ".$diffAllSalePatents.",".$allSecurity.", ".$allSecurityPatents.", ".$diffAllSecurityPatents.",".$allRelease.", ".$allReleasePatents.", ".$diffAllReleasePatents.",".$allLicenseIn.", ".$allLicenseInPatents.", ".$diffAllLicenseIn.",".$allLicenseOut.", ".$allLicenseOutPatents.", ".$diffAllLicenseOut.", null)");
									}
								}
								$grandEntered = true;								
							}
							/*End Update*/
							
							
							
							
							
							
							
							
							
							
							
							
							
							
							
							
							$queryCurrentYearRFIDs = 'SELECT rf_id FROM assignor WHERE rf_id IN ('.implode(',', $rfIDs).') AND date_format(exec_dt,"%Y-%m-%d") BETWEEN "'.$startDate.'" AND "'.$endDate.'" GROUP BY rf_id';
							
							$resultCurrentRFIDs = $con->query($queryCurrentYearRFIDs);
						
							if($resultCurrentRFIDs && $resultCurrentRFIDs->num_rows > 0) {
								while($rowRepresentativeRF = $resultCurrentRFIDs->fetch_object()) {
									array_push($currentYearRFID, $rowRepresentativeRF->rf_id);
								}
							}
							
							$queryPreviousYearRFIDs = 'SELECT rf_id FROM assignor WHERE rf_id IN ('.implode(',', $rfIDs).') AND date_format(exec_dt,"%Y-%m-%d") BETWEEN "'.$startPrevDate.'" AND "'.$endPrevDate.'" GROUP BY rf_id';
							
							$resultPreviousRFIDs = $con->query($queryPreviousYearRFIDs);
						
							if($resultPreviousRFIDs && $resultPreviousRFIDs->num_rows > 0) {
								while($rowRepresentativeRF = $resultPreviousRFIDs->fetch_object()) {
									array_push($previousYearRFID, $rowRepresentativeRF->rf_id);
								}
							}
						}
						
						//echo "FINDING FOR ".$representativeID."<br/>";
						//print_r($allCompanies);
						$grantedAssets = array();
						$grantAssetsWithDate = array();
						
						$queryFindGrantedPatents = 'SELECT appno_doc_num, grant_doc_num, appno_date FROM db_application.documentid WHERE grant_doc_num <> "" AND rf_id IN ('.implode(',', $rfIDs).') AND status = 0 GROUP BY appno_doc_num';
						$resultGrantedPatents = $con->query($queryFindGrantedPatents);
						
						if($resultGrantedPatents && $resultGrantedPatents->num_rows > 0) {
							echo "GRANT:".$resultGrantedPatents->num_rows."<br/>";
							while($rowGrant = $resultGrantedPatents->fetch_object()){
								array_push($grantedAssets, $rowGrant->appno_doc_num);
								array_push($grantAssetsWithDate, $rowGrant);
							}
							
							
							
							$patentAssetStatus = array();
							
							if(count($grantedAssets) > 0) {
								$queryFindPatentStatus = "SELECT appno_doc_num, event_code FROM db_patent_maintainence_fee.event_maintainence_fees WHERE appno_doc_num IN (".implode(',', $grantedAssets).") GROUP BY grant_doc_num ORDER BY event_date DESC";
								$resultPatentStatus = $con->query($queryFindPatentStatus);
								echo "GRANT STATUS:".$resultPatentStatus->num_rows."<br/>";
								
								if($resultPatentStatus && $resultPatentStatus->num_rows > 0) {
									while($rowStatus = $resultPatentStatus->fetch_object()){
										array_push($patentAssetStatus, $rowStatus);
									}
								}
							}
								
							
							$assetExpStatus = array();
							$assetLiveStatus = array();
							foreach($grantAssetsWithDate as $asset) {
								$fillingDate = $asset->appno_date;
								$currentDate = strtotime(date('Y-m-d'));
								if($fillingDate != null && $fillingDate != "") {
									$expirationYear = strtotime("+20 years",strtotime($fillingDate));
									$findLastEvent = false;
									if(count($patentAssetStatus) > 0){
										foreach($patentAssetStatus as $pStatus){
											if((int)$pStatus->appno_doc_num == (int)$asset->appno_doc_num && ($pStatus->event_code == 'EXP' || $pStatus->event_code == 'EXP.')){
												$findLastEvent = true;
												break;
											}
										}
									}
									if(($expirationYear > $currentDate) || $findLastEvent === true) {
										array_push($assetExpStatus, '"'.$asset->appno_doc_num.'"');
									} else {
										array_push($assetLiveStatus, '"'.$asset->appno_doc_num.'"');
									}
								}
							}
							echo "GRANT EXP:".count($assetExpStatus)."<br/>";
							echo "GRANT ACTIVE:".count($assetLiveStatus)."<br/>";
							if(count($assetExpStatus) > 0) {
								//echo "UPDATE db_application.documentid SET status = 2 WHERE appno_doc_num IN (".implode(',', $assetExpStatus).")<br/>";
								$con->query("UPDATE db_application.documentid SET `status` = 2 WHERE appno_doc_num IN (".implode(',', $assetExpStatus).")");
							}
							if(count($assetLiveStatus) > 0) {
								//echo "UPDATE db_application.documentid SET status = 1 WHERE appno_doc_num IN (".implode(',', $assetLiveStatus).")<br/>";
								$con->query("UPDATE db_application.documentid SET `status` = 1 WHERE appno_doc_num IN (".implode(',', $assetLiveStatus).")");
							}
						}
						
						/*End Granted Assets*/
						
						if(count($rfIDs) > 0) {
							
						
						
						$currentYearPatentTotal = 0;
						$currentYearApplicationTotal = 0;
						$prevYearPatentTotal = 0;
						$prevYearApplicationTotal = 0;
						$diff_patent = 0;
						$diff_application = 0;
						
						if(count($currentYearRFID) > 0){
							$queryCurrentAssetsA = 'SELECT count(*) as countAssets FROM (SELECT grant_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee` INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
						LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
						WHERE ( '.$allNames.' )  AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $currentYearRFID).') ) AND (appno_doc_num <> "" ) AND (grant_doc_num <> "" ) GROUP BY grant_doc_num) as temp';
							
							$resultCurrentAssetsA = $con->query($queryCurrentAssetsA);
							
							$queryCurrentAssetsB = 'SELECT count(*) as countAssets FROM (SELECT grant_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
							LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
							WHERE ( '.$allNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $currentYearRFID).')) AND (appno_doc_num <> "") AND (grant_doc_num <> "") GROUP BY grant_doc_num) as temp ';
							//echo $queryCurrentAssets."<br/>";	
							$resultCurrentAssetsB = $con->query($queryCurrentAssetsB);
							
							$totalB = 0;
							$totalA = 0;
							
							if($resultCurrentAssetsB){
								$totalB = $resultCurrentAssetsB->fetch_object()->countAssets;
							}
							
							if($resultCurrentAssetsA){
								$totalA = $resultCurrentAssetsA->fetch_object()->countAssets;
							}
							
							$currentYearPatentTotal = $totalB - $totalA;
							
							
							$queryCurrentAssetsA = 'SELECT count(*) as countAssets FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee` INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
						LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
						WHERE ( '.$allNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $currentYearRFID).') ) AND (appno_doc_num <> "" ) AND (grant_doc_num = "") GROUP BY appno_doc_num) as temp';
							
							$resultCurrentAssetsA = $con->query($queryCurrentAssetsA);
							
							$queryCurrentAssetsB = 'SELECT count(*) as countAssets FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
							LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
							WHERE ( '.$allNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $currentYearRFID).')) AND (appno_doc_num <> "") AND (grant_doc_num = "") GROUP BY appno_doc_num) as temp ';
							//echo $queryCurrentAssets."<br/>";	
							$resultCurrentAssetsB = $con->query($queryCurrentAssetsB);
							
							$totalB = 0;
							$totalA = 0;
							
							if($resultCurrentAssetsB){
								$totalB = $resultCurrentAssetsB->fetch_object()->countAssets;
							}
							
							if($resultCurrentAssetsA){
								$totalA = $resultCurrentAssetsA->fetch_object()->countAssets;
							}
							
							$currentYearApplicationTotal = $totalB - $totalA;
						}	
						
						if(count($previousYearRFID) > 0){
							$queryPrevAssetsA = 'SELECT count(*) as countAssets FROM (SELECT grant_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee` INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
						LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
						WHERE ( '.$allNames.' )  AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $previousYearRFID).') ) AND (appno_doc_num <> "" ) AND (grant_doc_num <> "") GROUP BY grant_doc_num) as temp';
							
							$resultPrevAssetsA = $con->query($queryPrevAssetsA);
							
							$queryPrevAssetsB = 'SELECT count(*) as countAssets FROM (SELECT grant_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
							LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
							WHERE ( '.$allNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $previousYearRFID).')) AND (appno_doc_num <> "") AND (grant_doc_num <> "") GROUP BY grant_doc_num) as temp ';
							//echo $queryCurrentAssets."<br/>";	
							$resultPrevAssetsB = $con->query($queryPrevAssetsB);
							
							$totalB = 0;
							$totalA = 0;
							
							if($resultPrevAssetsB){
								$totalB = $resultPrevAssetsB->fetch_object()->countAssets;
							}
							
							if($resultPrevAssetsA){
								$totalA = $resultPrevAssetsA->fetch_object()->countAssets;
							}
							
							$prevYearPatentTotal = $totalB - $totalA;
							
							
							$queryPrevAssetsA = 'SELECT count(*) as countAssets FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee` INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
						LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
						WHERE ( '.$allNames.' )  AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")  AND ac.rf_id IN ('.implode(", ", $previousYearRFID).') ) AND (appno_doc_num <> "") AND (grant_doc_num = "" ) GROUP BY appno_doc_num) as temp';
							
							$resultPrevAssetsA = $con->query($queryPrevAssetsA);
							
							$queryPrevAssetsB = 'SELECT count(*) as countAssets FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or`INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id
							LEFT JOIN representative as r ON r.representative_id = aaa.representative_id
							WHERE ( '.$allNames.' )  AND  ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder") AND ac.rf_id IN ('.implode(",", $previousYearRFID).')) AND (appno_doc_num <> "") AND (grant_doc_num = "") GROUP BY appno_doc_num) as temp ';
							//echo $queryCurrentAssets."<br/>";	
							$resultPrevAssetsB = $con->query($queryPrevAssetsB);
							
							$totalB = 0;
							$totalA = 0;
							
							if($resultPrevAssetsB){
								$totalB = $resultPrevAssetsB->fetch_object()->countAssets;
							}
							
							if($resultPrevAssetsA){
								$totalA = $resultPrevAssetsA->fetch_object()->countAssets;
							}
							
							$prevYearApplicationTotal = $totalB - $totalA;
						}
						
						$diff_patent = findDiffInPercentage($currentYearPatentTotal, $prevYearPatentTotal);
						$diff_application = findDiffInPercentage($currentYearApplicationTotal, $prevYearApplicationTotal);
						
						
						
						/*License or Government, or Option, or Security, or RestatedSecurity, or Other, or Missing*/
						/*$encumberedlist = array();
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
						}	*/					
						
						$con->query("DELETE FROM db_application.validity WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID );	
						
						$con->query("INSERT INTO db_application.validity (organisation_id, representative_id, application, patent, encumbered, current_patent_year, current_application_year, previous_patent_year, previous_application_year, difference_patent, difference_application) VALUES (".$orgRow->organisation_id.",".$representativeID.",".$applicationS.",".$patentS.",".$encumberedS.", ".$currentYearPatentTotal.", ".$currentYearApplicationTotal.", ".$prevYearPatentTotal.", ".$prevYearApplicationTotal.", ".$diff_patent.", ".$diff_application.")");
						
						
						
						echo "TRANSACTIONS<br/>";
						$buy = 0;
						$buyPatents = 0;
						$buyPatentsCurrent = 0;
						$buyPatentsPrev = 0;
						$diffBuyPatents = 0;
						
						$sale = 0;
						$salePatents = 0;
						$salePatentsCurrent = 0;
						$salePatentsPrev = 0;
						$diffSalePatents = 0;
						
						$security = 0;
						$securityPatents = 0;
						$securityPatentsCurrent = 0;
						$securityPatentsPrev = 0;
						$diffSecurityPatents = 0;
						
						$release = 0;
						$releasePatents = 0;
						$releasePatentsCurrent = 0;
						$releasePatentsPrev = 0;						
						$diffReleasePatents = 0;						
						
						$licenseIn = 0;
						$licenseInPatents = 0;
						$licenseInPatentsCurrent = 0;
						$licenseInPatentsPrev = 0;
						$diffLicenseIn = 0;
						
						$licenseOut = 0;
						$licenseOutPatents = 0;
						$licenseOutPatentsCurrent = 0;
						$licenseOutPatentsPrev = 0;
						$diffLicenseOut = 0;
			
						$queryAcquired = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
						//echo $queryAcquired."<br/>";
						$resultAcquired = $con->query($queryAcquired);

						if($resultAcquired) {
							$buy = $resultAcquired->fetch_object()->totalRecords;
						}
						
						$queryAcquiredPatent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id  INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
						
						$resultAcquiredPatent = $con->query($queryAcquiredPatent);

						if($resultAcquiredPatent) {
							$buyPatents = $resultAcquiredPatent->fetch_object()->totalRecords;
						}
						
						if(count($currentYearRFID) > 0) {
							$queryAcquiredPatentCurrent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND  ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $currentYearRFID).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							//echo $queryAcquiredPatentCurrent."<br/>";
							$resultAcquiredPatentCurrent = $con->query($queryAcquiredPatentCurrent);

							if($resultAcquiredPatentCurrent) {
								$buyPatentsCurrent = $resultAcquiredPatentCurrent->fetch_object()->totalRecords;
							}
						}
						
						if(count($previousYearRFID) > 0) {						
							$queryAcquiredPatentPrev = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $previousYearRFID).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							
							
							//echo $queryAcquiredPatentPrev."<br/>";
							
							$resultAcquiredPatentPrev = $con->query($queryAcquiredPatentPrev);

							if($resultAcquiredPatentPrev) {
								$buyPatentsPrev = $resultAcquiredPatentPrev->fetch_object()->totalRecords;
							}
						}
						
						$diffBuyPatents = findDiffInPercentage($buyPatentsCurrent, $buyPatentsPrev);
						
						$querySold = 'SELECT count(*) as totalRecords FROM (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' )  AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = 0 AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
						echo $querySold."<br/>";
						$resultSold = $con->query($querySold);

						if($resultSold) {
							$sale = $resultSold->fetch_object()->totalRecords;
						}
						
						$querySoldPatents = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' )  AND ac.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).')  AND ac.convey_ty IN ("assignment", "partialassignment")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id)  GROUP BY appno_doc_num, grant_doc_num) as temp';
						
						$resultSoldPatents = $con->query($querySoldPatents);

						if($resultSoldPatents) {
							$salePatents = $resultSoldPatents->fetch_object()->totalRecords;
						}
						
						if(count($currentYearRFID) > 0) {
							$querySoldPatentsCurrent = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' )  AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = 0 AND ac.convey_ty IN ("assignment", "partialassignment") AND or.rf_id IN('.implode(',', $currentYearRFID).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							//echo $querySoldPatentsCurrent."<br/>";
							$resultSoldPatentsCurrent = $con->query($querySoldPatentsCurrent);

							if($resultSoldPatentsCurrent) {
								$salePatentsCurrent = $resultSoldPatentsCurrent->fetch_object()->totalRecords;
							}
						}
						
						if(count($previousYearRFID) > 0) {
							$querySoldPatentsPrev = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' )  AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = 0 AND ac.convey_ty IN ("assignment", "partialassignment") AND or.rf_id IN('.implode(',', $previousYearRFID).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							
							//echo $querySoldPatentsPrev."<br/>";
							$resultSoldPatentsPrev = $con->query($querySoldPatentsPrev);

							if($resultSoldPatentsPrev) {
								$salePatentsPrev = $resultSoldPatentsPrev->fetch_object()->totalRecords;
							}
						}
						
						$diffSalePatents = findDiffInPercentage($salePatentsCurrent, $salePatentsPrev);
						
						$querySecurity = 'SELECT count(*) as totalRecords FROM (Select ee.* from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("security", "restatedsecurity")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
						//echo $querySecurity."<br/>";
						$resultSecurity = $con->query($querySecurity);

						if($resultSecurity) {
							$security = $resultSecurity->fetch_object()->totalRecords;
						}
						
						$querySecurityPatent = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("security", "restatedsecurity")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id)  GROUP BY appno_doc_num, grant_doc_num) as temp';
						//echo $querySecurity."<br/>";
						$resultSecurityPatent = $con->query($querySecurityPatent);

						if($resultSecurityPatent) {
							$securityPatents = $resultSecurityPatent->fetch_object()->totalRecords;
						}
						if(count($currentYearRFID) > 0) {
							$querySecurityPatentCurrent = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("security", "restatedsecurity") AND or.rf_id IN('.implode(',', $currentYearRFID).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id)  GROUP BY appno_doc_num, grant_doc_num) as temp';
							//echo $querySecurityPatentCurrent."<br/>";
							
							$resultSecurityPatentCurrent = $con->query($querySecurityPatentCurrent);

							if($resultSecurityPatentCurrent) {
								$securityPatentsCurrent = $resultSecurityPatentCurrent->fetch_object()->totalRecords;
							}
						}
						
						if(count($previousYearRFID) > 0) {
							$querySecurityPatentPrev = 'SELECT count(*) as totalRecords FROM ( SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("security", "restatedsecurity")  AND or.rf_id IN('.implode(',', $previousYearRFID).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id)  GROUP BY appno_doc_num, grant_doc_num) as temp';
							
							//echo $querySecurityPatentPrev."<br/>";
							$resultSecurityPatentPrev = $con->query($querySecurityPatentPrev);

							if($resultSecurityPatentPrev) {
								$securityPatentsPrev = $resultSecurityPatentPrev->fetch_object()->totalRecords;
							}
						}
						
						$diffSecurityPatents = findDiffInPercentage($securityPatentsCurrent, $securityPatentsPrev);
						
						$queryRelease = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("release", "restatedsecurity")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
						//echo $queryRelease."<br/>";
						$resultRelease = $con->query($queryRelease);

						if($resultRelease) {
							$release = $resultRelease->fetch_object()->totalRecords;
						}
						
						$queryReleasePatents = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("release", "restatedsecurity")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
						//echo $queryRelease."<br/>";
						$resultReleasePatents = $con->query($queryReleasePatents);

						if($resultReleasePatents) {
							$releasePatents = $resultReleasePatents->fetch_object()->totalRecords;
						}
						if(count($currentYearRFID) > 0) {
							$queryReleasePatentsCurrent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("release", "restatedsecurity")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $currentYearRFID).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							//echo $queryReleasePatentsCurrent."<br/>";
							$resultReleasePatentsCurrent = $con->query($queryReleasePatentsCurrent);

							if($resultReleasePatentsCurrent) {
								$releasePatentsCurrent = $resultReleasePatentsCurrent->fetch_object()->totalRecords;
							}
						}
						if(count($previousYearRFID) > 0) {
							$queryReleasePatentsPrev = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("release", "restatedsecurity")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $previousYearRFID).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							
							//echo $queryReleasePatentsPrev."<br/>";
							$resultReleasePatentsPrev = $con->query($queryReleasePatentsPrev);

							if($resultReleasePatentsPrev) {
								$releasePatentsPrev = $resultReleasePatentsPrev->fetch_object()->totalRecords;
							}
						}
						
						$diffReleasePatents = findDiffInPercentage($releasePatentsCurrent, $releasePatentsPrev);
						
						$queryLicenseIn = 'SELECT count(*) as totalRecords FROM (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) as temp';
												
						$resultLicenseIn = $con->query($queryLicenseIn);
						
						if($resultLicenseIn) {
							$licenseIn = $resultLicenseIn->fetch_object()->totalRecords;
						}
						
						$queryLicenseInPatents = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							
						$resultLicenseInPatents = $con->query($queryLicenseInPatents);
						
						if($resultLicenseInPatents) {
							$licenseInPatents = $resultLicenseInPatents->fetch_object()->totalRecords;
						}
						if(count($currentYearRFID) > 0) {
							$queryLicenseInPatentsCurrent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $currentYearRFID).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							//echo $queryLicenseInPatentsCurrent."<br/>";	
							$resultLicenseInPatentsCurrent = $con->query($queryLicenseInPatentsCurrent);
							
							if($resultLicenseInPatentsCurrent) {
								$licenseInPatentsCurrent = $resultLicenseInPatentsCurrent->fetch_object()->totalRecords;
							}
						}
						if(count($previousYearRFID) > 0) {
							$queryLicenseInPatentsPrev = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select or.rf_id from assignor as `or` INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment as a ON a.rf_id = ee.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND ee.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = or.rf_id WHERE or.rf_id IN('.implode(',', $previousYearRFID).') GROUP BY or.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							
							//echo $queryLicenseInPatentsPrev."<br/>";	
							$resultLicenseInPatentsPrev = $con->query($queryLicenseInPatentsPrev);
							
							if($resultLicenseInPatentsPrev) {
								$licenseInPatentsPrev = $resultLicenseInPatentsPrev->fetch_object()->totalRecords;
							}
						}
						$diffLicenseIn = findDiffInPercentage($licenseInPatentsCurrent, $licenseInPatentsPrev);
						
						$queryLicenseOut = 'SELECT count(*) as totalRecords FROM (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) as temp';
						
						//echo $queryLicenseOut."<br/>";
						$resultLicenseOut = $con->query($queryLicenseOut);
						
						if($resultLicenseOut) {
							$licenseOut = $resultLicenseOut->fetch_object()->totalRecords;
						}			
						
						$queryLicenseOutPatents = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern")) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
						
						//echo $queryLicenseOut."<br/>";
						$resultLicenseOutPatents = $con->query($queryLicenseOutPatents);
						
						if($resultLicenseOutPatents) {
							$licenseOutPatents = $resultLicenseOutPatents->fetch_object()->totalRecords;
						}	
						
						if(count($currentYearRFID) > 0) {
							$queryLicenseOutPatentsCurrent = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' ) AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern") AND or.rf_id IN('.implode(',', $currentYearRFID).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							
							//echo $queryLicenseOutPatentsCurrent."<br/>";
							$resultLicenseOutPatentsCurrent = $con->query($queryLicenseOutPatentsCurrent);
							
							if($resultLicenseOutPatentsCurrent) {
								$licenseOutPatentsCurrent = $resultLicenseOutPatentsCurrent->fetch_object()->totalRecords;
							}
						}
						if(count($previousYearRFID) > 0) {
							$queryLicenseOutPatentsPrev = 'SELECT count(*) as totalRecords FROM (SELECT appno_doc_num FROM documentid WHERE rf_id IN (Select ee.rf_id from assignee as `ee` INNER JOIN (Select `or`.rf_id from assignor as `or` INNER JOIN assignment as a ON a.rf_id = or.rf_id INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE ( '.$allNames.' )AND or.rf_id IN ('.implode(',', $rfIDs).') AND ac.employer_assign = "0" AND ac.convey_ty IN ("license", "licenseend", "govern") AND or.rf_id IN('.implode(',', $previousYearRFID).')) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id) GROUP BY appno_doc_num, grant_doc_num) as temp';
							
							//echo $queryLicenseOutPatentsPrev."<br/>";	
							//echo $queryLicenseOut."<br/>";
							$resultLicenseOutPatentsPrev = $con->query($queryLicenseOutPatentsPrev);
							
							if($resultLicenseOutPatentsPrev) {
								$licenseOutPatentsPrev = $resultLicenseOutPatentsPrev->fetch_object()->totalRecords;
							}
						}
						$diffLicenseOut = findDiffInPercentage($licenseOutPatentsCurrent, $licenseOutPatentsPrev);
						
						$con->query("DELETE FROM db_application.transaction WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID );
						
						$con->query("INSERT INTO db_application.`transaction`(organisation_id,representative_id,buy, buy_patent, diff_buy_patent,sale, sale_patent, diff_sale_patent, `security`, security_patent, diff_security_patent, `release`, release_patent, diff_release_patent,license_in, license_in_patent, diff_license_in_patent, license_out, license_out_patent, diff_license_out_patent,transaction_list) VALUES (".$orgRow->organisation_id.", ".$representativeID.", ".$buy.", ".$buyPatents.", ".$diffBuyPatents.", ".$sale.", ".$salePatents.", ".$diffSalePatents.",".$security.", ".$securityPatents.", ".$diffSecurityPatents.",".$release.", ".$releasePatents.", ".$diffReleasePatents.",".$licenseIn.", ".$licenseInPatents.", ".$diffLicenseIn.",".$licenseOut.", ".$licenseOutPatents.", ".$diffLicenseOut.", null)");
						
						
						//echo "UPDATE<br/>";
						$con->query("DELETE FROM db_application.update WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID );
						$weeklyTransCounter = 0;
						$weeklyAssetsCounter = 0;
						$lastMonthTransCounter = 0;
						$lastMonthAssetsCounter = 0;
						$quaterlyTransCounter = 0;
						$quaterlyAssetsCounter = 0;
						/*if(count($rfIDs) > 0) {
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
						}*/
						
						
						$con->query("INSERT INTO db_application.`update` (organisation_id,representative_id, weekly_transactions, weekly_applications, monthly_transactions,montly_applications,quaterly_transactions,quaterly_applications,update_transaction_list,update_application_list) VALUES (".$orgRow->organisation_id.", ".$representativeID.", ".$weeklyTransCounter.", ".$weeklyAssetsCounter.", ".$lastMonthTransCounter.", ".$lastMonthAssetsCounter.", ".$quaterlyTransCounter.", ".$quaterlyAssetsCounter.", NULL, NULL)");
						
						/*End of Insert in Application, Transaction, and Update table*/
						
						/*Tree*/
						//echo "Tree<br/>";
								
								
							$allNames = array();
							
							foreach($allCompanies as $company) {
								array_push($allNames, "'".$con->real_escape_string($company)."'");
							}
							
							$con->query("DELETE FROM tree WHERE organisation_id = ".$orgRow->organisation_id." AND representative_id = ".$representativeID);
							
							/*Acquisition*/
							
							/*Purchase*/
							$queryPurchase = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "0" as type, "0" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("assignment", "partialassignment") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							//echo $queryPurchase."<br/>";
							$con->query($queryPurchase);
							
							/*Sale*/
							$querySale = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "1" as type, "1" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
							//echo $querySale."<br/>";
							$con->query($querySale);
							
							
							/*License-In*/
							$queryLicenseIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "2" as type, "2" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("license", "licenseend", "govern") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							//echo $queryLicenseIn."<br/>";
							$con->query($queryLicenseIn);
							
							/*License-Out*/
							$queryLicenseOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "3" as type, "3" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("license", "licenseend", "govern") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
							//echo $queryLicenseOut."<br/>";
							$con->query($queryLicenseOut);
							
							/*SecurityOut*/
							$querySecurityOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "4" as type, "4" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("security", "restatedsecurity") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
							//echo $querySecurityOut."<br/>";
							$con->query($querySecurityOut);
							
							/*ReleaseIn*/
							$queryReleaseIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "5" as type, "4" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as `ee` INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("release", "restatedsecurity") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND  ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
							//echo $queryReleaseIn."<br/>";
							$con->query($queryReleaseIn);
							
							/*MergerIn*/
							$queryMergerIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "6" as type, "5" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							//echo $queryMergerIn."<br/>";
							$con->query($queryMergerIn);
							
							/*MergerOut*/
							$queryMergerOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "7" as type, "6" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							//echo $queryMergerOut."<br/>";
							$con->query($queryMergerOut);
							
							
							
							/*Option*/
							$queryOptionIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "8" as type, "7" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "option" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							//echo $queryOptionIn."<br/>";
							$con->query($queryOptionIn);
							
							$queryOptionOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "9" as type, "7" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "option" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							//echo $queryOptionOut."<br/>";
							$con->query($queryOptionOut);
							
							/*CourtOrders*/
							$queryCourtOrderIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "10" as type, "8" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "courtorder" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							//echo $queryCourtOrderIn."<br/>";
							$con->query($queryCourtOrderIn);
							
							$queryCourtOrderOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "11" as type, "8" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "courtorder" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							//echo $queryCourtOrderOut."<br/>";
							$con->query($queryCourtOrderOut);
							
							$customEmployeeQuery = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent,"12" as type, "9" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM db_uspto.assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.employer_assign = 1 AND ass.convey_ty IN ("assignment", "partialassignment", "employee") AND (aa.name IN ('.implode(",",$allNames).')) AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
							//echo $customEmployeeQuery."<br/>";						
							$con->query($customEmployeeQuery);
															
							/*Missing*/
							$queryMissingChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "13" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "missing" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
							//echo $queryMissingChange."<br/>";
							$con->query($queryMissingChange);
							
							$queryMissingChangeAssignor = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "14" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "missing" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							//echo $queryMissingChangeAssignor."<br/>";
							$con->query($queryMissingChangeAssignor);
							
							/*Other*/
							$queryOtherChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "15" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "other" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
							//echo $queryOtherChange."<br/>";
							$con->query($queryOtherChange);
							
							$queryOtherChangeAssignor = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "16" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "other" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							//echo $queryOtherChangeAssignor."<br/>";
							$con->query($queryOtherChangeAssignor);
							
							/*NameChange*/
							$queryNameChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "17" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "namechg" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
							//echo $queryNameChange."<br/>";
							$con->query($queryNameChange);
							
							$queryNameChangeAssignor = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "18" as type, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "namechg" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
							//echo $queryNameChangeAssignor."<br/>";
							$con->query($queryNameChangeAssignor);
							
							
							
							$con->query("DELETE FROM tree_parties WHERE organisation_id = ".$orgRow->organisation_id. " AND representative_id = ".$representativeID);
							$con->query("DELETE FROM tree_parties_collection WHERE organisation_id = ".$orgRow->organisation_id. " AND representative_id = ".$representativeID);
						
						$queryPurchase = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id  INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("assignment", "partialassignment") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
						//echo $queryPurchase."<br/>";
						$con->query($queryPurchase);
						
						$querySale = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "1" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
						//echo $querySale."<br/>";
						$con->query($querySale);
						
						/*License-In*/
						$queryLicenseIn = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "2" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id  INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("license", "licenseend", "govern") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
						//echo $queryLicenseIn."<br/>";
						$con->query($queryLicenseIn);
						
						/*License-Out*/
						$queryLicenseOut = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "3" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("license", "licenseend", "govern") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
						//echo $queryLicenseOut."<br/>";
						$con->query($queryLicenseOut);
						
						/*SecurityOut*/
						$querySecurityOut = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "4" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("security", "restatedsecurity") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
						//echo $querySecurityOut."<br/>";
						$con->query($querySecurityOut);
						
						/*ReleaseIn*/
						$queryReleaseIn = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "4" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as `ee` INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("release", "restatedsecurity") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						//echo $queryReleaseIn."<br/>";
						$con->query($queryReleaseIn);
						
						/*MergerIn*/
						$queryMergerIn = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name,  "5" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name  FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
						//echo $queryMergerIn."<br/>";
						$con->query($queryMergerIn);
						
						/*MergerOut*/
						$queryMergerOut = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name,  "6" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
						//echo $queryMergerOut."<br/>";
						$con->query($queryMergerOut);
						
						/*Option*/
						$queryOptionIn = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name,  "7" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "option" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
						//echo $queryOptionIn."<br/>";
						$con->query($queryOptionIn);
								
						$queryOptionOut = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "7" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id  INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "option" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
						//echo $queryOptionOut."<br/>";
						$con->query($queryOptionOut);
						
						/*CourtOrders*/
						$queryCourtOrderIn = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "8" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "courtorder" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
						//echo $queryCourtOrderIn."<br/>";
						$con->query($queryCourtOrderIn);
						
						$queryCourtOrderOut = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "8" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name  FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "courtorder" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
						//echo $queryCourtOrderOut."<br/>";
						$con->query($queryCourtOrderOut);
						
						$customEmployeeQuery = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "9" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM db_uspto.assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.employer_assign = 1 AND ass.convey_ty IN ("assignment", "partialassignment", "employee") AND (aa.name IN ('.implode(",",$allNames).')) AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
						//echo $customEmployeeQuery."<br/>";						
						$con->query($customEmployeeQuery);
						
						/*Missing*/
						$queryMissingChange = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "missing" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						//echo $queryMissingChange."<br/>";
						$con->query($queryMissingChange);
						
						$queryMissingChangeAssignor = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "missing" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
						//echo $queryMissingChangeAssignor."<br/>";
						$con->query($queryMissingChangeAssignor);
						
						/*Other*/
						$queryOtherChange = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "other" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						//echo $queryOtherChange."<br/>";
						$con->query($queryOtherChange);
						
						$queryOtherChangeAssignor = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "other" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
						//echo $queryOtherChangeAssignor."<br/>";
						$con->query($queryOtherChangeAssignor);
						
						/*NameChange*/
						$queryNameChange = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "namechg" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						//echo $queryNameChange."<br/>";
						$con->query($queryNameChange);
						
						$queryNameChangeAssignor = 'INSERT IGNORE INTO tree_parties (assignor_and_assignee_id, name, tab_id, organisation_id, representative_id, representative_name) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "10" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$representativeID.'" as representative_id, "'.$con->real_escape_string($nameRepresentaitve).'" as representative_name FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "namechg" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
						//echo $queryNameChangeAssignor."<br/>";
						$con->query($queryNameChangeAssignor);
						
						
						
						$queryPurchaseCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, or.rf_id, or.exec_dt, "0" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("assignment", "partialassignment") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id';
						//echo $queryPurchaseCollectionsQuery."<br/>";
						$con->query($queryPurchaseCollectionsQuery);

						$querySaleCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, ee.rf_id,(SELECT exec_dt FROM assignor where rf_id = ee.rf_id GROUP BY rf_id), "1" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id';
						//echo $querySaleCollectionsQuery."<br/>";
						$con->query($querySaleCollectionsQuery);	
						
						/*License-In*/
						$queryLicenseInCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, or.rf_id, or.exec_dt, "2" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("license", "licenseend", "govern") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id ';
						//echo $queryLicenseInCollectionsQuery."<br/>";
						$con->query($queryLicenseInCollectionsQuery);
						
						/*License-Out*/
						$queryLicenseOutCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, ee.rf_id,(SELECT exec_dt FROM assignor where rf_id = ee.rf_id GROUP BY rf_id), "3" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("license", "licenseend", "govern") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id ';
						//echo $queryLicenseOutCollectionsQuery."<br/>";
						$con->query($queryLicenseOutCollectionsQuery);
						
						/*SecurityOut*/
						$querySecurityOutCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, ee.rf_id,(SELECT exec_dt FROM assignor where rf_id = ee.rf_id GROUP BY rf_id), "4" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("security", "restatedsecurity") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id ';
						//echo $querySecurityOutCollectionsQuery."<br/>";
						$con->query($querySecurityOutCollectionsQuery);
						
						/*ReleaseIn*/
						$queryReleaseInCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id,or.rf_id, or.exec_dt, "4" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as `ee` INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty IN ("release", "restatedsecurity") AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id ';
						//echo $queryReleaseInCollectionsQuery."<br/>";
						$con->query($queryReleaseInCollectionsQuery);
						
						
						/*MergerIn*/
						$queryMergerInCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, or.rf_id, or.exec_dt, "5" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id NNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id ';
						//echo $queryMergerInCollectionsQuery."<br/>";
						$con->query($queryMergerInCollectionsQuery);
						
						
						/*MergerOut*/
						$queryMergerOutCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, ee.rf_id,(SELECT exec_dt FROM assignor where rf_id = ee.rf_id GROUP BY rf_id), "6" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id ';
						//echo $queryMergerOutCollectionsQuery."<br/>";
						$con->query($queryMergerOutCollectionsQuery);
						
						/*Option*/
						$queryOptionInCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, ee.rf_id,(SELECT exec_dt FROM assignor where rf_id = ee.rf_id GROUP BY rf_id), "7" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "option" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id  ';
						//echo $queryOptionInCollectionsQuery."<br/>";
						$con->query($queryOptionInCollectionsQuery);
						
						$queryOptionOutCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, or.rf_id, or.exec_dt, "7" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "option" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id ';
						//echo $queryOptionOutCollectionsQuery."<br/>";
						$con->query($queryOptionOutCollectionsQuery);
						
						
						/*CourtOrders*/
						$queryCourtOrderInCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, ee.rf_id,(SELECT exec_dt FROM assignor where rf_id = ee.rf_id GROUP BY rf_id), "8" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "courtorder" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id ';
						//echo $queryCourtOrderInCollectionsQuery."<br/>";
						$con->query($queryCourtOrderInCollectionsQuery);
						
						$queryCourtOrderOutCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, or.rf_id, or.exec_dt, "8" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "courtorder" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id ';
						//echo $queryCourtOrderOutCollectionsQuery."<br/>";
						$con->query($queryCourtOrderOutCollectionsQuery);
						
						$customEmployeeQueryCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, or.rf_id, or.exec_dt, "9" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM db_uspto.assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.employer_assign = 1 AND ass.convey_ty IN ("assignment", "partialassignment", "employee") AND (aa.name IN ('.implode(",",$allNames).')) AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id ';
						//echo $customEmployeeQueryCollectionsQuery."<br/>";						
						$con->query($customEmployeeQueryCollectionsQuery);
						
						/*Missing*/
						$queryMissingChangeCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, or.rf_id, or.exec_dt, "10" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "missing" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id ';
						//echo $queryMissingChangeCollectionsQuery."<br/>";
						$con->query($queryMissingChangeCollectionsQuery);
						
						$queryMissingChangeAssignorCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, ee.rf_id,(SELECT exec_dt FROM assignor where rf_id = ee.rf_id GROUP BY rf_id), "10" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "missing" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id ';
						//echo $queryMissingChangeAssignorCollectionsQuery."<br/>";
						$con->query($queryMissingChangeAssignorCollectionsQuery);
						
						/*Other*/
						$queryOtherChangeCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, or.rf_id, or.exec_dt, "10" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "other" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id  ';
						//echo $queryOtherChangeCollectionsQuery."<br/>";
						$con->query($queryOtherChangeCollectionsQuery);
						
						$queryOtherChangeAssignorCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, ee.rf_id,(SELECT exec_dt FROM assignor where rf_id = ee.rf_id GROUP BY rf_id), "10" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "other" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id ';
						//echo $queryOtherChangeAssignorCollectionsQuery."<br/>";
						$con->query($queryOtherChangeAssignorCollectionsQuery);
						
						/*NameChange*/
						$queryNameChangeCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, or.rf_id, or.exec_dt, "10" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "namechg" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id ';
						//echo $queryNameChangeCollectionsQuery."<br/>";
						$con->query($queryNameChangeCollectionsQuery);
						
						$queryNameChangeAssignorCollectionsQuery = 'INSERT IGNORE INTO tree_parties_collection (assignor_and_assignee_id, rf_id, exec_dt, tab_id, representative_id, organisation_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, ee.rf_id,(SELECT exec_dt FROM assignor where rf_id = ee.rf_id GROUP BY rf_id), "10" as tab, "'.$representativeID.'" as representative_id, "'.$orgRow->organisation_id.'" as organisation_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "namechg" AND (aa.name IN ('.implode(",",$allNames).')) AND ass.employer_assign = 0 AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id ';
						//echo $queryNameChangeAssignorCollectionsQuery."<br/>";
						$con->query($queryNameChangeAssignorCollectionsQuery);
							
						
							/*Timeline Table*/
							//echo "Timeline<br/>";
							$con->query("DELETE FROM `db_application`.`timeline` WHERE organisation_id =".$orgRow->organisation_id." AND representative_id = ".$representativeID);
							
							/*Purchase*/
							try{
								$queryPurchase = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "0" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryPurchase."<br/>";
								$con->query($queryPurchase);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Sale*/
							try{
								$querySale = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "1" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								//echo $querySale."<br/>";
								$con->query($querySale);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*License-In*/
							try{
								$queryLicenseIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "2" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("license", "licenseend", "govern") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryLicenseIn."<br/>";
								$con->query($queryLicenseIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*License-Out*/
							try{
								$queryLicenseOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "3" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("license", "licenseend", "govern") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryLicenseOut."<br/>";
								$con->query($queryLicenseOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*SecurityOut*/
							try{
								$querySecurityOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "4" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("security", "restatedsecurity") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								//echo $querySecurityOut."<br/>";
								$con->query($querySecurityOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*ReleaseIn*/
							try{
								$queryReleaseIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "4" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("release", "restatedsecurity") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryReleaseIn."<br/>";
								$con->query($queryReleaseIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*MergerIn*/
							try{
								$queryMergeIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "5" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "merger" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryMergeIn."<br/>";
								$con->query($queryMergeIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*MergerOut*/
							try{
								$queryMergerOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "6" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "merger" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryMergerOut."<br/>";
								$con->query($queryMergerOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Option*/
							try{
								$queryOptionIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "7" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "option" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryOptionIn."<br/>";
								$con->query($queryOptionIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*OptionOut*/
							try{
								$queryOptionOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "7" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "option" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryOptionOut."<br/>";
								$con->query($queryOptionOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*CourtOrders*/
							try{
								$queryCourtOrderIn = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "8" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "courtorder" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryCourtOrderIn."<br/>";
								$con->query($queryCourtOrderIn);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*CourtOrderOut*/
							try{
								$queryCourtOrderOut = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "8" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "courtorder" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryCourtOrderOut."<br/>";
								$con->query($queryCourtOrderOut);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Employer*/
							try{
								$customEmployeeQuery = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "9" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty IN ("assignment", "partialassignment", "employee") AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 1 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $customEmployeeQuery."<br/>";
								$con->query($customEmployeeQuery);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Missing*/
							try{
								$queryMissingChange = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "missing" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryMissingChange."<br/>";
								$con->query($queryMissingChange);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Missing*/
							try{
								$queryMissingChangeAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "missing" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryMissingChangeAssignee."<br/>";
								$con->query($queryMissingChangeAssignee);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*Other*/
							try{
								$queryOtherChange = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "other" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryOtherChange."<br/>";
								$con->query($queryOtherChange);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*NameChange*/
							try{
								$queryOtherChangeAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "other" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryOtherChangeAssignee."<br/>";
								$con->query($queryOtherChangeAssignee);
							}catch(Exception $e){
								print_r($e);
							}
							
							
							/*NameChange*/
							try{
								$queryNameChange = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT or.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignor" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, or.exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignor as `or` INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = or.rf_id	INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ac ON ac.rf_id = ee.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "namechg" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0 AND ee.rf_id IN ('.implode(',', $rfIDs).') GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY or.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryNameChange."<br/>";
								$con->query($queryNameChange);
							}catch(Exception $e){
								print_r($e);
							}
							
							/*NameChange*/
							try{
								$queryNameChangeAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no, frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign, tab) SELECT ee.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$representativeID.'" as representative_id, "Assignee" as type, aaa.name as original_name, aaa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ee.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign, "10" as tab FROM assignee as ee INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN assignment as ass ON ass.rf_id = ee.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ass.rf_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "namechg" AND (aa.name IN ('.implode(",",$allNames).')) AND ac.employer_assign = 0  AND or.rf_id IN ('.implode(',', $rfIDs).') GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY ee.rf_id, aaa.assignor_and_assignee_id';
								//echo $queryNameChangeAssignee."<br/>";
								$con->query($queryNameChangeAssignee);
							}catch(Exception $e){
								print_r($e);
							}
	
							/*Counter*/		
							$rfIDAssetsCount = array();
							$queryCompanyCustomers = "SELECT tree_parties_id, assignor_and_assignee_id, tab_id FROM tree_parties WHERE organisation_id = ".$orgRow->organisation_id." AND representative_id = ".$representativeID." GROUP BY tab_id, assignor_and_assignee_id";	
							//echo $queryCompanyCustomers."<br/>";
							$resultQueryCustomer = $con->query($queryCompanyCustomers);							
							if($resultQueryCustomer && $resultQueryCustomer->num_rows > 0) {
								while($rowCustomer = $resultQueryCustomer->fetch_object()) {
									$queryTransactions = "SELECT rf_id, assets_count FROM tree_parties_collection WHERE tab_id = ".$rowCustomer->tab_id." AND organisation_id = ".$orgRow->organisation_id." AND representative_id = ".$representativeID." AND assignor_and_assignee_id = ".$rowCustomer->assignor_and_assignee_id." GROUP BY rf_id";
									//echo $queryTransactions."<br/>";
									$resultTransaction = $con->query($queryTransactions);
									
									if($resultTransaction && $resultTransaction->num_rows > 0) {
										$allTransactions = array();
										$transactionCount = $resultTransaction->num_rows;
										while($rowTransactions = $resultTransaction->fetch_object()){
											if($rowTransactions->assets_count > 0){
												array_push($rfIDUpdated,$rowTransactions->rf_id);
											}
											array_push($allTransactions, $rowTransactions->rf_id);
										}
										
										$queryAssets = "SELECT rf_id, count(appno_doc_num) as assetsCount FROM documentid WHERE rf_id IN (".implode(",", $allTransactions).") GROUP BY rf_id";
										//echo $queryAssets."<br/>";
										$resultAssets = $con->query($queryAssets);
										
										$assetsCount = 0;
										
										if($resultAssets) {
											while($rowAssets = $resultAssets->fetch_object()){
												$assetsCount = $assetsCount + $rowAssets->assetsCount;
												array_push($rfIDAssetsCount, array('rf_id'=>$rowAssets->rf_id, 'assets_count'=>$rowAssets->assetsCount));
												if(!in_array($rowAssets->rf_id, $rfIDUpdated)){
													$con->query("UPDATE tree_parties_collection SET assets_count = ".$rowAssets->assetsCount." WHERE rf_id = ".$rowAssets->rf_id);
													$con->query("UPDATE timeline SET assets_count = ".$rowAssets->assetsCount." WHERE rf_id = ".$rowAssets->rf_id);
													array_push($rfIDUpdated, $rowAssets->rf_id);
												}
											}										
										}
										
										if($transactionCount > 0 && $assetsCount > 0) {
											/*$con->query("UPDATE tree_parties SET transaction_count = ".$transactionCount.", assets_count = ".$assetsCount. " WHERE tree_parties_id = ".$rowCustomer->tree_parties_id);*/
										}
									}
								}
							}
							
							$queryCompanyTimeline = "SELECT rf_id FROM timeline WHERE organisation_id = ".$orgRow->organisation_id." AND representative_id = ".$representativeID." AND assets_count = 0";	
							$resultQueryCustomer = $con->query($queryCompanyTimeline);
							$rfIDUpdated = array();
							if($resultQueryCustomer && $resultQueryCustomer->num_rows > 0) {
								$rfIDPending = array();
								while($rowCustomer = $resultQueryCustomer->fetch_object()) {
									$update = false;
									if(!in_array($rowCustomer->rf_id, $rfIDUpdated)){
										foreach($rfIDAssetsCount as $checkRFID) {
											if($checkRFID['rf_id'] == $rowCustomer->rf_id) {
												$con->query("UPDATE timeline SET assets_count = ".$checkRFID['assets_count']. " WHERE rf_id = ".$rowCustomer->rf_id);
												array_push($rfIDUpdated, $rowCustomer->rf_id);
												$update = true;
												break;
											}
										}
									} else {
										$update = true;
									}
									if($update === false) {
										array_push($rfIDPending, $rowCustomer->rf_id);
									}
								}
								
								if( count($rfIDPending) > 0 ) {
									$queryAssets = "SELECT rf_id, count(appno_doc_num) as assetsCount FROM documentid WHERE rf_id IN (".implode(",", $rfIDPending).") GROUP BY rf_id";
									
									$resultAssets = $con->query($queryAssets);
										
									$assetsCount = 0;
									$rfIDUpdated = array();	
									if($resultAssets) {
										while($rowAssets = $resultAssets->fetch_object()){
											if(!in_array($rowAssets->rf_id, $rfIDUpdated)){
												$con->query("UPDATE timeline SET assets_count = ".$rowAssets->assetsCount. " WHERE rf_id = ".$rowAssets->rf_id);
												array_push($rfIDUpdated, $rowAssets->rf_id);
											}
										}
									}
								}
							}
							/*End Counter update*/
						}
					}
				}
			}
		}
	}
}

function findDiffInPercentage($current, $previous) {
	if($current != 0 && $previous != 0) {
		return (($current - $previous) / $previous) * 100;
	} else if($current != 0 && $previous == 0){
		return 100;
	}else if($current == 0 && $previous != 0) {
		return -100;
	}else{
		return 0;
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

function findAssignmentsFromAssignorList($removeAssignors, $assignmentList, $con) {
	$queryAssignorAssignments = "SELECT a.rf_id  FROM assignor as a WHERE a.rf_id IN (".implode(",", $assignmentList).") AND a.assignor_and_assignee_id IN (".implode(",", $removeAssignors).") GROUP BY a.rf_id";
	//echo $queryAssignorAssignments."<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>";
	$resultAssignorAssignment = $con->query($queryAssignorAssignments);
	
	$assignorAssignmentList = array();
	
	if($resultAssignorAssignment && $resultAssignorAssignment->num_rows > 0) {
		while($rowAssignorAssignment = $resultAssignorAssignment->fetch_object()){
			array_push($assignorAssignmentList, $rowAssignorAssignment->rf_id);
		}
	}
	
	return $assignorAssignmentList;
}

function updateFlag($flag, $rfIDs, $con) {
	$updateQuery = "UPDATE db_application.assignment_conveyance SET employer_assign = " .$flag. " WHERE rf_id IN (".implode(',', $rfIDs).")";
	//echo $updateQuery."<br/><br/><br/><br/><br/><br/><br/><br/><br/>";
	$con->query($updateQuery);
	
	$updateQuery = "UPDATE db_uspto.representative_assignment_conveyance SET employer_assign = " .$flag. " WHERE rf_id IN (".implode(',', $rfIDs).")";
	
	$con->query($updateQuery);
}