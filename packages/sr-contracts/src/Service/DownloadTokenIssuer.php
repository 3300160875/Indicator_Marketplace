<?php
declare(strict_types=1);

namespace StockResource\Contracts\Service;

use StockResource\Contracts\Dto\DownloadTokenResponse;
use StockResource\Contracts\Value\IdempotencyKey;
use StockResource\Contracts\Value\PositiveId;
use StockResource\Contracts\Value\RequestId;

interface DownloadTokenIssuer
{
    public function issue(
        RequestId $requestId,
        IdempotencyKey $idempotencyKey,
        PositiveId $userId,
        PositiveId $resourceId,
        PositiveId $versionId,
    ): DownloadTokenResponse;
}
