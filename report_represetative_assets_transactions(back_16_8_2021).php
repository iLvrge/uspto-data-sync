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


$queryRepresentative = "SELECT representative_id, representative_name FROM representative";
$resultRepresentative = $con->query($queryRepresentative);
if($resultRepresentative && $resultRepresentative->num_rows > 0){
	while($row = $resultRepresentative->fetch_object()){
		
		$queryAssetsRFIDs = "INSERT IGNORE INTO report_representative_assets_transactions(representative_id, representative_name, appno_doc_num, grant_doc_num, rf_id, assignee, assignor, convey_ty) SELECT temp.representative_id, temp.representative_name, documentid.appno_doc_num, documentid.grant_doc_num , documentid.rf_id, (SELECT ee_name FROM assignee WHERE assignee.rf_id = documentid.rf_id LIMIT 1), (SELECT or_name FROM assignor WHERE assignor.rf_id = documentid.rf_id LIMIT 1), representative_assignment_conveyance.convey_ty
						FROM(
							SELECT temp1.representative_id, temp1.representative_name, appno_doc_num, grant_doc_num FROM documentid
							INNER JOIN (
								Select representative.representative_id, representative_name, rf_id FROM db_uspto.representative 
								INNER JOIN db_uspto.assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
								INNER JOIN db_uspto.assignee ON assignee.assignor_and_assignee_id = assignor_and_assignee.assignor_and_assignee_id
								WHERE representative.representative_id = ".$row->representative_id."
								UNION
								Select representative.representative_id, representative_name, rf_id FROM db_uspto.representative 
								INNER JOIN db_uspto.assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
								INNER JOIN db_uspto.assignor ON assignor.assignor_and_assignee_id = assignor_and_assignee.assignor_and_assignee_id
								WHERE representative.representative_id  = ".$row->representative_id."
							) as temp1 ON temp1.rf_id = documentid.rf_id
							GROUP BY temp1.representative_id, appno_doc_num, grant_doc_num
						) as temp
						INNER JOIN documentid ON documentid.appno_doc_num = temp.appno_doc_num AND documentid.grant_doc_num = temp.grant_doc_num
						INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = documentid.rf_id
						GROUP BY temp.representative_id, documentid.appno_doc_num, documentid.grant_doc_num , documentid.rf_id
						";
		
		
		$con->query($queryAssetsRFIDs);
		$queryAssetsRFIDsParties = "INSERT IGNORE INTO report_representative_assets_transactions_parties (rf_id, no_of_parties, party_type)	
	SELECT assignee.rf_id, count( distinct assignee.assignor_and_assignee_id) as no_of_parties, 0 as party_type  FROM db_uspto.assignee 
		INNER JOIN db_uspto.report_representative_assets_transactions ON report_representative_assets_transactions.rf_id = assignee.rf_id
		WHERE report_representative_assets_transactions.representative_id = ".$row->representative_id."
		GROUP BY assignee.rf_id
		UNION ALL
		SELECT assignor.rf_id, count( distinct assignor.assignor_and_assignee_id) as no_of_parties, 1 as party_type FROM db_uspto.assignor 
		INNER JOIN db_uspto.report_representative_assets_transactions ON report_representative_assets_transactions.rf_id = assignor.rf_id
		INNER JOIN db_uspto.representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = assignor.rf_id AND representative_assignment_conveyance.convey_ty <> 'employee'
		WHERE report_representative_assets_transactions.representative_id = ".$row->representative_id."
		GROUP BY assignor.rf_id
		UNION ALL
		SELECT assignor.rf_id, count( distinct assignor.assignor_and_assignee_id) as no_of_parties, 2 as party_type FROM db_uspto.assignor 
		INNER JOIN db_uspto.report_representative_assets_transactions ON report_representative_assets_transactions.rf_id = assignor.rf_id
		INNER JOIN db_uspto.representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = assignor.rf_id AND representative_assignment_conveyance.convey_ty = 'employee'
		WHERE report_representative_assets_transactions.representative_id = ".$row->representative_id."
		GROUP BY assignor.rf_id";
		
		$con->query($queryAssetsRFIDsParties);
	
		/*Report*/
		$queryReports = "INSERT IGNORE INTO representative_reports (representative_id, representative_name, no_of_assets, no_of_transactions, no_of_parties, no_of_inventor, no_of_activities) SELECT representative_id, representative_name, (SELECT count(asset) FROM (Select CASE 
				WHEN report_representative_assets_transactions.grant_doc_num <> '' 
					THEN report_representative_assets_transactions.grant_doc_num 
				ELSE report_representative_assets_transactions.appno_doc_num
			END as asset from report_representative_assets_transactions where representative_id = ".$row->representative_id."
			GROUP BY asset) as temp_asset) as noOfAssets, (SELECT count(transactions) FROM (SELECT report_representative_assets_transactions.rf_id as transactions
			FROM report_representative_assets_transactions
			WHERE report_representative_assets_transactions.representative_id = ".$row->representative_id."
			GROUP BY report_representative_assets_transactions.appno_doc_num, report_representative_assets_transactions.grant_doc_num,
			report_representative_assets_transactions.rf_id) as temp) as noOfTransactions, (SELECT sum(no_of_parties) FROM (SELECT report_representative_assets_transactions.grant_doc_num, 
			report_representative_assets_transactions.appno_doc_num, 
			report_representative_assets_transactions_parties.rf_id, 
			sum(report_representative_assets_transactions_parties.no_of_parties) as no_of_parties
			FROM report_representative_assets_transactions
			INNER JOIN report_representative_assets_transactions_parties ON report_representative_assets_transactions_parties.rf_id = report_representative_assets_transactions.rf_id AND party_type <> 2
			WHERE report_representative_assets_transactions.representative_id = ".$row->representative_id."
			GROUP BY report_representative_assets_transactions.grant_doc_num, 
			report_representative_assets_transactions.appno_doc_num, 
			report_representative_assets_transactions_parties.rf_id) as temp) as noOfParties, (SELECT sum(no_of_parties) FROM (SELECT report_representative_assets_transactions.grant_doc_num, 
			report_representative_assets_transactions.appno_doc_num, 
			report_representative_assets_transactions_parties.rf_id, 
			sum(report_representative_assets_transactions_parties.no_of_parties) as no_of_parties
			FROM report_representative_assets_transactions
			INNER JOIN report_representative_assets_transactions_parties ON report_representative_assets_transactions_parties.rf_id = report_representative_assets_transactions.rf_id AND party_type = 2
			WHERE report_representative_assets_transactions.representative_id = ".$row->representative_id."
			GROUP BY report_representative_assets_transactions.grant_doc_num, 
			report_representative_assets_transactions.appno_doc_num, 
			report_representative_assets_transactions_parties.rf_id) as temp) as noOfInventors, (SELECT count(*) FROM ( SELECT convey_ty FROM report_representative_assets_transactions WHERE report_representative_assets_transactions.representative_id = ".$row->representative_id." GROUP BY convey_ty) as temp) as noOfActivity FROM report_representative_assets_transactions WHERE report_representative_assets_transactions.representative_id = ".$row->representative_id." LIMIT 1";
		$con->query("DELETE FROM representative_reports WHERE representative_id = ".$row->representative_id);
		
		$con->query($queryReports); 
		
	}
}

?>