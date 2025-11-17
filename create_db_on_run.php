<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
$overAllArray = array();
$db = array();
ini_set('max_execution_time', 0);
$db['default']['hostname'] = '167.172.195.92';
$db['default']['username'] = 'patent_user';
$db['default']['password'] = 'P@t3nt@u5r';
$db['default']['database'] = 'db_patentrack';
$db['default']['username1'] = 'patent_user';
$db['default']['password1'] = 'P@t3nt@u5r';
$db['default']['database1'] = 'big_data_uspto';
ignore_user_abort(true);
ini_set('xdebug.max_nesting_level', 1000);
ini_set("memory_limit","256M");

function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

$con = mysqli_connect($db['default']['hostname'],$db['default']['username'],$db['default']['password'],$db['default']['database']);

if((int)mysqli_errno($con)==0){
	mysqli_set_charset($con, 'utf8'); // <- add this too
	mysqli_query($con, "SET NAMES 'utf8';");
	mysqli_query($con, "SET CHARACTER SET 'utf8';");
	mysqli_query($con, "SET COLLATION_CONNECTION = 'utf8_unicode_ci';");
	mysqli_query($con, "SET SQL_MODE='ALLOW_INVALID_DATES';");
	
	$con1 = mysqli_connect($db['default']['hostname'],$db['default']['username1'],$db['default']['password1'],$db['default']['database1']);
	
	mysqli_set_charset($con1, 'utf8'); // <- add this too
	mysqli_query($con1, "SET NAMES 'utf8';");
	mysqli_query($con1, "SET CHARACTER SET 'utf8';");
	mysqli_query($con1, "SET COLLATION_CONNECTION = 'utf8_unicode_ci';");
	
	
	$queryJob = "SELECT * FROM jobs WHERE status = 0 LIMIT 1";
	$resultJob = $con->query($queryJob);
	if($resultJob && $resultJob->num_rows > 0){
		$row = $resultJob->fetch_object();
		if($row->type == 3){
			/*Get organisation name*/
			$queryOrg = "SELECT name, org_db, org_host, org_pass, org_key, org_usr FROM organisations WHERE id = ".$row->project_id;
			$resultOrg = $con->query($queryOrg);
			if($resultOrg && $resultOrg->num_rows > 0){
				$orgRow = $resultOrg->fetch_object();
				$dbName = $orgRow->org_db;
				$host = $orgRow->org_host;
				$encryptedPassword = $orgRow->org_pass;
				$key = $orgRow->org_key;
				$username = $orgRow->org_usr;
				$decryptedPassword = openssl_decrypt($encryptedPassword,"AES-128-ECB",$key);
				$newConnection = "";
				if($orgRow->org_db == "" || $orgRow->org_db == null){
					$dbName = generateRandomString(10);
					$username = generateRandomString(10);
					$password = strtoupper(generateRandomString(1)).'$'.generateRandomString(14);
					$host = "localhost";
					$key = generateRandomString(32);
					$encryptedPassword = openssl_encrypt($password,"AES-128-ECB",$key);
					$decryptedPassword = openssl_decrypt($encryptedPassword,"AES-128-ECB",$key);
					
					echo "<pre>";
					print_r(array($host, $username, $password, $dbName, $key, $encryptedPassword, $decryptedPassword));
					echo "</pre>";					
					
					$rootConnection = mysqli_connect($host,"root","iLvr@312312");
					//$rootConnection = $con;
					if((int)mysqli_errno($rootConnection)==0){
						$queryCreateDatabase = "CREATE database ".$dbName;
						$resultDB = $rootConnection->query($queryCreateDatabase);
						if($resultDB){
							echo "DB done<br/>";
							$queryCreateUser = "CREATE USER '".$username."'@'".$host."' IDENTIFIED BY '".$decryptedPassword."'";
							
							echo $queryCreateUser."<br/>";
							
							$resultCreateUser = $rootConnection->query($queryCreateUser);
							
							if($resultCreateUser){
								echo "USER done<br/>";
								$queryGrantAll = "GRANT ALL ON ".$dbName.".* TO '".$username."'@'".$host."'";
								
								echo $queryGrantAll."<br/>";
								
								$resultGrant = $rootConnection->query($queryGrantAll);
								
								if($resultGrant){
									echo "GRANT done<br/>";
									echo "DB and user created";
									
									$newConnection = mysqli_connect($host, $username, $decryptedPassword, $dbName);
									
									if((int)mysqli_errno($newConnection)==0){
										
										$queryOrganisationUpdate = "UPDATE organisations SET org_key='".$key."',org_pass='".$encryptedPassword."',org_host='".$host."',org_db='".$dbName."',org_usr='".$username."' WHERE id = ".$orgRow->id;
										
										$con->query($queryOrganisationUpdate);
										
										
									} else {
										echo "ERROR4";
										echo mysqli_error($rootConnection)."<br/>";
									}
								} else {
									echo "ERROR3";
									echo mysqli_error($rootConnection)."<br/>";
								}
							} else {
								echo mysqli_error($rootConnection)."<br/>";
								echo "ERROR2";
							}
						} else {
							echo "ERROR1";
						}
					}
				} else {
					$newConnection = mysqli_connect($host, $username, $decryptedPassword, $dbName);
					if((int)mysqli_errno($newConnection)==0){
						echo "DB is already created";
					}
				}
				
				if($newConnection){
					try{
						insertAllTables($newConnection);
						echo "INSERT ALL TABLE";
						die;
						addCSVData($newConnection, $con1);
						echo "CSV Data INSERTED";
						die;
						
						getDataFromAllCSVTable($newConnection, $con, $con1, $orgRow->name, $row->project_id);
					}catch(Exception $e){
						
					}
				}
			}
		}
	}
}

