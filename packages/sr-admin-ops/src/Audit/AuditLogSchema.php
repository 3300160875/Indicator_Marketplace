<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Audit;

final readonly class AuditLogSchema
{
    public static function tableName(string $prefix): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', $prefix) ?? '';

        return $prefix.'sr_audit_logs';
    }

    public static function createSql(string $prefix = 'wp_'): string
    {
        $table = self::tableName($prefix);

        return <<<SQL
CREATE TABLE {$table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_type VARCHAR(16) NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  actor_role VARCHAR(64) NULL,
  action VARCHAR(96) NOT NULL,
  object_type VARCHAR(64) NOT NULL,
  object_id VARCHAR(64) NOT NULL,
  before_json LONGTEXT NULL,
  after_json LONGTEXT NULL,
  reason_code VARCHAR(64) NULL,
  note TEXT NULL,
  request_id CHAR(36) NOT NULL,
  idempotency_key VARCHAR(128) NULL,
  ip_hash CHAR(64) NULL,
  ua_hash CHAR(64) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_actor (actor_type, actor_id, created_at),
  KEY idx_object (object_type, object_id, created_at),
  KEY idx_action_time (action, created_at),
  KEY idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
SQL;
    }

    /**
     * @return list<string>
     */
    public static function requiredIndexes(): array
    {
        return ['idx_actor', 'idx_object', 'idx_action_time', 'idx_request'];
    }
}
