<?php


namespace Volc\Service\Vod\Upload;


use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RetryMiddleware;
use Throwable;
use Volc\Models\Vod\Business\VodStoreInfo;
use Volc\Models\Vod\Request\VodApplyUploadInfoRequest;
use Volc\Models\Vod\Request\VodCommitUploadInfoRequest;
use Volc\Models\Vod\Request\VodUploadMediaRequest;
use Volc\Models\Vod\Response\VodCommitUploadInfoResponse;
use Volc\Service\Vod\Vod;

const MinChunkSize = 1024 * 1024 * 20;
const LargeFileSize = 1024 * 1024 * 1024;

class VodUpload extends Vod
{
    public function uploadMedia(VodUploadMediaRequest $vodUploadMediaRequest): VodCommitUploadInfoResponse
    {
        $applyRequest = new VodApplyUploadInfoRequest();
        $applyRequest->setSpaceName($vodUploadMediaRequest->getSpaceName());
        $resp = $this->upload($applyRequest, $vodUploadMediaRequest->getFilePath());
        if ($resp[0] != 0) {
            return $resp[1];
        }
        $request = new VodCommitUploadInfoRequest();
        $request->setSpaceName($vodUploadMediaRequest->getSpaceName());
        $request->setSessionKey($resp[2]);
        $request->setCallbackArgs($vodUploadMediaRequest->getCallbackArgs());
        $request->setFunctions($vodUploadMediaRequest->getFunctions());
        try {
            return $this->commitUploadInfo($request);
        } catch (Throwable $e) {
            throw e;
        }
    }

    public function upload(VodApplyUploadInfoRequest $applyRequest, string $filePath)
    {
        if (!file_exists($filePath)) {
            return array(-1, "file not exists", "", "");
        }
        try {
            $response = $this->applyUploadInfo($applyRequest);
        } catch (Throwable $e) {
            return array(-1, $e->getMessage(), "", "");
        }
        if ($response->getResponseMetadata()->getError() != null) {
            print_r($response->getResponseMetadata()->getError());
        }

        $uploadAddress = $response->getResult()->getData()->getUploadAddress();

        $uploadHost = $uploadAddress->getUploadHosts()[0];
        $oid = $uploadAddress->getStoreInfos()[0]->getStoreUri();
        $session = $uploadAddress->getSessionKey();

        $respCode = $this->uploadFile($uploadHost, $uploadAddress->getStoreInfos()[0], $filePath);
        if ($respCode != 0) {
            return array(-1, "upload " . $filePath . " error", "", "");
        }

        return array(0, "", $session, $oid);
    }

