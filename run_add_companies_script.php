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
$con = new mysqli($host, $user, $password, $dbUSPTO);
$variables = $argv;
//$variables = $_GET;
if(count($variables) == 3) {
	$organisationID = $variables[1];
	
	if($organisationID > 0) {
		$companies = $variables[2];
		
		if($companies  != "") {
			$representatives = json_decode($companies);
			echo "COUNT:".count($representatives);
			print_r($representatives);
			if(count($representatives) > 0) {
				$companiesData = array();
				$query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID;	
				$result = $con->query($query);
				if($result && $result->num_rows > 0) {
					while($row = $result->fetch_object()) {
						$orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
						if($orgConnect) {
							$queryRepresentative = "SELECT original_name, representative_name FROM representative WHERE representative_id IN (".implode(',', $representatives).")";
					echo $queryRepresentative ;
							$resultRepresentative = $orgConnect->query($queryRepresentative);			
							echo $resultRepresentative->num_rows;
							if($resultRepresentative && $resultRepresentative->num_rows > 0) {
								while($representative = $resultRepresentative->fetch_object()){
									$name = $representative->representative_name == '' ? $representative->original_name : $representative->representative_name;
									array_push($companiesData , '"'.$orgConnect->real_escape_string($name).'"');
								}
							}
						}
					}
				}
				print_r($companiesData);
				foreach($companiesData as $representative) {
					echo $representative."<br/>";
					echo "php -f /var/www/html/trash/add_representative_rfids.php ".$organisationID." ".$representative."<br/>";
					exec('php -f /var/www/html/trash/add_representative_rfids.php '.$organisationID.' '.$representative);
					echo "php -f /var/www/html/trash/create_data_for_company_db_application.php ".$organisationID." ".$representative."<br/>";
					exec('php -f /var/www/html/trash/create_data_for_company_db_application.php '.$organisationID.' '.$representative.' 1', $create_data_for_company_db_application, $return);
					print_r($create_data_for_company_db_application);
					//echo 'php -f /var/www/html/trash/report_represetative_assets_transactions_by_account.php '.$organisationID.' '.$representative."<br/>";
					//exec('php -f /var/www/html/trash/report_represetative_assets_transactions_by_account.php '.$organisationID.' '.$representative);
					echo 'php -f /var/www/html/trash/admin_report_represetative_assets_transactions_by_account.php '.$organisationID.' '.$representative."<br/>";
					exec('php -f /var/www/html/trash/admin_report_represetative_assets_transactions_by_account.php '.$organisationID.' '.$representative, $admin_report_represetative_assets_transactions_by_account, $return);
					print_r($admin_report_represetative_assets_transactions_by_account);
				}
				if(count($companiesData) > 0) {

					//exec("php -f /var/www/html/trash/download_all_pdf.php ".$organisationID);
				}
			}
			
		}
	}
}
?>