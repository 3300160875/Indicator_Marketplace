<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Infrastructure\Migration;

use StockResource\Core\Infrastructure\Migration\Migration;

final readonly class EntitlementSchemaMigration implements Migration
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
            version: '202606290200',
            tableName: 'sr_entitlements',
            description: 'Create entitlements table.',
            sql: <<<'SQL'
CREATE TABLE {prefix}sr_entitlements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  edd_customer_id BIGINT UNSIGNED NULL,
  grant_type VARCHAR(24) NOT NULL,
  source_type VARCHAR(24) NOT NULL,
  source_order_id BIGINT UNSIGNED NULL,
  source_order_item_id BIGINT UNSIGNED NULL,
  plan_download_id BIGINT UNSIGNED NULL,
  parent_entitlement_id BIGINT UNSIGNED NULL,
  resource_id BIGINT UNSIGNED NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'active',
  starts_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  scope_type VARCHAR(24) NOT NULL,
  scope_snapshot_json LONGTEXT NOT NULL,
  quota_snapshot_json LONGTEXT NULL,
  rules_version VARCHAR(32) NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  created_by BIGINT UNSIGNED NULL,
  revoked_at DATETIME NULL,
  revoked_by BIGINT UNSIGNED NULL,
  revoke_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_source_order_item (source_order_item_id),
  KEY idx_user_active (user_id, status, expires_at),
  KEY idx_order (source_order_id),
  KEY idx_parent (parent_entitlement_id),
  KEY idx_resource (resource_id)
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

