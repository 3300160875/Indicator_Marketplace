<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage\Adapters;

interface HttpTransport
{
    /**
     * @param  array<string, string>  $headers
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    public function request(string $method, string $url, array $headers = [], string $body = ''): array;
}
