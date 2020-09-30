<?php
require('../vendor/autoload.php');
use Vcloud\Service\Vod;

$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

$vid = "";
$expire = 60; // 请求的签名有效期

echo "\nstaging 获取播放地址\n";
$response = $client->getPlayInfo(['query' => ['video_id' => $vid]]);
echo $response;

echo "\n获取源片播放地址\n";
$response = $client->getOriginVideoPlayInfo(['query' =>['Vid' => $vid, 'Ssl' => 1]]);
echo $response;

echo "\n获取follow 302播放地址\n";
$response = $client->getRedirectPlay(['query' =>['Vid' => $vid, 'X-Amz-Expires' => $expire]]);
echo $response;