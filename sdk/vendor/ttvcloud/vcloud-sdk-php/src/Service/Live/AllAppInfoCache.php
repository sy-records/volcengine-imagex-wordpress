<?php
namespace Vcloud\Service\Live;

const logPrefix = "[vcloud-live]";
class AllAppInfoCache{
    protected $client;
    protected static $cache = [];

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function isEmptyCache(){
        return (count(self::$cache) == 0);
    }

    public function updateAllAppInfosCache(){
        $response = $this->client->getDesensitizedAllAppInfos();
        if (isset($response["ResponseMetadata"]["Error"])){
            error_log(print_r($response["ResponseMetadata"]["Error"]));
            return false;
        }

        $cache = [];
        foreach($response["Result"]["Push2AppInfo"] as $pushID => $appInfo){
            $cache[$this->_genAppInfoKey($appInfo["Id"])] = $appInfo;
            $cache[$this->_genPush2AppInfoKey($pushID)] = $appInfo;
        }

        foreach ($response["Result"]["Push2AllPlayInfos"] as $pushID => $playInfos){
            $cache[$this->_genPush2AllPlayInfosKey($pushID)] = $playInfos;
        }
        self::$cache = $cache;
        echo logPrefix.'update all app info cache finished'.PHP_EOL;
        return true;
    }

    public function getAppInfoByPushID($pushID){
        return @self::$cache[$this->_genPush2AppInfoKey($pushID)];
    }

    public function getAppInfo($appID){
        return @self::$cache[$this->_genAppInfoKey($appID)];
    }

    public function getAllPlayInfosByPushID($pushID){
        return @self::$cache[$this->_genPush2AllPlayInfosKey($pushID)];
    }

    protected function _genAppInfoKey($id){
        return sprintf("app-%d",$id);
    }

    protected function _genPush2AppInfoKey($id){
        return sprintf("push2App-%d",$id);
    }

    protected function _genPush2AllPlayInfosKey($id){
        return sprintf("push2Play-%d",$id);
    }
}