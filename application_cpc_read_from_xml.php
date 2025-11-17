<?php 

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbAppBiblio = getenv('DB_APPLICATION_BIBLIO');
$dbGrantBiblio = getenv('DB_GRANT_BIBLIO');

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000);

$con = new mysqli($host,$user,$password,$dbGrantBiblio);
foreach(glob('/mnt/volume_sfo2_12/DOWNLOAD/US_PGPub_CPC_MCF_XML_2025-05-01/*.xml') as $fileName){
	echo $fileName."<br/>";
	$getFileContent = file_get_contents($fileName);
	
	if($getFileContent) {
		try{
			$xml = simplexml_load_string($getFileContent);
			if ($xml !== false) {
				$xmlObject = new SimpleXMLElement($getFileContent);	
				$xmlObject->registerXPathNamespace('com', 'http://www.wipo.int/standards/XMLSchema/ST96/Common');
				$xmlObject->registerXPathNamespace('pat', 'http://www.wipo.int/standards/XMLSchema/ST96/Patent');
				$cpcMasterClassificationRecord = $xmlObject->xpath('//uspat:CPCMasterClassificationRecord');
				if(count($cpcMasterClassificationRecord)>0){
					$cpc = array();
					echo "START<br/>";
					foreach($cpcMasterClassificationRecord as $masterClassification){						
						$applicationNumber = "";
						$patentNumber = "";
						$country = "";
						$patentKindCode = "";
						$patentGrantDate = "";
						
						$applicationIdentification = $masterClassification->xpath('pat:ApplicationIdentification');
						if($applicationIdentification != null) {
							
							$ipoOfficeCode = $applicationIdentification[0]->xpath('com:IPOfficeCode');
							if($ipoOfficeCode != null) {
								$country = (string)$ipoOfficeCode[0][0];
							}
							$applicationNumberObject = $applicationIdentification[0]->xpath('com:ApplicationNumber');
							if($applicationNumberObject != null){
								$appNumberText = $applicationNumberObject[0]->xpath('com:ApplicationNumberText');
								
								if($appNumberText != null) {
									$applicationNumber = (string)$appNumberText[0][0];
								}
							}							
						}						
						
						$patentClassificationBag = $masterClassification->xpath('pat:CPCClassificationBag');
						
						if($patentClassificationBag != null) {
							$patMainCPCList = $patentClassificationBag[0]->xpath('pat:MainCPC');
							$patFurtherCPC = $patentClassificationBag[0]->xpath('pat:FurtherCPC');
							
							if($patMainCPCList != null) {
								foreach($patMainCPCList as $patMainCPC)	{
									$patCPCClassification = $patMainCPC->xpath('pat:CPCClassification');
									if($patCPCClassification != null) {
										$classificationVersionDate = null;
										$section = null;
										$class = null;
										$subClass = null;
										$mainGroup = null;
										$subGroup = null;
										$symbolPositionCode = null;
										$cpcClassificationValueCode = null;
										$classificationVersionDateObj = $patCPCClassification[0]->xpath('pat:ClassificationVersionDate');
										if($classificationVersionDateObj != null) {
											$classificationVersionDate = (string)$classificationVersionDateObj[0][0];
										}
										$sectionObj = $patCPCClassification[0]->xpath('pat:CPCSection');
										if($sectionObj != null) {
											$section = (string)$sectionObj[0][0];
										}
										$classObj = $patCPCClassification[0]->xpath('pat:Class');
										if($classObj != null) {
											$class = (string)$classObj[0][0];
										}
										$subClassObj = $patCPCClassification[0]->xpath('pat:Subclass');
										if($subClassObj != null) {
											$subClass = (string)$subClassObj[0][0];
										}
										$mainGroupObj = $patCPCClassification[0]->xpath('pat:MainGroup');
										if($mainGroupObj != null) {
											$mainGroup = (string)$mainGroupObj[0][0];
										}
										$subGroupObj = $patCPCClassification[0]->xpath('pat:Subgroup');
										if($subGroupObj != null) {
											$subGroup = (string)$subGroupObj[0][0];
										}
										$symbolPositionCodeObj = $patCPCClassification[0]->xpath('pat:SymbolPositionCode');
										if($symbolPositionCodeObj != null) {
											$symbolPositionCode = (string)$symbolPositionCodeObj[0][0];
										}
										$cpcClassificationValueCodeObj = $patCPCClassification[0]->xpath('pat:CPCClassificationValueCode');
										if($cpcClassificationValueCodeObj != null) {
											$cpcClassificationValueCode = (string)$cpcClassificationValueCodeObj[0][0];
										}
										
										array_push($cpc, array('application_number'=>$applicationNumber,'classification_version_date'=>$classificationVersionDate, 'section'=>$section,'class'=>$class,'sub_class'=>$subClass,'main_group'=>$mainGroup,'sub_group'=>$subGroup,'symbol_position_code'=>$symbolPositionCode,'classification_value_code'=>$cpcClassificationValueCode,'type'=>0));
									}
								}
							}
							
							if($patFurtherCPC != null) {
								$patCPCClassificationList = $patFurtherCPC[0]->xpath('pat:CPCClassification');
								foreach($patCPCClassificationList as $patCPCClassification)	{
									
									if($patCPCClassification != null) {
										$classificationVersionDate = null;
										$section = null;
										$class = null;
										$subClass = null;
										$mainGroup = null;
										$subGroup = null;
										$symbolPositionCode = null;
										$cpcClassificationValueCode = null;
										$classificationVersionDateObj = $patCPCClassification->xpath('pat:ClassificationVersionDate');
										if($classificationVersionDateObj != null) {
											$classificationVersionDate = (string)$classificationVersionDateObj[0][0];
										}
										$sectionObj = $patCPCClassification->xpath('pat:CPCSection');
										if($sectionObj != null) {
											$section = (string)$sectionObj[0][0];
										}
										$classObj = $patCPCClassification->xpath('pat:Class');
										if($classObj != null) {
											$class = (string)$classObj[0][0];
										}
										$subClassObj = $patCPCClassification->xpath('pat:Subclass');
										if($subClassObj != null) {
											$subClass = (string)$subClassObj[0][0];
										}
										$mainGroupObj = $patCPCClassification->xpath('pat:MainGroup');
										if($mainGroupObj != null) {
											$mainGroup = (string)$mainGroupObj[0][0];
										}
										$subGroupObj = $patCPCClassification->xpath('pat:Subgroup');
										if($subGroupObj != null) {
											$subGroup = (string)$subGroupObj[0][0];
										}
										$symbolPositionCodeObj = $patCPCClassification->xpath('com:SymbolPositionCode');
										if($symbolPositionCodeObj != null) {
											$symbolPositionCode = (string)$symbolPositionCodeObj[0][0];
										}
										$cpcClassificationValueCodeObj = $patCPCClassification->xpath('pat:CPCClassificationValueCode');
										if($cpcClassificationValueCodeObj != null) {
											$cpcClassificationValueCode = (string)$cpcClassificationValueCodeObj[0][0];
										}
										
										array_push($cpc, array('application_number'=>$applicationNumber,'classification_version_date'=>$classificationVersionDate, 'section'=>$section,'class'=>$class,'sub_class'=>$subClass,'main_group'=>$mainGroup,'sub_group'=>$subGroup,'symbol_position_code'=>$symbolPositionCode,'classification_value_code'=>$cpcClassificationValueCode,'type'=>1));
									}
								}
							}
						}				
					}
					//add('patent_cpc', $cpc, $con);
					$sql = "INSERT IGNORE INTO application_cpc(application_number, classification_version_date, section, class, sub_class, main_group, sub_group, symbol_position_code, classification_value_code, type) VALUES ";
					
					foreach($cpc as $postValues){
						$sql .= "('".$con->real_escape_string(stripslashes($postValues['application_number']))."', '".$con->real_escape_string(stripslashes($postValues['classification_version_date']))."', '".$con->real_escape_string(stripslashes($postValues['section']))."', '".$con->real_escape_string(stripslashes($postValues['class']))."', '".$con->real_escape_string(stripslashes($postValues['sub_class']))."', '".$con->real_escape_string(stripslashes($postValues['main_group']))."', '".$con->real_escape_string(stripslashes($postValues['sub_group']))."', '".$con->real_escape_string(stripslashes($postValues['symbol_position_code']))."', '".$con->real_escape_string(stripslashes($postValues['classification_value_code']))."', '".$con->real_escape_string(stripslashes($postValues['type']))."'), ";
					}
					
					$sql =substr($sql,0,-2);
					$con->query($sql);
					unlink($fileName);
				}	
			}
		} catch (Exception $e) {
			
		}
	}
}
