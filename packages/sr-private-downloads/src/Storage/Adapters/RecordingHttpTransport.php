<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Storage\Adapters;

final class RecordingHttpTransport implements HttpTransport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string}> */
    public array $requests = [];

    /**
     * @param  array<string, array{0: int, 1: array<string, string>, 2: string}>  $responses
     */
    public function __construct(private array $responses) {}

    public function request(string $method, string $url, array $headers = [], string $body = ''): array
    {
        $this->requests[] = [
            'method' => strtoupper($method),
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        return $this->responses[strtoupper($method)] ?? [500, [], 'missing recording'];
    }
}
