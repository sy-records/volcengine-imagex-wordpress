<?php
require('../vendor/autoload.php');
use Vcloud\Service\Iam;

$client = Iam::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

echo "\nDemo 1\n";
$response = $client->listUsers([]);
echo $response;

echo "\nDemo 2\n";
$response = $client->listUsers(['query'=>['Limit'=>10, 'Offset'=>0]]);
echo $response;

echo "\nDemo 3\n";
$response = $client->listUsers(['v4_credentials'=>['ak'=>"$ak", 'sk'=>$sk], 'query'=>['Limit'=>10, 'Offset'=>0]]);
echo $response;