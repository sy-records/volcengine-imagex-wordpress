<?php
namespace Vcloud\Service\Live;

require "Cdn/CDNInterface.php";

const allowedSize2Priority = [
    "ao" => 10,
    "ld" => 20,
    "sd" => 30,
    "hd" => 40,
    "uhd" => 50,
    "origin" => 60
];

class FallbackPlayInfo{
    protected const tsBits = 32;
    protected const minCountBits = 8;
    protected const reservedBits = 8;
    protected const minPushIDBits = 10;
    protected const countBits = 8;
    protected const pushIDBits = self::minCountBits + self::reservedBits + self::minPushIDBits - self::countBits;

    protected static $pushIDMask;
    protected static $tsMask;

    protected $allAppInfoCache;
    protected $cdnHandler;

    public function __construct(&$allAppInfoCache)
    {
        $this->cdnHandler = Cdn\initHandler();
        $this->allAppInfoCache = $allAppInfoCache;
        self::$pushIDMask = pow(2, self::pushIDBits) - 1;
        self::$tsMask = pow(2, self::tsBits) - 1;
    }

    public function getStreamsFallbackPlayInfo(array $streams, $enableSSL=false, array $clientInfo=[], $enableStreamData = false){
        if(count($streams) == 0){
            return ["error" => "empty req streams"];
        }

        $getStreamsResult = $this->getStreamsFallbackInfo($streams);
        if (isset($getStreamsResult['error'])){
            return ["error" => $getStreamsResult['error']];
        }

        $playContext = [
            "streams" => $streams,
            "enableSSL" => $enableSSL,
            "streamInfos" => $this->_filterPlayStreamInfos($getStreamsResult)
        ];
        $scheduleResult = $this->scheduleByWeight($playContext["streamInfos"]);
        if(isset($scheduleResult["error"])){
            return ["error" => $scheduleResult["error"]];
        }

        $playContext["scheduleResult"] = $scheduleResult;
        $playInfos = $this->deserializePlayInfos($playContext);
        if ($enableStreamData){
            $this->fillPullData($playInfos);
        }
        return $playInfos;
    }

    protected function fillPullData(array &$playInfos){
        foreach ($playInfos as $stream => $playInfo){
            $pullData = [];
            $this->_fillData($pullData, $playInfo["Main"]);

            $jsonPullData = json_encode(["Data" => $pullData],JSON_UNESCAPED_SLASHES);
            if (!$jsonPullData){
                error_log("pullData json encode failed");
                return;
            }
            $playInfos[$stream]["StreamData"] = $jsonPullData;
            $playInfos[$stream]["StreamSizes"] = $this->_fillSizes($pullData);
        }
    }

    protected function _fillSizes(array $data){
        $sizes = [];
        foreach ($data as $size => $_){
            if(allowedSize2Priority[$size] == null){
                continue ;
            }
            array_push($sizes, $size);
        }
        usort($sizes, "Vcloud\Service\Live\_sortSizes");
        return $sizes;
    }

    protected function _fillData(array &$data, array $eleInfos){
        if (count($eleInfos) == 0 || $eleInfos == null){
            return ;
        }

        foreach ($eleInfos as $eleInfo){
            if(@allowedSize2Priority[$eleInfo["Size"]] == null){
                continue;
            }

            $pullURLs = @$data[$eleInfo["Size"]];
            if ($pullURLs == null){
                $pullURLs = [];
                $data[$eleInfo["Size"]] = $pullURLs;
            }

            $urlData = $this->_deserializeURLData($eleInfo);
            $pullURLs["Main"] = $urlData;

            $data[$eleInfo["Size"]] = $pullURLs;
        }
    }

    protected function _deserializeURLData(array $eleInfo){
        return [
            "Flv" => $eleInfo["Url"]["FlvUrl"],
            "Hls" => $eleInfo["Url"]["HlsUrl"],
            "Cmaf" => $eleInfo["Url"]["CmafUrl"],
            "Dash" => $eleInfo["Url"]["DashUrl"],
            "SDKParams" => "{}",
        ];
    }

