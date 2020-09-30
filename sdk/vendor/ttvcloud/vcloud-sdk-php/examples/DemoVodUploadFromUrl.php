<?php
require('../vendor/autoload.php');
use Vcloud\Service\Vod;

$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

$space = "";
$url = "";

$response = $client->uploadMediaByUrl(['query' => ['SpaceName' => $space, 'Format' => 'mp4', 'SourceUrls' => $url]]);
echo $response;
echo "\n";
