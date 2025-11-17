<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
//$dbApplication = getenv('DB_APPLICATION_DB');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbUSPTO);

$query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE organisation_id = 177' ;	
$result = $con->query($query);
if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
		if($orgConnect) {
			$queryCompanies = "SELECT * FROM representative";
			$resultCompanies = $orgConnect->query($queryCompanies);
			if($resultCompanies && $resultCompanies->num_rows > 0) {
				while($row = $resultCompanies->fetch_object()) {
					if($row->company_id == 0 && $row->type != 1) {
						$queryFindRepresentative = "SELECT representative_id FROM representative WHERE representative_name = '".$con->real_escape_string($row->representative_name)."' LIMIT 1";
						$resultRepresentative = $con->query($queryFindRepresentative);
						if($resultRepresentative && $resultRepresentative->num_rows > 0) {
							$rowData = $resultRepresentative->fetch_object();
							updateData("representative", array('company_id' => $rowData->representative_id, 'representative_id' => $row->representative_id), $orgConnect);
						}
					}
				}
			}
		}
	}
}

function updateData($tableName,$updateValues,$con){
	$stringName ="";
	$updateValues = (array)$updateValues;
	foreach($updateValues as $key=>$value){
		if($key != 'representative_id') { 
			$stringName .=$key."='".$con->real_escape_string($value)."',";
		}
	}
	$stringName = substr($stringName,0,-1);
	$sql = "UPDATE ".$tableName." SET ".$stringName." WHERE representative_id= ".$updateValues['representative_id']; 
	echo $sql."<br/><br>"; 
	$con->query($sql);	 
}
die;



/* $con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor`'); */


/* $con->query('ALTER TABLE `db_patent_application_bibliographic`.`application_grant` ADD INDEX `idx_title` (`title` ASC) VISIBLE, ADD INDEX `file_name` (`file_name` ASC) VISIBLE, ADD INDEX `appno_doc_num` (`appno_doc_num` ASC) VISIBLE, ADD INDEX `grant_doc_num` (`grant_doc_num` ASC) VISIBLE, ADD INDEX `app_dt` (`appno_date` ASC) VISIBLE, ADD INDEX `grant_dt` (`grant_date` ASC) VISIBLE, ADD UNIQUE INDEX `app_grant` (`appno_doc_num` ASC, `grant_doc_num` ASC) VISIBLE;'); 

 */ 
//echo $query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE  organisation_id > 0 AND organisation_id <> 3' ;	
//$result = $con->query($query);
//if($result && $result->num_rows > 0) {
	//while($row = $result->fetch_object()) {
		//print_r($row);
		//$orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
		//if($orgConnect) {
			//$orgConnect->query('ALTER TABLE `representative` ADD COLUMN `company_id` BIGINT NULL DEFAULT 0 AFTER `representative_id`') ;
			//$orgConnect->query('ALTER TABLE `representative` ADD COLUMN `mode` TINYINT NULL DEFAULT 0 AFTER `status`');
			//$orgConnect->query("ALTER TABLE `address` ADD COLUMN `country` VARCHAR(20) NULL AFTER `zip_code`");
			/* $orgConnect->query("ALTER TABLE  `representative` CHANGE COLUMN `status` `status` TINYINT NOT NULL DEFAULT '1'");

			$orgConnect->query("CREATE TABLE `categories` (
				`category_id` int NOT NULL AUTO_INCREMENT,
				`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
				`created_at` datetime DEFAULT NULL,
				`updated_at` datetime DEFAULT NULL,
				PRIMARY KEY (`category_id`),
				KEY `idx_name` (`name`)
				) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");

			$orgConnect->query("CREATE TABLE `products` (
				`product_id` int NOT NULL AUTO_INCREMENT,
				`name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
				`category_id` int NOT NULL,
				`created_at` datetime DEFAULT NULL,
				`updated_at` datetime DEFAULT NULL,
				PRIMARY KEY (`product_id`),
				UNIQUE KEY `unique_idx` (`category_id`,`name`),
				KEY `idx_cate` (`category_id`),
				KEY `idx_name` (`name`)
				) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1"); */
		//}
	//}
//}  
 
/* 
$query = "select id, name from db_patent_application_bibliographic.lawfirm where name LIKE '%&#x26;%'";
$result = $con->query($query);
if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) { 
		$con->query("UPDATE  db_patent_application_bibliographic.lawfirm set name = REPLACE(name, '&#x26;', '&') Where id = ".$row->id); 
	}
} */


