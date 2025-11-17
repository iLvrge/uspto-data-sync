<?php
ignore_user_abort(true);
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);

$variables = $argv;
if(count($variables) == 3) {
	$organisationID = $variables[1];
	$representativeID = [];
	if($variables[2] != '' && $variables[2] != '[]'){
		$representativeID = json_decode($variables[2]);
	} 
	print_r($representativeID);
	$org = "SELECT * FROM db_business.organisation where organisation_id = ".$organisationID;

	$resultQuery = $con->query($org);
	if($resultQuery && $resultQuery->num_rows > 0){
		$orgRow = $resultQuery->fetch_object();
		
		$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
		
		if($orgConnect) {
			if(count($representativeID) == 0) {
				$query = "SELECT * FROM representative";
			} else {
				$query = "SELECT representative_id FROM representative WHERE representative_id IN (".implode(',', $representativeID).") OR parent_id IN (".implode(',', $representativeID).")";
			}
			
			echo $query ;
			$resultRepresentativeCompanies = $orgConnect->query($query);
			
			$rfIDs = array();
			echo "Asasasasa".$resultRepresentativeCompanies->num_rows;
			if($resultRepresentativeCompanies && $resultRepresentativeCompanies->num_rows > 0){
				$allRepresentative = array();
				while($getCompanyRow = $resultRepresentativeCompanies->fetch_object()) {
					array_push($allRepresentative, $getCompanyRow->representative_id);
				}
				if(count($allRepresentative) > 0) {
					echo $queryFindAllRFIDs = "SELECT rf_id FROM db_uspto.list2 WHERE organisation_id = ".$organisationID." AND company_id IN (".implode(',', $allRepresentative).") GROUP BY rf_id";
					
					
					$resultRepresentativeRFIDs = $con->query($queryFindAllRFIDs);
					if($resultRepresentativeRFIDs && $resultRepresentativeRFIDs->num_rows > 0) {
						while($rowRepresentativeRF = $resultRepresentativeRFIDs->fetch_object()) {
							array_push($rfIDs, $rowRepresentativeRF->rf_id);
						}
					}
				}				
			}
			print_r($rfIDs);
			$keywordsString = "";
			$sqlKeywords = "SELECT keyword_name FROM db_uspto.keyword";
			$resultKeyword = $con->query($sqlKeywords);
			
			if($resultKeyword && $resultKeyword->num_rows > 0) {
				$keywords = array();
				while($keyword = $resultKeyword->fetch_object()){
					array_push($keywords, strtolower($keyword->keyword_name));
				}
				if(count($keywords) > 0) {
					$keywordsString = implode("|", $keywords);
				}
			}
			echo $sqlKeywords;
			print_r($keywordsString );

			$superKeywordsString = "";
			$sqlKeywords = "SELECT super_keyword_name FROM db_uspto.super_keyword";
			$resultKeyword = $con->query($sqlKeywords);
			if($resultKeyword && $resultKeyword->num_rows > 0) {
				$keywords = array();
				while($keyword = $resultKeyword->fetch_object()){
					array_push($keywords, strtolower($keyword->super_keyword_name));
				}
				if(count($keywords) > 0) {
					$superKeywordsString = implode("|", $keywords);
				}
			}
			echo $sqlKeywords;
			print_r($superKeywordsString );
			$stateKeywordsString = "";
			$sqlKeywords = "SELECT name FROM db_uspto.state";
			$resultKeyword = $con->query($sqlKeywords);
			if($resultKeyword && $resultKeyword->num_rows > 0) {
				$keywords = array();
				while($keyword = $resultKeyword->fetch_object()){
					array_push($keywords, strtolower($keyword->name));
				}
				if(count($keywords) > 0) {
					$stateKeywordsString = implode("|", $keywords);
				}
			}
			echo $sqlKeywords;
			print_r($stateKeywordsString );
			
		if(count($rfIDs) > 0){
			
			$query = "SELECT a.rf_id, a.cname, caddress_1, caddress_2, caddress_7, caddress_5, caddress_6  FROM db_uspto.correspondent as a WHERE a.rf_id IN (".implode(',', $rfIDs).") AND (caddress_1 <> '' OR caddress_2 <> '' OR cname <> '') GROUP BY a.rf_id";
			//echo $query;
			$resultAssignment = $con->query($query);
			echo "TOTAL: ".$resultAssignment->num_rows."<br/>";
			if($resultAssignment && $resultAssignment->num_rows > 0) {
				$i = 0;

				/*while($rowAssignment = $resultAssignment->fetch_object()){
					$updateQuery = "";
					if($rowAssignment->caddress_1 == '' && $rowAssignment->caddress_6 != '' && wordNotIn($rowAssignment->caddress_6) === true) {
						$updateQuery .= ' caddress_6 = "" , caddress_1= "'.$con->real_escape_string($rowAssignment->caddress_6).'", ';
					} else if($rowAssignment->cname == '' && $rowAssignment->caddress_6 != '' && wordNotIn($rowAssignment->caddress_6) === true) {
						$updateQuery .= ' caddress_6 = "" , cname= "'.$con->real_escape_string($rowAssignment->caddress_6).'", ';
					} else if($rowAssignment->caddress_1 == '' && $rowAssignment->caddress_5 != '' && wordNotIn($rowAssignment->caddress_5) === true) {
						$updateQuery .= ' caddress_5 = "" , caddress_1= "'.$con->real_escape_string($rowAssignment->caddress_5).'", ';
					} else if($rowAssignment->cname == '' && $rowAssignment->caddress_5 != '' && wordNotIn($rowAssignment->caddress_5) === true) {
						$updateQuery .= ' caddress_5 = "" , cname= "'.$con->real_escape_string($rowAssignment->caddress_5).'", ';
					} else if($rowAssignment->caddress_1 == '' && $rowAssignment->caddress_7 != '' && wordNotIn($rowAssignment->caddress_7) === true) {
						$updateQuery .= ' caddress_7 = "" , caddress_1= "'.$con->real_escape_string($rowAssignment->caddress_7).'", ';
					} else if($rowAssignment->cname == '' && $rowAssignment->caddress_7 != '' && wordNotIn($rowAssignment->caddress_7) === true) {
						$updateQuery .= ' caddress_7 = "" , cname= "'.$con->real_escape_string($rowAssignment->caddress_7).'", ';
					}
					if($updateQuery != "") {
						$updateQuery = substr($updateQuery, 0, -2);
						
						$updateQuery = 'UPDATE db_uspto.correspondent SET '.$updateQuery.' WHERE rf_id = '.$rowAssignment->rf_id;
						
						echo $updateQuery."<br/>";
						
						
						$update = $con->query($updateQuery); 
					}	
				}
				die;*/
				while($rowAssignment = $resultAssignment->fetch_object()) {
					
					$cname = $rowAssignment->cname;
					$caddress1 = $rowAssignment->caddress_1;
					$caddress2 = $rowAssignment->caddress_2;

					print_r($rowAssignment);
					
					$numericCheck = findNumericPattren($cname); //Numeric numbers 112, 154
					$cnameCheck = findStringPattren($keywordsString, $cname); //Keywords
					
					$numberInStringCheck = findNumberINStringPattren($cname); //Number in string One, Two, Three, First, Second, Third
					$superKeywordCheck = findStringAsSuperKeywords($superKeywordsString, $cname); //Super Keywords
					
					$stateCheck = findStringPattren($stateKeywordsString, $cname); // finding states USA only
					$zipCodeWithHyphen = findZipCodeWithHyphen($cname); // zip code with hypen 20041-54
					$zipCodeOnly = findZipCodeOnly($cname); //only zipcode without hypen

					/* print_r($numericCheck);
					print_r($cnameCheck);
					print_r($numberInStringCheck);
					print_r($superKeywordCheck);
					print_r($stateCheck);
					print_r($zipCodeWithHyphen);
					print_r($zipCodeOnly);




					echo "CHECKING cAddress1"; */

					
					$numeric1Check = findNumericPattren($caddress1);
					$caddress1Check = findStringPattren($keywordsString, $caddress1);
					
					$number1InStringCheck = findNumberINStringPattren($caddress1);
					$superKeyword1Check = findStringAsSuperKeywords($superKeywordsString, $caddress1);
					
					$state1Check = findStringPattren($stateKeywordsString, $caddress1);
					$zip1CodeWithHyphen = findZipCodeWithHyphen($caddress1); 
					$zip1CodeOnly = findZipCodeOnly($caddress1);

					/* print_r($numeric1Check);
					print_r($caddress1Check);
					print_r($number1InStringCheck);
					print_r($superKeyword1Check);
					print_r($state1Check);
					print_r($zip1CodeWithHyphen);
					print_r($zip1CodeOnly);
					
					
					echo "CHECKING cAddress2"; */
					//$caddress1CorporateCheck = findCorporateStringPattren($caddress1);
					
					$numeric2Check = findNumericPattren($caddress2);
					$caddress2Check = findStringPattren($keywordsString, $caddress2);
					
					$number2InStringCheck = findNumberINStringPattren($caddress2);
					$superKeyword2Check = findStringAsSuperKeywords($superKeywordsString, $caddress2);
					
					$state2Check = findStringPattren($stateKeywordsString, $caddress2);
					$zip2CodeWithHyphen = findZipCodeWithHyphen($caddress2);
					$zip2CodeOnly = findZipCodeOnly($caddress2);

					/* print_r($numeric2Check);
					print_r($caddress2Check);
					print_r($number2InStringCheck);
					print_r($superKeyword2Check);
					print_r($state2Check);
					print_r($zip2CodeWithHyphen);
					print_r($zip2CodeOnly); */
					
					/* if($rowAssignment->rf_id == 137300713) {
						echo $caddress1;
						print_r($numeric1Check);
						print_r($caddress1Check);
						print_r($number1InStringCheck);
						print_r($superKeyword1Check);
						print_r($state1Check);
						print_r($zip1CodeWithHyphen);
						print_r($zip1CodeOnly);
						die;ß
					} */
					/* echo $cname;
					echo $caddress1;
					echo $caddress2;
					echo "numeric1Check";
						print_r($numeric1Check);
						echo "caddress1Check";
						print_r($caddress1Check);
						echo "number1InStringCheck";
						print_r($number1InStringCheck);
						echo "superKeyword1Check";
						print_r($superKeyword1Check);
						echo "state1Check";
						print_r($state1Check);
						echo "zip1CodeWithHyphen";
						print_r($zip1CodeWithHyphen);
						echo "zip1CodeOnly";
						print_r($zip1CodeOnly); */
						
					$updateQuery = "";

					
					
					if((count($superKeywordCheck) > 0 || ((count($numericCheck) > 0 || count($numberInStringCheck) > 0)|| (count($cnameCheck) > 0 || count($stateCheck) > 0)) || (count($zipCodeWithHyphen) > 0 || count($zipCodeOnly) > 0)) && wordNotIn($cname) === false && checkNameInNormalizeOrRepresentativeTable($cname, $con) == 0) {
						$updateQuery .= ' cname = "" , caddress_7= "'.$con->real_escape_string($cname).'", ';
					}

					 
					
					if((count($superKeyword1Check) > 0 || ((count($numeric1Check)>0 || count($number1InStringCheck) > 0) || (count($caddress1Check) >0 || count($state1Check) > 0))|| (count($zip1CodeWithHyphen) > 0 || count($zip1CodeOnly) > 0))  && wordNotIn($caddress1) === false && checkNameInNormalizeOrRepresentativeTable($caddress1, $con) == 0) {
						$updateQuery .= ' caddress_1 = "" , caddress_5= "'.$con->real_escape_string($caddress1).'", ';
					}
					 
					
					if((count($superKeyword2Check) > 0 || ((count($numeric2Check) > 0 || count($number2InStringCheck) > 0) || (count($caddress2Check) > 0 || count($state2Check) > 0))|| (count($zip2CodeWithHyphen) > 0 || count($zip2CodeOnly) > 0))  && wordNotIn($caddress2) === false && checkNameInNormalizeOrRepresentativeTable($caddress2, $con) == 0) {
						$updateQuery .= ' caddress_2 = "" , caddress_6= "'.$con->real_escape_string($caddress2).'", ';
					}

						
					if($updateQuery != "") {
						$updateQuery = substr($updateQuery, 0, -2);

						print_r($rowAssignment);
						
						$updateQuery = 'UPDATE db_uspto.correspondent SET '.$updateQuery.' WHERE rf_id = '.$rowAssignment->rf_id;
						
						echo $updateQuery."<br/>";

						$update = $con->query($updateQuery); 
						echo $update."<br/>"; 
						
						/*
						$updateQuery = 'UPDATE db_application.assignment SET '.$updateQuery.' WHERE rf_id = '.$rowAssignment->rf_id;
						
						echo $updateQuery."<br/>";
						
						$update = $con->query($updateQuery); 
						echo $update."<br/>";*/
						$i++;
					}	
				}
				echo "UPDATE: ".$i."<br/>";
			} 
			/*Address shifting*/
			//die;
			$query = "SELECT a.rf_id, a.cname, caddress_1, caddress_2, caddress_3, caddress_4, caddress_5, caddress_6, caddress_7 FROM db_uspto.correspondent as a WHERE a.rf_id IN (".implode(',', $rfIDs).") AND (cname <> '' OR caddress_1 <> '' OR caddress_2 <> '' OR caddress_3 <> '' OR caddress_4 <> '' OR caddress_5 <> '' OR caddress_6 <> '' OR caddress_7 <> '') GROUP BY a.rf_id";
			
			echo $query ;
			
			$resultAssignment = $con->query($query);
			
			if($resultAssignment && $resultAssignment->num_rows > 0) {

				try{
					$i = 0;
					while($rowAssignment = $resultAssignment->fetch_object()){
						/**/
						$n = 0;
						$update = false;
						while($n < 4) {
							if($rowAssignment->caddress_3 != "" && $rowAssignment->caddress_4 == "") {
								$rowAssignment->caddress_4 = $rowAssignment->caddress_3;
								$rowAssignment->caddress_3 = "";
								$update = true;
							}
							if($rowAssignment->caddress_6 != "" && $rowAssignment->caddress_3 == "") {
								$rowAssignment->caddress_3 = $rowAssignment->caddress_6;
								$rowAssignment->caddress_6 = "";
								$update = true;
							}
							if($rowAssignment->caddress_5 != "" && $rowAssignment->caddress_6 == "") {
								$rowAssignment->caddress_6 = $rowAssignment->caddress_5;
								$rowAssignment->caddress_5 = "";
								$update = true;
							}
							if($rowAssignment->caddress_7 != "" && $rowAssignment->caddress_5 == "") {
								$rowAssignment->caddress_5 = $rowAssignment->caddress_7;
								$rowAssignment->caddress_7 = "";
								$update = true;
							}						
							$n++;
						}

						if($rowAssignment->caddress_3 == $rowAssignment->caddress_4){
							$rowAssignment->caddress_3 = '';
							$update = true;
							$n = 0;
							while($n < 3) {
								if($rowAssignment->caddress_6 != "" && $rowAssignment->caddress_3 == "") {
									$rowAssignment->caddress_3 = $rowAssignment->caddress_6;
									$rowAssignment->caddress_6 = "";
									$update = true;
								}
								if($rowAssignment->caddress_5 != "" && $rowAssignment->caddress_6 == "") {
									$rowAssignment->caddress_6 = $rowAssignment->caddress_5;
									$rowAssignment->caddress_5 = "";
									$update = true;
								}
								if($rowAssignment->caddress_7 != "" && $rowAssignment->caddress_5 == "") {
									$rowAssignment->caddress_5 = $rowAssignment->caddress_7;
									$rowAssignment->caddress_7 = "";
									$update = true;
								}						
								$n++;
							}
						} else if ($rowAssignment->caddress_6 == $rowAssignment->caddress_3) {
							$rowAssignment->caddress_6 = '';
							$update = true;
							$n = 0;
							while($n < 2) {
								if($rowAssignment->caddress_5 != "" && $rowAssignment->caddress_6 == "") {
									$rowAssignment->caddress_6 = $rowAssignment->caddress_5;
									$rowAssignment->caddress_5 = "";
									$update = true;
								}
								if($rowAssignment->caddress_7 != "" && $rowAssignment->caddress_5 == "") {
									$rowAssignment->caddress_5 = $rowAssignment->caddress_7;
									$rowAssignment->caddress_7 = "";
									$update = true;
								}						
								$n++;
							}
						}
						//check for cname, caddress1, caddress2 holes
						$n = 0;
						while($n < 2) {
							/* if($rowAssignment->caddress_1 != "" && $rowAssignment->cname == "") {
								$rowAssignment->cname = $rowAssignment->caddress_1;
								$rowAssignment->caddress_1 = "";
								$update = true;
							} */
							if($rowAssignment->caddress_2 != "" && $rowAssignment->caddress_1 == "") {
								$rowAssignment->caddress_1 = $rowAssignment->caddress_2;
								$rowAssignment->caddress_2 = '';
								$update = true;
							}
							if($rowAssignment->caddress_1 != "" && $rowAssignment->cname == "") {
								$rowAssignment->cname = $rowAssignment->caddress_1;
								$rowAssignment->caddress_1 = '';
								$update = true;
							}
							/* if($rowAssignment->caddress_2 != "" && $rowAssignment->cname == "") {
								$rowAssignment->cname = $rowAssignment->caddress_2;
								$rowAssignment->caddress_2 = "";
								$update = true;
							}	 */					
							$n++;
						}
						
						
						
						
						if($update === true) {
							updateData("correspondent", $rowAssignment, $con);
							$update = false;
						}
						$i++;
					}
				} catch( Exception $e){
					print_r($e);
					die;
				}
			}
		}
			/* while(1==1){
				echo "SASAS";
				$query = "SELECT count(distinct(a.rf_id)) as numRows FROM assignment as a INNER JOIN assignee as aa ON aa.rf_id = a.rf_id INNER JOIN db_uspto.representative_transactions as rt ON rt.rf_id = aa.rf_id WHERE rt.organisation_id = ".$organisationID." AND aa.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).") AND caddress_4 = '' AND caddress_3 <> '' ";
				
				$resultAddress7 = $con->query($query);
				
				$result7 = false;
				
				if($resultAddress7) {
					$getData = $resultAddress7->fetch_object();
					echo $getData->numRows;
					if($getData->numRows > 0) {
						$result7 = true;
					}
				}
				
				if($result7 === true) {
					$queryUpdate = "UPDATE assignment SET caddress_4 = caddress_3, caddress_3 = '' WHERE rf_id IN (SELECT aa.rf_id FROM assignee as aa INNER JOIN db_uspto.representative_transactions as rt ON rt.rf_id = aa.rf_id WHERE rt.organisation_id = ".$organisationID." AND aa.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).")) AND caddress_4 = '' AND caddress_3 <> ''";
					echo $queryUpdate;
					$con->query($queryUpdate);
				}
				
				$query = "SELECT count(distinct(a.rf_id)) as numRows FROM assignment as a INNER JOIN assignee as aa ON aa.rf_id = a.rf_id INNER JOIN db_uspto.representative_transactions as rt ON rt.rf_id = aa.rf_id WHERE rt.organisation_id = ".$organisationID." AND aa.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).") AND caddress_3 = '' AND caddress_6 <> '' ";
				
				$resultAddress6 = $con->query($query);
				
				$result6 = false;
				
				if($resultAddress6) {
					$getData = $resultAddress6->fetch_object();
					echo "@6".$getData->numRows;
					if($getData->numRows > 0) {
						$result6 = true;
					}
				}
				
				if($result6 === true) {
					$queryUpdate =  "UPDATE assignment SET caddress_3 = caddress_6, caddress_6 = '' WHERE rf_id IN (SELECT aa.rf_id FROM assignee as aa INNER JOIN db_uspto.representative_transactions as rt ON rt.rf_id = aa.rf_id WHERE rt.organisation_id = ".$organisationID." AND aa.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).")) AND caddress_3 = '' AND caddress_6 <> ''";
					$con->query($queryUpdate);
				}
				
				$query = "SELECT count(distinct(a.rf_id)) as numRows FROM assignment as a INNER JOIN assignee as aa ON aa.rf_id = a.rf_id INNER JOIN db_uspto.representative_transactions as rt ON rt.rf_id = aa.rf_id WHERE rt.organisation_id = ".$organisationID." AND aa.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).") AND caddress_6 = '' AND caddress_5 <> '' ";
				
				$resultAddress5 = $con->query($query);
				
				$result5 = false;
				
				if($resultAddress5) {
					$getData = $resultAddress5->fetch_object();
					echo "@5".$getData->numRows;
					if($getData->numRows > 0) {
						$result5 = true;
					}
				}
				
				if($result5 === true) {
					$queryUpdate = "UPDATE assignment SET caddress_6 = caddress_5, caddress_5 = '' WHERE rf_id IN (SELECT aa.rf_id FROM assignee as aa INNER JOIN db_uspto.representative_transactions as rt ON rt.rf_id = aa.rf_id WHERE rt.organisation_id = ".$organisationID." AND aa.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).")) AND caddress_6 = '' AND caddress_5 <> ''";
					$con->query($queryUpdate);
				}
				
				$query = "SELECT count(distinct(a.rf_id)) as numRows FROM assignment as a INNER JOIN assignee as aa ON aa.rf_id = a.rf_id INNER JOIN db_uspto.representative_transactions as rt ON rt.rf_id = aa.rf_id WHERE rt.organisation_id = ".$organisationID." AND aa.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).") AND caddress_5 = '' AND caddress_7 <> '' ";
				
				$resultAddress4 = $con->query($query);
				
				$result4 = false;
				
				if($resultAddress4) {
					$getData = $resultAddress4->fetch_object();
					echo "@4".$getData->numRows;
					if($getData->numRows > 0) {
						$result4 = true;
					}
				}
				
				if($result4 === true) {
					$queryUpdate = "UPDATE assignment SET caddress_5 = caddress_7, caddress_7 = '' WHERE rf_id IN (SELECT aa.rf_id FROM assignee as aa INNER JOIN db_uspto.representative_transactions as rt ON rt.rf_id = aa.rf_id WHERE rt.organisation_id = ".$organisationID." AND aa.assignor_and_assignee_id IN (".implode(',', $assignorAndAssigneeIDs).")) AND caddress_5 = '' AND caddress_7 <> ''";
					$con->query($queryUpdate);
				}
				
				if($result7 === false && $result6 === false && $result5 === false && $result4 === false){
					echo "22222222222222222222222";
					break;
				}
			} */
		}
	}
}