function insertAllTables($orgCon){
	$orgCon->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
	
	$orgCon->query("CREATE TABLE IF NOT EXISTS `assignees` (
	  `id` bigint(20) NOT NULL AUTO_INCREMENT,
	  `rf_id` bigint(20) NOT NULL DEFAULT '0',
	  `ee_name` varchar(500) NOT NULL DEFAULT '',
	  `ee_address_1` varchar(300) NOT NULL DEFAULT '',
	  `ee_address_2` varchar(300) NOT NULL DEFAULT '',
	  `ee_city` varchar(100) NOT NULL DEFAULT '',
	  `ee_state` varchar(100) NOT NULL DEFAULT '',
	  `ee_postcode` varchar(20) NOT NULL DEFAULT '',
	  `normalize_name` varchar(500) NOT NULL DEFAULT '',
	  `ee_country` varchar(100) NOT NULL DEFAULT '',
	  PRIMARY KEY (`id`),
	  KEY `rf_id` (`rf_id`),
	  FULLTEXT KEY `ee_name` (`ee_name`)
	) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
	
	$orgCon->query("CREATE TABLE IF NOT EXISTS `assignments` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `rf_id` bigint(20) NOT NULL DEFAULT '0',
		  `file_id` bigint(20) NOT NULL DEFAULT '0',
		  `cname` varchar(500) NOT NULL DEFAULT '',
		  `caddress_1` varchar(300) NOT NULL DEFAULT '',
		  `caddress_2` varchar(300) NOT NULL DEFAULT '',
		  `caddress_3` varchar(300) NOT NULL DEFAULT '',
		  `caddress_4` varchar(300) NOT NULL DEFAULT '',
		  `reel_no` varchar(50) NOT NULL DEFAULT '0',
		  `frame_no` varchar(50) NOT NULL DEFAULT '0',
		  `convey_text` varchar(500) NOT NULL DEFAULT '',
		  `record_dt` varchar(50) NOT NULL DEFAULT '',
		  `last_update_dt` varchar(50) NOT NULL DEFAULT '',
		  `page_count` int(11) NOT NULL DEFAULT '0',
		  `purge_in` varchar(50) NOT NULL DEFAULT '',
		  PRIMARY KEY (`id`),
		  KEY `rf_id` (`rf_id`),
		  KEY `reel_no_frame_no` (`reel_no`,`frame_no`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
		
	$orgCon->query("CREATE TABLE IF NOT EXISTS `assignment_conveyances` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `rf_id` bigint(20) NOT NULL DEFAULT '0',
		  `convey_ty` varchar(100) NOT NULL DEFAULT '0',
		  `employer_assign` int(11) NOT NULL DEFAULT '0',
		  `normalize_convey` varchar(100) NOT NULL DEFAULT '',
		  PRIMARY KEY (`id`),
		  KEY `rf_id` (`rf_id`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
		
	$orgCon->query("CREATE TABLE IF NOT EXISTS `assignors` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `rf_id` bigint(20) NOT NULL DEFAULT '0',
		  `or_name` varchar(500) NOT NULL DEFAULT '',
		  `normalize_name` varchar(500) NOT NULL DEFAULT '',
		  `exec_dt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  `ack_dt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		  PRIMARY KEY (`id`),
		  KEY `rf_id` (`rf_id`),
		  KEY `exec_dt` (`exec_dt`),
		  KEY `normalize_name` (`normalize_name`),
		  KEY `or_name` (`or_name`),
		  FULLTEXT KEY `or_name1` (`or_name`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
		
	$orgCon->query("CREATE TABLE IF NOT EXISTS `documentids` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `rf_id` bigint(20) NOT NULL DEFAULT '0',
		  `title` varchar(500) NOT NULL DEFAULT '',
		  `lang` varchar(20) NOT NULL DEFAULT '',
		  `appno_doc_num` varchar(50) NOT NULL DEFAULT '',
		  `appno_date` varchar(20) NOT NULL DEFAULT '',
		  `appno_country` varchar(20) NOT NULL DEFAULT '',
		  `pgpub_doc_num` varchar(50) NOT NULL DEFAULT '',
		  `pgpub_date` varchar(20) NOT NULL DEFAULT '',
		  `pgpub_country` varchar(20) NOT NULL DEFAULT '',
		  `grant_doc_num` varchar(50) NOT NULL DEFAULT '',
		  `grant_date` varchar(20) NOT NULL DEFAULT '',
		  `grant_country` varchar(20) NOT NULL DEFAULT '',
		  PRIMARY KEY (`id`),
		  KEY `rf_id` (`rf_id`),
		  KEY `grant_doc_num` (`grant_doc_num`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC");
		
	$orgCon->query("CREATE TABLE IF NOT EXISTS `folders` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `name` varchar(255) DEFAULT NULL,
		  `raw_name` varchar(255) DEFAULT NULL,
		  `normalize_name` varchar(255) DEFAULT NULL,
		  `logo` varchar(255) DEFAULT 'https://patentrack.com/resources/shared/images/test_logo.png',
		  `organisation_id` bigint(20) DEFAULT '0',
		  `user_id` bigint(20) DEFAULT '0' COMMENT 'Created by',
		  `created_at` datetime DEFAULT NULL,
		  `updated_at` datetime DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  KEY `organisation_id` (`organisation_id`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
	
	$orgCon->query("CREATE TABLE IF NOT EXISTS `projects` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `name` varchar(255) DEFAULT NULL,
		  `folder_id` bigint(20) DEFAULT NULL,
		  `status` tinyint(4) DEFAULT '0',
		  `user_id` bigint(20) DEFAULT '0' COMMENT 'created by',
		  `total_patent` bigint(20) DEFAULT '0',
		  `finished_patent` bigint(20) DEFAULT '0',
		  `ordered_patent` bigint(20) DEFAULT '0',
		  `created_at` datetime DEFAULT NULL,
		  `updated_at` datetime DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  KEY `folder_id` (`folder_id`),
		  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
		
	$orgCon->query("CREATE TABLE IF NOT EXISTS `patents` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `rf_id` bigint(20) NOT NULL DEFAULT '0',
		  `number` varchar(50) DEFAULT NULL,
		  `application` varchar(50) DEFAULT '',
		  `title` varchar(300) DEFAULT '',
		  `patent_date` datetime DEFAULT '0000-00-00 00:00:00',
		  `application_date` datetime DEFAULT '0000-00-00 00:00:00',
		  `project_id` bigint(20) DEFAULT NULL,
		  `status` tinyint(4) DEFAULT '0',
		  `comment` text,
		  `created_at` datetime DEFAULT NULL,
		  `updated_at` datetime DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  KEY `project_id` (`project_id`),
		  CONSTRAINT `patents_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
	
	$orgCon->query("CREATE TABLE IF NOT EXISTS `comments` (
		  `id` bigint(20) NOT NULL AUTO_INCREMENT,
		  `comment` text,
		  `organisation_id` bigint(20) NOT NULL,
		  `user_id` bigint(20) NOT NULL,
		  `type` tinyint(4) NOT NULL DEFAULT '0',
		  `folder_id` bigint(20) NOT NULL DEFAULT '0',
		  `project_id` bigint(20) NOT NULL DEFAULT '0',
		  `patent_number` varchar(20) NOT NULL DEFAULT '',
		  `created_at` datetime NOT NULL,
		  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		  PRIMARY KEY (`id`),
		  KEY `organisation_id_folder_id_project_id_patent_number` (`organisation_id`,`folder_id`,`project_id`,`patent_number`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
		
	$orgCon->query("CREATE TABLE IF NOT EXISTS `share_links` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `code` varchar(50) NOT NULL DEFAULT '',
		  `organisation_id` int(11) NOT NULL DEFAULT '0',
		  `user_id` int(11) NOT NULL DEFAULT '0',
		  `type` tinyint(4) NOT NULL DEFAULT '0',
		  `folder_ids` text,
		  `project_ids` text,
		  `patent_numbers` text,
		  `created_at` datetime NOT NULL,
		  `updated_at` datetime NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1");
	
	$orgCon->query("CREATE TABLE IF NOT EXISTS `assets_channel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset` varchar(50) DEFAULT NULL,
  `channel_id` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1");
}

function getDataFromAllCSVTable($orgCon, $con, $con1, $customerName, $orgID) {
	
	$queryAssignee = 'SELECT rf_id FROM assignees_copy as a WHERE normalize_name = "'.$customerName.'"';
	
	$resultAssignee = $con1->query($queryAssignee);
	
	$rfIDS = array();
	
	$findCustomers = array();
	
	if( $resultAssignee && $resultAssignee->num_rows > 0 ){
		$rfIDS = array();
		while($rowRFIDs = $resultAssignee->fetch_object()){
			array_push($rfIDS, $rowRFIDs->rf_id);
		}
		
		$queryCustomerAssignor = "SELECT rf_id, or_name, normalize_name FROM assignors_copy WHERE rf_id IN(".implode(',', $rfIDS).")";
		
		$resultCustomerAssignor = $con1->query($queryCustomerAssignor);
		
		if( $resultCustomerAssignor && $resultCustomerAssignor->num_rows > 0 ) {
			
			while($rowCustomer = $resultCustomerAssignor->fetch_object()) {
				
				array_push($findCustomers, array('rf_id'=>$rowCustomer->rf_id,'name'=>$rowCustomer->or_name,'normalize_name'=>$rowCustomer->normalize_name));
			}
		}
	}
	
	$assignorName = 'SELECT rf_id FROM assignors_copy as a WHERE normalize_name = "'.$customerName.'"';
	
	$assignorName = $con1->query($assignorName);
	
	$rfIDS = array();
	
	if( $assignorName && $assignorName->num_rows > 0 ){
		$rfIDS = array();
		while($rowRFIDs = $assignorName->fetch_object()){
			array_push($rfIDS, $rowRFIDs->rf_id);
		}
		
		$queryCustomerAssignee = "SELECT rf_id, ee_name, normalize_name FROM assignees_copy WHERE rf_id IN(".implode(',', $rfIDS).")";
		
		$resultCustomerAssignee = $con1->query($queryCustomerAssignee);
		
		if( $resultCustomerAssignee && $resultCustomerAssignee->num_rows > 0 ) {
			
			while($rowCustomer = $resultCustomerAssignee->fetch_object()) {
				
				array_push($findCustomers, array('rf_id'=>$rowCustomer->rf_id,'name'=>$rowCustomer->ee_name,'normalize_name'=>$rowCustomer->normalize_name));
			}
		}		
	}
	
	if(count($findCustomers) > 0){
		foreach($findCustomer as $customer){
			$name = $customer['normalize_name'];
			if($name == "" || $name == null){
				$name = $customer['name'];
			}
			$queryFolder = "SELECT id FROM folders WHERE name = '".$orgCon->real_escape_string($name)."'";
			$resultFolder = $orgCon->query( $queryFolder );
			
			$folderID = 0;
			
			if($resultFolder && $resultFolder->num_rows == 0){
				$folderID = add("folders", array('name'=>$name, 'normalize_name'=> $customer['normalize_name'], 'raw_name'=> $customer['name']),$orgCon);
			} else {
				$folder  = $resultFolder->fetch_object();
				$folderID = $folder->id;
			}
			
			if($folderID > 0) {
				$queryAssignment = "SELECT reel_no, frame_no FROM "
			}
		}
	}
	
}

function addCSVData($con, $con1, $customerName){
	
	$queryFind = "SELECT distinct(rf_id) FROM assignees_copy WHERE normalize_name = '".$con->real_escape_string($customerName)."'";
	
	$resultFind = $con1->query($queryFind);
	
	$listOfRef = array();
	
	if($resultFind && $resultFind->num_rows > 0){
		while($refs = $resultFind->fetch_object()){
			if(!in_array((int)$refs->rf_id, $listOfRef)) {
				array_push($listOfRef , (int)$refs->rf_id);
			}
		}
	}
	
	$queryFind = "SELECT distinct(rf_id) FROM assignors_copy WHERE  normalize_name = '".$con->real_escape_string($customerName)."'";
	
	$resultFind = $con1->query($queryFind);
	
	if($resultFind && $resultFind->num_rows > 0){
		while($refs = $resultFind->fetch_object()){
			if(!in_array((int)$refs->rf_id, $listOfRef)) {
				array_push($listOfRef , (int)$refs->rf_id);
			}
		}
	}
	
	/*SELECT distinct(a.rf_id), a.reel_no, a.frame_no FROM assignments_copy as a 
	LEFT JOIN (SELECT distinct(rf_id) FROM assignors_copy WHERE or_name LIKE '%BANK LEUMI%') as assignor ON a.rf_id = assignor.rf_id
	LEFT JOIN (SELECT distinct(rf_id) FROM assignees_copy WHERE ee_name LIKE '%BANK LEUMI%') as `assignees` ON a.rf_id = `assignees`.rf_id;*/
	
	if(count($listOfRef) > 0){
		$queryAssignment = "SELECT * FROM assignments_copy WHERE rf_id IN (".implode(',', $listOfRef).")";
		
		$resultReelFrames = $con1->query($queryAssignment);
		
		if($resultReelFrames && $resultReelFrames->num_rows > 0){
			echo "COUNT:".$resultReelFrames->num_rows."<br/>";
			$newRefIDList = array();
			while($rowReel = $resultReelFrames->fetch_object()){
				
				$queryCheck = "SELECT count(id) as countAll FROM assignments WHERE rf_id = ".$rowReel->rf_id;
				
				$resultCheck = $con->query($queryCheck);
				
				$addRow = true;
				if($resultCheck && $resultCheck->num_rows > 0){
					$checkRow = $resultCheck->fetch_object();
					if($checkRow->countAll > 0){
						$addRow = false;
					}
				}
				if($addRow === true){
					add("assignments",(array)$rowReel, $con);
				}
				
				$queryDocument = "SELECT * FROM documentids_copy where rf_id = ".$rowReel->rf_id;
				
				$resultDocument = $con1->query($queryDocument);
				
				if($resultDocument && $resultDocument->num_rows > 0){
					while($rowDoc = $resultDocument->fetch_object()){
						
						$queryCheck = "SELECT count(id) as countAll FROM documentids WHERE rf_id = ".$rowDoc->rf_id;
				
						$resultCheck = $con->query($queryCheck);
						
						$addRow = true;
						if($resultCheck && $resultCheck->num_rows > 0){
							$checkRow = $resultCheck->fetch_object();
							if($checkRow->countAll > 0){
								$addRow = false;
							}
						}
						if($addRow === true){
							add("documentids",(array)$rowDoc, $con);
						}
						
						if($rowDoc->grant_doc_num != ""){
							$queryPatent = "SELECT * FROM documentids_copy where grant_doc_num = '".$rowDoc->grant_doc_num."'";
							
							$resultPatent = $con1->query($queryPatent);
							
							while($rowPatent = $resultPatent->fetch_object()){
								
								
								$queryCheck = "SELECT count(id) as countAll FROM documentids WHERE rf_id = ".$rowPatent->rf_id;
								$resultCheck = $con->query($queryCheck);
					
								$addRow = true;
								if($resultCheck && $resultCheck->num_rows > 0){
									$checkRow = $resultCheck->fetch_object();
									if($checkRow->countAll > 0){
										$addRow = false;
									}
								}
								if($addRow === true){
									add("documentids",(array)$rowPatent, $con);
								}
								
								
								$queryAssignment = "SELECT * FROM assignments_copy WHERE rf_id =".$rowPatent->rf_id;
								
								$resultAssignment = $con1->query($queryAssignment);
								
								if($resultAssignment && $resultAssignment->num_rows > 0){
									$assignmentData = $resultAssignment->fetch_object();
									
									$queryCheck = "SELECT count(id) as countAll FROM assignments WHERE rf_id = ".$assignmentData->rf_id;
									$resultCheck = $con->query($queryCheck);
						
									$addRow = true;
									if($resultCheck && $resultCheck->num_rows > 0){
										$checkRow = $resultCheck->fetch_object();
										if($checkRow->countAll > 0){
											$addRow = false;
										}
									}
									if($addRow === true){
										add("assignments",(array)$assignmentData, $con);
									}
									
									
									$queryAssignmentConveyance = "SELECT * FROM assignment_conveyances_copy WHERE rf_id =".$assignmentData->rf_id;
								
									$resultAssignmentConveyance = $con1->query($queryAssignmentConveyance);
									
									if($resultAssignmentConveyance && $resultAssignmentConveyance->num_rows > 0){
										$rowCoveyance = $resultAssignmentConveyance->fetch_object();
										
										
										$queryCheck = "SELECT count(id) as countAll FROM assignment_conveyances WHERE rf_id = ".$rowCoveyance->rf_id;
										$resultCheck = $con->query($queryCheck);
							
										$addRow = true;
										if($resultCheck && $resultCheck->num_rows > 0){
											$checkRow = $resultCheck->fetch_object();
											if($checkRow->countAll > 0){
												$addRow = false;
											}
										}
										if($addRow === true){
											add("assignment_conveyances",(array)$rowCoveyance, $con);
										}
									}
									
									$queryAssignors = "SELECT * FROM assignors_copy WHERE rf_id = ".$assignmentData->rf_id;
									
									$resultAssignors = $con1->query($queryAssignors);
									
									$assignorsList = array();
									if($resultAssignors && $resultAssignors->num_rows > 0){
										while($rowAssignor = $resultAssignors->fetch_object()){
											array_push($assignorsList, $rowAssignor);
											
											$queryCheck = "SELECT count(id) as countAll FROM assignors WHERE rf_id = ".$rowAssignor->rf_id;
											$resultCheck = $con->query($queryCheck);
								
											$addRow = true;
											if($resultCheck && $resultCheck->num_rows > 0){
												$checkRow = $resultCheck->fetch_object();
												if($checkRow->countAll > 0){
													$addRow = false;
												}
											}
											if($addRow === true){
												add("assignors",(array)$rowAssignor, $con);
											}
										}
									}
									
									$queryAssignees = "SELECT * FROM assignees_copy WHERE rf_id = ".$assignmentData->rf_id;
									
									$resultAssignees = $con1->query($queryAssignees);
									
									$assigneesList = array();
									if($resultAssignees && $resultAssignees->num_rows > 0){
										while($rowAssignee = $resultAssignees->fetch_object()){
											array_push($assigneesList, $rowAssignee);
											
											$queryCheck = "SELECT count(id) as countAll FROM assignees WHERE rf_id = ".$rowAssignee->rf_id;
											$resultCheck = $con->query($queryCheck);
								
											$addRow = true;
											if($resultCheck && $resultCheck->num_rows > 0){
												$checkRow = $resultCheck->fetch_object();
												if($checkRow->countAll > 0){
													$addRow = false;
												}
											}
											if($addRow === true){
												add("assignees",(array)$rowAssignee, $con);
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	
}

function add($tableName,$postValues,$con){
	$stringName ="";
	$stringValue ="";
	foreach($postValues as $key=>$value){
		
		$stringName .= $key.",";
		$stringValue .="'".mysqli_real_escape_string($con,stripslashes($value))."'".",";
	}
	$stringName = substr($stringName,0,-1);
	$stringValue =substr($stringValue,0,-1);
	$sql = "INSERT INTO ".$tableName."(".$stringName.") VALUES (".$stringValue.")";		
	echo $sql ."<br/>";
	$result = $con->query($sql);
	if($result){
		return mysqli_insert_id($con);
	} else {
		return 0;
	}
}
function update($tableName,$postValues,$where,$con){
	$stringName ="";
	foreach($postValues as $key=>$value){
		$stringName .=$key."='".mysqli_real_escape_string($con,$value)."',";
	}
	$stringName = substr($stringName,0,-1);
	$sql = "UPDATE ".$tableName." SET ".$stringName." WHERE id= ".$where;	
	echo $sql."<br/>";
	$result = $con->query($sql);
	if($result){
		return $where;
	} else {
		return 0;
	}
}
