<?php
require('../vendor/autoload.php');
use Vcloud\Service\Live;

$client = Live::getInstance('cn-north-1');

echo "\nDemo 1\n";
$stream = 'stream-106753095100792963';
$startTime = 1;
$endTime = 2;
$response = $client->getStreamTimeShiftInfo($stream, $startTime, $endTime);
echo print_r($response);

