<?php 
ini_set('memory_limit', '90000M');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$con->query("SET SQL_MODE='ALLOW_INVALID_DATES'");
$con->query("SET FOREIGN_KEY_CHECKS = 0");

$query = "SELECT * FROM temp_assignee";

$result = $con->query($query);

if($result->num_rows > 0) {
	echo $result->num_rows."<br/>";
	while($row = $result->fetch_object()){
		$orName = $row->ee_name;						
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
		$assignorAndAssigneeID = findName($orName, $con);
		if($assignorAndAssigneeID  > 0) {
			update("assignee", array('original_name'=>$row->ee_name), $row->rf_id, $assignorAndAssigneeID, $con,  $dbUSPTO);
		}
	}
}

$query = "SELECT * FROM temp_assignor";

$result = $con->query($query);

if($result->num_rows > 0) {
	while($row = $result->fetch_object()){
		echo $result->num_rows."<br/>";
		
		$orName = $row->or_name;						
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
		$assignorAndAssigneeID = findName($orName, $con);
		if($assignorAndAssigneeID  > 0) {
			update("assignor", array('original_name'=>$row->or_name), $row->rf_id, $assignorAndAssigneeID, $con,  $dbUSPTO);
		}
	}
}

function findName($name, $con) {
	$query = 'SELECT assignor_and_assignee_id FROM assignor_and_assignee WHERE name = "'.$con->real_escape_string($name).'" LIMIT 1';
	$result = $con->query($query);
	$assignor_and_assignee_id = 0;
	if($result && $result->num_rows > 0) {
		$row = $result->fetch_object();
		$assignor_and_assignee_id = $row->assignor_and_assignee_id;
	}
	echo $query."<br/>";
	return $assignor_and_assignee_id;
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

function update($tableName, $postValues, $rfID, $assignorAndAssigneeID ,$con, $dbName){
	$stringName ="";
	foreach($postValues as $key=>$value){
		$stringName .=$key."='".$con->real_escape_string($value)."',";
	}
	$stringName = substr($stringName,0,-1);
	$sql = "UPDATE ".$dbName.".".$tableName." SET ".$stringName." WHERE rf_id= ".$rfID." AND assignor_and_assignee_id = ".$assignorAndAssigneeID;
	echo $sql."<br/>";
	
	$con->query($sql);
}