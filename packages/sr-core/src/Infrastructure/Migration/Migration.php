<?php
declare(strict_types=1);

namespace StockResource\Core\Infrastructure\Migration;

interface Migration
{
    public function version(): string;

    public function description(): string;

    public function checksum(): string;

    /**
     * @return list<string>
     */
    public function up(): array;
}
