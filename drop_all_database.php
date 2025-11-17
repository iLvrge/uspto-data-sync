<?php 
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

$rpassword = getenv('DB_RT_PWD');
$dbUSPTO = getenv('DB_USPTO_DB');
//$con = new mysqli($host, $user, $password, $dbApplication);

$con = new mysqli("localhost","root",$rpassword,$dbUSPTO);


die;
/*
$con->query("drop database db_435f2787df8eca1");
$con->query("drop database db_445f298dc8840ad");
$con->query("drop database db_455f29d9c5621ab");
$con->query("drop database db_465f2b10a98883d");
$con->query("drop database  db_495f2b68585d765");
$con->query("drop database  db_505f2c60697b6bb");
$con->query("drop database  db_515f2d102ca38d8");
$con->query("drop database  db_525f3187451411b");
$con->query("drop database  db_535f31b226cac23");
$con->query("drop database  db_545f32265e04867");
$con->query("drop database  db_555f323a5ecbc61");
$con->query("drop database  db_565f348f9a69889");
$con->query("drop database  db_575f35cd338a568");
$con->query("drop database  db_585f3c0d5954370");
$con->query("drop database  db_665f3cb0601d2c5");
$con->query("drop database  db_675f3cba9feeb72");
$con->query("drop database  db_695f4948b60e77a");
$con->query("drop database  db_705f56d4bb1b4ac"); 
$con->query("drop database  db_715f59a25987f86");
$con->query("drop database  db_725f5fcdcfc09fd");
$con->query("drop database  db_735f63d08384123");
$con->query("drop database  db_745f6bc4d962ef6");
$con->query("drop database  db_755f6e2edc0382a");
$con->query("drop database  db_765f79685fe27c6");
$con->query("drop database  db_775f7a8daf3cdf6");
$con->query("drop database  db_795f7b8bda84579");
$con->query("drop database  db_805f84e9c42e7b2");*/


$con->query("DROP USER '5f225658731e9'@'167.172.195.92'");
$con->query("DROP USER '5f22672263520'@'167.172.195.92'");
$con->query("DROP USER '5f2708fe4f41c'@'167.172.195.92'");
$con->query("DROP USER '5f2787df8eca2'@'167.172.195.92'");
$con->query("DROP USER '5f298dc8840b0'@'167.172.195.92'");
$con->query("DROP USER '5f29d9c5621b1'@'167.172.195.92'");
$con->query("DROP USER '5f2b10a988842'@'167.172.195.92'");
$con->query("DROP USER '5f2b68585d767'@'167.172.195.92'");
$con->query("DROP USER '5f2c60697b6be'@'167.172.195.92'");
$con->query("DROP USER '5f2d102ca38db'@'167.172.195.92'");
$con->query("DROP USER '5f3187451411d'@'167.172.195.92'");
$con->query("DROP USER '5f31b226cac25'@'167.172.195.92'");
$con->query("DROP USER '5f32265e04869'@'167.172.195.92'");
$con->query("DROP USER '5f323a5ecbc63'@'167.172.195.92'");
$con->query("DROP USER '5f348f9a6988c'@'167.172.195.92'");
$con->query("DROP USER '5f35cd338a56b'@'167.172.195.92'");
$con->query("DROP USER '5f3c0d5954373'@'167.172.195.92'");
$con->query("DROP USER '5f3cb0601d2c8'@'167.172.195.92'");
$con->query("DROP USER '5f3cba9feeb74'@'167.172.195.92'");
$con->query("DROP USER '5f4948b60e792'@'167.172.195.92'");
$con->query("DROP USER '5f56d4bb1b4b0'@'167.172.195.92'");
$con->query("DROP USER '5f59a25987f89'@'167.172.195.92'");
$con->query("DROP USER '5f5fcdcfc0a00'@'167.172.195.92'");
$con->query("DROP USER '5f63d08384126'@'167.172.195.92'");
$con->query("DROP USER '5f6bc4d962ef8'@'167.172.195.92'");
$con->query("DROP USER '5f6e2edc0382d'@'167.172.195.92'");
$con->query("DROP USER '5f79685fe27c9'@'167.172.195.92'");
$con->query("DROP USER '5f7a8daf3cdf9'@'167.172.195.92'");
$con->query("DROP USER '5f7b845e68c30'@'167.172.195.92'");
$con->query("DROP USER '5f7b8bda8457c'@'167.172.195.92'");
$con->query("DROP USER '5f84e9c42e7b5'@'167.172.195.92'");

