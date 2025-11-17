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
					$queryRepresentative = "SELECT representative_id FROM representative WHERE parent_id = 0";
				}
				
				//echo $queryRepresentative."<br/>";
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
				
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
					while($row = $resultRepresentativeParentCompany->fetch_object()){
						$findRepresentativeName = "Select representative_id, original_name, representative_name FROM representative WHERE representative_id = '".$row->representative_id."' OR parent_id = '".$row->representative_id."'";
						
						$resultRepresentativeCompanies = $orgConnect->query($findRepresentativeName);
						
						$allNames = array();
						while($rowRepresentative = $resultRepresentativeCompanies->fetch_object()){
							/*$name = $rowRepresentative->representative_name;
							if($name == null || $name == "") {
								
							}*/
							$name = $rowRepresentative->original_name;
							array_push($allNames, "'".$con->real_escape_string($name)."'");							
						}
						
						$con->query("DELETE FROM tree WHERE organisation_id = ".$orgRow->organisation_id);
						
						$customEmployeeQuery = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent,"0" as type, "0" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM db_uspto.assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.employer_assign = 1 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
						echo $customEmployeeQuery."<br/>";						
						$con->query($customEmployeeQuery);
						
						/*Acquisition*/
						
						/*Purchase*/
						$queryPurchase = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "1" as type, "1" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "assignment" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).'))) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
						echo $queryPurchase."<br/>";
						$con->query($queryPurchase);
						
						/*Sale*/
						$querySale = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "2" as type, "1" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ac ON ac.rf_id = or.rf_id INNER JOIN documentid as d ON ac.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ac.convey_ty = "assignment" AND ac.employer_assign = 0  AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
						echo $querySale."<br/>";
						$con->query($querySale);
						
						/*MergerIn*/
						$queryMergerIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "3" as type, "1" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name';
						echo $queryMergerIn."<br/>";
						$con->query($queryMergerIn);
						
						/*MergerOut*/
						$queryMergerOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "4" as type, "1" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id  FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "merger" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
						echo $queryMergerOut."<br/>";
						$con->query($queryMergerOut);
						
						/*SecurityOut*/
						$querySecurityOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "5" as type, "2" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignee as ee LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "security" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name';
						echo $querySecurityOut."<br/>";
						$con->query($querySecurityOut);
						
						/*SecurityIn*/
						$querySecurityIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "6" as type, "2" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as `ee` INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "security" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						echo $querySecurityIn."<br/>";
						$con->query($querySecurityIn);
						
						/*ReleaseOut*/
						$queryReleaseOut = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "7" as type, "2" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "release" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						echo $queryReleaseOut."<br/>";
						$con->query($queryReleaseOut);
						
						/*ReleaseIn*/
						$queryReleaseIn = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "8" as type, "2" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignee as `ee` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT or.rf_id FROM assignor as `or` INNER JOIN assignment_conveyance as ass ON ass.rf_id = or.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "release" AND ass.employer_assign = 0 AND  (aa.name IN ('.implode(",",$allNames).')) GROUP BY or.rf_id) as temp ON temp.rf_id = ee.rf_id GROUP BY show_name ';
						echo $queryReleaseIn."<br/>";
						$con->query($queryReleaseIn);
						
						/*NameChange*/
						$queryNameChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "9" as type, "3" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "namechg" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						echo $queryNameChange."<br/>";
						$con->query($queryNameChange);
						
						/*Govern*/
						$queryGovernChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "10" as type, "3" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "govern" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						echo $queryGovernChange."<br/>";
						$con->query($queryGovernChange);
						
						/*Correct*/
						$queryCorrectChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "11" as type, "3" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "correct" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						echo $queryCorrectChange."<br/>";
						$con->query($queryCorrectChange);
						
						/*Missing*/
						$queryMissingChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "12" as type, "3" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "missing" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).')) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						echo $queryMissingChange."<br/>";
						$con->query($queryMissingChange);
						
						/*Other*/
						$queryOtherChange = 'INSERT IGNORE INTO tree (assignor_and_assignee_id, name, parent, type, tab, organisation_id, representative_id) SELECT aaa.assignor_and_assignee_id as assignor_and_assignee_id, CASE WHEN r.representative_name IS NOT NULL THEN r.representative_name ELSE aaa.name END as show_name, "0" as parent, "13" as type, "3" as tab, "'.$orgRow->organisation_id.'" as organisation_id, "'.$row->representative_id.'" as representative_id FROM assignor as `or` LEFT JOIN assignor_and_assignee as aaa ON aaa.assignor_and_assignee_id = or.assignor_and_assignee_id LEFT JOIN representative as r ON r.representative_id = aaa.representative_id INNER JOIN (SELECT ee.rf_id FROM assignee as ee INNER JOIN assignment_conveyance as ass ON ass.rf_id = ee.rf_id INNER JOIN documentid as d ON ass.rf_id = d.rf_id INNER JOIN  assignor_and_assignee as aa ON aa.assignor_and_assignee_id = ee.assignor_and_assignee_id LEFT JOIN representative as r1 ON r1.representative_id = aa.representative_id WHERE ass.convey_ty = "other" AND ass.employer_assign = 0 AND (aa.name IN ('.implode(",",$allNames).') ) GROUP BY ee.rf_id) as temp ON temp.rf_id = or.rf_id GROUP BY show_name ';
						echo $queryOtherChange."<br/>";
						$con->query($queryOtherChange);
						
					}
				}
			}
		}
	}
}