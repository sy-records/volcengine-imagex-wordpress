<?php

namespace Vcloud\Service\Live\Cdn;

class Ali implements CDNInterface{
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
        return "http://$domain/$appName/$stream$suffix.m3u8";
    }

    public function genPullRtmpUrl($domain, $appName, $stream, $suffix){
        if(!$domain || !$appName || !$stream){
            return "";
        }
        return "http://$domain/$appName/$stream$suffix";
    }

    public function genPullCmafUrl($domain, $appName, $stream, $suffix){
        if(!$domain || !$appName || !$stream){
            return "";
        }
        return "http://$domain/$appName/$stream$suffix/index.mpd";
    }

    public function genPullDashUrl($domain, $appName, $stream, $suffix){
        if(!$domain || !$appName || !$stream){
            return "";
        }
        return "http://$domain/$appName/$stream$suffix/index.mpd";
    }
}