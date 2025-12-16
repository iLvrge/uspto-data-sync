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
		$queryAssets = "INSERT IGNORE INTO representative_ota (representative_id, representative_name, appno_doc_num, grant_doc_num, grant_date) SELECT list2.representative_id, list2.representative_name, documentid.appno_doc_num, documentid.grant_doc_num, documentid.grant_date
		FROM (
		SELECT representative.representative_id, representative_name, rf_id FROM db_uspto.representative 
		INNER JOIN db_uspto.assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
		INNER JOIN db_uspto.assignee ON assignee.assignor_and_assignee_id = assignor_and_assignee.assignor_and_assignee_id
		WHERE representative.representative_id = ".$row->representative_id."
		UNION
		SELECT representative.representative_id, representative_name, rf_id FROM db_uspto.representative 
		INNER JOIN db_uspto.assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
		INNER JOIN db_uspto.assignor ON assignor.assignor_and_assignee_id = assignor_and_assignee.assignor_and_assignee_id
		WHERE representative.representative_id  = ".$row->representative_id."
		) as list2
		INNER JOIN documentid ON documentid.rf_id = list2.rf_id
		INNER JOIN assignee ON assignee.rf_id = list2.rf_id
		INNER JOIN assignor ON assignee.rf_id = assignor.rf_id
		INNER JOIN representative_assignment_conveyance ON representative_assignment_conveyance.rf_id = list2.rf_id
		INNER JOIN conveyance ON conveyance.convey_name = representative_assignment_conveyance.convey_ty AND conveyance.is_ota = 1
		INNER JOIN LATERAL ( # Gets maximum execute date of OTA of a patent
			SELECT d1.appno_doc_num
				 , MAX(assignor.exec_dt) exec_dt
			FROM documentid as d1
			INNER JOIN assignor 
				 ON assignor.rf_id = d1.rf_id		
			INNER JOIN representative_assignment_conveyance 
				ON representative_assignment_conveyance.rf_id = d1.rf_id
			INNER JOIN conveyance 
				ON representative_assignment_conveyance.convey_ty = conveyance.convey_name			
			WHERE d1.appno_doc_num = documentid.appno_doc_num
				AND conveyance.is_ota = 1
			GROUP BY d1.appno_doc_num 
		) AS last_date
		WHERE assignor.exec_dt = last_date.exec_dt
		GROUP BY documentid.appno_doc_num, documentid.grant_doc_num";
		
		$con->query($queryAssets); 
		
		$todayDate = date('Y-m-d');
		$date = new DateTime($todayDate);
		$date->sub(new DateInterval('P10Y'));
		$last10YearDate = $date->format('Y-m-d');
		
		$queryMaintainence = "INSERT IGNORE INTO representative_ota_event(representative_id, representative_name, appno_doc_num, grant_doc_num, filling_date, grant_date, event_date, event_code) SELECT representative_ota.representative_id, representative_ota.representative_name, event_maintainence_fees.appno_doc_num, event_maintainence_fees.grant_doc_num, event_maintainence_fees.filling_date, event_maintainence_fees.grant_date, event_maintainence_fees.event_date, event_maintainence_fees.event_code FROM db_patent_maintainence_fee.event_maintainence_fees 
			INNER JOIN representative_ota ON representative_ota.appno_doc_num = event_maintainence_fees.appno_doc_num
			WHERE representative_ota.representative_id = ".$row->representative_id." AND representative_ota.grant_doc_num <> '' AND event_code IN ('EXP.', 'EXPX', 'M1551', 'M2551', 'M3551', 'M1552', 'M2552', 'M3552', 'M1553', 'M2553', 'M3553')
			AND date_format(event_date, '%Y-%m-%d') BETWEEN '".$last10YearDate."' AND '".$todayDate."' ";
		
		$con->query($queryMaintainence); 
	}
}

?>