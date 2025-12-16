<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
//$dbApplication = getenv('DB_APPLICATION_DB');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbUSPTO);




//$con->query('TRUNCATE db_uspto.inventors;');
$con->query('
INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) 
select assignor_and_assignee_id from assignor where rf_id IN (
select rf_id from representative_assignment_conveyance AS rac
where rac.convey_ty = "employee" AND employer_assign = 1
) LIMIT 7009620,  700962');
//700962





//7009628

/* 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) 
SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id 
AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') = 2023 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2022 AND 2023 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2021 AND 2022 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2020 AND 2021 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2019 AND 2020 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2018 AND 2019 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2017 AND 2018 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2016 AND 2017 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2015 AND 2016 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2014 AND 2015 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2013 AND 2014 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2012 AND 2013 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2011 AND 2012 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2010 AND 2011 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2009 AND 2010 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2008 AND 2009 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2007 AND 2008 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2006 AND 2007 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2005 AND 2006 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2004 AND 2005 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2003 AND 2004 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2002 AND 2003 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2001 AND 2002 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 2000 AND 2001 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 1999 AND 2000 GROUP BY assignor_and_assignee_id"); 

$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 1998 AND 1999 GROUP BY assignor_and_assignee_id"); 


$con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_uspto.assignor AS aor INNER JOIN db_uspto.representative_assignment_conveyance AS rac ON rac.rf_id AND aor.rf_id WHERE rac.convey_ty = 'employee' AND employer_assign = 1 AND date_format(exec_dt, '%Y') BETWEEN 1997 AND 1998 GROUP BY assignor_and_assignee_id");  */
?>