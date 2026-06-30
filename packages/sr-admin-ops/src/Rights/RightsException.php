<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Rights;

use InvalidArgumentException;

final class RightsException extends InvalidArgumentException
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
