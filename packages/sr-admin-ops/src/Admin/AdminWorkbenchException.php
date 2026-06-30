<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Admin;

use RuntimeException;

final class AdminWorkbenchException extends RuntimeException
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