/*$con->query("ALTER TABLE `db_patent_application_bibliographic`.`assignor_and_assignee`  ADD COLUMN `type` TINYINT NULL DEFAULT 0 AFTER `representative_id`, ADD INDEX `type_idx` (`type` ASC) VISIBLE") or die($con->error);*/


/* $query = 'SELECT name FROM db_patent_application_bibliographic.inventor GROUP BY name UNION SELECT name FROM db_patent_grant_bibliographic.inventor_new GROUP BY name' ;	
$result = $con->query($query);
if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$queryName = "SELECT count(*) as countRows FROM `db_patent_application_bibliographic`.`assignor_and_assignee` WHERE name = '".$con->real_escape_string($row->name)."' AND type = 0";
		$resultName = $con->query($queryName);
		if($resultName) {
			$rowName = $resultName->fetch_object();
			if($rowName->countRows > 0) {
				$con->query("UPDATE `db_patent_application_bibliographic`.`assignor_and_assignee`  SET `type` = 1 WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM `db_patent_application_bibliographic`.`assignor_and_assignee` WHERE name = '".$con->real_escape_string($row->name)."'  AND type = 0 GROUP BY assignor_and_assignee_id)");
			}
		}
		
	}
}  */


/* $query = "update db_patent_application_bibliographic.lawfirm set name = REPLACE(name, '&#x26;', '&') where name LIKE '%&#x26;%'"; */
/* $query = "ALTER TABLE db_new_application.cited_patents CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
$con->query($query); */


//$con->query("ALTER TABLE db_patent_grant_bibliographic.application_publication CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"); 
/* $con->query("ALTER TABLE `db_patent_grant_bibliographic`.`application_publication` ADD COLUMN `title` VARCHAR(250) NULL AFTER `file_name`, ADD INDEX `idx_title` (`title` ASC) VISIBLE"); 
$con->query("ALTER TABLE `db_patent_application_bibliographic`.`application_grant` ADD COLUMN `title` VARCHAR(250) NULL AFTER `file_name`, ADD INDEX `idx_title` (`title` ASC) VISIBLE");  */
//$con->query("OPTIMIZE TABLE db_patent_application_bibliographic.application_grant"); 

/* $con->query("TRUNCATE TABLE db_uspto.inventors");
$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') = 2023 GROUP BY assignor_and_assignee_id");

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2022 AND 2023 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2021 AND 2022 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2020 AND 2021 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2019 AND 2020 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2018 AND 2019 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2017 AND 2018 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2016 AND 2017 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2015 AND 2016 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2014 AND 2015 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2013 AND 2014 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2012 AND 2013 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2011 AND 2012 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2010 AND 2011 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2009 AND 2010 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2008 AND 2009 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2007 AND 2008 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2006 AND 2007 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2005 AND 2006 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2004 AND 2005 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2003 AND 2004 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2002 AND 2003 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2001 AND 2002 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 2000 AND 2001 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 1999 AND 2000 GROUP BY assignor_and_assignee_id");  

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE ((rac.convey_ty = 'employee' AND employer_assign = 1) OR ( rac.employer_assign = 1 and rac.convey_ty = 'assignment' )) AND date_format(exec_dt, '%Y') BETWEEN 1998 AND 1999 GROUP BY assignor_and_assignee_id"); 
*/
/* 
$variables = $argv;
$pages = $argv[1];
$total = $argv[2]
$constant = ceil($total / $pages);
$start = 0;


$con->query("TRUNCATE TABLE db_uspto.inventors");
for($i=0; $i <= $pages + 2; $i++) { 
	echo $start.'<br/>\n';
	$queryResult = $con->query("SELECT ass.rf_id  FROM db_uspto.assignment as ass
	INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = ass.rf_id
	WHERE rac.convey_ty = 'employee' OR (rac.employer_assign = 1 and rac.convey_ty = 'assignment' )
	GROUP BY ass.rf_id LIMIT ".$start.", ".$constant);

	if($queryResult && $queryResult->num_rows > 0) {
		$allRFIDs = array();
		while($row = $queryResult->fetch_object()) {
			array_push($allRFIDs, $row->rf_id);
		}
		if(count($allRFIDs) > 0) {
			$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor where rf_id IN (".implode(',', $allRFIDs).") AND date_format(exec_dt, '%Y') > 1998"); 
		}
	}
	$start = $start + $constant;
}

die;  */

