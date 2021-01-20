<?php
require('../../vendor/autoload.php');

use Volc\Service\ImageX;

$client = ImageX::getInstance();

// call below method if you dont set ak and sk in ～/.volc/config
$client->setAccessKey("ak");
$client->setSecretKey("sk");

$params = array();
$params["ServiceId"] = "imagex service id";
$filePaths = array("image path 1");

$response = $client->uploadImages($params, $filePaths);
echo $response;
