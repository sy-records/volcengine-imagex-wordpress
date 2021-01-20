<?php

namespace Volc\Service\Vod;

use Exception;
use Throwable;

class VodUtils {

    public static function formatRequestParam($req): array
    {
        try {
            $jsonData = $req->serializeToJsonString();
            $query = json_decode($jsonData, true);
            foreach ($query as $key => $value) {
                switch (gettype($query[$key])) {
                    case 'integer':
                    case 'string':
                    case 'double':
                    case 'NULL':
                    case 'boolean':
                        break;
                    default:
                        $d = json_encode($value);
                        $query[$key] = $d;
                }
            }
        } catch (Exception $e) {
            throw $e;
        } catch (Throwable $t) {
            throw $t;
        }
        return $query;
    }

    public static function parseResponseData($response, $respData)
    {
        try {
            $respData->mergeFromJsonString($response->getBody(), true);
        } catch (Exception $e) {
            if ($respData == null || $respData->getResponseMetadata() == null) {
                echo $e, "\n";
                throw new Exception($response->getReasonPhrase());
            }
        } catch (Throwable $t) {
            if ($respData == null || $respData->getResponseMetadata() == null) {
                echo $t, "\n";
                throw new Exception($response->getReasonPhrase());
            }
        }
        return $respData;
    }

}
