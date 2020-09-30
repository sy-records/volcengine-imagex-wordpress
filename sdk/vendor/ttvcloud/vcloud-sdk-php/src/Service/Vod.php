<?php

namespace Vcloud\Service;

use Vcloud\Base\V4Curl;
use GuzzleHttp\Client;

const ResourceSpaceFormat = "trn:vod:%s:*:space/%s";
const ResourceVideoFormat = "trn:vod::*:video_id/%s";
const ResourceStreamTypeFormat = "trn:vod:::stream_type/%s";
const ResourceWatermarkFormat = "trn:vod::*:watermark/%s";
const ActionGetPlayInfo = "vod:GetPlayInfo";
const ActionApplyUpload = "vod:ApplyUpload";
const ActionCommitUpload = "vod:CommitUpload";
const Star = "*";
const Statement = "Statement";

class Vod extends V4Curl
{
    private static $UPDATE_INTERVAL = 10;
    private $lastDomainUpdateTime;
    private $domainCache = array();

    protected function getConfig(string $region)
    {
        switch ($region) {
            case 'cn-north-1':
                $config = [
                    'host' => 'https://vod.bytedanceapi.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'cn-north-1',
                            'service' => 'vod',
                        ],
                    ],
                ];
                break;
            case 'ap-singapore-1':
                $config = [
                    'host' => 'https://vod.ap-singapore-1.bytedanceapi.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'ap-singapore-1',
                            'service' => 'vod',
                        ],
                    ],
                ];
                break;
            case 'us-east-1':
                $config = [
                    'host' => 'https://vod.us-east-1.bytedanceapi.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'us-east-1',
                            'service' => 'vod',
                        ],
                    ],
                ];
                break;
            default:
                throw new \Exception("Cant find the region, please check it carefully");
        }
        return $config;
    }

    public function getSpace(array $query)
    {
        $response = $this->request('GetSpace', $query);
        return $response->getBody();
    }

    public function getPlayAuthToken(array $config = [], string $version = "v1")
    {
        switch ($version) {
            case "v1":
                $token = ["Version" => $version];
                $token["GetPlayInfoToken"] = parse_url($this->getRequestUrl("GetPlayInfo", $config))["query"];
                return base64_encode(json_encode($token));
            case "v0": // deprecated func
                $url = $this->getRequestUrl("GetPlayInfo", $config);
                $m = parse_url($url);
                return $m["query"];
            default:
                $token = ["Version" => $version];
                $token["GetPlayInfoToken"] = parse_url($this->getRequestUrl("GetPlayInfo", $config))["query"];
                return base64_encode(json_encode($token));
        }
    }

    public function getPlayInfo(array $query)
    {
        $response = $this->request('GetPlayInfo', $query);
        return (string)$response->getBody();
    }

    public function getOriginVideoPlayInfo(array $query)
    {
        $response = $this->request('GetOriginVideoPlayInfo', $query);
        return (string)$response->getBody();
    }

    public function getRedirectPlay(array $query)
    {
        $response = $this->getRequestUrl('RedirectPlay', $query);
        return $response;
    }

    // 开放参数设置
    public function getUploadAuthToken(array $config = [], string $version = "v1")
    {
        $token = ["Version" => $version];
        switch ($version) {
            case "v1":
                $this->getUploadAuthTokenV1($config, $token);
            default:
                $token["Version"] = "v1";
                $this->getUploadAuthTokenV1($config, $token);
        }
        return base64_encode(json_encode($token));
    }

    private function getUploadAuthTokenV1(array $config, array &$token)
    {
        $url = $this->getRequestUrl("ApplyUpload", $config);
        $m = parse_url($url);

        $token["ApplyUploadToken"] = $m["query"];

        $url = $this->getRequestUrl("CommitUpload", $config);
        $m = parse_url($url);

        $token["CommitUploadToken"] = $m["query"];
    }

    public function applyUpload(array $query)
    {
        $response = $this->request('ApplyUpload', $query);
        return (string)$response->getBody();
    }

    public function commitUpload(array $query)
    {
        $response = $this->request('CommitUpload', $query);
        return (string)$response->getBody();
    }

    public function uploadFile(string $uploadHost, $storeInfo, string $filePath)
    {
        if (!file_exists($filePath)) {
            return -1;
        }
        $content = file_get_contents($filePath);
        $crc32 = dechex(crc32($content));

        $body = fopen($filePath, "r");
        $tosClient = new Client([
            'base_uri' => "http://" . $uploadHost,
            'timeout' => 5.0,
        ]);

        $response = $tosClient->request('PUT', $storeInfo["StoreUri"], ["body" => $body, "headers" => ['Authorization' => $storeInfo["Auth"], 'Content-CRC32' => $crc32]]);
        $uploadResponse = json_decode((string)$response->getBody(), true);
        if (!isset($uploadResponse["success"]) || $uploadResponse["success"] != 0) {
            return -2;
        }
        return 0;
    }

    public function upload(string $spaceName, string $filePath, string $fileType)
    {
        if (!file_exists($filePath)) {
            return array(-1, "file not exists", "", "");
        }
        $content = file_get_contents($filePath);
        $crc32 = dechex(crc32($content));

        $response = $this->applyUpload(['query' => ['SpaceName' => $spaceName]]);
        $applyResponse = json_decode($response, true);
        if (isset($applyResponse["ResponseMetadata"]["Error"])) {
            return array(-1, $applyResponse["ResponseMetadata"]["Error"]["Message"], "", "");
        }
        $uploadHost = $applyResponse['Result']['UploadAddress']['UploadHosts'][0];
        $oid = $applyResponse['Result']['UploadAddress']['StoreInfos'][0]['StoreUri'];
        $session = $applyResponse['Result']['UploadAddress']['SessionKey'];

        $respCode = $this->uploadFile($uploadHost, $applyResponse['Result']['UploadAddress']['StoreInfos'][0], $filePath);
        if ($respCode != 0) {
            return array(-1, "upload " . $filePath . " error", "", "");
        }

        return array(0, "", $session, $oid);
    }

    public function uploadVideo(string $spaceName, string $filePath, array $functions = [])
    {
        $resp = $this->upload($spaceName, $filePath, "video");
        if ($resp[0] != 0) {
            return $resp[1];
        }
        $response = $this->commitUpload(['query' => ['SpaceName' => $spaceName], 'json' => ['SessionKey' => $resp[2], 'Functions' => $functions]]);
        return (string)$response;
    }

    public function uploadPoster(string $vid, string $spaceName, string $filePath)
    {
        $resp = $this->upload($spaceName, $filePath, "image");
        if ($resp[0] != 0) {
            return $resp[1];
        }
        $response = $this->modifyVideoInfo(['query' => [], 'json' => ['SpaceName' => $spaceName, 'Vid' => $vid, 'Info' => ['PosterUri' => $resp[3]]]]);
        return (string)$response;
    }

    public function uploadMediaByUrl(array $query)
    {
        $response = $this->request('UploadMediaByUrl', $query);
        return (string)$response->getBody();
    }

    public function modifyVideoInfo(array $query)
    {
        $response = $this->request('ModifyVideoInfo', $query);
        return (string)$response->getBody();
    }

    public function startTranscode(array $query)
    {
        $response = $this->request('StartTranscode', $query);
        return (string)$response->getBody();
    }

    public function setVideoPublishStatus(array $query)
    {
        $response = $this->request('SetVideoPublishStatus', $query);
        return (string)$response->getBody();
    }

    private function getDomainInfo(string $space, array $fallbackWeights)
    {
        if (!empty($this->lastDomainUpdateTime)) {
            $now = time();
            if ($now - $this->lastDomainUpdateTime <= Vod::$UPDATE_INTERVAL) {
                $domainArray = $this->domainCache[$space];
                return $this->packDomainInfo($domainArray);
            }
        }
        $this->lastDomainUpdateTime = time();
        $response = $this->request('GetCdnDomainWeights', ['query' => ['SpaceName' => $space]]);
        $respJson = json_decode($response->getBody(), true);
        if (array_key_exists('Error', $respJson['ResponseMetadata']) || !is_array($respJson['Result'][$space])) {
            $this->domainCache[$space] = $fallbackWeights;
        } else {
            $this->domainCache[$space] = $respJson['Result'][$space];
        }
        $domainArray = $this->domainCache[$space];
        return $this->packDomainInfo($domainArray);
    }

    private function packDomainInfo(array $domainArray)
    {
        $mainDomain = $this->randWeights($domainArray, '');
        $backupDomain = $this->randWeights($domainArray, $mainDomain);
        return array('MainDomain' => $mainDomain, 'BackupDomain' => $backupDomain);
    }

    public function getPosterUrl(string $space, string $uri, array $fallbackWeights, VodOption $opt)
    {
        $domainInfo = $this->getDomainInfo($space, $fallbackWeights);
        $proto = VodOption::$HTTP;
        if ($opt->getHttps()) {
            $proto = VodOption::$HTTPS;
        }
        $format = VodOption::$FORMAT_ORIGINAL;
        if (!empty($opt->getFormat())) {
            $format = $opt->getFormat();
        }
        $tpl = VodOption::$VOD_TPL_NOOP;
        if (!empty($opt->getTpl())) {
            $tpl = $opt->getTpl();
        }

        if ($tpl == VodOption::$VOD_TPL_OBJ || $tpl == VodOption::$VOD_TPL_NOOP) {
            $tpl = $opt->getTpl();
        } else {
            $tpl = sprintf('%s:%d:%d', $opt->getTpl(), $opt->getW(), $opt->getH());
        }

        $mainUrl = sprintf('%s://%s/%s~%s.%s', $proto, $domainInfo['MainDomain'], $uri, $tpl, $format);
        $backupUrl = sprintf('%s://%s/%s~%s.%s', $proto, $domainInfo['BackupDomain'], $uri, $tpl, $format);
        return array('MainUrl' => $mainUrl, 'BackupUrl' => $backupUrl);
    }

    public function getVideoPlayAuthWithExpiredTime(array $vidList, array $streamTypeList, array $watermarkList, int $expire)
    {
        $actions = [ActionGetPlayInfo];
        $resources = [];
        $this->addSts2Resources($vidList, ResourceVideoFormat, $resources);
        $this->addSts2Resources($streamTypeList, ResourceStreamTypeFormat, $resources);
        $this->addSts2Resources($watermarkList, ResourceWatermarkFormat, $resources);
        $statement = $this->newAllowStatement($actions, $resources);
        $policy = [
            Statement => [$statement],
        ];
        return $this->signSts2($policy, $expire);
    }

    public function getUploadVideoAuth()
    {
        return $this->getUploadVideoAuthWithExpiredTime(60 * 60);
    }

    public function getUploadVideoAuthWithExpiredTime(int $expire)
    {
        $actions = [ActionApplyUpload, ActionCommitUpload];
        $resources = [];
        $statement = $this->newAllowStatement($actions, $resources);
        $policy = [
            Statement => [$statement],
        ];
        return $this->signSts2($policy, $expire);
    }

    public function getVideoPlayAuth(array $vidList, array $streamTypeList, array $watermarkList)
    {
        return $this->getVideoPlayAuthWithExpiredTime($vidList, $streamTypeList, $watermarkList, 60 * 60);
    }

    private function addSts2Resources(array $list, string $resourceFormat, array &$resources)
    {
        if (sizeof($list) == 0) {
            $resources[] = sprintf($resourceFormat, Star);
        } else {
            foreach ($list as $value) {
                $resources[] = sprintf($resourceFormat, $value);
            }
        }
    }

    private function randWeights(array $domainWights, string $excludeDomain)
    {
        $weightSum = 0;
        foreach ($domainWights as $key => $value) {
            if ($key == $excludeDomain) {
                continue;
            }
            $weightSum += $value;
        }
        if ($weightSum <= 0) {
            return '';
        }
        $r = rand(1, $weightSum);
        foreach ($domainWights as $key => $value) {
            if ($key == $excludeDomain) {
                continue;
            }
            $r -= $value;
            if ($r <= 0) {
                return $key;
            }
        }
        return '';
    }

    protected $apiList = [
        'GetSpace' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetSpace',
                    'Version' => '2018-12-01',
                ],
            ]
        ],
        'ApplyUpload' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'ApplyUpload',
                    'Version' => '2018-01-01',
                ],
            ]
        ],
        'CommitUpload' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'CommitUpload',
                    'Version' => '2018-01-01',
                ],
            ]
        ],
        'GetPlayInfo' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetPlayInfo',
                    'Version' => '2019-03-15',
                ],
            ]
        ],
        'UploadMediaByUrl' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'UploadMediaByUrl',
                    'Version' => '2018-01-01',
                ],
            ]
        ],
        'StartTranscode' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'StartTranscode',
                    'Version' => '2018-01-01',
                ],
            ]
        ],
        'SetVideoPublishStatus' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'SetVideoPublishStatus',
                    'Version' => '2018-01-01',
                ],
            ]
        ],
        'RedirectPlay' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'RedirectPlay',
                    'Version' => '2018-01-01',
                ],
            ]
        ],
        'GetOriginVideoPlayInfo' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetOriginVideoPlayInfo',
                    'Version' => '2018-01-01',
                ],
            ]
        ],
        'GetCdnDomainWeights' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetCdnDomainWeights',
                    'Version' => '2019-07-01',
                ],
            ]
        ],
        'ModifyVideoInfo' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'ModifyVideoInfo',
                    'Version' => '2018-01-01',
                ],
            ]
        ],
    ];
}
