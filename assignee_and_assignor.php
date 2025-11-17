<?php 
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
ini_set('max_execution_time', '0');

$con->query("SET FOREIGN_KEY_CHECKS = 0");
/*$query = "SELECT d.name FROM company_temp AS d GROUP By d.name";
$result = $con->query($query);

if($result ) {
	$i = 0;
	while($row = $result->fetch_object()){
		try{
			$queryGroup = 'SELECT name, sum(instances) as instances FROM company_temp WHERE name = "'.$con->real_escape_string($row->name).'" GROUP BY name';
			
				echo $queryGroup."<br/>";
			
			$resultN = $con->query($queryGroup);
			if($resultN){
				$getData = $resultN->fetch_object();
				echo "<pre>"; 
				print_r($getData);
				
				
				$checkQuery = 'SELECT * FROM assignor_and_assignee WHERE name = "'.$con->real_escape_string($getData->name).'"';
				$resultCheck = $con->query($checkQuery);
				if($resultCheck){
					$newID = 0;
					if($resultCheck->num_rows > 0) {
						echo "<br/>FOUND:".$resultCheck->num_rows."<br/>";
						$row = $resultCheck->fetch_object();
						$newID = $row->assignor_and_assignee_id;
						$con->query('UPDATE assignor_and_assignee SET instances = '.((int)$checkQuery->instances +  (int) $getData->instances).' WHERE id = '.$newID);
					} else {
						
						$queryInsert = 'INSERT IGNORE INTO assignor_and_assignee (name, instances) SELECT name, sum(instances) as instances FROM company_temp WHERE name = "'.$con->real_escape_string($row->name).'" GROUP BY name';
						echo $queryInsert."<br/>";
						$con->query($queryInsert);
				
						$newID = $con->insert_id;
					}
					echo "NEW ID:".$newID."<br/>";
					if($newID > 0) {
						$con->query('UPDATE assignee SET assignor_and_assignee_id = '.$newID.' WHERE ee_name="'.$con->real_escape_string($getData->name).'"');
						
						$con->query('UPDATE assignor SET assignor_and_assignee_id = '.$newID.' WHERE or_name="'.$con->real_escape_string($getData->name).'"');
					}				
				}		
			}
		}catch(Exception $e){
			
		}
		$i++;
	}
}*/


$query = "SELECT assignor_and_assignee_id, name FROM assignor_and_assignee ORDER BY assignor_and_assignee_id ASC";
$result = $con->query($query);

if($result) {
	$i = 0;
	while($row = $result->fetch_object()){
		$con->query('UPDATE assignee SET assignor_and_assignee_id = '.$row->assignor_and_assignee_id.' WHERE ee_name="'.$con->real_escape_string($row->name).'"');
				
		$con->query('UPDATE assignor SET assignor_and_assignee_id = '.$row->assignor_and_assignee_id.' WHERE or_name="'.$con->real_escape_string($row->name).'"');
		
		echo $i.": ".$row->name.": ".$row->assignor_and_assignee_id.'\n';
		$i++;
	}
}
$con->query("SET FOREIGN_KEY_CHECKS = 1");
?>
