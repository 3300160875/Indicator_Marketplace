<?php
declare(strict_types=1);

namespace StockResource\Contracts\Value;

use StockResource\Contracts\Exception\ValidationException;

final readonly class IdempotencyKey
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $length = strlen($value);
        if ($length < 8 || $length > 128) {
            throw new ValidationException('IdempotencyKey must be between 8 and 128 characters.');
        }
        if (! preg_match('/^[A-Za-z0-9._:-]+$/', $value)) {
            throw new ValidationException('IdempotencyKey contains unsupported characters.');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
