<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Favorite;

use RuntimeException;

final class FavoriteException extends RuntimeException
{
    public function __construct(private readonly string $stableCode, string $message)
    {
        parent::__construct($message);
    }

    public function code(): string
    {
        return $this->stableCode;
    }
}
