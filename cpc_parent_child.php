<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbGrantBiblio = getenv('DB_GRANT_BIBLIO');
$con = new mysqli($host, $user, $password, $dbGrantBiblio);


$query = 'SELECT cpc_code FROM cpc_defination WHERE hierarchical_structure IS NULL';	
$result = $con->query($query);
if($result && $result->num_rows > 0) {
	while($row = $result->fetch_object()) {
		$code = $row->cpc_code;
		
		if(strpos($code, '/') !== false) {
			$code = str_replace('+', '%20',urlencode(substr($code, 0, 4).' '. substr($code, 4)));
		}
		$url = 'https://data.epo.org/linked-data/query?query=prefix%20cpc%3A%20%3Chttp%3A%2F%2Fdata.epo.org%2Flinked-data%2Fdef%2Fcpc%2F%3E%0Aprefix%20dcterms%3A%20%3Chttp%3A%2F%2Fpurl.org%2Fdc%2Fterms%2F%3E%0Aprefix%20ipc%3A%20%3Chttp%3A%2F%2Fdata.epo.org%2Flinked-data%2Fdef%2Fipc%2F%3E%0Aprefix%20mads%3A%20%3Chttp%3A%2F%2Fwww.loc.gov%2Fstandards%2Fmads%2Frdf%2Fv1.rdf%3E%0Aprefix%20owl%3A%20%3Chttp%3A%2F%2Fwww.w3.org%2F2002%2F07%2Fowl%23%3E%0Aprefix%20patent%3A%20%3Chttp%3A%2F%2Fdata.epo.org%2Flinked-data%2Fdef%2Fpatent%2F%3E%0Aprefix%20rdf%3A%20%3Chttp%3A%2F%2Fwww.w3.org%2F1999%2F02%2F22-rdf-syntax-ns%23%3E%0Aprefix%20rdfs%3A%20%3Chttp%3A%2F%2Fwww.w3.org%2F2000%2F01%2Frdf-schema%23%3E%0Aprefix%20skos%3A%20%3Chttp%3A%2F%2Fwww.w3.org%2F2004%2F02%2Fskos%2Fcore%23%3E%0Aprefix%20st3%3A%20%3Chttp%3A%2F%2Fdata.epo.org%2Flinked-data%2Fdef%2Fst3%2F%3E%0Aprefix%20text%3A%20%3Chttp%3A%2F%2Fjena.apache.org%2Ftext%23%3E%0Aprefix%20vcard%3A%20%3Chttp%3A%2F%2Fwww.w3.org%2F2006%2Fvcard%2Fns%23%3E%0Aprefix%20xsd%3A%20%3Chttp%3A%2F%2Fwww.w3.org%2F2001%2FXMLSchema%23%3E%0A%0ASELECT%20%3FbroaderCPC%20%3Ftitle%20%7B%0A%20%20%3FCPC%20rdf%3Atype%2Frdfs%3AsubClassOf%20cpc%3AClassification.%0A%20%3FCPC%20rdfs%3Alabel%20%22'.$code.'%22.%20%20%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%0A%20%20%3FCPC%20skos%3Abroader*%20%3FbroaderCPC.%0A%20%20%3FbroaderCPC%20dcterms%3Atitle%20%3Ftitle%0A%7D%0AORDER%20BY%20ASC(%3FbroaderCPC)%0ALIMIT%2020%0A&output=json';
		
		echo $url;
		
		$getData = sendCPCRequest($url);
		
		if($getData != '' && $getData != null) {
			try{				
				$getData = JSON_decode($getData, true);
				if(isset($getData['results']) && isset($getData['results']['bindings'])) {
					$resultBindings = $getData['results']['bindings'];
					
					if(count($resultBindings) > 0) {
						$childParents = array();
						foreach($resultBindings as $binding) {
							array_push($childParents, array('cpc_code'=>str_replace('http://data.epo.org/linked-data/def/cpc/', '', $binding['broaderCPC']['value']), 'title'=>$binding['title']['value']));
						}
						echo "UPDATE cpc_defination SET hierarchical_structure = '".$con->real_escape_string(JSON_encode($childParents))."' WHERE cpc_code = '".$row->cpc_code."'<br/>";
						$con->query("UPDATE cpc_defination SET hierarchical_structure = '".$con->real_escape_string(JSON_encode($childParents))."' WHERE cpc_code = '".$row->cpc_code."'");
						
						sleep(2);
					}
				}	
			}catch(Exception $e) {
				
			}
		}
			
	}	
}

function sendCPCRequest($url) {
	$ch = curl_init();
	$curlConfig = array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true
	);
	curl_setopt_array($ch, $curlConfig);
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}