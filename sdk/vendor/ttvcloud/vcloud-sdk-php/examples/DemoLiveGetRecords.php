<?php
require('../vendor/autoload.php');
use Vcloud\Service\Live;

$client = Live::getInstance('cn-north-1');

echo "\nDemo 1\n";
$stream = 'stream-106753095100792963';
$response = $client->getRecords($stream);
echo print_r($response);

