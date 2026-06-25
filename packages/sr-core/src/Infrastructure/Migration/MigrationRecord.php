<?php
declare(strict_types=1);

namespace StockResource\Core\Infrastructure\Migration;

final readonly class MigrationRecord
{
    public function __construct(
        public string $version,
        public string $checksum,
        public int $batchNo,
        public int $executionMs,
        public string $appliedAt,
    ) {
    }
}
