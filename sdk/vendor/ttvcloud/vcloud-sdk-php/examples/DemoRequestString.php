<?php
require('../vendor/autoload.php');
use Vcloud\Service\Iam;

$string = Iam::getInstance()->getRequestUrl('ListUsers');
echo $string;
