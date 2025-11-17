<?php 
ignore_user_abort(true);
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
					$rowRepresentative = $resultRepresentativeParentCompany->fetch_object();
					$findRepresentativeName = "Select representative_id, original_name, representative_name FROM representative WHERE representative_id = '".$rowRepresentative->representative_id."' OR parent_id = '".$rowRepresentative->representative_id."'";
					//echo $findRepresentativeName."<br/>";
					$resultRepresentativeCompanies = $orgConnect->query($findRepresentativeName);
					
					$con->query("DELETE FROM `db_application`.`timeline` WHERE organisation_id =".$orgRow->organisation_id);
					
					while($row = $resultRepresentativeCompanies->fetch_object()){
						$name = $row->representative_name;
						if($name == null || $name == "") {
							$name = $row->original_name;
						}
						echo "EMPLOYER<br/>";
						/*Employer*/
						try{
							$queryAsAssingor = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign) SELECT ac.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", "'.$row->representative_id.'" as representative_id, "Assignor" as type, aa.name as original_name, aa.assignor_and_assignee_id, ac.exec_dt, acc.convey_ty, acc.employer_assign FROM assignor as ac INNER JOIN assignment as ass ON ass.rf_id = ac.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ac.rf_id INNER JOIN assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ac.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN documentid as d ON d.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE (aaa.name = "'.$orgConnect->real_escape_string($name).'"  or r.representative_name = "'.$orgConnect->real_escape_string($name).'") AND date_format(d.appno_date,"%Y") > "1999") as temp ON temp.rf_id = ac.rf_id WHERE  acc.employer_assign = 1';
							//echo $queryAsAssingor."<br/>";
							$con->query($queryAsAssingor);
						}catch(Exception $e){
							print_r($e);
						}
						//echo "EMPLOYER1<br/>";
						try{
							$queryAsAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign) SELECT ac.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", '.$row->representative_id.' as representative_id, "Assignee" as type, aa.name as original_name, aa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ac.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign FROM assignee as ac  INNER JOIN assignment as ass ON ass.rf_id = ac.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ac.rf_id  INNER JOIN assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ac.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN documentid as d ON d.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id  WHERE (aaa.name = "'.$orgConnect->real_escape_string($name).'"  or r.representative_name = "'.$orgConnect->real_escape_string($name).'") AND date_format(d.appno_date,"%Y") > "1999" ) as temp ON temp.rf_id = ac.rf_id WHERE  acc.employer_assign = 1 ';
							$con->query($queryAsAssignee);
						}catch(Exception $e){
							
						}
						/*Assignment and Merger*/
						try{
							$queryAsAssingor = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign) SELECT ac.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", '.$row->representative_id.' as representative_id, "Assignor" as type, aa.name as original_name, aa.assignor_and_assignee_id, ac.exec_dt, acc.convey_ty, acc.employer_assign FROM assignor as ac INNER JOIN assignment as ass ON ass.rf_id = ac.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ac.rf_id INNER JOIN assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ac.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN documentid as d ON d.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE (aaa.name = "'.$orgConnect->real_escape_string($name).'"  or r.representative_name = "'.$orgConnect->real_escape_string($name).'") AND date_format(d.appno_date,"%Y") > "1999") as temp ON temp.rf_id = ac.rf_id WHERE acc.convey_ty IN ("assignment", "merger") AND acc.employer_assign = 0';
							$con->query($queryAsAssingor);
						}catch(Exception $e){
							
						}
						try{
							$queryAsAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign) SELECT ac.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", '.$row->representative_id.' as representative_id, "Assignee" as type, aa.name as original_name, aa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ac.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign FROM assignee as ac  INNER JOIN assignment as ass ON ass.rf_id = ac.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ac.rf_id  INNER JOIN assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ac.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN documentid as d ON d.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id  WHERE (aaa.name = "'.$orgConnect->real_escape_string($name).'"  or r.representative_name = "'.$orgConnect->real_escape_string($name).'") AND date_format(d.appno_date,"%Y") > "1999" ) as temp ON temp.rf_id = ac.rf_id WHERE acc.convey_ty IN ("assignment", "merger") AND acc.employer_assign = 0 ';
							$con->query($queryAsAssignee);
						}catch(Exception $e){
							
						}
						
						/*Security and Release*/
						try{
							$queryAsAssingor = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign) SELECT ac.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", '.$row->representative_id.' as representative_id, "Assignor" as type, aa.name as original_name, aa.assignor_and_assignee_id, ac.exec_dt, acc.convey_ty, acc.employer_assign FROM assignor as ac INNER JOIN assignment as ass ON ass.rf_id = ac.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ac.rf_id INNER JOIN assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ac.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN documentid as d ON d.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE (aaa.name = "'.$orgConnect->real_escape_string($name).'"  or r.representative_name = "'.$orgConnect->real_escape_string($name).'") AND date_format(d.appno_date,"%Y") > "1999") as temp ON temp.rf_id = ac.rf_id WHERE acc.convey_ty IN ("security", "release") AND acc.employer_assign = 0';
							$con->query($queryAsAssingor);
						}catch(Exception $e){
							
						}
						try{
							$queryAsAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign) SELECT ac.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", '.$row->representative_id.' as representative_id, "Assignee" as type, aa.name as original_name, aa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ac.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign FROM assignee as ac  INNER JOIN assignment as ass ON ass.rf_id = ac.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ac.rf_id  INNER JOIN assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ac.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN documentid as d ON d.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id  WHERE (aaa.name = "'.$orgConnect->real_escape_string($name).'"  or r.representative_name = "'.$orgConnect->real_escape_string($name).'") AND date_format(d.appno_date,"%Y") > "1999" ) as temp ON temp.rf_id = ac.rf_id WHERE acc.convey_ty IN ("security", "release") AND acc.employer_assign = 0 ';
							$con->query($queryAsAssignee);
						}catch(Exception $e){
							
						}
						
						/*'namechg', 'govern', 'other', 'missing', 'correct'*/
						try{
							$queryAsAssingor = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign) SELECT ac.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", '.$row->representative_id.' as representative_id, "Assignor" as type, aa.name as original_name, aa.assignor_and_assignee_id, ac.exec_dt, acc.convey_ty, acc.employer_assign FROM assignor as ac INNER JOIN assignment as ass ON ass.rf_id = ac.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ac.rf_id INNER JOIN assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ac.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN documentid as d ON d.rf_id = ee.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id WHERE (aaa.name = "'.$orgConnect->real_escape_string($name).'"  or r.representative_name = "'.$orgConnect->real_escape_string($name).'") AND date_format(d.appno_date,"%Y") > "1999") as temp ON temp.rf_id = ac.rf_id WHERE acc.convey_ty IN ("namechg", "govern", "other", "missing", "correct") AND acc.employer_assign = 0';
							$con->query($queryAsAssingor);
						}catch(Exception $e){
							
						}
						try{
							$queryAsAssignee = 'INSERT IGNORE INTO `db_application`.`timeline` (rf_id, reel_no,	frame_no, record_dt, organisation_id, representative_id, type, original_name, assignor_and_assignee_id, exec_dt, convey_ty, employer_assign) SELECT ac.rf_id, ass.reel_no, ass.frame_no, ass.record_dt, "'.$orgRow->organisation_id.'", '.$row->representative_id.' as representative_id, "Assignee" as type, aa.name as original_name, aa.assignor_and_assignee_id, (SELECT ap.exec_dt FROM assignor as ap WHERE ap.rf_id = ac.rf_id ORDER BY ap.exec_dt ASC LIMIT 1) as exec_dt, acc.convey_ty, acc.employer_assign FROM assignee as ac  INNER JOIN assignment as ass ON ass.rf_id = ac.rf_id INNER JOIN assignment_conveyance as acc ON acc.rf_id = ac.rf_id  INNER JOIN assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ac.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN documentid as d ON d.rf_id = or.rf_id INNER JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id  WHERE (aaa.name = "'.$orgConnect->real_escape_string($name).'"  or r.representative_name = "'.$orgConnect->real_escape_string($name).'") AND date_format(d.appno_date,"%Y") > "1999" ) as temp ON temp.rf_id = ac.rf_id WHERE acc.convey_ty IN ("namechg", "govern", "other", "missing", "correct") AND acc.employer_assign = 0 ';
							$con->query($queryAsAssignee);
						}catch(Exception $e){
							
						}
					}
				}
			}
		}
	}
}
/*Timeline Data*/