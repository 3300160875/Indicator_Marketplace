<?php
declare(strict_types=1);

namespace StockResource\Core\Infrastructure\Migration;

final readonly class TransactionSupportDetector
{
    public function __construct(private string $databaseEngine)
    {
    }

    public function supportsTransactionalDdl(): bool
    {
        return in_array(strtolower($this->databaseEngine), ['postgresql', 'sqlite'], true);
    }

    public function supportsTransactionalDml(): bool
    {
        return in_array(strtolower($this->databaseEngine), ['innodb', 'mariadb', 'mysql', 'postgresql', 'sqlite'], true);
    }
}
