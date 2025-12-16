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
$con = new mysqli($host, $user, $password, $dbApplication);

$variables = $argv;
print_r($variables);
if(count($variables) >= 3) {
    try {

    
        $organisationID = $variables[1];
        $representativeName = $variables[2];
        $runOtherScript = 0;
        if(isset($variables[3]) && $variables[3] == '1') {
            $runOtherScript = 1;
        }
        if((int)$organisationID > 0) {		
            if($representativeName != '') {
                $query = "DELETE FROM company WHERE  organisation_id = ".(int)$organisationID ." AND company_name = '".$con->real_escape_string($representativeName)."'";
            } else {
                $query = "DELETE FROM company WHERE  organisation_id = ".(int)$organisationID ;
            }
            
            $result = $con->query($query);
            $query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID;	
            $result = $con->query($query);
            $accountType = "";
            if($result && $result->num_rows > 0) {  
                while($row = $result->fetch_object()) {
                    $accountType = $row->organisation_type;
                    $orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
                    if($orgConnect) {
                        $queryRepresentative = "SELECT representative_id, original_name, parent_id, child FROM representative WHERE type = 0";
                        if($representativeName != '') {
                            $queryRepresentative .= " AND representative_name = '".$orgConnect->real_escape_string($representativeName)."' OR original_name = '".$orgConnect->real_escape_string($representativeName)."'";
                        }

                        $queryRepresentative .= " ORDER BY status DESC";
                        echo $queryRepresentative."<br/>";
                        $resultRepresentative = $orgConnect->query($queryRepresentative);		
                                
                        if($resultRepresentative && $resultRepresentative->num_rows > 0) {
                            $companiesData = array();
                            
                            while($representative = $resultRepresentative->fetch_object()){
                                array_push($companiesData , array('company_id'=>$representative->representative_id, 'company_name'=>$representative->original_name, 'parent_id'=>$representative->parent_id, 'child'=>$representative->child, 'organisation_id'=>$row->organisation_id));
                            }
                            insertData($dbApplication, 'company', $companiesData, $con);
                        }
                    }
                }
            }
            /*if($representativeName != '') {
                $query = "SELECT * FROM company WHERE company_name = '".$con->real_escape_string($representativeName)."' AND (parent_id = 0 OR child = 1) AND organisation_id = ".(int)$organisationID;
            } else {
                
            }*/
            
            $query = 'SELECT * FROM company WHERE (parent_id = 0 OR child = 1) AND organisation_id = '.(int)$organisationID;
            $result = $con->query($query);
            echo $result->num_rows."<br/>";
            if($result && $result->num_rows > 0) {
                exec('php -f /var/www/html/trash/find_release_security.php '.(int)$organisationID);

                $allCompanies = array();
                while($row = $result->fetch_object()) {
                    print_r($row);
                    echo "ACCOUNT TYPE: ".$accountType;
                    array_push($allCompanies, $row->company_id);
                    $con->query('CALL db_uspto.routine_list1("'.$row->company_name.'", '.$row->company_id.', '.$row->organisation_id.');');
                    
                    $con->query('CALL db_uspto.routine_list2("'.$row->company_name.'", '.$row->company_id.', '.$row->organisation_id.');');
                    $con->query('CALL db_uspto.routine_tableA("'.$row->company_name.'", '.$row->company_id.', '.$row->organisation_id.');');
                    $con->query('CALL db_uspto.routine_tableB("'.$row->company_name.'", '.$row->company_id.', '.$row->organisation_id.');');
                    $con->query('CALL db_uspto.routine_tableC("'.$row->company_name.'", '.$row->company_id.', '.$row->organisation_id.');');
                    $con->query('CALL db_uspto.routine_broken_title('.$row->company_id.', '.$row->organisation_id.');');
                    $con->query('CALL db_uspto.routine_correct_details("'.$row->company_name.'", '.$row->company_id.', '.$row->organisation_id.');');
                    
                    /*Activities, Parties, and Transactions*/
                    
                    $con->query('CALL db_uspto.routine_activities_parties_transactions('.$row->company_id.', '.$row->organisation_id.');');
                    
                    $date1 = getMinusYear(4);
                    $date2 = getMinusYear(3);
                    $date3 = getMinusYear(8);
                    $date4 = getMinusYear(7);
                    $date5 = getMinusYear(12);
                    $date6 = getMinusYear(11);
                    
                    /*Maintainence Assets*/
                    
                    $con->query('CALL db_uspto.routine_maintainence_assets('.$row->company_id.', '.$row->organisation_id.', "'.$date1.'", "'.$date2.'", "'.$date3.'", "'.$date4.'", "'.$date5.'", "'.$date6.'");');
                    
                    $con->query("DELETE FROM company WHERE organisation_id = ".$row->organisation_id." AND company_name = '".$con->real_escape_string($row->company_name)."'");	

                    //exec('php -f /var/www/html/trash/dashboard_with_company.php '.$row->company_id.' '.$row->organisation_id.' "'.$con->real_escape_string($row->company_name).'"');
                    
                    echo "Done with all procedures";

                    if((int)$accountType == 2) {
                        /**Bank Account */
                        $resultParties = $con->query("CALL db_new_application.`routine_parties`('".$row->company_id."', '".$row->organisation_id."', '5,12', 15, 0)");
                        
                        if($resultParties) {
                            echo "IN PROCEDURE";
                            $parties = array();
                            
                            while($rowParties = $resultParties->fetch_object()) {
                                array_push($parties, $rowParties);
                            }
                            $resultParties->free_result();
                            do{} while(mysqli_more_results($con) && mysqli_next_result($con));
                            $TRANSACTION_YEAR = 2000;
                            $layoutID = 15;
                            if(count($parties) > 0) {
                                $con->query('DELETE FROM db_new_application.assets_with_bank WHERE company_id = '.$row->company_id.' AND organisation_id = '.$row->organisation_id);
                                $con->query('DELETE FROM db_new_application.assets_with_bank_expired WHERE company_id = '.$row->company_id.' AND organisation_id = '.$row->organisation_id);
                                $con->query('DELETE FROM db_new_application.lost_assets WHERE company_id = '.$row->company_id.' AND organisation_id = '.$row->organisation_id);
                                foreach($parties as $party) {
                                    /* print_r('CALL db_uspto.`routine_assets_for_bank`("'.$row->company_id.'", "'.$row->organisation_id.'", "'.$con->real_escape_string($party->entityName).'")');*/
                                    $queryALLP = $con->query("SELECT aaa.assignor_and_assignee_id FROM db_uspto.assignor_and_assignee AS aaa
                                    WHERE aaa.name = '".$con->real_escape_string($party->entityName)."' OR aaa.representative_id IN ( SELECT r.representative_id FROM  db_uspto.representative AS r where r.representative_name = '".$con->real_escape_string($party->entityName)."' ) GROUP BY aaa.assignor_and_assignee_id");

                                    if($queryALLP && $queryALLP->num_rows > 0) {
                                        $allPartyIDs = array();
                                        while($partiesID = $queryALLP->fetch_object()){
                                            array_push($allPartyIDs, $partiesID->assignor_and_assignee_id);
                                        }
                                        do{} while(mysqli_more_results($con) && mysqli_next_result($con));
                                        $msc = microtime(true);
                                        echo "PARTY:".$party->entityName."<br/>";
                                        $runProcedure = $con->query('CALL db_uspto.`routine_assets_for_bank`("'.$row->company_id.'", "'.$row->organisation_id.'", "'.implode(',', $allPartyIDs).'")');
                                        $msc = microtime(true)-$msc;
                                        if($runProcedure) {
                                            echo $msc . ' s<br/>'; // in seconds
                                            echo ($msc * 1000) . ' ms<br/>'; // in millseconds
                                        }
                                        do{} while(mysqli_more_results($con) && mysqli_next_result($con));

                                    }

                                    
                                /* $con->query("INSERT IGNORE INTO db_new_application.assets_with_bank(appno_doc_num, appno_date, grant_doc_num, grant_date, company_id, organisation_id, rf_id, exec_dt, convey_ty, assignor_id, assignor_name, assignee_id, assignee_name)			
                                    SELECT  MAX(d.appno_doc_num), MAX(d.appno_date),  MAX(d.grant_doc_num),  MAX(d.grant_date), ".$row->company_id.", ".$row->organisation_id.", rac.rf_id, aor.exec_dt, rac.convey_ty, aor.assignor_and_assignee_id, aor.or_name,  ass.assignor_and_assignee_id, ass.ee_name  
                                    FROM db_uspto.documentid AS d 
                                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id = d.rf_id AND rac.convey_ty IN ('security', 'restatedsecurity')
                                    INNER JOIN db_uspto.assignee AS ass ON ass.rf_id = rac.rf_id 
                                    INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = rac.rf_id
                                    INNER JOIN LATERAL (SELECT d1.appno_doc_num AS appno_doc_num,
                                    max(aor1.exec_dt) AS exec_dt
                                    FROM db_uspto.documentid AS d1 
                                                    INNER JOIN db_uspto.representative_assignment_conveyance AS rac1 ON rac1.rf_id = d1.rf_id AND rac1.convey_ty IN ('security', 'release', 'restatedsecurity')
                                                    INNER JOIN db_uspto.assignee AS ass1 ON ass1.rf_id = rac1.rf_id 
                                                    INNER JOIN db_uspto.assignor AS aor1 ON aor1.rf_id = rac1.rf_id 
                                                    WHERE appno_doc_num IN (
                                                    
                                                    SELECT appno_doc_num FROM db_uspto.documentid WHERE rf_id IN (
                                                        SELECT rf_id FROM db_new_application.activity_parties_transactions where organisation_id = ".$row->organisation_id." AND company_id = ".$row->company_id."
                                                        AND activity_id = ".$layoutID."  GROUP BY rf_id)
                                                        AND date_format(appno_date, '%Y') >= ".$TRANSACTION_YEAR." 
                                                    GROUP BY appno_doc_num
                                                    
                                                    ) 
                                                    AND (
                                                        ass1.assignor_and_assignee_id IN (
                                                            SELECT aaa.assignor_and_assignee_id FROM db_uspto.assignor_and_assignee AS aaa
                                                            LEFT JOIN db_uspto.representative AS r ON aaa.representative_id = r.representative_id
                                                            WHERE aaa.name = '".$con->real_escape_string($party->entityName)."' OR r.representative_name = '".$con->real_escape_string($party->entityName)."'
                                                            GROUP BY aaa.assignor_and_assignee_id
                                                        ) OR aor1.assignor_and_assignee_id IN (
                                                            SELECT aaa.assignor_and_assignee_id FROM db_uspto.assignor_and_assignee AS aaa
                                                            LEFT JOIN db_uspto.representative AS r ON aaa.representative_id = r.representative_id
                                                            WHERE aaa.name = '".$con->real_escape_string($party->entityName)."' OR r.representative_name = '".$con->real_escape_string($party->entityName)."'
                                                            GROUP BY aaa.assignor_and_assignee_id
                                                        )
                                                    )
                                    GROUP BY appno_doc_num) AS max_date ON max_date.appno_doc_num = d .appno_doc_num AND max_date.exec_dt = aor.exec_dt
                                    WHERE d.appno_doc_num IN (
                                        SELECT appno_doc_num FROM db_uspto.documentid WHERE rf_id IN (
                                                        SELECT rf_id FROM db_new_application.activity_parties_transactions where organisation_id = ".$row->organisation_id." AND company_id = ".$row->company_id." 
                                                        AND activity_id = ".$layoutID."   GROUP BY rf_id)
                                                        AND date_format(appno_date, '%Y') >= ".$TRANSACTION_YEAR." 
                                                    GROUP BY appno_doc_num
                                    ) 
                                    AND (
                                        ass.assignor_and_assignee_id IN (
                                            SELECT aaa.assignor_and_assignee_id FROM db_uspto.assignor_and_assignee AS aaa
                                            LEFT JOIN db_uspto.representative AS r ON aaa.representative_id = r.representative_id
                                            WHERE aaa.name = '".$con->real_escape_string($party->entityName)."' OR r.representative_name = '".$con->real_escape_string($party->entityName)."'
                                            GROUP BY aaa.assignor_and_assignee_id
                                        ) OR aor.assignor_and_assignee_id IN (
                                            SELECT aaa.assignor_and_assignee_id FROM db_uspto.assignor_and_assignee AS aaa
                                            LEFT JOIN db_uspto.representative AS r ON aaa.representative_id = r.representative_id
                                            WHERE aaa.name = '".$con->real_escape_string($party->entityName)."' OR r.representative_name = '".$con->real_escape_string($party->entityName)."'
                                            GROUP BY aaa.assignor_and_assignee_id
                                        )
                                    )
                                    GROUP BY d.appno_doc_num");*/
                                    
                                }
                                do{} while(mysqli_more_results($con) && mysqli_next_result($con));
                                $con->query("INSERT IGNORE INTO db_new_application.assets_with_bank_expired(appno_doc_num, expire_date, company_id, organisation_id)
                                SELECT appno_doc_num, date_format(emf.event_date, '%Y-%m-%d') AS expiry_date, ".$row->company_id." , ".$row->organisation_id." FROM db_patent_maintainence_fee.event_maintainence_fees AS emf
                                WHERE appno_doc_num IN (
                                    SELECT tawb.appno_doc_num FROM db_new_application.assets_with_bank as tawb 
                                    WHERE tawb.company_id = ".$row->company_id."  AND tawb.organisation_id = ".$row->organisation_id." 
                                    GROUP BY tawb.appno_doc_num
                                ) AND event_code IN ('EXP.', 'EXPX')
                                GROUP BY appno_doc_num");
                                do{} while(mysqli_more_results($con) && mysqli_next_result($con));
                                
                                $con->query("INSERT IGNORE INTO db_new_application.assets_with_bank_expired(appno_doc_num, expire_date, company_id, organisation_id)
                                SELECT d.appno_doc_num, DATE_SUB(DATE_ADD(d.appno_date, INTERVAL 20 YEAR), INTERVAL 1 DAY) AS expiry_date, ".$row->company_id." , ".$row->organisation_id."
                                FROM db_uspto.documentid AS d 
                                INNER JOIN db_new_application.assets_with_bank as tawb ON tawb.appno_doc_num = d.appno_doc_num
                                WHERE tawb.company_id = ".$row->company_id."  AND tawb.organisation_id = ".$row->organisation_id."
                                GROUP BY d.appno_doc_num");
                                $con->query('CALL db_uspto.routine_lost_assets_bank("'.$row->company_id.'", "'.$row->organisation_id.'")');
                                do{} while(mysqli_more_results($con) && mysqli_next_result($con));
                                exec('php -f /var/www/html/trash/assets_bank_broken_title.php "'.$row->company_id.'" "'.$row->organisation_id.'"');
                                exec('php -f /var/www/html/trash/dashboard_with_bank.php "'.$row->company_id.'" "'.$row->organisation_id.'"');
                            }
                        }
                    } else {
                        echo "Send request for summary and dashboard_with_company";
                        //exec('php -f /var/www/html/trash/summary.php "'.(int)$organisationID.'" "'.$row->company_id.'"');
                        echo 'php -f /var/www/html/trash/dashboard_with_company.php "'.$row->company_id.'" "'.$row->organisation_id.'"';
                        exec('php -f /var/www/html/trash/dashboard_with_company.php "'.$row->company_id.'" "'.$row->organisation_id.'"'); 
                    }
                    exec('php -f /var/www/html/trash/report_represetative_assets_transactions_by_account.php '.$row->organisation_id.' "'.$con->real_escape_string($row->company_name).'"');
                }	 

                if((int)$accountType != 2) {
                    echo "Loop finished Summary Org and Assets Family and Logos retrieval";
                    exec('php -f /var/www/html/trash/summary.php "'.(int)$organisationID.'" "" "1"');

                    /**
                     * Send Push Notification
                     */
                    exec('node /var/www/html/script/send_push_notification.js "Update is compelete sucessfully"');

                    if($runOtherScript == '0') {
                        exec('php -f /var/www/html/script/assets_family.php "'.(int)$organisationID.'"');
                       /*  if(count($allCompanies) > 0) {
                            foreach($allCompanies as $company) {
                                echo "retrieved_logos";
                                $output = shell_exec('php -f /var/www/html/trash/retrieved_logos.php "'.(int)$organisationID.'" "'.$company.'"');
                                print_r($output);
                            }
                        } */
                    }
                } else {
                    exec('node /var/www/html/script/send_push_notification.js "Update is compelete sucessfully"');
                }
            }
        }	 
    } catch (Exception $e) {
        exec('node /var/www/html/script/send_push_notification.js "Erron in update."');
    }  
}

function getMinusYear($number){
	$time = new DateTime('now');
	return $time->modify('-'.$number.' year')->format('Y-m-d');
}

function insertData($dbUSPTO, $tableName, $list, $con, $childJSON = false, $param = ''){		
	if(count($list) > 0) {
		$i = 0;
		$stringName ="";
		$stringValue ="";
		for($i = 0; $i < count($list); $i++){
			$stringValue .="(";
			foreach($list[$i] as $key=>$value) {
				if($i == 0) {
					$stringName .= $key.", ";
				}
				if($childJSON === true && $param == $key){
					$stringValue .="'".json_encode($value)."'".", ";
				} else {
					$stringValue .="'".$con->real_escape_string($value)."'".", ";
				}
				
			}
			$stringValue = substr($stringValue, 0, -2);
			$stringValue .="), ";
		}
		$stringValue = substr($stringValue, 0, -2);
		$stringName = substr($stringName, 0, -2);
		$sql = "INSERT IGNORE INTO ".$dbUSPTO.".".$tableName."(".$stringName.") VALUES ".$stringValue;	
		echo $sql."<br/>";
		$result = $con->query($sql);
	}
}