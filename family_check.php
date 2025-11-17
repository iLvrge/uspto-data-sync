<?php 
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);
/*$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');*/
$dbApplication = getenv('DB_APPLICATION_DB');
$password = getenv('DB_RT_PWD');



$con = new mysqli("localhost","root",$password,$dbApplication);


/*$query  = "Select * from ".$dbUSPTO.".family_flag LIMIT 20";*/
$query  = "Select * from family_flag";

$result = $con->query($query);


if($result) {
	
	while($row = $result->fetch_object()) {
		if($row->family != null) {
			$getFamilyList = json_decode($row->family);
			echo "<pre>";
			echo "============================NEW================================<br/>";
			print_r($getFamilyList);
			echo "</pre>";
			if(count($getFamilyList) > 0) {
				foreach($getFamilyList as $family) {
					echo "KIND:  ".$family->kind."<br/>";
					if($family->country == 'US' && strpos($family->kind, 'B') !== false){
						echo "FIND KIND:  ".$family->kind."<br/>";
						$patentNumber = $family->patent_number;
						$applicationNUmber = $family->application_number;
						if($patentNumber != '') {
							$queryP = "Select * from db_uspto.patent_family_member where patent_number = '".$patentNumber."'";
							echo $queryP."<br/>";
							$resultP = $con->query($queryP);
							
							if($resultP) {
								while($rowP = $resultP->fetch_object()) {
									echo "A".$rowP->claims."B<br/>";
									$totalClaims =  array();
									if($rowP->claims != '') {
										$totalClaims = json_decode($rowP->claims);
										echo "COUNT: 	".count($totalClaims)."<br/>";
									}
									if( count($totalClaims) == 0 ) {
										$queryD = "Select appno_doc_num from db_uspto.documentid as d where d.grant_doc_num = '".$patentNumber."'";
										
										$resultD = $con->query($queryD);
										
										if($resultD && $resultD->num_rows > 0) {
											$rowA = $resultD->fetch_object();
											
											$queryC = "SELECT * FROM db_patent_grant_bibliographic.application_claims WHERE appno_doc_num = '".$rowA->appno_doc_num."'";
											echo $queryC."<br/>";
											$resultD = $con->query($queryC);
											
											if($resultD && $resultD->num_rows > 0) {
												$allClaims = [];
												while($rowC = $resultD->fetch_object()) {
													array_push($allClaims, $rowC->text);
												}
												
												if(count($allClaims) > 0) {
													updateData("db_uspto", "patent_family_member", $rowP->id, array('claims'=>json_encode($allClaims)), $con);
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
}


function updateData($dbUSPTO, $tableName, $id, $postValues, $con) {
	$stringName ="";
	foreach($postValues as $key=>$value){
		$stringName .=$key."='".$con->real_escape_string($value)."',";
	}
	$stringName = substr($stringName,0,-1);
	$sql = "UPDATE ".$dbUSPTO.".".$tableName." SET ".$stringName." WHERE id = ".$id;	
	echo $sql."<br/>";
	$result = $con->query($sql);
	if($result){
		return $id;
	} else {
		return 0;
	}
}