<?php 


ini_set('max_execution_time', 0);
$startDate = '2005-01-04';
$week = 01;
$endDate = '2005-12-27';
while(1==1) {
	if(strtotime($startDate) <= strtotime($endDate)){
		$date = new DateTime($startDate);
		$fileName = 'ipg'.$date->format('ymd').'.zip';
		$zip_file = './grant/'.$fileName;
		echo $zip_file."<br/>"; 
		$zip = zip_open($zip_file);
		if (is_resource($zip)) {
			// consider zip file opened successfully
			exec('unzip '.$zip_file);
			$xmlFile = 'ipg'.$date->format('ymd').'.xml';
			if(file_exists($xmlFile)){
				echo $xmlFile."<br/>";
				rename($xmlFile, './extract_files/'.$xmlFile);
				if(file_exists('./extract_files/'.$xmlFile)){
					echo "FILE EXIST<br/>";
					unset($zip_file);
				}
			} else {
				echo "FILE NOT EXIST<br/>";
			}
		}
		$date->modify('next tuesday');
		$startDate = $date->format('Y-m-d');
	} else {
		break;
	}
}

?>
