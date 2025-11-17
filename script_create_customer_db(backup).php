<?php 

ini_set('max_execution_time', '0');

$con = new mysqli("localhost","root","iLvr@312312","db_business");

$variables = $argv;

if(count($variables) == 2) {
	
	$organisationID = $variables[1];
	$flag = false;
	if((int)$organisationID > 0) {
		$queryOrganisation = "SELECT organisation_id, name, org_key, org_pass, org_host, org_db, org_usr FROM `db_business`.`organisation` WHERE organisation_id = ".(int)$organisationID;
		
		//echo $queryOrganisation."<br/>";
		$resultOrg = $con->query($queryOrganisation);
		//echo $resultOrg->num_rows."<br/>";
		
		if($resultOrg && $resultOrg->num_rows > 0) {	
			$orgData = $resultOrg->fetch_object();	
			/*echo "<pre>";
			print_r($orgData);*/
			if($orgData->org_usr == "" || $orgData->org_usr == null ) {
				//echo "Creating Company database<br/>";
				$org_db = 'db_'.$organisationID.uniqid(); 
				$org_usr = uniqid();		
				$org_pass = strtoupper(chr(rand(65,90))).'!'.uniqid();
				$org_host = '167.172.195.92';
				$org_key = '';		
				/*echo "CREATE DATABASE ".$org_db."<br/>";
				echo "CREATE USER '".$org_usr."'@'%' IDENTIFIED BY '".$org_pass."'<br/>";
				echo "GRANT ALL PRIVILEGES ON ".$org_db.". * TO '".$org_db."'@'localhost'<br/>";
				*/
				$con->query("CREATE DATABASE ".$org_db);
				$con->query("CREATE USER '".$org_usr."'@'%' IDENTIFIED BY '".$org_pass."'");
				$con->query("GRANT ALL PRIVILEGES ON ".$org_db.". * TO '".$org_usr."'@'%'");
				$con->query("FLUSH PRIVILEGES");
				
				//echo "SHOW DATABASES LIKE '".$org_db."'<br/>";
				$queryCheck = $con->query("SHOW DATABASES LIKE '".$org_db."'");
				$flag = true;
				if($queryCheck && $queryCheck->num_rows > 0) {
					$queryUpdate = "UPDATE `db_business`.`organisation` SET org_key='".$org_key."', org_pass='".$org_pass."', org_host='".$org_host."', org_db='".$org_db."', org_usr = '".$org_usr."' WHERE organisation_id = ".$orgData->organisation_id;
					//echo $queryUpdate."<br/>";
					$con->query($queryUpdate);
				}
				
			} else {
				$org_db = $orgData->org_db; 
				$org_usr = $orgData->org_usr;		
				$org_pass = $orgData->org_pass;
				$org_host = $orgData->org_host;
				$org_key = $orgData->org_key;
				//echo "CREATED DATABASE<br/>";	
			} 
			/*$orgConnect = new mysqli($org_host, $org_usr, $org_pass, $org_db);*/
			$con->query("USE ".$org_db);
			$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
			$con->query("SET FOREIGN_KEY_CHECKS = 0");
			$con->query("CREATE TABLE IF NOT EXISTS `subject_type` (
  `subject_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`subject_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
			$con->query("CREATE TABLE IF NOT EXISTS `type` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");
			$con->query("CREATE TABLE IF NOT EXISTS `firm` (
  `firm_id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_name` varchar(250) DEFAULT NULL,
  `firm_logo` varchar(500) DEFAULT NULL,
  `firm_linkedin_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`firm_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");
			$con->query("CREATE TABLE IF NOT EXISTS `document` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(300) NOT NULL DEFAULT '',
  `file` varchar(500) NOT NULL DEFAULT '',
  `type` tinyint(2) NOT NULL DEFAULT '0',
  `description` text,
  `user_id` bigint(20) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint(2) DEFAULT '0',
  PRIMARY KEY (`document_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");
			$con->query("CREATE TABLE IF NOT EXISTS `professional` (
  `professional_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(75) NOT NULL DEFAULT '',
  `last_name` varchar(75) NOT NULL DEFAULT '',
  `email_address` varchar(255) NOT NULL DEFAULT '',
  `telephone` varchar(15) DEFAULT '',
  `telephone1` varchar(15) DEFAULT '',
  `linkedin_url` varchar(500) DEFAULT '',
  `profile_logo` varchar(500) DEFAULT '',
  `firm_id` int(11) DEFAULT NULL,
  `type` tinyint(4) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`professional_id`),
  KEY `professional_firm_id_idx` (`firm_id`),
  CONSTRAINT `professional_firm_id` FOREIGN KEY (`firm_id`) REFERENCES `firm` (`firm_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");
			$con->query("CREATE TABLE IF NOT EXISTS `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `email_address` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT '',
  `job_title` varchar(300) DEFAULT '',
  `telephone` varchar(15) DEFAULT '',
  `telephone1` varchar(15) DEFAULT '',
  `logo` varchar(255) DEFAULT '',
  `status` tinyint(4) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `role_id` int(11) DEFAULT '0',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");
			$con->query("CREATE TABLE IF NOT EXISTS `activity` (
  `activity_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0',
  `professional_id` int(11) NOT NULL DEFAULT '0',
  `subject` varchar(20) NOT NULL DEFAULT '',
  `comment` mediumtext,
  `type` int(11) DEFAULT NULL,
  `subject_type` int(11) DEFAULT '0',
  `document_id` int(11) NOT NULL DEFAULT '0',
  `complete` tinyint(4) DEFAULT '0',
  `share_url` varchar(250) DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`activity_id`),
  KEY `activity_document_id_fkey_idx` (`document_id`),
  KEY `activity_professional_id_fkey_idx` (`professional_id`),
  KEY `activity_type_id_fkey_idx` (`type`),
  KEY `activty_user_id_fkey_idx` (`user_id`),
  KEY `activty_subject_type_fkey_idx` (`subject_type`),
  CONSTRAINT `activity_document_id_fkey` FOREIGN KEY (`document_id`) REFERENCES `document` (`document_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `activity_professional_id_fkey` FOREIGN KEY (`professional_id`) REFERENCES `professional` (`professional_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `activity_type_id_fkey` FOREIGN KEY (`type`) REFERENCES `type` (`type_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `activty_subject_type_fkey` FOREIGN KEY (`subject_type`) REFERENCES `subject_type` (`subject_type_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `activty_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");
			$con->query("CREATE TABLE IF NOT EXISTS `representative` (
  `representative_id` int(11) NOT NULL AUTO_INCREMENT,
  `original_name` varchar(245) DEFAULT NULL,
  `representative_name` varchar(245) DEFAULT NULL,
  `instances` int(11) DEFAULT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`representative_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");
			$con->query("TRUNCATE `type`");
			$con->query("TRUNCATE `subject_type`");
			$con->query("TRUNCATE `user`");
			$con->query("TRUNCATE `".$org_db."`.`user`");
			$con->query("TRUNCATE `".$org_db."`.`firm`");
			$con->query("TRUNCATE `".$org_db."`.`professional`");
			if($flag === true) {
				$con->query("TRUNCATE `".$org_db."`.`representative`");
			}			
			$con->query("INSERT INTO `type` (`type_id`, `name`) VALUES (1, 'FixIt'),(2, 'RecordIt'),(3, 'Comment')");
			$con->query("INSERT INTO `subject_type` (`subject_type_id`, `subject_name`) VALUES	(1, 'Company'),	(2, 'Party'),	(3, 'RfID'),	(4, 'Application'),	(5, 'Patent'),	(6, NULL)");
			
				$queryUsers = "INSERT INTO `".$org_db."`.`user`(user_id, first_name, last_name, username, email_address, linkedin_url, job_title, telephone, telephone1, logo, role_id, created_at, updated_at) SELECT user_id, first_name, last_name, username, email_address,  linkedin_url, job_title, telephone, telephone1, logo, role_id, created_at, updated_at FROM `db_business`.`user` WHERE organisation_id =".(int)$organisationID;
				//echo $queryUsers."<br/>";
				$con->query($queryUsers) or die($con->error);
				//echo "USER_INSERT:".$con->insert_id."<br/>";
				$queryFirm = 'INSERT INTO `'.$org_db.'`.`firm`(firm_name) VALUES ("'.$con->real_escape_string($orgData->name).'")';
				//echo $queryFirm."<br/>";
				$con->query($queryFirm) or die($con->error);
				
				$firmID = $con->insert_id;
				//echo "FIRMID:".$firmID."<br/>";
				if($firmID > 0) {
					$queryProfessionals = "INSERT INTO `".$org_db."`.`professional`(first_name,last_name,email_address,telephone, telephone1,linkedin_url,profile_logo,firm_id,type,created_at, updated_at)  SELECT first_name, last_name,email_address,telephone, telephone1,linkedin_url,logo,".$firmID." as firm_id, 0 as type, created_at, updated_at FROM  `".$org_db."`.`user`";
					//echo $queryProfessionals."<br/>";
					$con->query($queryProfessionals);
				}
				
				$queryFindRepresentative = 'SELECT * FROM `db_application`.`representative` WHERE representative_name = "'.$con->real_escape_string($orgData->name).'" LIMIT 1';
				//echo $queryFindRepresentative."<br/>";
				
				$resultRepresentative = $con->query($queryFindRepresentative);
				
				if($resultRepresentative && $resultRepresentative->num_rows > 0) {
					$rowData = $resultRepresentative->fetch_object();
					$queryInstances = 'SELECT name, instances FROM `db_application`.`assignor_and_assignee` WHERE name = "'.$con->real_escape_string($orgData->name).'"';
					//echo $queryInstances."<br/>";
					$resultInstances = $con->query($queryInstances);
					
					if($resultInstances && $resultInstances->num_rows > 0) {
						$instanceData = $resultInstances->fetch_object();
						
						if($flag === false) {
							$con->query("DELETE FROM `".$org_db."`.`representative` WHERE representative_id = ".$rowData->representative_id."  OR parent_id = ".$rowData->representative_id);
						}
						
						$insertParent = "INSERT IGNORE INTO `".$org_db."`.`representative`(representative_id, original_name, representative_name,instances) SELECT representative_id, representative_name, representative_name, ".$instanceData->instances." FROM `db_application`.`representative` WHERE representative_id = ".$rowData->representative_id;
						//echo $insertParent."<br/>";
						$con->query($insertParent);
						
						if($con->insert_id > 0) {
							$parentRepresentativeID = $con->insert_id;
							$parentInstance = $instanceData->instances;
							$queryFindChild = 'SELECT a.*, r.representative_name as normalize_name FROM `db_application`.`assignor_and_assignee` as a LEFT JOIN `db_application`.`representative` as r ON r.representative_id = a.representative_id WHERE r.representative_id = '.$rowData->representative_id.' AND name <> "'.$con->real_escape_string($orgData->name).'"';
							//echo $queryFindChild."<br/>";
							$resultChild = $con->query($queryFindChild);
							
							
							if($resultChild && $resultChild->num_rows > 0) {
								$queryChild = "INSERT IGNORE INTO `".$org_db."`.`representative`(original_name,representative_name,instances,parent_id) VALUES ";
								
								while($childRow = $resultChild->fetch_object()){
									$normalize_name = $childRow->normalize_name;
									if($normalize_name == "" || $normalize_name == null) {
										$normalize_name = $childRow->name;
									}
									$queryChild .= '("'.$con->real_escape_string($childRow->name).'", "'.$con->real_escape_string($normalize_name).'", "'.$childRow->instances.'", "'.$parentRepresentativeID.'"), ';
									$parentInstance += $childRow->instances;
								}
								
								$queryChild = substr($queryChild, 0 , -2);
								//echo $queryChild."<br/>";
								$con->query($queryChild);
								$con->query("UPDATE `".$org_db."`.`representative` SET instances = ".$parentInstance." WHERE representative_id = ".$rowData->representative_id);
								echo "User database created.";
							}
						}			
					}			
				} 				
			/*}*/
		} else {
			printf("Error message: %s\n", $con->error);
		}
	} else {
		echo "No ORG";
	}
}
?>