function updateData($tableName,$updateValues,$con){
	$stringName ="";
	$updateValues = (array)$updateValues;
	foreach($updateValues as $key=>$value){
		$stringName .=$key."='".$con->real_escape_string($value)."',";
	}
	$stringName = substr($stringName,0,-1);
	$sql = "UPDATE db_uspto.".$tableName." SET ".$stringName." WHERE rf_id= ".$updateValues['rf_id'];
	//$sqlApplication = "UPDATE db_application.".$tableName." SET ".$stringName." WHERE rf_id= ".$updateValues['rf_id'];
	//echo $sql."<br/><br>".$sqlApplication."<br/><br/>";
	echo $sql."<br/><br>"; 
	$con->query($sql);	
	//$con->query($sqlApplication);	
}

function checkNameInNormalizeOrRepresentativeTable($string, $con) {
	$query = "SELECT name FROM law_firm WHERE name = '".$con->real_escape_string($string)."' AND representative_id > 0 LIMIT 1 UNION ALL SELECT name FROM representative_law_firm WHERE name = '".$con->real_escape_string($string)."' LIMIT 1 UNION ALL SELECT name FROM lawyer WHERE name = '".$con->real_escape_string($string)."' AND representative_lawyer_id > 0 LIMIT 1 UNION ALL SELECT name FROM representative_lawyer WHERE name = '".$con->real_escape_string($string)."' LIMIT 1 ";

	$result = $con->query($query);	

	$records = 0;

	if($result && $result->num_rows > 0) {
		$records = $result->num_rows;
	}
	return $records;
}

