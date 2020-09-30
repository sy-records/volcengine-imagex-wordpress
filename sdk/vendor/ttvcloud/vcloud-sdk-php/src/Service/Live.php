<?php

namespace Vcloud\Service;

use GuzzleHttp\HandlerStack;
use Vcloud\Base\V4Curl;
use GuzzleHttp\Client;
use Vcloud\Service\Live\AllAppInfoCache;
use Vcloud\Service\Live\FallbackPlayInfo;

const logPrefix = "[vcloud-live]";
class Live extends V4Curl
{
    protected $fallbackPlayInfo;
    public $allAppInfoCache;

    public function __construct()
    {
        $this->region = func_get_arg(0);
        $this->stack = HandlerStack::create();
        $this->stack->push($this->replaceUri());
        $this->stack->push($this->v4Sign());

        $config = $this->getConfig($this->region);
        $this->client = new Client([
            'handler' => $this->stack,
            'base_uri' => $config['host'],
        ]);

        $this->allAppInfoCache = new AllAppInfoCache($this);
        $this->fallbackPlayInfo = new FallbackPlayInfo($this->allAppInfoCache);
    }

    protected function getConfig(string $region)
    {
        switch ($region) {
            case 'cn-north-1':
                $config = [
                    'host' => 'https://live.bytedanceapi.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'cn-north-1',
                            'service' => 'live',
                        ],
                    ],
                ];
                break;
            case 'ap-singapore-1':
                $config = [
                    'host' => 'https://live.ap-singapore-1.bytedanceapi.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'ap-singapore-1',
                            'service' => 'live',
                        ],
                    ],
                ];
                break;
            case 'us-east-1':
                $config = [
                    'host' => 'https://live.us-east-1.bytedanceapi.com',
                    'config' => [
                        'timeout' => 5.0,
                        'headers' => [
                            'Accept' => 'application/json'
                        ],
                        'v4_credentials' => [
                            'region' => 'us-east-1',
                            'service' => 'live',
                        ],
                    ],
                ];
                break;
            default:
                throw new \Exception("Cant find the region, please check it carefully");
        }
        return $config;
    }

    protected $apiList = [
        'CreateStream' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'CreateStream',
                    'Version' => '2019-10-01',
                ],
            ]
        ],
        'MGetStreamsPushInfo' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'MGetStreamsPushInfo',
                    'Version' => '2019-10-01',
                ],
            ]
        ],
        'MGetStreamsPlayInfo' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'MGetStreamsPlayInfo',
                    'Version' => '2019-10-01',
                ],
            ]
        ],
        'GetVODs' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetVODs',
                    'Version' => '2019-10-01',
                ],
            ]
        ],
        'GetRecords' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetRecords',
                    'Version' => '2019-10-01',
                ],
            ]
        ],
        'GetSnapshots' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetSnapshots',
                    'Version' => '2019-10-01',
                ],
            ]
        ],
        'GetStreamTimeShiftInfo' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetStreamTimeShiftInfo',
                    'Version' => '2019-10-01',
                ],
            ]
        ],
        'GetOnlineUserNum' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetOnlineUserNum',
                    'Version' => '2019-10-01',
                ],
            ]
        ],
        'ForbidStream' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'ForbidStream',
                    'Version' => '2019-10-01',
                ],
            ]
        ],
        'GetDesensitizedAllAppInfos' => [
            'url' => '/',
            'method' => 'get',
            'config' => [
                'query' => [
                    'Action' => 'GetDesensitizedAllAppInfos',
                    'Version' => '2019-10-01',
                ],
            ]
        ]
    ];

    public function request($api, array $config = [])
    {
        if($this->allAppInfoCache->isEmptyCache() && $api != "GetDesensitizedAllAppInfos"){
            $this->allAppInfoCache->updateAllAppInfosCache();
        }
        return parent::request($api, $config);
    }

    // 创建直播流
    public function createStream($appID, $stream = '', $delayTime = 0, $extra = '', array $clientInfo=null) {
        $json = [
            'AppID' => $appID,
            'Stream' => $stream,
            'DelayTime' => $delayTime,
            'Extra' => $extra,
            'ClientInfo' => $clientInfo
        ];

        $response = $this->request('CreateStream', ['json' => $json]);
        return json_decode((string)$response->getBody(),true);
    }

    // 获取推流信息
    public function getStreamsPushInfo(array $streams) {
        $json = [
            'Streams' => $streams
        ];

        $response = $this->request('MGetStreamsPushInfo', ['json' => $json]);
       return json_decode((string)$response->getBody(),true);
    }

    // 获取播放地址
    public function getStreamsPlayInfo(array $streams, $enableSSL=false, array $clientInfo=null, $enableStreamData = false) {
        $json = [
            'Streams' => $streams,
            'EnableSSL' => $enableSSL,
            'ClientInfo' => $clientInfo,
            'EnableStreamData' => $enableStreamData
        ];

        $response = $this->request('MGetStreamsPlayInfo', ['json' => $json]);
        $respArr = json_decode((string)$response->getBody(),true);
        if (isset($respArr["ResponseMetadata"]["Error"])){
            $playInfos = $this->fallbackPlayInfo->getStreamsFallbackPlayInfo($streams, $enableSSL, $clientInfo, $enableStreamData);

            if (isset($playInfos["error"])){
                error_log(logPrefix.'mget stream fall back play info failed, err='.$playInfos["error"]);
            }else {
                $respArr["ResponseMetadata"]["Error"] = null;
                return [
                    "Result" => ["PlayInfos" => $playInfos],
                    "ResponseMetadata" => $respArr["ResponseMetadata"]
                ];
            }
        }

        return $respArr;
    }

    // 获取点播信息
    public function getVODs($stream) {
        $query = ['Stream' => $stream];

        $response = $this->request('GetVODs', ['query' => $query]);
        return json_decode((string)$response->getBody(),true);
    }

    // 获取录像信息
    public function getRecords($stream) {
        $query = ['Stream' => $stream];

        $response = $this->request('GetRecords', ['query' => $query]);
        return json_decode((string)$response->getBody(),true);
    }

    // 获取截图信息
    public function getSnapshots($stream) {
        $query = ['Stream' => $stream];

        $response = $this->request('GetSnapshots', ['query' => $query]);
        return json_decode((string)$response->getBody(),true);
    }

    // 获取时移信息
    public function getStreamTimeShiftInfo($stream, $startTime, $endTime) {
        $query = [
            'Stream' => $stream,
            'StartTime' => $startTime,
            'EndTime' => $endTime,
        ];

        $response = $this->request('GetStreamTimeShiftInfo', ['query' => $query]);
        return json_decode((string)$response->getBody(),true);
    }

    // 获取在线人数
    public function getOnlineUserNum($stream, $startTime, $endTime) {
        $query = [
            'Stream' => $stream,
            'StartTime' => $startTime,
            'EndTime' => $endTime,
        ];

        $response = $this->request('GetOnlineUserNum', ['query' => $query]);
        return json_decode((string)$response->getBody(),true);
    }

    // 禁播单路流
    public function forbidStream($stream, $forbidInterval=0) {
        $json = [
            'Stream' => $stream,
            'ForbidInterval' => $forbidInterval,
        ];

        $response = $this->request('ForbidStream', ['json' => $json]);
        return json_decode((string)$response->getBody(),true);
    }

    // 获取脱敏元信息
    public function getDesensitizedAllAppInfos() {
        $response = $this->request('GetDesensitizedAllAppInfos');
        return json_decode((string)$response->getBody(),true);
    }
}
