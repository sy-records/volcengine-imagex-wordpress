<?php

$paths = [
    'vendor/volcengine/volc-sdk-php/src/Service/Base/Models',
    'vendor/volcengine/volc-sdk-php/src/Service/Imp',
    'vendor/volcengine/volc-sdk-php/src/Service/Live',
    'vendor/volcengine/volc-sdk-php/src/Service/Vms',
    'vendor/volcengine/volc-sdk-php/src/Service/Vod',
    'vendor/volcengine/volc-sdk-php/src/Service/AdBlocker.php',
    'vendor/volcengine/volc-sdk-php/src/Service/Billing.php',
    'vendor/volcengine/volc-sdk-php/src/Service/BusinessSecurity.php',
    'vendor/volcengine/volc-sdk-php/src/Service/Cdn.php',
    'vendor/volcengine/volc-sdk-php/src/Service/GameProduct.php',
    'vendor/volcengine/volc-sdk-php/src/Service/Iam.php',
    'vendor/volcengine/volc-sdk-php/src/Service/Live.php',
    'vendor/volcengine/volc-sdk-php/src/Service/Rtc.php',
    'vendor/volcengine/volc-sdk-php/src/Service/Sms.php',
    'vendor/volcengine/volc-sdk-php/src/Service/Sts.php',
    'vendor/volcengine/volc-sdk-php/src/Service/VEdit.php',
    'vendor/volcengine/volc-sdk-php/src/Service/Visual.php',
];

$dir = __DIR__;

foreach ($paths as $path) {
    `rm -r {$dir}/{$path}`;
}
