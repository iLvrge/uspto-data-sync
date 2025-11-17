<?php 

require_once '/var/www/html/trash/s3_bucket/vendor/autoload.php';

use Aws\S3\S3Client;

use Aws\Common\Credentials\Credentials;

use Aws\S3\Sync\UploadSyncBuilder;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
ignore_user_abort(true);
ini_set('xdebug.max_nesting_level', 1000);

/*echo "DDS".getenv('AWS_ACCESS_KEY')."AS".getenv('AWS_SECRET_KEY');*/
$credentials = new Credentials('AKIAIE4NFX6DV2F7YEBA', 'ijWrDa9qAWuRO7mRSl7albQgVSjwhSTr0bqodoiS');

$client = S3Client::factory(array(
    'credentials' => $credentials,
	'region'  => 'us-west-1',
));

$result = $client->listBuckets();

$dir = '/var/www/html/beta/resources/shared/data/';
$bucket = 'static.patentrack.com';
$keyPrefix = 'assignments';
/*
$client->uploadDirectory($dir, $bucket, $keyPrefix, array(
    'params'      => array('ACL' => 'public-read'),
    'concurrency' => 20,
    'debug'       => true
));
*/

UploadSyncBuilder::getInstance()
    ->setClient($client)
    ->setBucket($bucket)
    ->setKeyPrefix($keyPrefix)
    ->setAcl('public-read')
    ->uploadFromGlob($dir.'*.pdf')
    ->build()
    ->transfer();