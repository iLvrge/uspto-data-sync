<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);



/* $query = 'Select representative_name, count(representative_name) as counter from representative GROUP BY representative_name having counter > 1';

$result = $con->query($query);
		
if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$queryA = 'Select * from assignor_and_assignee where representative_id IN (Select representative_id from representative where representative_name = "'.$row->representative_name.'") ORDER BY representative_id ASC LIMIT 1 ';
		$resultA = $con->query($queryA);
		if($resultA) {
			if($resultA && $resultA->num_rows > 0) {
				$rowA = $resultA->fetch_object();
				$con->query('UPDATE assignor_and_assignee SET representative_id = '.$rowA->representative_id.' where representative_id IN (Select representative_id from representative where representative_name = "'.$row->representative_name.'")');
			}
		}
	}
} */

/* $query = 'Select representative_id, representative_name, count(representative_name) as counter from representative GROUP BY representative_name having counter > 1 ORDER BY representative_id ASC';

$result = $con->query($query);

if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$con->query('DELETE FROM representative WHERE representative_name = "'.$row->representative_name.'" AND representative_id <> '.$row->representative_id);
	}
} */

/* $query = 'Select representative_id FROM representative';

$result = $con->query($query);

if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$queryA = "SELECT * FROM assignor_and_assignee where representative_id = ".$row->representative_id;
		$resultA = $con->query($queryA);
		if($resultA) {
			if($resultA && $resultA->num_rows == 0) {
				$con->query('DELETE FROM representative WHERE representative_id = '.$row->representative_id);
			}
		}		
	}
} */

/* $query = 'Select representative_id, representative_name FROM representative';

$result = $con->query($query);

if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$queryA = 'SELECT * FROM assignor_and_assignee where name = "'.$row->representative_name.'" LIMIT 1';
		$resultA = $con->query($queryA);
		if($resultA) {
			if($resultA && $resultA->num_rows > 0) {
				$rowA = $resultA->fetch_object();
				if($rowA->representative_id == 0 || $rowA->representative_id == null) {
					$con->query('UPDATE assignor_and_assignee SET representative_id = '.$row->representative_id.' where assignor_and_assignee_id = "'.$rowA->assignor_and_assignee_id.'")');
				}				
			}
		}
	}
} */


$query = 'SELECT assignor_and_assignee_id
  FROM assignor_and_assignee 
 WHERE name <> CONVERT(name USING ASCII)';
 
 $result = $con->query($query);

if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$queryA = "SELECT * FROM assignment where rf_id IN (SELECT rf_id FROM assignee WHERE assignor_and_assignee_id = ".$row->assignor_and_assignee_id.") LIMIT 1";
		$resultA = $con->query($queryA);
		$aee = 0;
		if($resultA && $resultA->num_rows == 0) {
			$queryA = "SELECT * FROM assignment where rf_id IN (SELECT rf_id FROM assignor WHERE assignor_and_assignee_id = ".$row->assignor_and_assignee_id.") LIMIT 1";
			$resultA = $con->query($queryA);
			$aee = 1;
		}
		$inserRecords = '';
		if($resultA) {
			if($resultA && $resultA->num_rows > 0) {
				$rowA = $resultA->fetch_object();
				$id = (int)$rowA->reel_no."-".(int)$rowA->frame_no;
				$appURL = "https://assignment.uspto.gov/solr/aotw/select?fl=*&fq=id:".$id."&hl=true&lowercaseOperators=true&q=id:'".$id."'+OR+reelNo:'".(int)$rowA->reel_no."'+OR+frameNo:'".(int)$rowA->frame_no."'&rows=1&sort=patAssignorEarliestExDate+desc,+recordedDate+desc&wt=json";
				echo $appURL."<br/>";
				$dataUSPTO = curl($appURL);
				try{
					if($dataUSPTO != "" && $dataUSPTO != null) {
						$assignmentList = json_decode($dataUSPTO,true);
						if(isset($assignmentList['response']) && isset($assignmentList['response']['docs'])) {
							if(count($assignmentList['response']['docs']) > 0) {
								$record = $assignmentList['response']['docs'][0];
								$assignees = $record['patAssigneeName'];
								$assignors = $record['patAssignorName'];
								if($aee === 0) {
									foreach($assignees as $assignee) {
										$eeName = $assignee;						
										$eeName = removeDoubleSpace( $eeName );
										$eeName = strReplace( $eeName );
										$findString = 0;
										$stringC = remove_if_trailing($eeName, "corporation");
										if($stringC[1] === 0) {
											$stringC = remove_if_trailing($eeName, "incorporated");
											if($stringC[1] === 0) {
												$stringC = remove_if_trailing($eeName, "limited");
												if($stringC[1] === 0) {
													$stringC = remove_if_trailing($eeName, "company");
													if($stringC[1] === 1) {
														$findString = $stringC[1];
														$eeName = removeDoubleSpace($stringC[0]);
													}
												} else {
													$findString = $stringC[1];
													$eeName = removeDoubleSpace($stringC[0]);
												}	
											} else {
												$findString = $stringC[1];
												$eeName = removeDoubleSpace($stringC[0]);
											}
										} else {
											$findString = $stringC[1];
											$eeName = removeDoubleSpace($stringC[0]);
										}
										$inserRecords .= "('".$row->assignor_and_assignee_id."', '".$con->real_escape_string($assignee)."', '".$con->real_escape_string($eeName)."', 0), ";
									}
								}
								
								if($aee === 1) {
									foreach($assignors as $assignor) {
										$orName = $assignor;						
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
										
										$inserRecords .= "('".$row->assignor_and_assignee_id."', '".$con->real_escape_string($assignor)."', '".$con->real_escape_string($orName)."', 1), ";
									}
								}
								$inserRecords = substr($inserRecords, 0, -2);	
								$inserRecords = 'INSERT INTO '.$dbUSPTO.'.temp_assignor_and_assignee_name(assignor_and_assignee_id,original_name,name,type) VALUES '.$inserRecords;
								$con->query($inserRecords);
								
							}
						}
					}
					
				} catch (Exception $e) {
					
				}
			}
		}
		sleep(2);
	}
}



function removeDoubleSpace($string) {
	return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return trim(ucwords(strtolower($string)));
}

/**
 * A slightly more readable, non-regex solution.
 */
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
			$haystack .= " Co";
		} else if(strtolower($needle) == "incorporated"){
			$haystack .= " Inc";
		} else if(strtolower($needle) == "limited"){
			$haystack .= " Ltd";
		} else if(strtolower($needle) == "corporation"){
			$haystack .= " Corp";
		}
		$lp = 1;
    }
    return array(trim(ucwords(strtolower($haystack))), $lp);
}

function curl($url) {
	echo $url."<br/>";
	$ch = curl_init ();
	curl_setopt ( $ch, CURLOPT_URL, $url );
	/*curl_setopt ( $ch, CURLOPT_HEADER, array('Accept:application/xml') );*/
	curl_setopt ( $ch, CURLOPT_HEADER,false );
	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 0 );
	$dataUSPTO = curl_exec ( $ch );
	if (curl_errno ( $ch )) {	
		//echo curl_errno ( $ch );die;
		curl_close ( $ch );			
	} else {
		curl_close ( $ch );
	}
	return $dataUSPTO;
}
 
