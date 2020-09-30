<?php
require('../vendor/autoload.php');
use Vcloud\Service\Live;

$client = Live::getInstance('cn-north-1');

echo "\nDemo 1\n";
$stream = 'stream-107094090941989005';
$response = $client->getStreamsPlayInfo([$stream],true,['DeviceId' => '20200723'],true);
echo print_r($response);
