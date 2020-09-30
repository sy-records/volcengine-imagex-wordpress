<?php

namespace Vcloud\Service;

use Vcloud\Base\V4Curl;
use GuzzleHttp\Client;


const ResourceServiceIdTRN = "trn:ImageX:*:*:ServiceId/%s";

class ImageX extends V4Curl
{
	private static $updateInterval = 10;
    private $lastDomainUpdateTime = 0;
    private $domainCache = [];

    protected function getConfig(string $region)
    {
        switch ($region) {
            case 'cn-north-1':
                $config = [
                    'host' => 'https://imagex.bytedanceapi.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'cn-north-1',
                            'service' => 'ImageX',
                        ],
                    ],
                ];
                break;
            case 'cn-north-1-inner':
                $config = [
                    'host' => 'http://imagex.byted.org',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'cn-north-1',
                            'service' => 'ImageX',
                        ],
                    ],
                ];
                break;
            case 'ap-singapore-1':
                $config = [
                    'host' => 'https://imagex.ap-singapore-1.bytedanceapi.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'ap-singapore-1',
                            'service' => 'ImageX',
                        ],
                    ],
                ];
                break;
            case 'ap-singapore-1-inner':
                $config = [
                    'host' => 'http://imagex.ap-singapore-1.byted.org',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'ap-singapore-1',
                            'service' => 'ImageX',
                        ],
                    ],
                ];
                break;
            case 'us-east-1':
                $config = [
                    'host' => 'https://imagex.us-east-1.bytedanceapi.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'us-east-1',
                            'service' => 'ImageX',
                        ],
                    ],
                ];
                break;
            case 'us-east-1-inner':
                $config = [
                    'host' => 'http://imagex.us-east-1.byted.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'us-east-1',
                            'service' => 'ImageX',
                        ],
                    ],
                ];
                break;
            default:
                throw new \Exception("Cant find the region, please check it carefully");
        }
        return $config;
    }

    public function applyUploadImage(array $query)
    {
        $response = $this->request('ApplyImageUpload', $query);
        return (string) $response->getBody();
    }

    public function commitUploadImage(array $query)
    {
        $response = $this->request('CommitImageUpload', $query);
        return (string) $response->getBody();
    }

    public function updateImageUrls($serviceID, $urls, $action = 0)
    {
        if ($action < 0 || $action > 2)
        {
            throw new \Exception(sprintf("update action should be [0,2], %d", $action));
        }

        $config = [
            "query" => ["ServiceId" => $serviceID],
            "json" => [
                "Action" => $action,
                "ImageUrls" => $urls,
            ],
        ];

        
        $response = $this->request('UpdateImageUploadFiles', $config);
        return (string)$response->getBody();
    }

    public function upload(string $uploadHost, $storeInfo, string $filePath)
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
        $uploadResponse = json_decode((string) $response->getBody(), true);
        if (!isset($uploadResponse["success"]) || $uploadResponse["success"] != 0) {
            return -2;
        }
        return 0;
    }

    public function uploadImages(array $params = [], array $filePaths = [])
    {
        if (!isset($params["UploadNum"]) || $params["UploadNum"] == 0) {
            $params["UploadNum"] = 1;
        }
        if (count($filePaths) != $params["UploadNum"]) {
            return "images num != upload num";
        }
        $applyUploadParams = array();
        if (!isset($params["ServiceId"])) {
            return "no ServiceId found";
        }
        $applyUploadParams["ServiceId"] = $params["ServiceId"];
        if (isset($params["SessionKey"])) {
            $applyUploadParams["SessionKey"] = $params["SessionKey"];
        }
        if (!isset($params["StoreKeys"]) || count($params["StoreKeys"]) != $params["UploadNum"]) {
            return "no StoreKeys found or StoreKeys size is unmatch";
        }
        $applyUploadParams["StoreKeys"] = array();
        $applyUploadParams["UploadNum"] = $params["UploadNum"];

        // build query custom
        $applyUploadParams["Action"] = "ApplyImageUpload";
        $applyUploadParams["Version"] = "2018-08-01";

        $queryStr = http_build_query($applyUploadParams);

        foreach ($params["StoreKeys"] as $key => $value) {
            $queryStr = $queryStr . "&StoreKeys=" . urlencode($value);
        }

        $response = $this->applyUploadImage(['query' => $queryStr]);
        $applyResponse = json_decode($response, true);
        if (isset($applyResponse["ResponseMetadata"]["Error"])) {
            return $applyResponse["ResponseMetadata"]["Error"]["Message"];
        }
        $uploadAddr = $applyResponse['Result']['UploadAddress'];
        if (count($uploadAddr['UploadHosts']) == 0) {
            return "no upload host found";
        }
        $uploadHost = $uploadAddr['UploadHosts'][0];
        if (count($uploadAddr['StoreInfos']) != $params["UploadNum"]) {
            return "store infos num != upload num";
        }

        for ($i = 0; $i < count($filePaths); ++$i) {
            $respCode = $this->upload($uploadHost, $uploadAddr['StoreInfos'][$i], $filePaths[$i]);
            if ($respCode != 0) {
                return "upload " . $filePaths[$i] . " error";
            }
        }

        $commitUploadParams = [
            "query" => ["ServiceId" => $params["ServiceId"]],
            "json" => ["SessionKey" => $uploadAddr['SessionKey']],
        ];

        $response = $this->commitUploadImage($commitUploadParams);
        return (string) $response;
    }

    public function deleteImages(string $serviceID, array $uris = [])
    {
        $response = $this->request('DeleteImageUploadFiles', ['query' => ['ServiceId' => $serviceID], 'json' => ['StoreUris' => $uris]]);
        return (string)$response->getBody();
    }

    // getImagexURL 获取图片地址
    public function getImageXURL(string $serviceID, string $uri, string $tpl, array $fallbackWeights, ImageXOption $opt)
    {
        $domainInfo = $this->getDomainInfo($serviceID, $fallbackWeights);

        $proto = ImageXOption::$HTTP;
        if ($opt->getHTTPs()) 
        {
            $proto = ImageXOption::$HTTPS;
        }

        $format = $opt->getFormat();

        $mainURL   = sprintf('%s://%s/%s~%s.%s', $proto, $domainInfo['MainDomain'], $uri, $tpl, $format);
        $backupURL = sprintf('%s://%s/%s~%s.%s', $proto, $domainInfo['BackupDomain'], $uri, $tpl, $format);
        return ['MainUrl' => $mainURL, 'BackupUrl' => $backupURL];
    }

    public function getUploadAuthToken($query)
    {
        $token = [
            "Version" => 'v1',
        ];

        $url = $this->getRequestUrl("ApplyImageUpload", $query);
        $m = parse_url($url);
        $token["ApplyUploadToken"] = $m["query"];


        $url = $this->getRequestUrl("CommitImageUpload", $query);
        $m = parse_url($url);
        $token["CommitUploadToken"] = $m["query"];

        return base64_encode(json_encode($token));
    }

    // getUploadAuth 获取上传图片的sts
    public function getUploadAuth(array $serviceIDList, int $expire = 3600)
    {
        $actions = ['ImageX:ApplyImageUpload', 'ImageX:CommitImageUpload'];
        $resources = [];
        if (sizeof($serviceIDList) == 0)
        {
            $resources[] = sprintf(ResourceServiceIdTRN, "*");
        }else
        {
            foreach ($serviceIDList as $serviceID)
            {
                $resources[] = sprintf(ResourceServiceIdTRN, $serviceID);
            }
        }

        $statement = $this->newAllowStatement($actions, $resources);
        $policy = [
            'Statement' => [$statement],
        ];

        return $this->signSts2($policy, $expire);
    }

    // getDomainInfo
    private function getDomainInfo(string $serviceID, array $fallbackWeights)
    {
        $now = time();
        if ($now - $this->lastDomainUpdateTime <= Imagex::$updateInterval) 
        {
            // 命中cache
            $domainArray = $this->domainCache[$serviceID];
            return $this->packDomainInfo($domainArray);
        }   

        $this->lastDomainUpdateTime = time();
        $response = $this->request('GetCdnDomainWeights', ['query' => ['ServiceId' => $serviceID, 'ProductLine' => 'imagex']]);
        $respJson = json_decode($response->getBody(), true);

        if (array_key_exists('Error', $respJson['ResponseMetadata']) || !is_array($respJson['Result'][$serviceID])) {
            $this->domainCache[$serviceID] = $fallbackWeights;
        } else {
            $this->domainCache[$serviceID] = $respJson['Result'][$serviceID];
        }

        // 更新cache的数据
        $domainArray = $this->domainCache[$serviceID];
        return $this->packDomainInfo($domainArray);
    }

    // packDomainInfo
    private function packDomainInfo(array $domainArray) 
    {
        $mainDomain = $this->randWeights($domainArray, '');
        $backupDomain = $this->randWeights($domainArray, $mainDomain);
        return array('MainDomain' => $mainDomain, 'BackupDomain' => $backupDomain);
    }

    // randWeigths
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
        'ApplyImageUpload' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'ApplyImageUpload',
                    'Version' => '2018-08-01',
                ],
            ]
        ],
        'CommitImageUpload' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'CommitImageUpload',
                    'Version' => '2018-08-01',
                ],
            ]
        ],
        'UpdateImageUploadFiles' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'UpdateImageUploadFiles',
                    'Version' => '2018-08-01',
                ],
            ]
        ],
        'DeleteImageUploadFiles' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'DeleteImageUploadFiles',
                    'Version' => '2018-08-01',
                ],
            ]
        ],
    ];
}
