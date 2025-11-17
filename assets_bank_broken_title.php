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
	$companyID = $variables[1];
	$organisationID = $variables[2];
	if((int)$organisationID > 0) {	
		$listAllAssets = array();
		$queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbApplication.".assets_with_bank WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." GROUP BY appno_doc_num";
		
		$resultAllAssetsList = $con->query($queryAllAssetsList);
		if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
			while($rowAsset = $resultAllAssetsList->fetch_object()) {
				array_push($listAllAssets, '"'.$rowAsset->appno_doc_num.'"');
			}
		} 
		if(count($listAllAssets) > 0) {
			$con->query("DELETE FROM ".$dbApplication.".lost_assets WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID);
			$queryLostAssets = "INSERT IGNORE INTO ".$dbApplication.".lost_assets(assignor_and_assignee_id, assignor_id, appno_doc_num, appno_date, grant_doc_num, grant_date, rf_id, original_name, representative_name, company_id, organisation_id)
			SELECT assignor_and_assignee_id, assignor_id, appno, appnoDt, grantNo, grantDt, rf_id, name, representative_name, ".$companyID.", ".$organisationID." FROM (
				SELECT ass.assignor_and_assignee_id, tawb.assignor_id, MAX(doc.appno_doc_num) AS appno, MAX(doc.appno_date) AS appnoDt, MAX(doc.grant_doc_num) AS grantNo, MAX(doc.grant_date) AS grantDt,  rac.rf_id, aaa.name AS name,
				 (SELECT representative_name FROM db_uspto.representative WHERE representative_id = aaa.representative_id) AS representative_name  
				FROM db_new_application.assets_with_bank as tawb
				INNER JOIN (
					SELECT appno_doc_num, appno_date, grant_doc_num, grant_date, rf_id
					FROM ".$dbUSPTO.".documentid AS tempdoc
					WHERE tempdoc.appno_doc_num IN (".implode(', ', $listAllAssets).")	
				) AS doc ON doc.appno_doc_num = tawb.appno_doc_num
				INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = doc.rf_id
				INNER JOIN db_uspto.conveyance AS con ON con.convey_name = rac.convey_ty AND con.is_ota = 1 
				INNER JOIN db_uspto.assignee as ass ON ass.rf_id = rac.rf_id
				INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = ass.assignor_and_assignee_id
				WHERE tawb.company_id = ".$companyID." AND tawb.organisation_id = ".$organisationID." 
				GROUP BY db_uspto.ass.assignor_and_assignee_id, tawb.assignor_id, doc.appno_doc_num, rac.rf_id
				) AS temp
				WHERE representative_name <> '' AND LOWER(name) <> LOWER(representative_name)";
			$con->query($queryLostAssets);
		}
	
		$query = 'SELECT assignor_id, COUNT(appno_doc_num) AS cont FROM '.$dbApplication.'.assets_with_bank WHERE company_id = '.$companyID.' AND organisation_id = '.$organisationID.' GROUP BY assignor_id ORDER BY cont DESC';	
		$result = $con->query($query);
		$layoutID = 1;
		if($result && $result->num_rows > 0) {  
			while($row = $result->fetch_object()) {
				$listAssets = array();
				echo $row->assignor_id."<br/>";
				
				$con->query("DELETE FROM ".$dbApplication.".assets_bank_broken WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_id = ".$row->assignor_id);
				$con->query("DELETE FROM ".$dbUSPTO.".temp_assets_bank_broken WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_id = ".$row->assignor_id);
				$con->query("DELETE FROM ".$dbUSPTO.".temp_transaction_bank_parties_count WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_id = ".$row->assignor_id);
				
				
				$queryAssetsList = "SELECT appno_doc_num FROM ".$dbApplication.".assets_with_bank WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_id = ".$row->assignor_id." GROUP BY appno_doc_num";
				$resultAssetsList = $con->query($queryAssetsList);
				if($resultAssetsList && $resultAssetsList->num_rows > 0) {
					while($rowAsset = $resultAssetsList->fetch_object()) {
						array_push($listAssets, '"'.$rowAsset->appno_doc_num.'"');
					}
				}  
				
				if(count($listAssets) > 0) {					
					/*
					-Part 0
					*/
					
					$queryPart0 = "INSERT IGNORE INTO ".$dbUSPTO.".temp_assets_bank_broken (appno_doc_num, assignor_id, company_id, organisation_id)
					SELECT appno_doc_num, ".$row->assignor_id.", ".$companyID.", ".$organisationID." FROM ".$dbApplication.".assets_with_bank WHERE appno_doc_num NOT IN (SELECT documentid.appno_doc_num FROM ".$dbUSPTO.".documentid AS documentid 
							INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = documentid.rf_id
							INNER JOIN ".$dbUSPTO.".assignor AS assignor ON assignor.rf_id = rac.rf_id
							INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id
							LEFT JOIN ".$dbUSPTO.".representative AS representative ON representative.representative_id  = assignor_and_assignee.representative_id
							WHERE appno_doc_num IN (".implode(',', $listAssets).") AND (rac.convey_ty = 'employee' OR (rac.convey_ty = 'assignment' AND rac.employer_assign = 1))
							GROUP BY documentid.appno_doc_num) AND assignor_id = ".$row->assignor_id." AND company_id = ".$companyID." AND organisation_id = ".$organisationID." GROUP BY appno_doc_num";
					
					$con->query($queryPart0);
					
					/*
						-Part 1 
						Transaction Type is employee	
							Return
								Count of transaction in a Assets
								Count of Inventor in a Assets
					*/
					$queryPart1 = "INSERT IGNORE INTO ".$dbUSPTO.".temp_transaction_bank_parties_count (appno_doc_num, transaction_count, rf_ids, parties_count, assignor_id, company_id, organisation_id) 
					SELECT appno_doc_num, COUNT(DISTINCT rf_id) as transaction_count, GROUP_CONCAT(DISTINCT rf_id) AS rf_ids,  COUNT( DISTINCT name) AS parties_count, ".$row->assignor_id.", ".$companyID.", ".$organisationID." FROM (SELECT documentid.appno_doc_num, rac.rf_id, IF(representative.representative_name <> null, representative.representative_name, assignor_and_assignee.name) AS name FROM ".$dbUSPTO.".documentid AS documentid 
					INNER JOIN ".$dbUSPTO.".representative_assignment_conveyance AS rac ON rac.rf_id = documentid.rf_id
					INNER JOIN ".$dbUSPTO.".assignor AS assignor ON assignor.rf_id = rac.rf_id
					INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id
					LEFT JOIN ".$dbUSPTO.".representative AS representative ON representative.representative_id  = assignor_and_assignee.representative_id
					WHERE appno_doc_num IN (".implode(',', $listAssets).") AND (rac.convey_ty = 'employee' OR (rac.convey_ty = 'assignment' AND rac.employer_assign = 1))) AS temp
					GROUP BY documentid.appno_doc_num";
					
					$con->query($queryPart1);
					
					/*
						-Part 2 
							Transaction count is 1 AND No of Inventors <> No of Inventors from Bibliographic database
							Return
								Assets			
					*/
					
					$queryPart2 = "INSERT IGNORE INTO ".$dbUSPTO.".temp_assets_bank_broken (appno_doc_num, assignor_id, company_id, organisation_id)
					SELECT tempTransactions.appno_doc_num, ".$row->assignor_id.", ".$companyID.", ".$organisationID." FROM ".$dbUSPTO.".temp_transaction_bank_parties_count AS tempTransactions
					INNER JOIN (
						SELECT appno_doc_num, COUNT(DISTINCT inventor.name) AS inventor_count FROM db_patent_application_bibliographic.inventor AS inventor WHERE appno_doc_num IN (".implode(',', $listAssets).")
						GROUP BY appno_doc_num
						UNION
						SELECT appno_doc_num, COUNT(DISTINCT inventor.name) AS inventor_count  FROM db_patent_grant_bibliographic.inventor AS inventor
						WHERE appno_doc_num IN (".implode(',', $listAssets).")
						GROUP BY appno_doc_num
					) AS tempInventors ON tempInventors.appno_doc_num = tempTransactions.appno_doc_num
					WHERE tempTransactions.transaction_count = 1 AND tempTransactions.assignor_id = ".$row->assignor_id." AND tempTransactions.company_id = ".$companyID." AND tempTransactions.organisation_id = ".$organisationID." AND tempInventors.inventor_count > tempTransactions.parties_count
					GROUP BY tempTransactions.appno_doc_num";
					
					$con->query($queryPart2);
					
					/*
						-Part 3 
							Transaction count is greater than 1 AND Inventor last name from bibliographic database is not find in USPTO assignor database
							Return
								Assets			
					*/
					
					$queryPart3 = "INSERT IGNORE INTO ".$dbUSPTO.".temp_assets_bank_broken (appno_doc_num, assignor_id, company_id, organisation_id)
						WITH t1 AS (SELECT temp1.appno_doc_num, temp1.transaction_name AS name, 	
							SUBSTRING_INDEX(SUBSTRING_INDEX(temp1.transaction_name, ' ', 1), ' ', -1) AS family_name,
							SUBSTRING_INDEX(SUBSTRING_INDEX(temp1.transaction_name, ' ', 2), ' ', -1) AS given_name FROM (Select temp_transaction_bank_parties_count.appno_doc_num AS appno_doc_num, IF(representative.representative_name <> null, representative.representative_name, assignor_and_assignee.name) AS transaction_name from ".$dbUSPTO.".temp_transaction_bank_parties_count 
							INNER JOIN ".$dbUSPTO.".documentid AS documentid ON documentid.appno_doc_num = temp_transaction_bank_parties_count.appno_doc_num
							INNER JOIN ".$dbUSPTO.".assignor AS assignor ON assignor.rf_id = documentid.rf_id
							INNER JOIN ".$dbUSPTO.".assignor_and_assignee AS assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id
							LEFT JOIN ".$dbUSPTO.".representative AS representative ON representative.representative_id  = assignor_and_assignee.representative_id
							WHERE temp_transaction_bank_parties_count.transaction_count > 1 AND temp_transaction_bank_parties_count.assignor_id = ".$row->assignor_id." AND temp_transaction_bank_parties_count.company_id = ".$companyID." AND temp_transaction_bank_parties_count.organisation_id = ".$organisationID."
							GROUP BY appno_doc_num, transaction_name) AS temp1)
							
							SELECT temp2.appno_doc_num, ".$row->assignor_id.", ".$companyID.", ".$organisationID." FROM (
								SELECT inventorMain.appno_doc_num AS appno_doc_num, CONCAT(inventorMain.family_name, ' ', inventorMain.given_name)  As name1, inventorMain.given_name As given_name, inventorMain.family_name AS family_name 
								FROM db_patent_application_bibliographic.inventor AS inventorMain
								INNER JOIN ".$dbUSPTO.".temp_transaction_bank_parties_count AS temp_transaction_bank_parties_count ON inventorMain.appno_doc_num = temp_transaction_bank_parties_count.appno_doc_num
								WHERE temp_transaction_bank_parties_count.transaction_count > 1 AND temp_transaction_bank_parties_count.assignor_id = ".$row->assignor_id." AND temp_transaction_bank_parties_count.company_id = ".$companyID." AND temp_transaction_bank_parties_count.organisation_id = ".$organisationID."
								GROUP BY appno_doc_num, name1, given_name, family_name
							) AS temp2
							LEFT OUTER JOIN (
								SELECT t1.* FROM t1
							) AS temp3 ON temp2.appno_doc_num = temp3.appno_doc_num AND (temp2.family_name = temp3.family_name OR temp2.name1 = temp3.name)
							WHERE temp3.name IS NULL
							GROUP BY temp2.appno_doc_num";
					$con->query($queryPart3);
					/*
						-Part 4
							Assignee not in Assignor
					*/
					$brokenAssets = array();
					$queryBrokenAssetsList = "SELECT appno_doc_num FROM temp_assets_bank_broken WHERE company_id = ".$companyID." AND assignor_id = ".$row->assignor_id." AND organisation_id = ".$organisationID." GROUP BY appno_doc_num";
					$resultBrokenAssetsList = $con->query($queryBrokenAssetsList);
					if($resultBrokenAssetsList && $resultBrokenAssetsList->num_rows > 0) {
						while($rowAsset = $resultBrokenAssetsList->fetch_object()) {
							array_push($brokenAssets, '"'.$rowAsset->appno_doc_num.'"');
						}
					}
					
					$queryPart4 = "INSERT IGNORE INTO temp_assets_bank_broken (appno_doc_num, assignor_id, company_id, organisation_id)
					SELECT * FROM (
					SELECT temp1.appno_doc_num, ".$row->assignor_id.", ".$companyID.", ".$organisationID." FROM (		
						SELECT temp_broken_title.appno_doc_num, temp_broken_title.appno_date, max(temp_broken_title.grant_doc_num) AS grant_doc_num, max(temp_broken_title.grant_date) AS grant_date, temp_broken_title.rf_id, assignor.assignor_and_assignee_id, case WHEN assignor_and_assignee.representative_id <> 0 THEN representative.representative_name ELSE assignor_and_assignee.name END as representativeName, ".$row->assignor_id." AS assignor_id, ".$companyID." AS company_id, ".$organisationID." AS organisation_id FROM (	
							SELECT documentid.appno_doc_num, assets_with_bank.exec_dt AS last_exec_dt, documentid.appno_date, max(documentid.grant_doc_num) AS grant_doc_num, max(documentid.grant_date) AS grant_date, 
							documentid.rf_id, ".$row->assignor_id.", ".$companyID.", ".$organisationID."  From db_new_application.assets_with_bank AS assets_with_bank
							INNER JOIN (
								SELECT rf_id, appno_doc_num, appno_date, grant_doc_num, grant_date
								FROM db_uspto.documentid
								WHERE appno_doc_num IN (".implode(',', $listAssets).")							
							) AS documentid	ON documentid.appno_doc_num = assets_with_bank.appno_doc_num
							INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = documentid.rf_id 
							AND representative_assignment_conveyance.convey_ty <> 'employee' AND representative_assignment_conveyance.employer_assign = 0 
							INNER JOIN conveyance ON conveyance.convey_name = representative_assignment_conveyance.convey_ty AND conveyance.is_ota = 1 
							WHERE company_id = ".$companyID." AND assignor_id = ".$row->assignor_id." AND organisation_id = ".$organisationID;
			
						if(count($brokenAssets) > 0) {
							$queryPart4 .= " AND documentid.appno_doc_num NOT IN (".implode(',', $brokenAssets).") ";
						}

						$queryPart4 .= "	
							GROUP BY documentid.appno_doc_num, documentid.rf_id
						) AS temp_broken_title
						INNER JOIN assignor ON assignor.rf_id = temp_broken_title.rf_id 	
						INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = assignor.rf_id
						INNER JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignor.assignor_and_assignee_id
						LEFT JOIN representative ON representative.representative_id = assignor_and_assignee.representative_id
						WHERE assignor.exec_dt <= last_exec_dt 
						AND temp_broken_title.appno_doc_num IN (SELECT appno_doc_num FROM temp_transaction_bank_parties_count WHERE company_id = ".$companyID." AND assignor_id = ".$row->assignor_id." AND organisation_id = ".$organisationID." GROUP BY  appno_doc_num)";
						
						if(count($brokenAssets) > 0) {
							$queryPart4 .= " AND temp_broken_title.appno_doc_num NOT IN (".implode(',', $brokenAssets).") ";
						}

						$queryPart4 .= "
						GROUP BY temp_broken_title.appno_doc_num, temp_broken_title.rf_id, assignor.assignor_and_assignee_id, representativeName
					) AS temp1
					LEFT OUTER JOIN (SELECT * FROM (
							SELECT temp_broken_title.appno_doc_num, temp_broken_title.appno_date, max(temp_broken_title.grant_doc_num) AS grant_doc_num, 
							max(temp_broken_title.grant_date) AS grant_date, temp_broken_title.rf_id, assignee.assignor_and_assignee_id, 
							case WHEN assignor_and_assignee.representative_id <> 0 THEN representative.representative_name ELSE assignor_and_assignee.name END as representativeName, ".$row->assignor_id." AS assignor_id, ".$companyID." AS company_id, ".$organisationID." AS organisation_id FROM (	
								SELECT documentid.appno_doc_num, assets_with_bank.exec_dt AS last_exec_dt, documentid.appno_date, max(documentid.grant_doc_num) AS grant_doc_num, max(documentid.grant_date) AS grant_date, 
								documentid.rf_id, ".$row->assignor_id." AS assignor_id, ".$companyID." AS company_id, ".$organisationID." AS organisation_id  From db_new_application.assets_with_bank AS assets_with_bank								
								INNER JOIN (
								SELECT rf_id, appno_doc_num, appno_date, grant_doc_num, grant_date
								FROM db_uspto.documentid
								WHERE appno_doc_num IN (".implode(',', $listAssets).")							
							) AS documentid	ON documentid.appno_doc_num = assets_with_bank.appno_doc_num								
								INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = documentid.rf_id
								INNER JOIN conveyance ON conveyance.convey_name = representative_assignment_conveyance.convey_ty AND conveyance.is_ota = 1
								WHERE documentid.rf_id <>  assets_with_bank.rf_id AND company_id = ".$companyID." AND organisation_id = ".$organisationID;

						if(count($brokenAssets) > 0) {
							$queryPart4 .= " AND documentid.appno_doc_num NOT IN (".implode(',', $brokenAssets).") ";
						}


						$queryPart4 .= "
								GROUP BY documentid.appno_doc_num, documentid.rf_id
							) AS temp_broken_title 
							INNER JOIN assignee ON assignee.rf_id = temp_broken_title.rf_id
							INNER JOIN assignor_and_assignee ON assignor_and_assignee.assignor_and_assignee_id = assignee.assignor_and_assignee_id
							LEFT JOIN representative ON representative.representative_id = assignor_and_assignee.representative_id
							INNER JOIN assignor ON assignor.rf_id = assignee.rf_id AND assignor.exec_dt <= last_exec_dt   
							WHERE temp_broken_title.appno_doc_num IN (SELECT appno_doc_num FROM temp_transaction_bank_parties_count WHERE company_id = ".$companyID." AND assignor_id = ".$row->assignor_id." AND organisation_id = ".$organisationID." GROUP BY  appno_doc_num)";

						if(count($brokenAssets) > 0) {
							$queryPart4 .= " AND temp_broken_title.appno_doc_num NOT IN (".implode(',', $brokenAssets).") ";
						}	
							
						$queryPart4 .= "
							GROUP BY temp_broken_title.appno_doc_num, temp_broken_title.rf_id, assignee.assignor_and_assignee_id, representativeName
						) AS temp2
					) AS temp3 ON temp1.appno_doc_num = temp3.appno_doc_num AND temp1.representativeName = temp3.representativeName
					WHERE temp3.representativeName IS NULL
					GROUP BY temp1.appno_doc_num) AS temp4";
					
					$con->query($queryPart4);	 
					
					/*Part 5
						Insert Everything into assets table
					*/
					$queryPart5 = "INSERT IGNORE INTO ".$dbApplication.".assets_bank_broken (rf_id, appno_doc_num, appno_date, grant_doc_num, grant_date, layout_id, company_id, assignor_id, organisation_id)
					SELECT rf_id, appno_doc_num, appno_date, grant_doc_num, grant_date, ".$layoutID.", ".$companyID.", ".$row->assignor_id.", ".$organisationID." FROM ".$dbApplication.".assets_with_bank AS assets_with_bank					 WHERE assets_with_bank.appno_doc_num IN (SELECT appno_doc_num FROM temp_assets_bank_broken WHERE company_id = ".$companyID." AND assignor_id = ".$row->assignor_id." AND organisation_id = ".$organisationID." ) GROUP BY appno_doc_num";
					$con->query($queryPart5);
					$con->query("DELETE FROM ".$dbUSPTO.".temp_assets_bank_broken WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_id = ".$row->assignor_id);
					$con->query("DELETE FROM ".$dbUSPTO.".temp_transaction_bank_parties_count WHERE company_id = ".$companyID." AND organisation_id = ".$organisationID." AND assignor_id = ".$row->assignor_id);
				}				
			}
		}
	}
}