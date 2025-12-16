<?php 


ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000);
/* foreach(glob('/var/www/html/beta/resources/shared/data/assignment*.pdf') as $fileName){
	$fileRemove = str_replace(".pdf", "", $fileName );
	if(!file_exists($fileRemove.'_agreement.pdf')) {
		echo $fileName;
		exec('python3 /var/www/html/python_script/split_pdf_v4.py "'.$fileName.'" "/var/www/html/beta/resources/shared/data/" "/var/www/html/beta/resources/shared/data/"');
	}	
} */

?>