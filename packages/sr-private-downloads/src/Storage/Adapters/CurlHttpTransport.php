<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage\Adapters;

use StockResource\PrivateDownloads\Storage\StorageException;

final class CurlHttpTransport implements HttpTransport
{
    public function request(string $method, string $url, array $headers = [], string $body = ''): array
    {
        if (! extension_loaded('curl')) {
            throw StorageException::unavailable('PHP curl extension is required for storage HTTP transport.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw StorageException::unavailable('Unable to initialize storage HTTP transport.');
        }

        $responseHeaders = [];
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        if (strtoupper($method) === 'HEAD') {
            curl_setopt($handle, CURLOPT_NOBODY, true);
        } elseif ($body !== '') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw StorageException::unavailable('Storage HTTP request failed: '.$error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return [$status, $responseHeaders, (string) $responseBody];
    }

    /**
     * @param  array<string, string>  $headers
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }

        return $lines;
    }
}
