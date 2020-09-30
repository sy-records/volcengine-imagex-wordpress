<?php
require('../vendor/autoload.php');
use Vcloud\Service\Vod;

$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

$vid = "";
$space = "";
$tid = "";

echo "\n开始转码\n";
$response = $client->startTranscode(['query' => ['TemplateId' => $tid], 'json' => ['Vid' => $vid, 'Priority' => 0]]);
echo $response;
