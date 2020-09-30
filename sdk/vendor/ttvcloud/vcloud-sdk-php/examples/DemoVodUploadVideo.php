<?php
require('../vendor/autoload.php');

use Vcloud\Service\Vod;


$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);


echo "\n上传视频\n";
$space = "";
$file = "";
$response = $client->uploadVideo($space, $file, [['Name' => 'Snapshot', 'Input' => ['SnapshotTime' => 2.3]], ['Name' => 'GetMeta']]);
echo $response;
