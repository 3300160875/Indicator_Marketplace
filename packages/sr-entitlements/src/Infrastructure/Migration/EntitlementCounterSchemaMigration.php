<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Infrastructure\Migration;

use StockResource\Core\Infrastructure\Migration\Migration;

final readonly class EntitlementCounterSchemaMigration implements Migration
{
    private function __construct(
        private string $version,
        private string $tableName,
        private string $description,
        private string $sql,
    ) {
    }

    public static function create(): self
    {
        return new self(
            version: '202606290201',
            tableName: 'sr_entitlement_counters',
            description: 'Create entitlement quota counter table.',
            sql: <<<'SQL'
CREATE TABLE {prefix}sr_entitlement_counters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entitlement_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  period_type VARCHAR(16) NOT NULL,
  period_key VARCHAR(32) NOT NULL,
  limit_snapshot INT UNSIGNED NOT NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  reserved_count INT UNSIGNED NOT NULL DEFAULT 0,
  lock_version INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_counter_period (entitlement_id, period_type, period_key),
  KEY idx_user_period (user_id, period_type, period_key)
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

