<?php 
ini_set('max_execution_time', 0);
ignore_user_abort(true);
$dir    = '/var/www/html/PatenTrack/resources/shared/data/';
$files = scandir($dir);
if(count($files) > 0) {
	foreach($files as $file){
		if(strpos($file,"form") === false && strpos($file,"agreement") === false && strpos($file,".json") === false){
			if($file != '.' && $file != '..'){
				$newFile  = str_replace('.pdf','_form.pdf',$file);
				if(!file_exists($dir.$newFile)){
					echo $dir.$file."<br/>";
					$command = escapeshellcmd('python3 /var/www/html/python_script/split_pdf_v3.py '.$dir.$file.' '.$dir.' '.$dir);
					$output = shell_exec($command);				
					echo "<pre>$output</pre>";
				}				
			}
		}
	}
}
?>
