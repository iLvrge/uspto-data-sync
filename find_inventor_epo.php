<?php 

require_once('epo_class.php');


ignore_user_abort(true);
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);

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
	$representativeID = $variables[2];
	$queryOrg = "SELECT org_host, org_usr, org_pass, org_db, logo, name FROM db_business.organisation WHERE organisation_id = ".(int)$organisationID;
	$resultOrg = $con->query($queryOrg);
	if($resultOrg && $resultOrg->num_rows > 0){
		$row = mysqli_fetch_object($resultOrg);
		if($row->org_host != null && $row->org_usr != null && $row->org_pass != null && $row->org_db != null){
			$orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
			
			if($orgConnect) {
				$queryRepresentative = "SELECT representative_name FROM representative WHERE representative_id = ".$representativeID;
				
				$resultRepresentative = $orgConnect->query($queryRepresentative);
				
				if($resultRepresentative && $resultRepresentative->num_rows > 0) {
					$representative = $resultRepresentative->fetch_object();
					$name = $representative->representative_name;
						
					$query = "SELECT appno_doc_num, grant_doc_num FROM ".$dbUSPTO.".documentid INNER JOIN (
						SELECT rf_id FROM ".$dbUSPTO.".representative 
						INNER JOIN ".$dbUSPTO.".assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
						INNER JOIN ".$dbUSPTO.".assignee ON assignee.assignor_and_assignee_id = assignor_and_assignee.assignor_and_assignee_id
						WHERE representative_name = '".$con->real_escape_string($name)."'
						UNION 
						SELECT rf_id FROM ".$dbUSPTO.".representative 
						INNER JOIN ".$dbUSPTO.".assignor_and_assignee ON assignor_and_assignee.representative_id = representative.representative_id
						INNER JOIN ".$dbUSPTO.".assignor ON assignor.assignor_and_assignee_id = assignor_and_assignee.assignor_and_assignee_id
						WHERE representative_name = '".$con->real_escape_string($name)."'
						
					) as temp1 ON temp1.rf_id = documentid.rf_id 
					WHERE status = 0
					GROUP BY appno_doc_num, grant_doc_num";
					
					if($query) {
						$resultAssets = $con->query($query);
						if($resultAssets && $resultAssets->num_rows > 0) {
							$epodoc = new EpoDoc();
			
							$epoDocToken = $epodoc->read_token('HedCET');	
							$publication = 'publication';
							
							$db = 'epodoc';
							echo "==========================NO_OF_ROWS:".$resultAssets->num_rows."========================" ;
							while( $rowAsset = $resultAssets->fetch_object()) {
								$assetType = 1;
								$asset = $rowAsset->grant_doc_num;
								if( $asset == null || $asset == '' ) {
									$assetType = 0;
									$asset = $rowAsset->appno_doc_num;
								}
								
								if( $asset != '' && $asset != null ) {
									
									if($assetType == 0) {
										$getBiblioData = $epodoc->runUrl($epoDocToken,'published-data',$publication,$db,'US'.$asset."A",'biblio','');
									} else {
										$getBiblioData = $epodoc->runUrl($epoDocToken,'published-data',$publication,$db,'US'.$asset,'biblio','');
										if(empty($getBiblioData['error'])){
											if(!empty($getBiblioData['data'])){
												$xml=simplexml_load_string($getBiblioData['data']);
												if ($xml !== false) {
													$xmlObject = new SimpleXMLElement($getBiblioData['data']);
													if(isset($xmlObject->code) && (($xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found") || $xmlObject->code=="CLIENT.InvalidReferenceFormat")  ){
														$getBiblioData = $epodoc->runUrl($epoDocToken,'published-data',$publication,$db,'US'.$rowAsset->appno_doc_num."A",'biblio','');
													}
												}
											}
										}
									}
									$insert = false;
									
									if(isset($getBiblioData) && !empty($getBiblioData['data'])){
										$xml=simplexml_load_string($getBiblioData['data']);
										if ($xml !== false) {
											$xmlObject = new SimpleXMLElement($getBiblioData['data']);
											
											if(isset($xmlObject->code) && (($xmlObject->code=="SERVER.EntityNotFound" &&  isset($xmlObject->message) && $xmlObject->message=="No results found") || $xmlObject->code=="CLIENT.InvalidReferenceFormat")  ){
												$insert = false;
											} else {
												$insert = true;
											}
										} else {
											$insert = false;
										}
									}
									if($insert === true){
										print_r($xmlObject);
										die;
										/*$worldPatentData = $xmlObject->xpath('//ops:world-patent-data');
										if(isset($worldPatentData[0])){
									
										}*/
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