<?php
ini_set('xdebug.max_nesting_level', 1000);
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, 'db_uspto');


$con->query("ALTER TABLE `db_patent_application_bibliographic`.`inventor` 
ADD INDEX `IDx_given` (`given_name` ASC) VISIBLE,
ADD INDEX `IDX_family` (`family_name` ASC) VISIBLE;
");

$con->query("ALTER TABLE `db_patent_grant_bibliographic`.`inventor` 
ADD INDEX `IDx_given` (`given_name` ASC) VISIBLE,
ADD INDEX `IDX_family` (`family_name` ASC) VISIBLE;
");