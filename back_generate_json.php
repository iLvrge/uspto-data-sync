<?php
ini_set('max_execution_time', '0');
function findBoxData($all_boxes,$box_type){
	$boxArray = array();
	if(count($all_boxes) > 0){
		foreach($all_boxes as $box){
			if($box->id == $box_type){
				array_push($boxArray,$box);
				break;
			}
		}
	}
	return $boxArray;
}

function findConnectionTo($patentNumber, $assignmentNo, $con){
	$connectionToRelations = array();
	$queryRelation = "SELECT a.connection_to FROM lead_assignment_headings as a WHERE a.patent_number = '".$patentNumber."' AND a.assignment_no = '".$assignmentNo."' AND a.connection_to > 0";
	$resultRelation = $con->query($queryRelation);
	
	if($resultRelation && mysqli_num_rows($resultRelation) > 0){
		$data = mysqli_fetch_object($resultRelation);
		
		$queryAssignmentRelationData = "SELECT DISTINCT(r.id),r.patent_number,r.parent_id,r.child_id,r.connection_type,r.reel,r.frame,r.description,r.date,r.assignment_no,'' as note_file,'' as note FROM lead_patent_assigment_relation as r WHERE r.patent_number = '".$patentNumber."' AND r.assignment_no = '".$data->connection_to."'";
		
		$resultAssignmentRelation = $con->query($queryAssignmentRelationData);
		
		if($resultAssignmentRelation && mysqli_num_rows($resultAssignmentRelation) > 0){
			while($row = mysqli_fetch_object($resultAssignmentRelation)){
				array_push($connectionToRelations, $row);
			}
		}
	}
	
	return $connectionToRelations;
}

function findIndex($array,$findValue){
	$findArray = array();
	foreach($array as $list){
		if($list->id == $findValue){
			array_push($findArray,$list);
			break;
		}
	}
	return $findArray;
}

function findLineColorType($type,$list){
	$data = array('color'=>'','line_type'=>'','tooltip'=>'');
	foreach($list as $line){
		if($line->id == $type){
			$data['color'] = $line->color;
			$data['line_type'] = $line->type;
			$data['tooltip'] = $line->tooltip;
			break;
		}
	}
	return $data;
}

$con = new mysqli("167.172.195.92","patent_user","P@t3nt@u5r","db_patentrack");

