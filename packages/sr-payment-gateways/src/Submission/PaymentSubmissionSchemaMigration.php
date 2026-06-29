<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Submission;

use StockResource\Core\Infrastructure\Migration\Migration;

final readonly class PaymentSubmissionSchemaMigration implements Migration
{
    private function __construct(
        private string $version,
        private string $tableName,
        private string $description,
        private string $sql,
    ) {}

    public static function create(): self
    {
        return new self(
            version: '202606290001',
            tableName: 'sr_payment_submissions',
            description: 'Create payment submissions table.',
            sql: <<<'SQL'
CREATE TABLE {prefix}sr_payment_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_key CHAR(36) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  state VARCHAR(24) NOT NULL DEFAULT 'submitted',
  channel VARCHAR(32) NOT NULL,
  account_key VARCHAR(64) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'CNY',
  expected_amount DECIMAL(12,2) NOT NULL,
  reported_amount DECIMAL(12,2) NOT NULL,
  reported_paid_at DATETIME NOT NULL,
  payer_note VARCHAR(255) NULL,
  proof_storage_key VARCHAR(512) NOT NULL,
  proof_sha256 CHAR(64) NOT NULL,
  proof_mime_type VARCHAR(128) NOT NULL,
  proof_file_size BIGINT UNSIGNED NOT NULL,
  proof_deleted_at DATETIME NULL,
  external_reference VARCHAR(128) NULL,
  transaction_fingerprint CHAR(64) NULL,
  verified_amount DECIMAL(12,2) NULL,
  verified_paid_at DATETIME NULL,
  approval_idempotency_key_hash CHAR(64) NULL,
  reviewer_id BIGINT UNSIGNED NULL,
  claimed_at DATETIME NULL,
  decision_code VARCHAR(64) NULL,
  internal_note TEXT NULL,
  user_message TEXT NULL,
  lock_version INT UNSIGNED NOT NULL DEFAULT 0,
  submitted_at DATETIME NOT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_submission_key (submission_key),
  UNIQUE KEY uq_transaction_fingerprint (transaction_fingerprint),
  KEY idx_order (order_id),
  KEY idx_order_state (order_id, state),
  KEY idx_user (user_id),
  KEY idx_state_created (state, created_at)
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
