<?php
require('../vendor/autoload.php');

use Vcloud\Service\Edit;

$client = Edit::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);


// async 
// below just an example, not complete

$body = [
    'VideoId' => 'vid',
    'SensitiveWord' => 1,
    'WordsPerLine' => 3,
    'CallbackArgs' => 'your callback args',
    'CallbackUri' => 'your callback uri'
];

$response = $client->submitSubtitleRecognizationTaskAsync(['json' => $body]);
echo $response;
