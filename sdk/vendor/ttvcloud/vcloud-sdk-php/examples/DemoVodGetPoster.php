<?php
require('../vendor/autoload.php');
use Vcloud\Service\Vod;
use Vcloud\Service\VodOption;

$client = Vod::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);

$space = "";
$uri = "";
// set fallbackWeights if necessary
$fallbackWeights = ['p1.test.com' => 10, 'p3.test.com' => 5];

$opt = new VodOption();
$opt->setHttps(true);
$opt->setVodTplSmartCrop(600, 392);
$opt->setFormat(VodOption::$FORMAT_AWEBP);

echo "\n获取poster url\n";
$resp = $client->getPosterUrl($space, $uri, $fallbackWeights, $opt);
var_dump($resp);