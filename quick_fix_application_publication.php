<?php 
 ini_set('max_execution_time', '0');
 
 
 $con = new mysqli('167.172.195.92', 'db_user_all', 'wDv%5tgn0O0kMkMN', 'db_patent_grant_bibliographic');
 
 
 
 if($con) {
	$query = 'SELECT appno_doc_num, pgpub_doc_num FROM application_publication WHERE file_name IS NULL OR file_name = ""';
	 
	$result = $con->query($query);
	if($result && $result->num_rows > 0) {  
		while($row = $result->fetch_object()) {
			$query1 = "SELECT file_name FROM inventor WHERE appno_doc_num = '".$row->appno_doc_num."' LIMIT 1";
			$result1 = $con->query($query1);
			if($result1 && $result1->num_rows > 0) {
				$rowInventor = $result1->fetch_object();		
				echo "UPDATE application_publication SET file_name = '".$rowInventor->file_name."' WHERE appno_doc_num = '".$row->appno_doc_num."' AND pgpub_doc_num = '".$row->pgpub_doc_num."'\n";
				$con->query("UPDATE application_publication SET file_name = '".$rowInventor->file_name."' WHERE appno_doc_num = '".$row->appno_doc_num."' AND pgpub_doc_num = '".$row->pgpub_doc_num."'");
			}
		}
	}
 }