<?php 

ini_set('max_execution_time', '0');

$dbBusiness = getenv('DB_BUSINESS');
$password = getenv('DB_RT_PWD');
$con = new mysqli("localhost","root",$password ,$dbBusiness);

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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
$con->query("CREATE TABLE IF NOT EXISTS `firm` (
  `firm_id` int(11) NOT NULL AUTO_INCREMENT,
  `firm_name` varchar(250) DEFAULT NULL,
  `firm_logo` varchar(500) DEFAULT NULL,
  `firm_linkedin_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`firm_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
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
  KEY `professional_firm_id_idx` (`firm_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
$con->query("CREATE TABLE IF NOT EXISTS `activity` (
  `activity_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0',
  `professional_id` int(11) NOT NULL DEFAULT '0',
  `subject` varchar(150) NOT NULL DEFAULT '',
  `comment` mediumtext,
  `type` int(11) DEFAULT NULL,
  `subject_type` int(11) DEFAULT '0',
  `document_id` int(11) NOT NULL DEFAULT '0',
  `upload_file` varchar(500) DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
$con->query("CREATE TABLE IF NOT EXISTS `representative` (
  `representative_id` int(11) NOT NULL AUTO_INCREMENT,
  `original_name` varchar(245) DEFAULT NULL,
  `representative_name` varchar(245) DEFAULT NULL,
  `instances` int(11) DEFAULT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `child` TINYINT NULL  DEFAULT '0',
  `type` TINYINT NULL  DEFAULT '0',
  `status` TINYINT NULL  DEFAULT '1',
  PRIMARY KEY (`representative_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
$con->query("CREATE TABLE IF NOT EXISTS `telephone` (
  `telephone_id` int(11) NOT NULL AUTO_INCREMENT,
  `representative_id` bigint(20) NOT NULL DEFAULT '0',
  `telephone_number` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`telephone_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
$con->query("CREATE TABLE IF NOT EXISTS `comment` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `comment` mediumtext NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`comment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
$con->query("CREATE TABLE IF NOT EXISTS `address` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `representative_id` bigint(20) NOT NULL DEFAULT '0',
  `street_address` longtext NOT NULL,
  `suite` text NOT NULL,
  `city` char(50) NOT NULL,
  `state` char(50) NOT NULL,
  `zip_code` varchar(20) NOT NULL,
  `country` varchar(20) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `telephone_2` varchar(20) DEFAULT NULL,
  `telephone_3` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`address_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
$con->query("CREATE TABLE IF NOT EXISTS `collection` (
  `collection_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(150) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1");
$con->query("CREATE TABLE IF NOT EXISTS `collection_company` (
  `collection_company_id` int(11) NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(300) NOT NULL DEFAULT '',
  `instances` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`collection_company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1");
$con->query("CREATE TABLE IF NOT EXISTS `collection_patent` (
  `collection_company_id` int(11) NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) NOT NULL DEFAULT '0',
  `appno_doc_num` varchar(300) NOT NULL DEFAULT '',
  `grant_doc_num` varchar(300) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`collection_company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
$con->query("CREATE TABLE IF NOT EXISTS `lawfirm` (
  `lawfirm_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(300) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`lawfirm_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
$con->query("CREATE TABLE IF NOT EXISTS `lawfirm_address` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `lawfirm_id` int(11) NOT NULL DEFAULT '0',
  `street_address` longtext NOT NULL,
  `suite` text NOT NULL,
  `city` char(50) NOT NULL,
  `state` char(50) NOT NULL,
  `country` char(50) NOT NULL,
  `zip_code` varchar(20) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `telephone_2` varchar(20) DEFAULT NULL,
  `telephone_3` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`address_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
$con->query("CREATE TABLE IF NOT EXISTS `company_lawfirm` (
  `company_lawfirm_id` int(11) NOT NULL AUTO_INCREMENT,
  `representative_id` bigint(20) NOT NULL DEFAULT '0',
  `lawfirm_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`company_lawfirm_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");

$con->query("CREATE TABLE IF NOT EXISTS `assets_channel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset` varchar(50) DEFAULT NULL,
  `channel_id` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;");
			
			if($flag === true) {
				$con->query("TRUNCATE `".$org_db."`.`representative`");
				$con->query("TRUNCATE `type`");
				$con->query("TRUNCATE `subject_type`");
				$con->query("TRUNCATE `user`");
				$con->query("TRUNCATE `".$org_db."`.`user`");
				$con->query("TRUNCATE `".$org_db."`.`firm`");
				$con->query("TRUNCATE `".$org_db."`.`professional`");
				$con->query("INSERT INTO `type` (`type_id`, `name`) VALUES	(1, 'fix'),	(2, 'record'),	(3, 'asset'),	(4, 'transaction'),	(5, 'customer'),	(6, 'company'),	(7, 'error')");
				$con->query("INSERT INTO `subject_type` (`subject_type_id`, `subject_name`) VALUES	(1, 'Fix'),	(2, 'Record'),	(3, 'Asset'),	(4, 'Transaction'),	(5, 'Customer'),	(6, 'Company'),	(7, 'Error')");
			}
		} else {
			printf("Error message: %s\n", $con->error);
		}
	} else {
		echo "No ORG";
	}
}
?>
