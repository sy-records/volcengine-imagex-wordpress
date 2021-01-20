<?php
namespace Volc\Base;

use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;

class SignatureV4
{
    use SignatureTrait;
    const ISO8601_BASIC = 'Ymd\THis\Z';

    public function signRequestToUrl(RequestInterface $request, $credentials)
    {
        $ldt = gmdate(self::ISO8601_BASIC);
        $sdt = substr($ldt, 0, 8);
        $ak = $credentials['ak'];
        $cs = $this->createScope($sdt, $credentials['region'], $credentials['service']);

        $parsed = $this->parseRequest($request);
        $parsed['query']['X-Date'] = $ldt;
        $parsed['query']['X-NotSignBody'] = true;
        $parsed['query']['X-Algorithm'] = "HMAC-SHA256";
        $parsed['query']['X-Credential'] = "{$ak}/${cs}";
        $parsed['query']['X-SignedHeaders'] = '';

        $signedQueries = array_keys($parsed['query']);
        sort($signedQueries);
        $parsed['query']['X-SignedQueries'] = implode(';', $signedQueries);

        $cs = $this->createScope($sdt, $credentials['region'], $credentials['service']);
        $payload = $this->getPayload($request);
        $context = $this->createContext($parsed, $payload);
        $toSign = $this->createStringToSign($ldt, $cs, $context['creq']);
        $signingKey = $this->getSigningKey(
            $sdt,
            $credentials['region'],
            $credentials['service'],
            $credentials['sk']
        );
        $signature = hash_hmac('sha256', $toSign, $signingKey);

        $parsed['query']['X-Signature'] = $signature;

        return $this->buildRequestString($parsed);
    }

    public function signRequest(
        RequestInterface $request,
        $credentials
    ) {
        $ldt = gmdate(self::ISO8601_BASIC);
        $sdt = substr($ldt, 0, 8);
        $parsed = $this->parseRequest($request);
        $parsed['headers']['X-Date'] = [$ldt];

        $cs = $this->createScope($sdt, $credentials['region'], $credentials['service']);
        $payload = $this->getPayload($request);
        $context = $this->createContext($parsed, $payload);
        $toSign = $this->createStringToSign($ldt, $cs, $context['creq']);
        $signingKey = $this->getSigningKey(
            $sdt,
            $credentials['region'],
            $credentials['service'],
            $credentials['sk']
        );
        $signature = hash_hmac('sha256', $toSign, $signingKey);

        $ak = $credentials['ak'];
        $parsed['headers']['Authorization'] = [
            "HMAC-SHA256 "
            . "Credential={$ak}/{$cs}, "
            . "SignedHeaders={$context['headers']}, Signature={$signature}"
        ];

        return $this->buildRequest($parsed);
    }

    protected function getPayload(RequestInterface $request)
    {
        // Calculate the request signature payload
        if ($request->hasHeader('X-Content-Sha256')) {
            // Handle streaming operations (e.g. Glacier.UploadArchive)
            return $request->getHeaderLine('X-Content-Sha256');
        }

        if (!$request->getBody()->isSeekable()) {
            throw new CouldNotCreateChecksumException('sha256');
        }

        try {
            return Psr7\hash($request->getBody(), 'sha256');
        } catch (\Exception $e) {
            throw new CouldNotCreateChecksumException('sha256', $e);
        }
    }

    protected function createCanonicalizedPath($path)
    {
        $doubleEncoded = rawurlencode(ltrim($path, '/'));

        return '/' . str_replace('%2F', '/', $doubleEncoded);
    }

    private function createStringToSign($longDate, $credentialScope, $creq)
    {
        $hash = hash('sha256', $creq);

        return "HMAC-SHA256\n{$longDate}\n{$credentialScope}\n{$hash}";
    }

