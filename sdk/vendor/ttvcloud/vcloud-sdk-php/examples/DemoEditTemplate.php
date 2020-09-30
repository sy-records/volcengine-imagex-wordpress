<?php
require('../vendor/autoload.php');

use Vcloud\Service\Edit;

$client = Edit::getInstance();
// call below method if you dont set ak and sk in ～/.vcloud/config
// $client->setAccessKey($ak);
// $client->setSecretKey($sk);


// async 
// below just an example, not complete
$param = [
    [
        'Name' => 'img1',
        'Type' => 'image',
        'Position' => 's0e0',
        'Source' => 'your source 1'
    ],
    [
        'Name' => 'img2',
        'Type' => 'image',
        'Position' => 's1e0',
        'Source' => 'your source 2'
    ]
];

$body = [
    'TemplateId' => 'templateId',
    'Space' => 'your space',
    'VideoName' => ['your title'],
    'Params' => [$param],
    'CallbackArgs' => 'your callback args',
    'CallbackUri' => 'your callback uri'
];

$response = $client->submitTemplateTaskAsync(['json' => $body]);
echo $response;
