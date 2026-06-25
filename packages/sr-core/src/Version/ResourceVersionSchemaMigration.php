<?php
declare(strict_types=1);

namespace StockResource\Core\Version;

use StockResource\Core\Infrastructure\Migration\Migration;

final readonly class ResourceVersionSchemaMigration implements Migration
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
            version: '202606250016',
            tableName: 'sr_resource_versions',
            description: 'Create resource version table.',
            sql: <<<'SQL'
CREATE TABLE {prefix}sr_resource_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  resource_id BIGINT UNSIGNED NOT NULL,
  version_label VARCHAR(64) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  storage_provider VARCHAR(24) NULL,
  storage_bucket VARCHAR(128) NULL,
  storage_key VARCHAR(512) NULL,
  original_filename VARCHAR(255) NULL,
  mime_type VARCHAR(128) NULL,
  file_size BIGINT UNSIGNED NULL,
  sha256 CHAR(64) NULL,
  compatibility_json LONGTEXT NULL,
  scan_status VARCHAR(24) NOT NULL DEFAULT 'pending',
  scan_result_json LONGTEXT NULL,
  release_notes LONGTEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  activated_at DATETIME NULL,
  suspended_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_resource_status (resource_id, status),
  KEY idx_resource_current (resource_id, is_current),
  KEY idx_sha256 (sha256)
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