    /**
     * @param array $streamInfos
     * @return array
     */
    protected function _filterPlayStreamInfos(array $streamInfos){
        $filteredMap = [];
        foreach ($streamInfos as $stream => $streamInfo){
            if (@$streamInfo["playTypes"] == null){
                error_log("empty playTypes stream:".$stream);
                continue;
            }

            if (@$streamInfo["resolutions"] == null){
                error_log("empty resolutions stream:".$stream);
                continue;
            }

            if (@$streamInfo["pushMainCdnappId"] == null){
                error_log("push main appID empty, stream:".$stream);
                continue;
            }

            $filteredMap[$stream] = $streamInfo;
        }
        return $filteredMap;
    }

    /**
     * @param array $streamInfos
     * @return array[]|string[]
     */
    protected function scheduleByWeight(array $streamInfos){
        $scheduleInfos = [];
        foreach ($streamInfos as $stream => $streamInfo){
            $scheduleInfo = ["streamInfo" => $streamInfo];

            $playCdnAppInfos = $this->allAppInfoCache->getAllPlayInfosByPushID($streamInfo["pushMainCdnappId"]);
            if (!$playCdnAppInfos){
                error_log("get play cdn app info failed, pushID=".$streamInfo["pushMainCdnappId"]);
                continue;
            }

            $main = $this->scheduleStreamByWeight($playCdnAppInfos);
            if(isset($main["error"])){
                error_log("schedule main for stream:$stream, err:".$main["error"]);
                continue;
            }

            $scheduleInfo["mainScheduleResult"] = $main;
            $scheduleInfos[$stream] = $scheduleInfo;
        }

        if (count($scheduleInfos) == 0){
            return [
                "error" => "schedule by weight result empty"
            ];
        }

        return $scheduleInfos;
    }

    /**
     * @param array $playCdnAppInfos
     * @return array[]|string[]
     */
    protected function scheduleStreamByWeight(array $playCdnAppInfos){
        if(count($playCdnAppInfos) == 0){
            return [
                'error' => "empty playCdnAppInfos"
            ];
        }

        $totalWeight = 0;
        foreach ($playCdnAppInfos as $playCdnAppInfo){
            $totalWeight += $playCdnAppInfo["PlayCdnApp"]["PlayProportion"];
        }

        $randNum = rand(0, $totalWeight-1);
        $temWeight = 0;
        foreach ($playCdnAppInfos as $i => $playCdnAppInfo){
            if ($playCdnAppInfo["PlayCdnApp"]["PlayProportion"] == 0){
                continue;
            }
            $temWeight += $playCdnAppInfo["PlayCdnApp"]["PlayProportion"];
            if ($temWeight > $randNum){
                return [
                    "playCdnApp" => $playCdnAppInfo["PlayCdnApp"],
                    "cdn" => $playCdnAppInfo["Cdn"],
                    "templates" => $this->_addOriginToTemplates($playCdnAppInfo["Templates"])
                ];
            }
        }

        return [
            "error" => "schedule play by weight failed"
        ];
    }

    protected function _addOriginToTemplates(array &$origin){
        $origin[] = [
            "Name" => "origin",
            "Size" => "origin"
        ];
        return $origin;
    }

    protected function deserializePlayInfos(array $playContext){
        $playInfos = [];
        foreach ($playContext["streams"] as $stream){
            $streamInfo = @$playContext["streamInfos"][$stream];
            if (!$streamInfo){
                continue;
            }

            $scheduleResult = @$playContext["scheduleResult"][$stream];
            if (!$scheduleResult){
                error_log("stream:$stream schedules failed");
                continue;
            }

            $main = $this->_deserializeElePlayInfo([
                "enableSSL" => $playContext["enableSSL"],
                "stream" => $stream,
                "streamInfo" => $streamInfo,
                "scheduleResult" => $scheduleResult["mainScheduleResult"]
            ]);

            if (count($main) == 0){
                error_log("deserialize main play info empty, stream:$stream");
                continue;
            }

            $playInfos[$stream] = [
                "StreamBase" => [
                    "AppID" => $streamInfo["appid"],
                    "Stream" => $streamInfo["liveId"],
                    "Extra" => $streamInfo["description"],
                    "CreateTime" => $streamInfo["createTime"],
                    "Status" => $streamInfo["status"],
                    ],
                "Main" => $main,
            ];
        }

        if (count($playInfos) == 0){
            return ["error" => "empty fallback result"];
        }

        return $playInfos;
    }

