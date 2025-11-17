<?php
$query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID;	
echo $query;
$result = $GLOBALS['mainConnection']->query($query);
echo $result->num_rows;
if($result && $result->num_rows > 0) {  
    $row = $result->fetch_object();
    $orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
    if($orgConnect) {
     	$GLOBALS['orgConnect'] = $orgConnect; 
    }
}