//$con->query('CREATE TABLE db_patent_application_bibliographic.aaa ( `assignor_and_assignee_id` bigint NOT NULL AUTO_INCREMENT, `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL, `instances` int NOT NULL, `representative_id` bigint DEFAULT NULL, PRIMARY KEY (`assignor_and_assignee_id`), UNIQUE KEY `unq` (`name`), KEY `representative_id` (`representative_id`), KEY `idx_name` (`name`), FULLTEXT KEY `full_text_name` (`name`) ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
  

  //$con->query("INSERT IGNORE INTO db_patent_application_bibliographic.aaa (assignor_and_assignee_id, name, instances, representative_id) SELECT assignor_and_assignee_id, name, instances, representative_id FROM db_patent_application_bibliographic.assignor_and_assignee");

 /*  $con->query("ALTER TABLE `db_patent_application_bibliographic`.`assignor_and_assignee` RENAME TO  `db_patent_application_bibliographic`.`a1`");
  $con->query("ALTER TABLE `db_patent_application_bibliographic`.`aaa` RENAME TO  `db_patent_application_bibliographic`.`assignor_and_assignee`");
 */
//$con->query('ALTER TABLE `db_patent_application_bibliographic`.`assignor_and_assignee` ADD INDEX `idx_name` (`name` ASC) VISIBLE, ADD FULLTEXT INDEX `full_text_name` (`name`) VISIBLE; ');
//$con->query('REPAIR TABLE db_patent_application_bibliographic.assignor_and_assignee') ;
/* $con->query('INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor`') or die("ERROR");  */


