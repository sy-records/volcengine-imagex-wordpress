<?php
require('../vendor/autoload.php');

use Vcloud\Service\Vod;


$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

$expire = 60; // 请求的签名有效期

echo "\n获取上传的Token\n";
$space = "";
$response = $client->getUploadAuthToken(['query' => ['SpaceName' => $space, 'X-Amz-Expires' => $expire]]);
echo $response;

echo "\n获取播放的Token\n";
$vid = "";
$response = $client->getPlayAuthToken(['query' => ['video_id' => $vid, 'X-Amz-Expires' => $expire]]);
echo $response;
