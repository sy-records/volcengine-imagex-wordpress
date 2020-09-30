<?php
require('../vendor/autoload.php');
use Vcloud\Service\Live;

$client = Live::getInstance('cn-north-1');
echo "\nDemo 1\n";
$stream = 'stream-106753608883634307'; // 流信息
$startTime = 1590754217-360; // 正确的时间戳
$endTime = 1590754217; // 正确的时间戳
$response = $client->getOnlineUserNum($stream, $startTime, $endTime);
echo print_r($response);