$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor`');

/*
$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 0, 2015685');

$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 2015685, 2015685');

$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 4031370, 2015685');

$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 6047055, 2015685');

$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 8062740, 2015685');

$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 10078425, 2015685');
$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 12094110, 2015685');

$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 14109795, 2015685');
$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 16125480, 2015685');
$con->query('
INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 18141165, 2015685');
$con->query('INSERT IGNORE INTO `db_patent_grant_bibliographic`.`inventor_new` 
(`appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path`) 
SELECT `appno_doc_num`, `name`, `given_name`, `middle_name`, `family_name`, `file_name`, `full_path` 
FROM `db_patent_grant_bibliographic`.`inventor` LIMIT 20156850, 20156855');*/



function removeDoubleSpace($string) {
	return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return trim(ucwords(strtolower($string)));
}
 
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

function update($postValues, $where, $tableName, $con){
	echo "=================TABLE==========================<br/>"; 
	print_r($tableName);
	$stringName ="";
	foreach($postValues as $key=>$value){
		$stringName .=$key."='".mysqli_real_escape_string($con,$value)."',";
	}
	$stringName = substr($stringName,0,-1);
	
	$condition = "";
	foreach($where as $key=>$value){
		$condition .=$key."='".mysqli_real_escape_string($con,$value)."' AND ";
	}
	$condition = substr($condition, 0, -4);
	
	$sql = "UPDATE ".$tableName." SET ".$stringName." WHERE ".$condition;	
	echo $sql."<br/>";
	$result = $con->query($sql);
	if($result){
		echo "AFFECTED ROWS : ".$con->affected_rows."<br/>";
	} else {
		echo "AFFECTED ROWS : 0<br/>";
	}
}


$query = 'SELECT assignee_id, original_name FROM db_patent_application_bibliographic.assignee WHERE (name IS NULL OR name = "") AND original_name <> ""  GROUP BY assignee_id' ;	
$result = $con->query($query);
if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$orName = $row->original_name;						
		$orName = removeDoubleSpace( $orName );
		$orName = strReplace( $orName );
		$findString = 0;
		$stringC = remove_if_trailing($orName, "corporation");
		if($stringC[1] === 0) {
			$stringC = remove_if_trailing($orName, "incorporated");
			if($stringC[1] === 0) {
				$stringC = remove_if_trailing($orName, "limited");
				if($stringC[1] === 0) {
					$stringC = remove_if_trailing($orName, "company");
					if($stringC[1] === 1) {
						$findString = $stringC[1];
						$orName = removeDoubleSpace($stringC[0]);
					}
				} else {
					$findString = $stringC[1];
					$orName = removeDoubleSpace($stringC[0]);
				}	
			} else {
				$findString = $stringC[1];
				$orName = removeDoubleSpace($stringC[0]);
			}
		} else {
			$findString = $stringC[1];
			$orName = removeDoubleSpace($stringC[0]);
		}
		update(array('name'=>$orName), array('assignee_id'=>$row->assignee_id), 'db_patent_application_bibliographic.assignee', $con); 
	}
}

$query = 'SELECT assignee_id, original_name FROM db_patent_grant_bibliographic.assignee WHERE (name IS NULL OR name = "") AND original_name <> ""  GROUP BY assignee_id' ;	
$result = $con->query($query);
if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$orName = $row->original_name;						
		$orName = removeDoubleSpace( $orName );
		$orName = strReplace( $orName );
		$findString = 0;
		$stringC = remove_if_trailing($orName, "corporation");
		if($stringC[1] === 0) {
			$stringC = remove_if_trailing($orName, "incorporated");
			if($stringC[1] === 0) {
				$stringC = remove_if_trailing($orName, "limited");
				if($stringC[1] === 0) {
					$stringC = remove_if_trailing($orName, "company");
					if($stringC[1] === 1) {
						$findString = $stringC[1];
						$orName = removeDoubleSpace($stringC[0]);
					}
				} else {
					$findString = $stringC[1];
					$orName = removeDoubleSpace($stringC[0]);
				}	
			} else {
				$findString = $stringC[1];
				$orName = removeDoubleSpace($stringC[0]);
			}
		} else {
			$findString = $stringC[1];
			$orName = removeDoubleSpace($stringC[0]);
		}
		update(array('name'=>$orName), array('assignee_id'=>$row->assignee_id), 'db_patent_grant_bibliographic.assignee', $con); 
	}
}

$query = 'SELECT applicant_id, original_name FROM db_patent_application_bibliographic.applicant WHERE (name IS NULL OR name = "") AND original_name <> ""  GROUP BY applicant_id' ;	
$result = $con->query($query);
if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$orName = $row->original_name;						
		$orName = removeDoubleSpace( $orName );
		$orName = strReplace( $orName );
		$findString = 0;
		$stringC = remove_if_trailing($orName, "corporation");
		if($stringC[1] === 0) {
			$stringC = remove_if_trailing($orName, "incorporated");
			if($stringC[1] === 0) {
				$stringC = remove_if_trailing($orName, "limited");
				if($stringC[1] === 0) {
					$stringC = remove_if_trailing($orName, "company");
					if($stringC[1] === 1) {
						$findString = $stringC[1];
						$orName = removeDoubleSpace($stringC[0]);
					}
				} else {
					$findString = $stringC[1];
					$orName = removeDoubleSpace($stringC[0]);
				}	
			} else {
				$findString = $stringC[1];
				$orName = removeDoubleSpace($stringC[0]);
			}
		} else {
			$findString = $stringC[1];
			$orName = removeDoubleSpace($stringC[0]);
		}
		update(array('name'=>$orName), array('applicant_id'=>$row->applicant_id), 'db_patent_application_bibliographic.applicant', $con); 
	}
}

$query = 'SELECT applicant_id, original_name FROM db_patent_grant_bibliographic.applicant WHERE (name IS NULL OR name = "") AND original_name <> ""  GROUP BY applicant_id' ;	
$result = $con->query($query);
if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$orName = $row->original_name;						
		$orName = removeDoubleSpace( $orName );
		$orName = strReplace( $orName );
		$findString = 0;
		$stringC = remove_if_trailing($orName, "corporation");
		if($stringC[1] === 0) {
			$stringC = remove_if_trailing($orName, "incorporated");
			if($stringC[1] === 0) {
				$stringC = remove_if_trailing($orName, "limited");
				if($stringC[1] === 0) {
					$stringC = remove_if_trailing($orName, "company");
					if($stringC[1] === 1) {
						$findString = $stringC[1];
						$orName = removeDoubleSpace($stringC[0]);
					}
				} else {
					$findString = $stringC[1];
					$orName = removeDoubleSpace($stringC[0]);
				}	
			} else {
				$findString = $stringC[1];
				$orName = removeDoubleSpace($stringC[0]);
			}
		} else {
			$findString = $stringC[1];
			$orName = removeDoubleSpace($stringC[0]);
		}
		update(array('name'=>$orName), array('applicant_id'=>$row->applicant_id), 'db_patent_grant_bibliographic.applicant', $con); 
	}
}
