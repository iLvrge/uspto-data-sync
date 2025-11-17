<?php
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1);
 ini_set('display_startup_errors', 1);
 error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000);
foreach(glob('./extract_application_files/*.xml') as $fileName){
	echo $fileName."<br/>";
	$getFileContent = file_get_contents($fileName);
	
	if($getFileContent) {
		try{
			
			$xml = simplexml_load_file(
				sprintf(
					'<?xml version="1.0" encoding="UTF-8"?>%s' .
					'<!DOCTYPE >%s' . 
					'<roots>%s</roots>',
					PHP_EOL, 
					PHP_EOL, 
					str_replace(
						array(
							'<?xml version="1.0" encoding="UTF-8"?>', 
							'<!DOCTYPE patent-application-publication SYSTEM "pap-v15-2001-01-31.dtd" []>'
						),
						'',
						$getFileContent
					)
				) 
			);
			//$xml = simplexml_load_file($fileName);
			if ($xml !== false) {
				//$xmlObject = new SimpleXMLElement($getFileContent);	
				echo "<pre>";
				print_r($xml);
				
			}
			die;
		}catch(Exception $e){
			print_r($e);
		}
	}
}
function get_reader($file){
  $reader = new XMLReader;
  $reader->open($file);
  return $reader;
}
?>