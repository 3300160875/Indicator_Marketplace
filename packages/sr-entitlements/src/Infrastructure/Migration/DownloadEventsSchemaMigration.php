<?php
declare(strict_types=1);

namespace StockResource\Entitlements\Infrastructure\Migration;

use StockResource\Core\Infrastructure\Migration\Migration;

final readonly class DownloadEventsSchemaMigration implements Migration
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
            version: '202606290202',
            tableName: 'sr_download_events',
            description: 'Create download events table.',
            sql: <<<'SQL'
CREATE TABLE {prefix}sr_download_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id CHAR(36) NOT NULL,
  token_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  entitlement_id BIGINT UNSIGNED NULL,
  resource_id BIGINT UNSIGNED NOT NULL,
  version_id BIGINT UNSIGNED NOT NULL,
  access_source VARCHAR(24) NOT NULL,
  counted TINYINT(1) NOT NULL DEFAULT 0,
  result VARCHAR(24) NOT NULL,
  error_code VARCHAR(64) NULL,
  bytes_expected BIGINT UNSIGNED NULL,
  ip_hash CHAR(64) NULL,
  ua_hash CHAR(64) NULL,
  started_at DATETIME NOT NULL,
  redirected_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_request (request_id),
  UNIQUE KEY uq_event_token (token_id),
  KEY idx_user_date (user_id, created_at),
  KEY idx_resource_result (resource_id, result, created_at)
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

