<?php 

ini_set('max_execution_time', '0');
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbApplication);
 

$queryOrganisation = "SELECT appno_doc_num, grant_doc_num FROM db_new_application.assets WHERE layout_id = 15 AND organisation_id = 177 AND company_id = 41 GROUP BY appno_doc_num";
		
//echo $queryOrganisation."<br/>";
$resultOrg = $con->query($queryOrganisation);
//echo $resultOrg->num_rows."<br/>";

if($resultOrg && $resultOrg->num_rows > 0) {	
	while($row = $resultOrg->fetch_object()) { 
		//exec('php -f /var/www/html/trash/create_data_for_company_db_application.php "'.$row->organisation_id.'" ""');
        if($row->appno_doc_num != '' && $row->appno_doc_num != null) {
            exec('node /var/www/html/script/update_application_title.js '.$row->appno_doc_num.'', $output, $return); 
            print_r($output); 
        }

        if($row->grant_doc_num != '' && $row->grant_doc_num != null) {
            exec('node /var/www/html/script/update_grant_title.js '.$row->grant_doc_num.'', $output1, $return1); 
            print_r($output1); 
        }
	}
}