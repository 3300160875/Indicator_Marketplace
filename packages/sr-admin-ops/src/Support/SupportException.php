<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Support;

use InvalidArgumentException;

final class SupportException extends InvalidArgumentException
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
