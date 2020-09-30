<?php
require('../vendor/autoload.php');
use Vcloud\Service\Live;

$client = Live::getInstance('cn-north-1');

echo "\nDemo 1\n";
$stream = 'stream-107094072957075597';
$response = $client->getStreamsPushInfo([$stream]);
echo print_r($response);

