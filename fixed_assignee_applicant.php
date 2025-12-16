<?php 




ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set("log_errors", 1);
ini_set("error_log", "/var/www/html/trash/daily_file.log");

ini_set('xdebug.max_nesting_level', 1000);
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, 'db_patent_application_bibliographic');


function removeDoubleSpace($string) {
	return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
	$string = preg_replace('/,/', '', $string);
	$string = preg_replace('/\./', '', $string);
	$string = preg_replace('/!/', '', $string);
	return $string;
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
    return array(trim($haystack), $lp);
}

$variables = $argv;
if(count($variables) == 2) {
	$TYPE = $variables[1];
    if((int)$TYPE == 1) {   
        $queryApplicant = "SELECT * FROM applicant WHERE type = 0 AND (name = '' or name IS NULL)";
        $resultApplicant = $con->query($queryApplicant);
        if($resultApplicant && $resultApplicant->num_rows > 0) {
            while($row = $resultApplicant->fetch_object()){
                $eeName = $row->original_name;
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

                $queryUpdate = "UPDATE applicant SET name = '".$con->real_escape_string($eeName)."' WHERE applicant_id = ".$row->applicant_id;
                echo $queryUpdate ;
                $con->query($queryUpdate);
            }
        }
    } else if((int)$TYPE == 2) {   
        $queryApplicant = "SELECT * FROM applicant WHERE type = 1 AND (name = '' or name IS NULL)";
        $resultApplicant = $con->query($queryApplicant);
        if($resultApplicant && $resultApplicant->num_rows > 0) {
            while($row = $resultApplicant->fetch_object()){
                $eeName = $row->given_name;
                if($row->given_name != "") {
                    $eeName .= " ".$row->middle_name;
                }
                if($row->family_name != "") {
                    $eeName .= " ".$row->family_name;
                }
                $eeName = trim($eeName);
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

                $queryUpdate = "UPDATE applicant SET name = '".$con->real_escape_string($eeName)."' WHERE applicant_id = ".$row->applicant_id;
                echo $queryUpdate ;
                $con->query($queryUpdate);
            }
        }
    } else if((int)$TYPE == 3){
        $queryAssignee = "SELECT * FROM assignee WHERE (name = '' or name IS NULL)";
        $resultAssignee = $con->query($queryAssignee);
        if($resultAssignee && $resultAssignee->num_rows > 0) {
            while($row = $resultAssignee->fetch_object()){
                $eeName = $row->original_name;
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

                $queryUpdate = "UPDATE assignee SET name = '".$con->real_escape_string($eeName)."' WHERE assignee_id = ".$row->assignee_id;
                echo $queryUpdate ;
                $con->query($queryUpdate);
            }
        }
    }
}


