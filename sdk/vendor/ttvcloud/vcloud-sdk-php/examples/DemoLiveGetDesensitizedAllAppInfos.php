<?php
require('../vendor/autoload.php');
use Vcloud\Service\Live;

$client = Live::getInstance('cn-north-1');
echo "\nDemo 1\n";
$response = $client->getDesensitizedAllAppInfos();
echo print_r($response);