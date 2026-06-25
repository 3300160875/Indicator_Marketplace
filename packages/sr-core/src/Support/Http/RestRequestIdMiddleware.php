<?php
declare(strict_types=1);

namespace StockResource\Core\Support\Http;

final class RestRequestIdMiddleware
{
    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function withRequestIdHeader(array $headers, RequestContext $context): array
    {
        $headers['X-Request-ID'] = $context->requestId;

        return $headers;
    }
}
