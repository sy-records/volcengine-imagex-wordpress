<?php
require('../vendor/autoload.php');
use Vcloud\Service\Live;

$client = Live::getInstance('cn-north-1');

echo "\nDemo 1\n";
$appID = 200002; // 获取必要参数
$stream = 'stream-106753807123480717';
$response = $client->forbidStream($appID, $stream);
echo print_r($response);