function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return $string;
}

function wordNotIn($string) {
	$string = trim(strReplace($string));
	$words = explode(' ', strtolower($string));
	$flag = false;
	
	if(in_array('et', $words) && (in_array('al', $words) ||  in_array('la', $words))){
		$flag = true;
	}

	if($flag === false) {
		$pattern = '/\b(?:llp|plc|group|llc|pllc)\b/';
		$result = preg_match_all($pattern, strtolower($string), $matches);
		if($result > 0) {
			$flag = true;
		}
	}  
	return $flag;
}

/*Super keywords as a string */
function findStringAsSuperKeywords($keywordsString, $string){
	$pattern = '/\b(?i)(?:'.$keywordsString.')\b/';
	$result = preg_match_all($pattern, strtolower($string), $matches);
	return $matches[0];
}
/*keywords street address*/
function findStringPattren($keywordsString, $string) {
	/*$pattern = '/\b(?:'.$keywordsString.')\b/';*/
	$pattern = '/\b(?i)(?:'.$keywordsString.')\b/';
	
	$result = preg_match_all($pattern, strtolower($string), $matches);
	return $matches[0];
}
/*States list*/
function findStates($string) {
	$pattern = '/\b(?:alabama|al|alaska|ak|arizona|az|arkansas|ar|california|ca|colorado|co|connecticut|ct|delaware|de|florida|fl|georgia|ga|hawaii|hi|idaho|id|illinois|il|indiana|in|iowa|ia|kansas|ks|kentucky|ky|louisiana|la|maine|me|maryland|md|massachusetts|ma|michigan|mi|minnesota|mn|mississippi|ms|missouri|mo|montana|mt|nebraska|ne|nevada|nv|new hampshire|nh|new jersey|nj|new mexico|nm|new york|ny|north carolina|nc|north dakota|nd|ohio|oh|oklahoma|ok|oregon|or|pennsylvania|pa|rhode island|ri|south carolina|sc|south dakota|sd|tennessee|tn|texas|tx|utah|ut|vermont|vt|virginia|va|washington|wa|west virginia|wv|wisconsin|wi|wyoming|wy)\b/';
	$result = preg_match_all($pattern, strtolower($string), $matches);
	return $matches[0];
}
/*zipcode with pattern 5-4 (xxxxx-xxxx)*/
function findZipCodeWithHyphen($string) {
	$pattern = '/^\d{5}-\d{4}$/';
	$result = preg_match_all($pattern, $string, $matches);
	return $matches[0];
}
/*zipcode with 5 digit*/
function findZipCodeOnly($string) {
	$pattern = '/^\d{5}$/';
	$result = preg_match_all($pattern, $string, $matches);
	return $matches[0];
}
/*Corporate keywords*/
function findCorporateStringPattren($string) {
	$pattern = '/\b(?:inc|llc|corp|llp|gmbh|lp|agent|sas|na|bank|co|states|ltd|kk|a\/s)\b/';
	
	$result = preg_match_all($pattern, strtolower($string), $matches);
	return $matches[0];
}
/*check numeric in string */
function findNumberINStringPattren($string) {
	$pattern = '/\b(?:one|two|three|four|five|six|seven|eight|nine|ten|first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth)\b/';
	
	$result = preg_match_all($pattern, strtolower($string), $matches);
	return $matches[0];
}

/*Check digit*/
function findNumericPattren($string) {
	$pattern = '!\d+!';
	$result = preg_match_all($pattern, $string, $matches); 
	return $matches[0];
}