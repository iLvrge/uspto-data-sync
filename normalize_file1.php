<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
$overAllArray = array();
$db = array();
ini_set('max_execution_time', 0);

ignore_user_abort(true);
ini_set('xdebug.max_nesting_level', 1000);
ini_set("memory_limit","256M");
$patentData = "";
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
	
$queryCount = "SELECT count(*) as numRows FROM assignor";

$resultCount = $con->query($queryCount);
$i = 0;

if($resultCount){
	$countRows = $resultCount->fetch_object();
	
	if($countRows->numRows > 0) {
		$numRows = $countRows->numRows;
		
		while($i < $numRows) {
			$con->query("SET FOREIGN_KEY_CHECKS = 0");		
			$queryJob = "SELECT rf_id, or_name FROM assignor ORDER BY or_name ASC LIMIT ".$i.", 1000000";
			echo $queryJob."<br/>";
			$resultQuery = $con->query($queryJob);
			
			if($resultQuery->num_rows > 0) {
				while($row = $resultQuery->fetch_object()){
					
					$orName = $row->or_name;
					//$orName = removeDoubleSpace( $orName );
					//$orName = strReplace( $orName );
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
					
					
					if($findString === 1) {
						echo 'UPDATE assignor SET assignor_and_assignee_id = 0 , or_name = "'.$con->real_escape_string($orName).'" WHERE rf_id = '.$row->rf_id.' AND or_name = "'.$con->real_escape_string($row->or_name).'"<br/>';
						$sp = $con->query( 'UPDATE assignor SET assignor_and_assignee_id = 0 , or_name = "'.$con->real_escape_string($orName).'" WHERE rf_id = '.$row->rf_id.' AND or_name = "'.$con->real_escape_string($row->or_name).'"' );
						echo $i.":".$sp."<br/>";
						
					} else {
						echo $i.":0<br/>";
					}
					/*$sp = $con->query( 'UPDATE assignor SET assignor_and_assignee_id = 0 , or_name = "'.$con->real_escape_string($orName).'" WHERE rf_id = '.$row->rf_id.' AND or_name = "'.$con->real_escape_string($row->or_name).'"' );
						echo $i.":".$sp."<br/>";*/
					$i++;
				}
			}
		}
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
?>
