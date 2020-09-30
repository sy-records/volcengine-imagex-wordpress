<?php
namespace Vcloud\Service\Live\Cdn;

class Ks implements CDNInterface{
    public function genPullFlvUrl($domain, $appName, $stream, $suffix){
        if(!$domain || !$appName || !$stream){
            return "";
        }

        return "http://$domain/$appName/$stream$suffix.flv";
    }

    public function genPullHlsUrl($domain, $appName, $stream, $suffix){
        if(!$domain || !$appName || !$stream){
            return "";
        }
        return "http://$domain/$appName/$stream$suffix/index.m3u8";
    }

    public function genPullRtmpUrl($domain, $appName, $stream, $suffix){
        if(!$domain || !$appName || !$stream){
            return "";
        }
        return "http://$domain/$appName/$stream$suffix";
    }

    public function genPullCmafUrl($domain, $appName, $stream, $suffix){
        return "";
    }

    public function genPullDashUrl($domain, $appName, $stream, $suffix){
        return "";
    }
}