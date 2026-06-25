<?php
declare(strict_types=1);

namespace StockResource\Contracts\Value;

use DateTimeImmutable;
use DateTimeZone;
use StockResource\Contracts\Exception\ValidationException;
use Throwable;

final readonly class UtcDateTime
{
    private function __construct(private DateTimeImmutable $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
            throw new ValidationException('UtcDateTime must use ISO-8601 UTC format.');
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            throw new ValidationException('UtcDateTime is invalid.', previous: $exception);
        }

        $utc = $date->setTimezone(new DateTimeZone('UTC'));
        if ($utc->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new ValidationException('UtcDateTime contains an impossible calendar date or non-UTC value.');
        }

        return new self($utc);
    }

    public function toString(): string
    {
        return $this->value->format('Y-m-d\TH:i:s\Z');
    }
}
