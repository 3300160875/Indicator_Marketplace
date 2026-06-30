<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

use RuntimeException;

final class AuditException extends RuntimeException
{
    public function __construct(private readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }

    public function code(): string
    {
        return $this->reasonCode;
    }
}
