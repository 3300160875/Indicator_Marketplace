<?php
declare(strict_types=1);

namespace StockResource\Core\Infrastructure\Migration;

final readonly class SchemaMigrationDefinition implements Migration
{
    private function __construct(
        private string $version,
        private string $tableName,
        private string $description,
        private string $sql,
    ) {
    }

    public static function createTable(): self
    {
        return new self(
            version: '202606250000',
            tableName: 'sr_schema_migrations',
            description: 'Create schema migration registry table.',
            sql: <<<'SQL'
CREATE TABLE {prefix}sr_schema_migrations (
  migration_id VARCHAR(64) NOT NULL,
  checksum CHAR(64) NOT NULL,
  batch_no INT UNSIGNED NOT NULL,
  execution_ms INT UNSIGNED NOT NULL DEFAULT 0,
  applied_at DATETIME NOT NULL,
  PRIMARY KEY (migration_id),
  KEY idx_batch (batch_no, applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
SQL,
        );
    }

    public function version(): string
    {
        return $this->version;
    }

    public function tableName(): string
    {
        return $this->tableName;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function checksum(): string
    {
        return hash('sha256', $this->sql);
    }

    public function sql(string $prefix): string
    {
        return str_replace('{prefix}', $prefix, $this->sql);
    }

    public function up(): array
    {
        return [$this->sql('{prefix}')];
    }
}
