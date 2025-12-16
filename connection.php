<?php
ini_set('max_execution_time', 0);
ignore_user_abort(true);
ini_set('xdebug.max_nesting_level', 1000);
ini_set("memory_limit","256M");

$db = array();

$db['default']['hostname'] = '167.172.195.92';
$db['default']['username'] = 'patent_user';
$db['default']['password'] = 'P@t3nt@u5r';
$db['default']['database'] = 'db_patentrack';

$con = mysqli_connect($db['default']['hostname'],$db['default']['username'],$db['default']['password'],$db['default']['database']);

if((int)mysqli_errno($con)==0){
	mysqli_set_charset($con, 'utf8'); // <- add this too
	mysqli_query($con, "SET NAMES 'utf8';");
	mysqli_query($con, "SET CHARACTER SET 'utf8';");
	mysqli_query($con, "SET COLLATION_CONNECTION = 'utf8_unicode_ci';");
} else {
	die("Error");
}
?>
