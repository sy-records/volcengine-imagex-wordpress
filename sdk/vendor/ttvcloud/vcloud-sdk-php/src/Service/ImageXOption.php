<?php

namespace Vcloud\Service;

class ImageXOption
{
    public static $HTTP  = 'http';
    public static $HTTPS = 'https';

    public $isHTTPS = false;
    public $format  = 'image';

    public function getFormat()
    {
        return $this->format;
    }

    public function getHTTPs()
    {
        return $this->isHTTPS;
    }
}
