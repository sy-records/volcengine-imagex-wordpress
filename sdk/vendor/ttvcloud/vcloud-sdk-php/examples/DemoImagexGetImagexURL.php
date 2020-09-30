<?php
require('../vendor/autoload.php');
use Vcloud\Service\ImageX;
use Vcloud\Service\ImageXOption;

$client = ImageX::getInstance();

$serviceID = ""; // service ID
$uri = "test";
$tpl = "tplv-vod-obj";
$fallbackWeights = ['p1.test.com' => 10, 'p3.test.com' => 5];

$opt = new ImageXOption();
$opt->isHTTPS = true;

echo "\n获取imagex url\n";
$resp = $client->getImageXURL($serviceID, $uri, $tpl, $fallbackWeights, $opt);
var_dump($resp);
