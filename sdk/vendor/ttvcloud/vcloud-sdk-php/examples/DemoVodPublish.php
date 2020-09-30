<?php
require('../vendor/autoload.php');

use Vcloud\Service\Vod;

$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

$vid = "";
$space = "";

echo "\n修改发布状态\n";
$response = $client->SetVideoPublishStatus(['json' => ['Vid' => $vid, 'SpaceName' => $space, 'Status' => 'Published']]);
echo $response;
