<?php
require('../vendor/autoload.php');

use Vcloud\Service\ImageX;

$xclient = ImageX::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
$xclient->setAccessKey("your_ak");
$xclient->setSecretKey("your_sk");

$serviceID = "your_service_id";
$uris = ["uri", "uri"];

$response = $xclient->deleteImages($serviceID, $uris);
echo $response;
