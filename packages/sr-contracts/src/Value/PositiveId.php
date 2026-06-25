<?php
declare(strict_types=1);

namespace StockResource\Contracts\Value;

use StockResource\Contracts\Exception\ValidationException;

final readonly class PositiveId
{
    private function __construct(private int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw new ValidationException('PositiveId must be greater than zero.');
        }

        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
