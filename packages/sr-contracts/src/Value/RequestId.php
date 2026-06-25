<?php
declare(strict_types=1);

namespace StockResource\Contracts\Value;

use StockResource\Contracts\Exception\ValidationException;

final readonly class RequestId
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower($value);
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $normalized)) {
            throw new ValidationException('RequestId must be a valid UUID.');
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
