<?php 
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
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbUSPTO);

$query = "SELECT appNo FROM (SELECT MAX(appno_doc_num) AS appNo,  MAX(grant_doc_num) AS patent FROM documentid where appno_doc_nnum = '17052655'  GROUP BY appno_doc_num) AS temp WHERE patent = ''";

$resultAssets = $con->query($query);
if($resultAssets && $resultAssets->num_rows > 0) {
    $allAssets = array();
    while($assets = $resultAssets->fetch_object()){
        array_push($allAssets, '"'.$assets->appNo.'"');
    }


    

    $queryGrant = "SELECT appno_doc_num, appno_date, grant_doc_num, grant_date FROM db_patent_application_bibliographic.application_grant WHERE appno_doc_num IN (".implode(',', $allAssets).") GROUP BY appno_doc_num";
    echo "AAA: ".$queryGrant."<br/>";
    $resultGrant = $con->query($queryGrant);

    if($resultGrant && $resultGrant->num_rows > 0) {
        echo "COUNT: ".$resultGrant->num_rows. "<br/>";
        while($row = $resultGrant->fetch_object()){ 
            echo $row->appno_date."<br/>";
            if($row->appno_date != '' && $row->appno_date != null) {
                $date = new DateTime($row->appno_date);
                echo "DATE: ".$date->format('Y')."<br/>";
                if($date->format('Y') >= 1997) {

                    $grantNo = ltrim($row->grant_doc_num, "0"); 
        
                    echo "UPDATE documentid SET grant_doc_num = '".$grantNo."', grant_date = '".$row->grant_date."' WHERE appno_doc_num = '".$row->appno_doc_num."'";
                    die;
        
                    //$con->query("UPDATE documentid SET grant_doc_num = '".$grantNo."', grant_date = '".$row->grant_date."' WHERE appno_doc_num = '".$row->appno_doc_num."'");
                }

            }
        }
    }
}