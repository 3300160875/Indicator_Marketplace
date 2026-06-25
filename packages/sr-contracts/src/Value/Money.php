<?php
declare(strict_types=1);

namespace StockResource\Contracts\Value;

use StockResource\Contracts\Exception\ValidationException;

final readonly class Money
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (! preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/', $value)) {
            throw new ValidationException('Money must be a non-negative decimal string with up to two decimals.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
