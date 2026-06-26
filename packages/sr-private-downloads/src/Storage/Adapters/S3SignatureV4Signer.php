<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage\Adapters;

use StockResource\PrivateDownloads\Storage\StorageObjectKey;

final readonly class S3SignatureV4Signer
{
    public function __construct(
        private string $region,
        private string $accessKey,
        private string $secretKey,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public function authorizationHeaders(string $method, string $url, array $headers, string $payloadHash, int $now): array
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $host .= ':'.$parts['port'];
        }

        $amzDate = gmdate('Ymd\THis\Z', $now);
        $date = gmdate('Ymd', $now);
        $headers['host'] = $host;
        $headers['x-amz-content-sha256'] = $payloadHash;
        $headers['x-amz-date'] = $amzDate;
        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= strtolower($name).':'.trim($value)."\n";
        }
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headers)));
        $canonicalRequest = strtoupper($method)."\n"
            .($parts['path'] ?? '/')."\n"
            .($parts['query'] ?? '')."\n"
            .$canonicalHeaders."\n"
            .$signedHeaders."\n"
            .$payloadHash;
        $scope = $date.'/'.$this->region.'/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n".$amzDate."\n".$scope."\n".hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($date));
        $headers['Authorization'] = 'AWS4-HMAC-SHA256 Credential='.$this->accessKey.'/'.$scope.', SignedHeaders='.$signedHeaders.', Signature='.$signature;

        return $headers;
    }

    public function presignedUrl(string $endpoint, string $bucket, StorageObjectKey $key, int $ttlSeconds, int $now, bool $pathStyle = true): string
    {
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $date = gmdate('Ymd', $now);
        $scope = $date.'/'.$this->region.'/s3/aws4_request';
        $base = rtrim($endpoint, '/');
        $path = ($pathStyle ? '/'.rawurlencode($bucket) : '').'/'.$key->encodedPath();
        $host = (string) (parse_url($base, PHP_URL_HOST) ?? '');
        $port = parse_url($base, PHP_URL_PORT);
        if (is_int($port)) {
            $host .= ':'.$port;
        }
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKey.'/'.$scope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $ttlSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);
        $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $canonicalRequest = "GET\n".$path."\n".$canonicalQuery."\nhost:".$host."\n\nhost\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n".$amzDate."\n".$scope."\n".hash('sha256', $canonicalRequest);
        $query['X-Amz-Signature'] = hash_hmac('sha256', $stringToSign, $this->signingKey($date));

        return $base.$path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function signingKey(string $date): string
    {
        $kDate = hash_hmac('sha256', $date, 'AWS4'.$this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
