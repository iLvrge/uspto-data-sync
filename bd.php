<?php 
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
ignore_user_abort(true);
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$query = "SELECT d.appno_doc_num, count(d.rf_id) AS counter FROM documentid AS d
INNER JOIN ( SELECT d.appno_doc_num, count(d.rf_id) AS counter FROM db_uspto.documentid as d
INNER JOIN db_uspto.assignment as a ON a.rf_id = d.rf_id
INNER JOIN db_uspto.representative_assignment_conveyance as ac ON ac.rf_id = d.rf_id
WHERE ac.convey_ty IN('security', 'relatedsecurity') AND d.appno_doc_num <> ''
GROUP BY d.appno_doc_num
HAVING counter > 3) as securityTable ON d.appno_doc_num = securityTable.appno_doc_num
INNER JOIN db_uspto.assignment as a ON a.rf_id = d.rf_id
INNER JOIN db_uspto.representative_assignment_conveyance as ac ON ac.rf_id = d.rf_id
WHERE ac.convey_ty IN('merger') AND d.appno_doc_num <> '' and date_format(d.appno_date, '%Y') > 1999
GROUP BY d.appno_doc_num
HAVING counter >= 1";

$resultDocument = $con->query($query);

if( $resultDocument && $resultDocument->num_rows > 0) {
	$assets = array();
	while($row = $resultDocument->fetch_object()){
		array_push( $assets, $row->appno_doc_num);
	}
	
	$queryFirst = "SELECT ee.ee_name as name FROM assignee as ee INNER JOIN representative_assignment_conveyance as rac ON rac.rf_id = ee.rf_id WHERE rac.convey_ty IN ('employee', 'assignment', 'partialassignment', 'merger', 'namechange','courtorder', 'courtappointment') AND rac.rf_id IN (SELECT d.rf_id FROM documentid as d WHERE d.appno_doc_num IN (".implode(',', $assets).")) GROUP BY name";
	
	
	$resultFirst = $con->query($queryFirst);
	
	$companyFirst = array();
	
	if( $resultFirst && $resultFirst->num_rows > 0 ) {
		while( $rowFirst = $resultFirst->fetch_object()) {
			array_push( $companyFirst, $rowFirst->name);
		}
	}
	
	
	$querySecond = "SELECT or.or_name as name FROM assignor as or INNER JOIN representative_assignment_conveyance as rac ON rac.rf_id = or.rf_id WHERE rac.convey_ty IN ('assignment', 'partialassignment', 'merger', 'namechange','courtorder', 'courtappointment') ANd rac.employer_assign = 0 AND rac.rf_id IN (SELECT d.rf_id FROM documentid as d WHERE d.appno_doc_num IN (".implode(',', $assets).")) GROUP BY name";
	
	$resultSecond = $con->query($queryFirst);
	
	$companySecond = array();
	
	if( $resultSecond && $resultSecond->num_rows > 0 ) {
		while( $rowSecond = $resultSecond->fetch_object()) {
			array_push( $companySecond, $rowSecond->name);
		}
	}
	
	$companyFinal = array();
	
	if(count($companyFirst) > 0 && count($companySecond) > 0) {
		$companyFinal = array_intersect($companyFirst, $companySecond);
	} else if(count($companyFirst) > 0) {
		$companyFinal = $companyFirst;
	} else if(count($companySecond) > 0) {
		$companyFinal = $companySecond;
	}
	
	
	
	$endCompanies = array();
	
	if(count($companyFinal) >0) {
		
		foreach($companyFinal as $company) {
			$allNames = "";
			$allNames .= ' aaa.name = "'.$con->real_escape_string($company).'"';
			
			
			$queryAssets = 'SELECT appno_doc_num, grant_doc_num FROM documentid WHERE rf_id IN (SELECT `ee`.rf_id  from assignee as `ee` INNER JOIN representative_assignment_conveyance as ac ON ac.rf_id = ee.rf_id  WHERE ee.ee_name = "'.$con->real_escape_string($company).'" AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")) AND appno_doc_num NOT IN (SELECT appno_doc_num FROM documentid WHERE rf_id IN (SELECT `or`.rf_id  from assignor as `or` INNER JOIN representative_assignment_conveyance as ac ON ac.rf_id = or.rf_id WHERE or.or_name = "'.$con->real_escape_string($company).'" AND ac.convey_ty IN ("assignment","partialassignment","namechg","merger","employee", "courtappointment", "courtorder")) GROUP BY appno_doc_num) GROUP BY appno_doc_num';
			
			echo $queryAssets ."<br/>";
			$resultAssets = $con->query($queryAssets);
			
			
			if( $resultAssets && $resultAssets->num_rows > 0 ) {
				if($resultAssets->num_rows >= 1000 && $resultAssets->num_rows <= 5000) {
					array_push($endCompanies, $company);
				}
			}
		}		
	}
	if(count($endCompanies) > 0) {
		$f = fopen('./bd.txt', 'w');
		foreach($endCompanies as $c) {
			fputcsv($f, array($c));
		}
		fclose($f);
	}
	
	echo "<pre>";
	print_r($endCompanies);
}
