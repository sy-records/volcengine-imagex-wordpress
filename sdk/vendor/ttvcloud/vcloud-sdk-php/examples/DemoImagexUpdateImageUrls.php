<?php
require('../vendor/autoload.php');
use Vcloud\Service\ImageX;
use Vcloud\Service\ImageXOption;

$client = ImageX::getInstance();

$serviceID = ""; // service ID
$urls = ["url1", "url2"];

echo "\n刷新图片url\n";
$resp = $client->updateImageUrls($serviceID, $urls);
var_dump($resp);
