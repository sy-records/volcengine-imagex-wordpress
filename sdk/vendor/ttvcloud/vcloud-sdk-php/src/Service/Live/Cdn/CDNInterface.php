<?php

namespace Vcloud\Service\Live\Cdn;

const CDN_ALI  = "ali";
const CDN_WS   = "ws";
const CDN_KS   = "ks";
const CDN_FCDN = "fcdn";

interface CDNInterface {
    public function genPullFlvUrl($domain, $appName, $stream, $suffix);
    public function genPullHlsUrl($domain, $appName, $stream, $suffix);
    public function genPullRtmpUrl($domain, $appName, $stream, $suffix);
    public function genPullCmafUrl($domain, $appName, $stream, $suffix);
    public function genPullDashUrl($domain, $appName, $stream, $suffix);
}

function registerCdnInstance(array &$mapCdn, $cdnName, $ci){
    if(!$cdnName || !$ci){
        error_log("[vcloud-live]register key[$cdnName] failed! Input nil param");
        return ;
    }

    if (isset($mapCdn[$cdnName])){
        error_log("[vcloud-live]register key[#$cdnName] multi-times");
        return;
    }

    $mapCdn[$cdnName] = $ci;
    echo "[vcloud-live]register cdn:$cdnName".PHP_EOL;
}

function initHandler(){
    $mapCdn = [];
    registerCdnInstance($mapCdn, CDN_ALI, new Ali());
    registerCdnInstance($mapCdn, CDN_FCDN, new Fcdn());
    registerCdnInstance($mapCdn, CDN_KS, new Ks());
    registerCdnInstance($mapCdn, CDN_WS, new Ws());
    return $mapCdn;
}