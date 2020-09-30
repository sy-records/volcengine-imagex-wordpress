<?php
require('../vendor/autoload.php');

use Vcloud\Service\Vod;

$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

echo "\n获取Space列表\n";
$response = $client->getSpace(['query' => ['Type' => 'list', 'ProjectNames' => 'default']]);
echo $response;
