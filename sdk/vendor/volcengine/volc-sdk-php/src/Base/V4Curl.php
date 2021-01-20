<?php

/**
 * creator: maigohuang
 */

namespace Volc\Base;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Exception\ClientException;

abstract class V4Curl extends Singleton
{
    protected $client = null;
    protected $stack = null;
    protected $region = '';
    protected $ak = '';
    protected $sk = '';
    

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
        
        $this->version = trim(file_get_contents(__DIR__.'/../../VERSION'));
        
    }

    public function setAccessKey($ak)
    {
        if ($ak != "") {
            $this->ak = $ak;
        }
    }

    public function setHost($host)
    {
        if ($host != "") {
            $this->client = new Client([
                'handler' => $this->stack,
                'base_uri' => $host,
            ]);
        }
    }

    public function setSecretKey($sk)
    {
        if ($sk != "") {
            $this->sk = $sk;
        }
    }

    protected function v4Sign()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $v4 = new SignatureV4();
                $credentials = $this->prepareCredentials($options['v4_credentials']);
                $request = $v4->signRequest($request, $credentials);
                return $handler($request, $options);
            };
        };
    }

    abstract protected function getConfig(string $region);

    private function prepareCredentials(array $credentials)
    {
        if (!isset($credentials['ak']) || !isset($credentials['sk'])) {
            if ($this->ak != "" && $this->sk != "") {
                $credentials['ak'] = $this->ak;
                $credentials['sk'] = $this->sk;
            } elseif (getenv("VOLC_ACCESSKEY") != "" && getenv("VOLC_SECRETKEY") != "") {
                $credentials['ak'] = getenv("VOLC_ACCESSKEY");
                $credentials['sk'] = getenv("VOLC_SECRETKEY");
            } else {
                $json = json_decode(file_get_contents(getenv('HOME') . '/.volc/config'), true);
                if (is_array($json) && isset($json['ak']) && isset($json['sk'])) {
                    $credentials = array_merge($credentials, $json);
                }
            }
        }
        return $credentials;
    }

    public function getRequestUrl($api, array $config = [])
    {
        $config_api = isset($this->apiList[$api]) ? $this->apiList[$api] : false;

        $defaultConfig = $this->getConfig($this->region);
        $config = $this->configMerge($defaultConfig['config'], $config_api['config'], $config);
        $info = array_merge($defaultConfig, $config_api);

        $method = $info['method'];
        $request = new Request($method, $info['host'] . $info['url'] . '?' . http_build_query($config['query']));

        $credentials = $this->prepareCredentials($config['v4_credentials']);
        $v4 = new SignatureV4();

        return $v4->signRequestToUrl($request, $credentials);
    }


    public function request($api, array $config = [])
    {
        $config_api = isset($this->apiList[$api]) ? $this->apiList[$api] : false;

        $defaultConfig = $this->getConfig($this->region);
        $config = $this->configMerge($defaultConfig['config'], $config_api['config'], $config);
        $config['headers']['User-Agent'] = 'volc-sdk-php/'.$this->version;        
        $info = array_merge($defaultConfig, $config_api);
        $info['config'] = $config;

        $method = $info['method'];
        try {
            $response = $this->client->request($method, $info['url'], $info['config']);
            return $response;
        } catch (ClientException $exception) {
            return $exception->getResponse();
        }
    }

    public function signSts2(array $policy, int $expire)
    {
        // 获取长期的aksk信息
        $credentials = $this->prepareCredentials([]);
        $now = time();

        $sts = [
            "AccessKeyId" => $this->generateAccessKeyId("AKTP"),
            "SecretAccessKey" => $this->generateSecretKey(),
            "ExpiredTime" => date('Y-m-d\TH:i:sP', $now + $expire),
            "CurrentTime" => date('Y-m-d\TH:i:sP', $now),
        ];

        $innerToken = $this->createInnerToken($credentials, $sts, $policy, $now + $expire);
        $sts["SessionToken"] = "STS2" . base64_encode(json_encode($innerToken));

        return $sts;
    }

    private function createInnerToken(array $credentials, array $sts, array $policy, int $expire)
    {
        $inner = [
            "LTAccessKeyId" => $credentials["ak"],
            "AccessKeyId" => $sts["AccessKeyId"],
            "ExpiredTime" => $expire,
        ];

        $key = md5($credentials["sk"], true);
        $inner["SignedSecretAccessKey"] = $this->aesEncrypt($sts["SecretAccessKey"], $key);

        if (sizeof($policy) > 0) {
            $inner["PolicyString"] = json_encode($policy);
        }else {
            $inner["PolicyString"] = "";
        }

        $signStr = sprintf("%s|%s|%d|%s|%s",
            $inner["LTAccessKeyId"],
            $inner["AccessKeyId"],
            $inner["ExpiredTime"],
            $inner["SignedSecretAccessKey"],
            $inner["PolicyString"]);

        $inner["Signature"] = hash_hmac('sha256', $signStr, $key);

        return $inner;
    }

    public function newAllowStatement(array $actions, array $resources)
    {
        return [
            "Effect" => "Allow",
            "Action" => $actions,
            "Resource" => $resources,
        ];
    }

    public function newDenyStatement(array $actions, array $resources)
    {
        return [
            "Effect" => "Deny",
            "Action" => $actions,
            "Resource" => $resources,
        ];
    }

    protected function configMerge($c1, $c2, $c3)
    {
        $result = $c1;
        foreach ($c2 as $k => $v) {
            if (isset($result[$k]) && is_array($result[$k]) && is_array($v)) {
                $result[$k] = array_merge($result[$k], $v);
            } else {
                $result[$k] = $v;
            }
        }

        foreach ($c3 as $k => $v) {
            if (isset($result[$k]) && is_array($result[$k]) && is_array($v)) {
                $result[$k] = array_merge($result[$k], $v);
            } else {
                $result[$k] = $v;
            }
        }
        return $result;
    }

    protected function replaceUri()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                if (isset($options['replace'])) {
                    $replace = $options['replace'];
                    $uri = (string) $request->getUri();

                    $func = function ($matches) use ($replace) {
                        $key = substr($matches[0], 1, -1);
                        return $replace[$key];
                    };
                    $uri = preg_replace_callback('/\{.*?\}/', $func, $uri);

                    $func2 = function ($matches) use ($replace) {
                        $key = substr($matches[0], 3, -3);
                        return $replace[$key];
                    };
                    $uri = preg_replace_callback('/%7B.*?%7D/', $func2, $uri);
                    $request = $request->withUri(new Uri($uri));
                }
                return $handler($request, $options);
            };
        };
    }

    protected function generateAccessKeyId($prefix)
    {
        // 随机128bit,转化成16进制后再base64编码
        $accessKeyId = $prefix . base64_encode(bin2hex(random_bytes(16)));

        $accessKeyId = str_replace("=", "", $accessKeyId);
        $accessKeyId = str_replace("/", "", $accessKeyId);
        $accessKeyId = str_replace("+", "", $accessKeyId);
        $accessKeyId = str_replace("-", "", $accessKeyId);
        return $accessKeyId;
    }

    protected function generateSecretKey()
    {
        return base64_encode(bin2hex(random_bytes(30)));
    }

    protected function aesEncrypt($src, $pwd)
    {
        return base64_encode(openssl_encrypt($src, "AES-128-CBC", $pwd, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $pwd));
    }
}
