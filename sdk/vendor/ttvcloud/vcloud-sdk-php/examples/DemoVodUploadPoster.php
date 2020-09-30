<?php
require('../vendor/autoload.php');

use Vcloud\Service\Vod;

$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

$space = "";
$vid = "";

echo "\n上传封面图\n";
$response = $client->uploadPoster($vid, $space, "your file path");
echo $response;