    public function uploadFile(string $uploadHost, VodStoreInfo $storeInfo, string $filePath)
    {
        if (!file_exists($filePath)) {
            return -1;
        }

        $oid = $storeInfo->getStoreUri();
        $auth = $storeInfo->getAuth();

        $handlerStack = HandlerStack::create(new CurlHandler());
        $handlerStack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));

        $client = new Client([
            'base_uri' => "http://" . $uploadHost,
            'timeout' => 30.0,
            'handler' => $handlerStack,
        ]);

        $fileSize = filesize($filePath);
        if ($fileSize < MinChunkSize) {
            return $this->directUpload($oid, $auth, $filePath, $client);
        } elseif ($fileSize > LargeFileSize) {
            return $this->chunkUpload($oid, $auth, $filePath, true, $client);
        } else {
            return $this->chunkUpload($oid, $auth, $filePath, false, $client);
        }
    }

    private function retryDecider()
    {
        return function ($retries,
                         Request $request,
                         Response $response = null,
                         RequestException $exception = null) {
            if ($retries >= 3) {
                return false;
            }
            return false;
        };
    }

    private function retryDelay()
    {
        return function ($num) {
            return RetryMiddleware::exponentialDelay($num);
        };
    }

    private function directUpload(string $oid, string $auth, string $filePath, Client $client)
    {
        $content = file_get_contents($filePath);
        $crc32 = sprintf("%08x", crc32($content));
        $response = $client->put($oid, ["body" => $content, "headers" => ['Authorization' => $auth, 'Content-CRC32' => $crc32]]);
        $directUploadResponse = json_decode((string)$response->getBody(), true);
        if (!isset($directUploadResponse["success"]) || $directUploadResponse["success"] != 0) {
            return -2;
        }
        return 0;
    }

    private function chunkUpload(string $oid, string $auth, string $filePath, bool $isLargeFile, Client $client)
    {
        $uploadID = $this->initUploadPart($oid, $auth, $isLargeFile, $client);
        if ($uploadID == "") {
            return -1;
        }
        $fileSize = filesize($filePath);
        $num = floor($fileSize / MinChunkSize);
        $lastNum = $num - 1;
        $fp = fopen($filePath, 'r');
        $checkSum = [];
        for ($i = 0; $i < $lastNum; $i++) {
            $data = stream_get_contents($fp, MinChunkSize, $i * MinChunkSize);
            $crc32 = $this->uploadPart($oid, $auth, $uploadID, $i, $data, $isLargeFile, $client);
            if ($crc32 == "") {
                return -1;
            }
            $checkSum[] = $crc32;
        }
        $maxLength = $fileSize - $lastNum * MinChunkSize;
        $data = stream_get_contents($fp, $maxLength, $lastNum * MinChunkSize);
        $crc32 = $this->uploadPart($oid, $auth, $uploadID, $lastNum, $data, $isLargeFile, $client);
        if ($crc32 == "") {
            return -1;
        }
        $checkSum[] = $crc32;
        return $this->uploadMergePart($oid, $auth, $uploadID, $checkSum, $isLargeFile, $client);
    }

    private function initUploadPart(string $oid, string $auth, bool $isLargeFile, Client $client)
    {
        $headers = ['Authorization' => $auth];
        if ($isLargeFile) {
            $headers[] = ['X-Storage-Mode' => 'gateway'];
        }
        $response = $client->put($oid . '?uploads', ['headers' => $headers]);
        $initUploadResponse = json_decode((string)$response->getBody(), true);
        if (!isset($initUploadResponse["success"]) || $initUploadResponse["success"] != 0) {
            return "";
        }
        return $initUploadResponse['payload']['uploadID'];
    }

    private function uploadPart(string $oid, string $auth, string $uploadID, int $partNumber, $data, bool $isLargeFile, Client $client)
    {
        $uri = sprintf("%s?partNumber=%d&uploadID=%s", $oid, $partNumber, $uploadID);
        $crc32 = sprintf("%08x", crc32($data));
        $headers = ['Authorization' => $auth, 'Content-CRC32' => $crc32];
        if ($isLargeFile) {
            $headers[] = ['X-Storage-Mode' => 'gateway'];
        }
        $response = $client->put($uri, ['headers' => $headers, 'body' => $data]);
        $uploadPartResponse = json_decode((string)$response->getBody(), true);
        if (!isset($uploadPartResponse["success"]) || $uploadPartResponse["success"] != 0) {
            return "";
        }
        return $crc32;
    }

    private function uploadMergePart(string $oid, string $auth, string $uploadID, array $checkSum, bool $isLargeFile, Client $client)
    {
        $uri = sprintf("%s?uploadID=%s", $oid, $uploadID);
        $m = [];
        for ($i = 0; $i < count($checkSum); $i++) {
            $m[] = sprintf("%d:%s", $i, $checkSum[$i]);
        }
        $body = implode(",", $m);
        $headers = ['Authorization' => $auth];
        if ($isLargeFile) {
            $headers[] = ['X-Storage-Mode' => 'gateway'];
        }
        $response = $client->put($uri, ['headers' => $headers, 'body' => $body]);
        $uploadPartResponse = json_decode((string)$response->getBody(), true);
        if (!isset($uploadPartResponse["success"]) || $uploadPartResponse["success"] != 0) {
            return -1;
        }
        return 0;
    }


}