    protected function _deserializeElePlayInfo(array $params){
        $elePlayInfo = [];
        if (!$params["scheduleResult"]){
            return $elePlayInfo;
        }

        $streamInfo = $params["streamInfo"];
        $scheduleResult = $params["scheduleResult"];
        $playTypes = split($streamInfo["playTypes"], ',');
        $resolutions = array_flip(split($streamInfo["resolutions"],','));
        $playInfoMap = [];

        foreach($scheduleResult["templates"] as $template){
            $templateName = $template["Name"];
            if(!isset($resolutions[$templateName])){
                continue;
            }

            $genParams = [
                "enableSSL" => $params["enableSSL"],
                "size" => $templateName,
                "playTypes" => $playTypes,
                "streamInfo" => $streamInfo,
                "playCdnApp" => $scheduleResult["playCdnApp"],
                "cdn" => $scheduleResult["cdn"],
                "templateInfo" => $template
            ];
            $playURL = $this->_genElePlayURL($genParams);
            if(isset($playURL["error"])){
                error_log("gen ele play url for template:$templateName, error=".$playURL["error"]);
                continue;
            }

            $playInfoMap[$templateName] = $playURL;
        }

        if(count($playInfoMap) == 0){
            error_log("deserialize stream:".$streamInfo['liveId']." play info empty");
            return [];
        }

        return array_values($playInfoMap);
    }

    function _genElePlayURL(array $params){
        $playUrl = [
            "HlsUrl" => "",
            "RtmpUrl" => "",
            "FlvUrl" => "",
            "CmafUrl" => "",
            "DashUrl" => ""
        ];
        $app = $params["playCdnApp"]["PlayApp"];
        $stream = $params["streamInfo"]["liveId"];
        $suffix = $params["templateInfo"]["Suffix"];
        $enableSSL = $params["enableSSL"];

        $cdnInterface = $this->cdnHandler[$params["cdn"]["Name"]];
        if (!$cdnInterface){
            return ["error" => "unsupported cdn:".$params["cdn"]["Name"]];
        }

        foreach ($params["playTypes"] as $playType) {
            switch ($playType){
                case "rtmp":
                    $domain = $params["cdn"]["PlayRtmpDomain"];
                    $playUrl["RtmpUrl"] = $this->_formatSchema($cdnInterface->genPullRtmpUrl($domain, $app, $stream, $suffix), $enableSSL);
                    break;

                case "hls":
                $domain = $params["cdn"]["PlayHlsDomain"];
                $playUrl["HlsUrl"] = $this->_formatSchema($cdnInterface->genPullHlsUrl($domain, $app, $stream, $suffix), $enableSSL);
                break;

                case "flv":
                    $domain = $params["cdn"]["PlayFlvDomain"];
                    if($params["templateInfo"]["Name"] == "md" && $params["cdn"]["AdminFlvDomain"] != ""){
                        $domain = $params["cdn"]["AdminFlvDomain"];
                        $enableSSL = true;
                    }
                    $playUrl["FlvUrl"] = $this->_formatSchema($cdnInterface->genPullFlvUrl($domain, $app, $stream, $suffix), $enableSSL);
                    break;

                case "cmaf":
                    $domain = $params["cdn"]["PlayCmafDomain"];
                    $playUrl["CmafUrl"] = $this->_formatSchema($cdnInterface->genPullCmafUrl($domain, $app, $stream, $suffix), $enableSSL);
                    break;

                case "dash":
                    $domain = $params["cdn"]["PlayDashDomain"];
                    $playUrl["DashUrl"] = $this->_formatSchema($cdnInterface->genPullDashUrl($domain, $app, $stream, $suffix), $enableSSL);
                    break;

                default:
                    error_log("unsupported play type:$playType");
            }
        }
        if (!$playUrl["RtmpUrl"] && !$playUrl["FlvUrl"] && !$playUrl["HlsUrl"] && !$playUrl["CmafUrl"] && !$playUrl["DashUrl"]){
            return ["error" => "all urls empty"];
        }
        return [
            "Size" => $params["size"],
            "Url" => $playUrl,
        ];
    }

