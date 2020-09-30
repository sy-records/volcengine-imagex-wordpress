<?php

namespace Vcloud\Service;

use Vcloud\Base\V4Curl;

class Edit extends V4Curl
{
    protected function getConfig(string $region = '')
    {
        return [
            'host' => 'https://open.bytedanceapi.com',
            'config' => [
                'timeout' => 5.0,
                'headers' => [
                    'Accept' => 'application/json'
                ],
                'v4_credentials' => [
                    'region' => 'cn-north-1',
                    'service' => 'edit',
                ],
            ],
        ];
    }

    public function submitSubtitleRecognizationTaskAsync(array $query = [])
    {
        $response = $this->request('SubmitSubtitleRecognizationTaskAsync', $query);
        return $response->getBody();
    }

    public function submitTemplateTaskAsync(array $query = [])
    {
        $response = $this->request('SubmitTemplateTaskAsync', $query);
        return $response->getBody();
    }

    public function submitDirectEditTaskAsync(array $query = [])
    {
        $response = $this->request('SubmitDirectEditTaskAsync', $query);
        return $response->getBody();
    }

    public function submitDirectEditTaskSync(array $query = [])
    {
        $response = $this->request('SubmitDirectEditTaskSync', $query);
        return $response->getBody();
    }

    public function getDirectEditResult(array $query = [])
    {
        $response = $this->request('GetDirectEditResult', $query);
        return $response->getBody();
    }

    protected $apiList = [
        'SubmitSubtitleRecognizationTaskAsync' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'SubmitSubtitleRecognizationTaskAsync',
                    'Version' => '2018-01-01',
                ],
            ],
        ],
        'SubmitTemplateTaskAsync' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'SubmitTemplateTaskAsync',
                    'Version' => '2018-01-01',
                ],
            ],
        ],
        'SubmitDirectEditTaskAsync' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'SubmitDirectEditTaskAsync',
                    'Version' => '2018-01-01',
                ],
            ],
        ],
        'SubmitDirectEditTaskSync' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'SubmitDirectEditTaskSync',
                    'Version' => '2018-01-01',
                ],
            ],
        ],
        'GetDirectEditResult' => [
            'url' => '/',
            'method' => 'post',
            'config' => [
                'query' => [
                    'Action' => 'GetDirectEditResult',
                    'Version' => '2018-01-01',
                ],
            ],
        ],
    ];
}
