<?php
require('../vendor/autoload.php');

use Vcloud\Service\ImageX;


$xclient = ImageX::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
$xclient->setAccessKey("your ak");
$xclient->setSecretKey("your sk");

$params = array();
$params["ServiceId"] = "your serviceId";

// notice
// $params["UploadNum"] should be equal with the length of filePaths and the length $params["StoreKeys"] 
$params["UploadNum"] = 2;
$params["StoreKeys"] = array("", "");

$filePaths = array("", "");

echo "\n上传ImageX\n";
$response = $xclient->uploadImages($params, $filePaths);
echo $response;