    /**
     * @param array  $parsedRequest
     * @param string $payload Hash of the request payload
     * @return array Returns an array of context information
     */
    private function createContext(array $parsedRequest, $payload)
    {
        // The following headers are not signed because signing these headers
        // would potentially cause a signature mismatch when sending a request
        // through a proxy or if modified at the HTTP client level.
        static $blacklist = [
            'cache-control'       => true,
            'content-type'        => true,
            'content-length'      => true,
            'expect'              => true,
            'max-forwards'        => true,
            'pragma'              => true,
            'range'               => true,
            'te'                  => true,
            'if-match'            => true,
            'if-none-match'       => true,
            'if-modified-since'   => true,
            'if-unmodified-since' => true,
            'if-range'            => true,
            'accept'              => true,
            'authorization'       => true,
            'proxy-authorization' => true,
            'from'                => true,
            'referer'             => true,
            'user-agent'          => true
        ];

        // Normalize the path as required by SigV4
        $canon = $parsedRequest['method'] . "\n"
            . $this->createCanonicalizedPath($parsedRequest['path']) . "\n"
            . $this->getCanonicalizedQuery($parsedRequest['query']) . "\n";

        $signedHeadersString = '';
        $canonHeaders = [];
        // Case-insensitively aggregate all of the headers.
        if (!isset($parsedRequest['query']['X-SignedQueries'])) {
            $aggregate = [];
            foreach ($parsedRequest['headers'] as $key => $values) {
                $key = strtolower($key);
                if (!isset($blacklist[$key])) {
                    foreach ($values as $v) {
                        $aggregate[$key][] = $v;
                    }
                }
            }

            ksort($aggregate);
            foreach ($aggregate as $k => $v) {
                if (count($v) > 0) {
                    sort($v);
                }
                $canonHeaders[] = $k . ':' . preg_replace('/\s+/', ' ', implode(',', $v));
            }

            $signedHeadersString = implode(';', array_keys($aggregate));
        }
        $canon .= implode("\n", $canonHeaders) . "\n\n"
            . $signedHeadersString . "\n"
            . $payload;

        return ['creq' => $canon, 'headers' => $signedHeadersString];
    }

    private function getCanonicalizedQuery(array $query)
    {
        unset($query['X-Signature']);

        if (!$query) {
            return '';
        }

        $qs = '';
        if (isset($query['X-SignedQueries'])) {
            foreach (explode(';', $query['X-SignedQueries']) as $k) {
                $v = $query[$k];
                if (!is_array($v)) {
                    $qs .= rawurlencode($k) . '=' . rawurlencode($v) . '&';
                } else {
                    sort($v);
                    foreach ($v as $value) {
                        $qs .= rawurlencode($k) . '=' . rawurlencode($value) . '&';
                    }
                }
            }
        }else {
            ksort($query);
            foreach ($query as $k => $v) {
                if (!is_array($v)) {
                    $qs .= rawurlencode($k) . '=' . rawurlencode($v) . '&';
                } else {
                    sort($v);
                    foreach ($v as $value) {
                        $qs .= rawurlencode($k) . '=' . rawurlencode($value) . '&';
                    }
                }
            }
        }

        return substr($qs, 0, -1);
    }

    private function parseRequest(RequestInterface $request)
    {
        // Clean up any previously set headers.
        /** @var RequestInterface $request */
        $request = $request
            ->withoutHeader('X-Date')
            ->withoutHeader('Date')
            ->withoutHeader('Authorization');
        $uri = $request->getUri();

        return [
            'method'  => $request->getMethod(),
            'path'    => $uri->getPath(),
            'query'   => Psr7\parse_query($uri->getQuery()),
            'uri'     => $uri,
            'headers' => $request->getHeaders(),
            'body'    => $request->getBody(),
            'version' => $request->getProtocolVersion()
        ];
    }

    private function buildRequest(array $req)
    {
        if ($req['query']) {
            $req['uri'] = $req['uri']->withQuery(Psr7\build_query($req['query']));
        }

        return new Psr7\Request(
            $req['method'],
            $req['uri'],
            $req['headers'],
            $req['body'],
            $req['version']
        );
    }

    private function buildRequestString(array $req)
    {
        if ($req['query']) {
            $req['uri'] = $req['uri']->withQuery(Psr7\build_query($req['query']));
        }
        return (string)$req['uri'];
    }
}