if (mysqli_connect_errno()) {	
  exit();  
} else {
	if(isset($_REQUEST['p']) && $_REQUEST['p'] != ''){
		$patentNumber = trim($_REQUEST['p']);
		$data = array('box'=>array(),'inventor_boxes'=>array(),'connection'=>array(),'popup'=>array(),'assignments'=>array(),'names'=>array());
		if($patentNumber != null && $patentNumber != '') {
			try{
				$data['general'] = array('background' => '#000000','patent_number'=>$patentNumber,'logo_1'=>'https://patentrack.com/resources/shared/images/company-default.png','logo_2'=>'https://patentrack.com/resources/shared/images/user-default.png','copyright'=>date('Y').' copyright XYZ corporation Inc.');		
				
				if(isset($_REQUEST['o']) && $_REQUEST['o'] > 0) {
					$queryOrg = "SELECT logo, name FROM organisations WHERE id = ".(int)$_REQUEST['o'];
					$resultOrg = $con->query($queryOrg);
					if($resultOrg && mysqli_num_rows($resultOrg) > 0){
						$row = mysqli_fetch_object($resultOrg);
						if($row->logo != "" && $row->logo != null){
							$data['general']['logo_1'] = $row->logo;
						}
						$data['general']['copyright'] = date('Y'). ' '.$row->name;
					}					
				}
				
				if(isset($_REQUEST['u']) && $_REQUEST['u'] > 0) {
					$queryUser = "SELECT logo FROM users WHERE id = ".(int)$_REQUEST['u'];
					$resultUser = $con->query($queryUser);
					if($resultUser && mysqli_num_rows($resultUser) > 0){
						$row = mysqli_fetch_object($resultUser);
						if($row->logo != "" && $row->logo != null){
							$data['general']['logo_2'] = $row->logo;
						}
					}					
				}
				
				$data['box_menu'] = array('border_color'=>array('#e8665d','#e8a41c','#c1ed0e','#ed0e2f'),'background_color'=>array('#fae3e3','#f5f5d7','#d7f0f5','#f5d7dc'));
				
				$getTitle = "SELECT title, comment FROM patents WHERE number = '".mysqli_real_escape_string($con,trim($patentNumber))."'";
				$data['title'] = '';
				$data['comment'] = '';
				$resultTitle = $con->query($getTitle);			
				if($resultTitle && mysqli_num_rows($resultTitle) == 0){	
					$getTitle = "SELECT title, comment FROM patents WHERE application = '".mysqli_real_escape_string($con,trim($patentNumber))."'";
					$resultTitle = $con->query($getTitle);	
				}
				if($resultTitle && mysqli_num_rows($resultTitle) > 0){
					$row = mysqli_fetch_object($resultTitle);
					if($row->title != "" && $row->title != null){
						$data['title'] = $row->title;
						$data['general']['patent_number'] = $patentNumber." ".ucfirst(strtolower($row->title));
					}
					if($row->comment != "" && $row->comment != null){
						$data['comment'] = $row->comment;
					}
				}
				
				
				
				/*switch(strlen($patentNumber)){
					case 9:
						$patentNumber =substr($patentNumber,2);
					break;
					case 11:
					case 13:
						$patentNumber =substr($patentNumber,2);
						$patentNumber =substr($patentNumber,0,-2);
					break;
					default:
						$patentNumber = $patentNumber;
					break;
				}*/
				
				$queryInventorBoxesData = 'SELECT id,patent_number,name,description,date_format(execution_date,"%b %d, %Y") as execution_date,date_format(recorded,"%b %d, %Y") as recorded,recorded as recorded_date,"Inventor" as type,"" as document_file,execution_date as date_1,assignment_no,box_type FROM lead_patent_assignment WHERE patent_number = "'.$patentNumber.'" AND type = 0 ORDER BY id ASC';
				
				$resultInventor = $con->query($queryInventorBoxesData);
				$inventorBoxes = array();
				if($resultInventor && mysqli_num_rows($resultInventor) > 0){
					while($row = mysqli_fetch_object($resultInventor)){
						array_push($inventorBoxes,$row);
					}
				}
				
				$queryOtherBoxesData = 'SELECT id,patent_number,name,description,execution_date,recorded,type,document_file,execution_date as date_1,assignment_no,box_type FROM lead_patent_assignment WHERE patent_number = "'.$patentNumber.'" AND type <> 0 ORDER BY id ASC';
				
				$resultOtherBox = $con->query($queryOtherBoxesData);
				$otherBoxes = array();
				if($resultOtherBox && mysqli_num_rows($resultOtherBox) > 0){
					while($row = mysqli_fetch_object($resultOtherBox)){
						array_push($otherBoxes,$row);
					}
				}
				
				$queryAssignmentData = 'SELECT id,patent_number,name,description,execution_date,recorded,type,reel_no,frame_no,document_file FROM lead_patent_assignment WHERE patent_number = "'.$patentNumber.'" ORDER BY execution_date ASC';
				
				$resultAssignmentData = $con->query($queryAssignmentData);
				
				$list = array();
				if($resultAssignmentData && mysqli_num_rows($resultAssignmentData) > 0){
					while($row = mysqli_fetch_object($resultAssignmentData)){
						array_push($list,$row);
					}
				}
				
				$jsonRelations = array();
				$queryAssignmentRelationData = 'SELECT DISTINCT(r.id),r.patent_number,r.parent_id,r.child_id,creator_id,start_creator_id,r.connection_type,r.reel,r.frame,r.description,r.date,r.assignment_no,"" as note_file,"" as note,r.line_type FROM lead_patent_assigment_relation as r WHERE patent_number = "'.$patentNumber.'" ORDER BY r.date ASC';
				
				$resultAssignmentRelationData = $con->query($queryAssignmentRelationData);
				
				if(mysqli_num_rows($resultAssignmentRelationData)>0){
					while($row = mysqli_fetch_object($resultAssignmentRelationData)){
						array_push($jsonRelations,$row);
					}
				}
				
				$all_boxes = array();
				
				$queryAllBoxes = "SELECT * FROM lead_assignment_box";
				$resultAllBox = $con->query($queryAllBoxes);
				
				if(mysqli_num_rows($resultAllBox) >0){
					while($row = mysqli_fetch_object($resultAllBox)){
						array_push($all_boxes,$row);
					}
				}
				
				$assignment_type_list = array();
				
				$queryAllAssignmentLines = "SELECT id,name as tooltip, color, line_type as type, explanation FROM lead_assignment_heading_list ORDER BY order_no ASC";
				$resultAllLines = $con->query($queryAllAssignmentLines);
				
				if(mysqli_num_rows($resultAllLines)>0){
					while($row = mysqli_fetch_object($resultAllLines)){
						array_push($assignment_type_list,$row);
					}
				}
				
				$assignmentList = array();
				
				$queryAllAssignmentHeadings = "SELECT ah.id,ah.original,ah.original_text,ahl.name as modified,ah.modified as modified_by,ah.assignment_no,ah.connection_to FROM lead_assignment_headings as ah LEFT JOIN lead_assignment_heading_list as ahl ON ahl.id = ah.original_text WHERE ah.patent_number = '".$patentNumber."'";
				$resultAllHeadings = $con->query($queryAllAssignmentHeadings);
				
				if(mysqli_num_rows($resultAllHeadings)>0){
					while($row = mysqli_fetch_object($resultAllHeadings)){
						array_push($assignmentList,$row);
					}
				}
				
				if(count($assignmentList) > 0) {
					for($i=0;$i<count($assignmentList);$i++) {
						$modified = preg_replace("/\"/","",$assignmentList[$i]->modified);
						$original = preg_replace("/\"/","",$assignmentList[$i]->original);
						$assignmentList[$i]->modified = $modified;
						$assignmentList[$i]->original = $original;
					}
				}
				
				
				$all_names = array();
				
				$queryAllAssignmentNames = "SELECT id,original,modified FROM lead_assignment_names WHERE patent_number = '".$patentNumber."'";
				$resultAllNames = $con->query($queryAllAssignmentNames);
				
				if(mysqli_num_rows($resultAllNames)>0){
					while($row = mysqli_fetch_object($resultAllNames)){
						array_push($all_names,$row);
					}
				}
				
				$data['assignments'] = $assignmentList;
				$data['names'] = $all_names;
				$data['legend'] = $assignment_type_list;
				$data['all_boxes'] = $all_boxes;
				$data['inventor_boxes'] = $inventorBoxes;
				if(count($data['inventor_boxes'])>0){ 
					$data['box'] = $data['inventor_boxes'];
				}
				unset($data['inventor_boxes']);
				if(count($otherBoxes)>0){ 
					for($i=0;$i<count($otherBoxes);$i++){
						$type = "";
						if($otherBoxes[$i]->type == 2){
							$type = "3rdParties";
						} else {
							$type = $otherBoxes[$i]->description;
						}
						$recordedDate = "";
						$executionDate = "";
						if($otherBoxes[$i]->execution_date != "0000-00-00"){
							$executionDate = date('M d,Y',strtotime($otherBoxes[$i]->execution_date));
						}					
						if($otherBoxes[$i]->recorded != "0000-00-00"){
							$recordedDate = date('M d,Y',strtotime($otherBoxes[$i]->recorded));
						}	
						$boxArray = array('id'=>$otherBoxes[$i]->id,'name'=>$otherBoxes[$i]->name,'execution_date'=>$executionDate,'recorded_date'=>$recordedDate,'document'=>$otherBoxes[$i]->document_file,'type'=>$type,'date_1'=>strtotime($otherBoxes[$i]->date_1),'box_type'=>$otherBoxes[$i]->box_type);
						if($type === "3rdParties") {
							$boxArray['execution_date'] = '';
							$boxArray['recorded_date'] = '';
						}
						array_push($data['box'],$boxArray);
					}
				}
				if(count($data['box']) > 0){
					for($b=0;$b<count($data['box']);$b++){
						$boxType = -1;
						if(is_array($data['box'][$b])){
							$boxType = $data['box'][$b]['box_type'];
						} else {
							$boxType = $data['box'][$b]->box_type;
						}
						if($boxType >= 0){
							$findBoxData = findBoxData($all_boxes,$boxType);
							if(count($findBoxData) > 0){
								if(is_array($data['box'][$b])){
									$data['box'][$b]['shape'] = $findBoxData[0]->shape;
									$data['box'][$b]['dimension'] = $findBoxData[0]->dimension;
									$data['box'][$b]['segment'] = $findBoxData[0]->segment;
									$data['box'][$b]['border_color'] = $findBoxData[0]->border_color;
									$data['box'][$b]['border_linepx'] = $findBoxData[0]->border_px;
									$data['box'][$b]['background_color'] = $findBoxData[0]->background_color;
								} else {
									$data['box'][$b]->shape = $findBoxData[0]->shape;
									$data['box'][$b]->dimension = $findBoxData[0]->dimension;
									$data['box'][$b]->segment = $findBoxData[0]->segment;
									$data['box'][$b]->border_color = $findBoxData[0]->border_color;
									$data['box'][$b]->border_linepx = $findBoxData[0]->border_px;
									$data['box'][$b]->background_color = $findBoxData[0]->background_color;
								}
							}
						}
					}
				}
				$re = 0;
				foreach($jsonRelations as $relation){
					if($relation->connection_type == 2) {
						$parentID = $relation->parent_id;
						$childID = $relation->child_id;
						$pRelation = array();
						$getConnectionTo = findConnectionTo($patentNumber,$relation->assignment_no, $con);
						
						if(count($getConnectionTo) > 0){
							foreach($getConnectionTo as $connection){
								if($connection->parent_id == $childID && $connection->child_id == $parentID) {
									$pRelation = $connection;
									break;
								}
							}
						} else {
							$parentData = findIndex($list,$childID);
							if(count($parentData) == 1){
								$childData = findIndex($list,$parentID);
								if(count($childData) == 1) {
									foreach($jsonRelations as $r){
										if($r->parent_id == $childID && $r->child_id == $parentID) {
											$pRelation = $r;
											break;
										}
									}
								}
							}
						}
						if(is_object($pRelation)) {
							$pRelation->parent_name = $parentData[0]->name;
							$pRelation->child_name = $childData[0]->name;
							$jsonRelations[$re]->org_relation = $pRelation;
						}
					}
					$re++;
				}
				
				$jsRIncrement = 0;
				foreach($jsonRelations as $relation){		
					if((int)$relation->connection_type == 2) {			
						if(isset($relation->org_relation)) {
							$jsonRelations[$jsRIncrement]->reverse = true;
							$jsonRelations[$jsRIncrement]->reverse_frame = $relation->org_relation->frame;
							$jsonRelations[$jsRIncrement]->reverse_reel = $relation->org_relation->reel;
							$jsonRelations[$jsRIncrement]->reff_id = $relation->org_relation->id;
							$jsonRelations[$jsRIncrement]->assignment_no2 = $relation->org_relation->assignment_no;
							$jsonRelations[$jsRIncrement]->note2 = $relation->org_relation->note;
							$jsonRelations[$jsRIncrement]->note_file2 = $relation->org_relation->note_file;
							
						}
					}
					$jsRIncrement++;
				}
				
				if(count($jsonRelations)>0){
					foreach($jsonRelations as $relation){
						$pouptop = $relation->reel.'-'.$relation->frame;
						$assignment_no1 = $relation->assignment_no;
						$poupbottom = "";
						$reffID = 0;
						$assignment_no2 = 0;
						$document1 = "";
						$document2 = "";
						$note1 = $relation->note;
						$note2 = "";
						$noteFile1 = $relation->note_file;
						if($note1 == null){
							$note1 = "";
						}
						if($noteFile1 == null){
							$noteFile1 = "";
						}
						$noteFile2 = "";
						$sourceDIR = '/var/www/html/PatenTrack/';
						$fileName = "resources/shared/data/assignment-pat-".$pouptop.".pdf";
						if(file_exists($sourceDIR.$fileName)){
							$document1 = "https://patentrack.com/".$fileName;
						}
						if(isset($relation->reverse) && $relation->reverse === true) {
							$poupbottom = $relation->reverse_reel.'-'.$relation->reverse_frame;
							$reffID = $relation->reff_id;
							$assignment_no2 = $relation->assignment_no2;
							$note2 = $relation->note2;
							$noteFile2 = $relation->note_file2;
							$fileName = "resources/shared/data/assignment-pat-".$poupbottom.".pdf";
							if(file_exists($sourceDIR.$fileName)){
								$document2 = "https://patentrack.com/".$fileName;
							}
						}
						$type = $relation->description;
						$lineType = $relation->line_type;
						$popup = array();
						$comments = array();
						if($pouptop != ""){
							array_push($popup,$pouptop);
							$noteArray = array();
							array_push($noteArray,$note1);
							array_push($noteArray,'');
							$com = array();
							$com[$pouptop] = $noteArray ;
							array_push($comments,$com);
						}
						if($poupbottom != ""){
							array_push($popup,$poupbottom);
							$noteArray = array();
							array_push($noteArray,$note2);
							array_push($noteArray,'');
							$com = array();
							$com[$poupbottom] = $noteArray ;
							array_push($comments,$com);
						}
						$findLineColorType = findLineColorType($lineType,$data['legend']);
						$userFiles = array();
						array_push($userFiles,$noteFile1);
						if($poupbottom != ""){
							array_push($userFiles,$noteFile2);
						}
						$typeLine = "";
						if($findLineColorType['line_type'] == 0){
							$typeLine = "Solid";
						}else if($findLineColorType['line_type'] == 1){
							$typeLine = "Dashed";
						}
						array_push($data['connection'],array('id'=>$relation->id,'assignment_no1'=>$assignment_no1,'color'=>$findLineColorType['color'],'type'=>$type,'type_line'=>$typeLine,'ref_id'=>$reffID,'start_id'=>$relation->parent_id,'end_id'=>$relation->child_id,'box_creator_id'=>$relation->creator_id,'box_creator_id2'=>$relation->start_creator_id,'popup'=>$popup,'comment'=>$comments,'user_files'=>$userFiles,'tooltip'=>$findLineColorType['tooltip'],'date'=>date('M d,Y',strtotime($relation->date)),'date_1'=>strtotime($relation->date),'document1'=>$document1,'document2'=>$document2,'note1'=>$note1,'pdf1'=>$noteFile1,'note2'=>$note2,'pdf2'=>$noteFile2,'popuptop'=>$pouptop,'popupbottom'=>$poupbottom));
					}				 
				}
				$data['line'] = $data['connection'];
				$file = file_get_contents("http://patentrack.com/resources/shared/data/".$patentNumber.".json");
				if($file != "") {
					try{
						$docs = json_decode($file);
						if(isset($docs->response)) {
							if($docs->response->numFound > 0) {
								$data['popup'] = $docs->response->docs;
							}
						}
					}catch(Exception $e) {					
					}
				}
				echo json_encode($data);
			} catch(Exception $e) {		
				
			}
		}
	}
	
}
?>
