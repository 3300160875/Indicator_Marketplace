<?php

declare(strict_types=1);

namespace StockResource\PrivateDownloads\Scan;

final readonly class ScanResult
{
    /**
     * @param  array<string, mixed>  $details
     */
    private function __construct(
        public string $verdict,
        public array $details = [],
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public static function clean(array $details = []): self
    {
        return new self('clean', $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function infected(array $details = []): self
    {
        return new self('infected', $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function failed(array $details = []): self
    {
        return new self('failed', $details);
    }
}