    protected function _formatSchema($fullUrl, $enableSSL){
        $parsedUrl = parse_url($fullUrl);
        if(!$parsedUrl){
            error_log(logPrefix."parse url failed");
            return "";
        }

        if ($enableSSL && $parsedUrl["scheme"] == "http"){
            $parsedUrl["scheme"] = "https";
        }
        return sprintf('%s://%s%s', $parsedUrl["scheme"], $parsedUrl["host"], $parsedUrl["path"]);
    }

    /**
     * @param array $streams
     * @return array[]|string[]
     */
    protected function getStreamsFallbackInfo(array $streams){
        $streamInfos = [];
        foreach ($streams as $stream) {
            $streamInfo = $this->_getStreamFallBackInfo($stream);
            if (!$streamInfo){
                continue;
            }
            $streamInfos[$stream] = $streamInfo;
        }

        if (count($streamInfos) == 0){
            return [
                "error" => "get streams fall back info empty"
            ];
        }
        return $streamInfos;
    }

    /**
     * @param $stream
     * @return array|bool
     */
    protected function _getStreamFallBackInfo($stream){
        $parsedResult = $this->parseStreamID($stream);
        if (!$parsedResult){
            return false;
        }

        $appInfo = $this->allAppInfoCache->getAppInfoByPushID($parsedResult["pushID"]);
        if (!$appInfo){
            error_log(logPrefix."not found app info");
            return false;
        }

        return [
            "status" => -1,
            "resolutions" => $appInfo["Resolutions"],
            "playTypes" => $this->_concatPlayTypes($appInfo),
            "pushMainCdnappId" => $parsedResult["pushID"],
            "liveId" => $stream,
            "appid" => $appInfo["Id"],
            "createTime" => $parsedResult["createTime"],
            "description" => "{}"
        ];
    }

    /**
     * @param array $appInfo
     * @return string
     */
    protected function _concatPlayTypes(array $appInfo){
        $playTypes = [
            "rtmp" => $appInfo["IsPlayRtmp"],
            "flv" => $appInfo["IsPlayFlv"],
            "hls" => $appInfo["IsPlayHls"],
            "dash" => $appInfo["IsPlayDash"],
            "cmaf" => $appInfo["IsPlayCmaf"]
        ];

        $result = [];
        foreach ($playTypes as $playType => $ok){
            if ($ok){
                $result[] = $playType;
            }
        }
        return join(',', $result);
    }

    protected function parseStreamID($stream){
        $fields = explode('-', $stream);
        if (count($fields) != 2){
            return false;
        }

        $id = (int)$fields[count($fields)-1];
        if($id == 0 ){
            error_log("unparsable stream id=$stream");
            return false;
        }

        if($id >> 62 != 0 ){
            return false;
        }

        $pushID = $id & self::$pushIDMask;
        $createTime = ($id >> (self::pushIDBits + self::countBits)) & self::$tsMask;
        return [
            "createTime" => $createTime,
            "pushID" => $pushID
        ];
    }
}

function _sortSizes($a, $b){
    return (allowedSize2Priority[$a] < allowedSize2Priority[$b])?-1:1;
}

function split($str, $sep){
    $str = trim($str, ' ');
    if ($str == ''){
        return [];
    }

    return explode($sep, $str);
}