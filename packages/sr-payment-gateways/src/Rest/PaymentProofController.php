<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Rest;

use DateTimeImmutable;
use RuntimeException;
use StockResource\PaymentGateways\Submission\PaymentSubmission;
use StockResource\PaymentGateways\Submission\PaymentSubmissionException;
use StockResource\PaymentGateways\Submission\PaymentSubmissionRepository;

final readonly class PaymentProofController
{
    private const int MAX_PROOF_BYTES = 8 * 1024 * 1024;

    /**
     * @var list<string>
     */
    private const ALLOWED_CHANNELS = ['wechat', 'alipay', 'other'];

    /**
     * @var array<string, string>
     */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    private mixed $nowProvider;

    private mixed $idempotencyKeyProvider;

    private mixed $proofWriter;

    /**
     * @param callable(int): array<string, mixed> $orderResolver
     * @param callable(string, string, string, array<string, mixed>): bool $proofWriter
     */
    public function __construct(
        private PaymentSubmissionRepository $repository,
        private mixed $orderResolver,
        mixed $nowProvider = null,
        mixed $idempotencyKeyProvider = null,
        private array $allowedStatuses = ['pending'],
        mixed $proofWriter = null,
    ) {
        if (! is_callable($this->orderResolver)) {
            throw new RuntimeException('orderResolver must be callable.');
        }

        $this->nowProvider = $nowProvider ?? static fn (): string => (new DateTimeImmutable('now'))->format(DATE_ATOM);
        $this->idempotencyKeyProvider = $idempotencyKeyProvider ?? static fn (int $orderId, int $userId, string $idempotencyKey): string => self::buildSubmissionKey(
            $orderId,
            $userId,
            $idempotencyKey,
        );
        $this->proofWriter = $proofWriter ?? static function (string $storageKey, string $content, string $mimeType, array $metadata): bool {
            throw new PaymentProofException(
                'proof_storage_unavailable',
                'A private proof writer is required before accepting payment proof submissions.',
            );
        };

        if (! is_callable($this->proofWriter)) {
            throw new RuntimeException('proofWriter must be callable.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitPaymentProof(int $orderId, int $requestUserId, string $idempotencyKey, array $payload): array
    {
        $order = $this->resolveOrder($orderId);
        $this->assertOrderOwnership($order, $requestUserId);
        $this->assertOrderState($order);

        $channel = self::sanitizeString($payload['channel'] ?? '');
        $reportedAmount = self::sanitizeString($payload['reported_amount'] ?? '');
        $reportedPaidAt = self::sanitizeString($payload['reported_paid_at'] ?? '');
        $payerNote = self::sanitizeNullableString($payload['payer_note'] ?? null);
        $proofData = $payload['proof'] ?? null;
        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 255) {
            throw new PaymentProofException('invalid_idempotency_key', 'Idempotency-Key is required and must be <= 255 chars.');
        }

        if (! is_string($channel) || ! in_array($channel, self::ALLOWED_CHANNELS, true)) {
            throw new PaymentProofException(
                'invalid_channel',
                'channel must be one of '.implode(', ', self::ALLOWED_CHANNELS).'.',
            );
        }

        if (! is_string($reportedAmount) || ! self::isMoney($reportedAmount)) {
            throw new PaymentProofException('invalid_reported_amount', 'reported_amount must be a decimal with up to two places.');
        }

        $expectedAmount = self::readOrderAmount($order);
        if (! self::isMoney($expectedAmount)) {
            throw new PaymentProofException('invalid_order_amount', 'order total must be a decimal with up to two places.');
        }

        if (! is_string($reportedPaidAt) || ! self::isValidDateTime($reportedPaidAt)) {
            throw new PaymentProofException('invalid_reported_paid_at', 'reported_paid_at must be a valid datetime.');
        }

        if ($payerNote !== null && mb_strlen($payerNote) > 255) {
            throw new PaymentProofException('invalid_payer_note', 'payer_note must be <= 255 characters.');
        }

        if (! is_array($proofData)) {
            throw new PaymentProofException('missing_proof', 'proof payload is required.');
        }

        $proofRaw = self::normalizeProofPayload($proofData);
        if ($proofRaw === '') {
            throw new PaymentProofException('invalid_proof', 'proof payload content is empty.');
        }

        $proofSize = strlen($proofRaw);
        if ($proofSize <= 0 || $proofSize > self::MAX_PROOF_BYTES) {
            throw new PaymentProofException('invalid_proof_size', 'proof must be 1..8MiB.');
        }

        $proofMime = self::assertProofMime($proofData, $proofRaw);
        $proofContent = $proofMime === 'image/jpeg' ? self::stripExif($proofRaw, $proofMime) : $proofRaw;
        $proofHash = hash('sha256', $proofContent);
        $submissionKey = ($this->idempotencyKeyProvider)($orderId, $requestUserId, $idempotencyKey);
        $proofStorageKey = self::buildProofStorageKey(
            orderId: $orderId,
            userId: $requestUserId,
            proofHash: $proofHash,
            proofMime: $proofMime,
        );

        $submission = PaymentSubmission::create(
            submissionKey: $submissionKey,
            orderId: $orderId,
            userId: $requestUserId,
            channel: $channel,
            expectedAmount: $expectedAmount,
            reportedAmount: $reportedAmount,
            reportedPaidAt: $reportedPaidAt,
            proofStorageKey: $proofStorageKey,
            proofSha256: $proofHash,
            proofMimeType: $proofMime,
            proofFileSize: $proofSize,
            nowUtc: $this->now(),
            accountKey: self::sanitizeNullableString($order['meta']['account_key'] ?? null),
            payerNote: $payerNote,
            externalReference: null,
            currency: self::sanitizeString($order['currency'] ?? 'CNY'),
        );

        try {
            if ($this->repository->findBySubmissionKey($submissionKey) === null) {
                $this->persistProof($proofStorageKey, $proofContent, $proofMime, [
                    'proof_sha256' => $proofHash,
                    'proof_file_size' => strlen($proofContent),
                    'order_id' => $orderId,
                    'user_id' => $requestUserId,
                ]);
            }

            $submission = $this->repository->create($submission);
        } catch (PaymentSubmissionException $exception) {
            throw match ($exception->codeName) {
                'duplicate_submission_key' => new PaymentProofException('state_conflict', $exception->getMessage()),
                'duplicate_transaction_fingerprint' => new PaymentProofException('duplicate_transaction_fingerprint', $exception->getMessage()),
                default => new PaymentProofException($exception->codeName, $exception->getMessage()),
            };
        }

        return [
            'data' => $this->publicSubmission($submission),
            'request_id' => self::newRequestId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentStatus(int $orderId, int $requestUserId): array
    {
        $order = $this->resolveOrder($orderId);
        $this->assertOrderOwnership($order, $requestUserId);

        $submissions = $this->repository->findByOrder($orderId);
        if ($submissions === []) {
            throw new PaymentProofException('submission_not_found', 'No payment proof submission found for order.');
        }

        $submission = $submissions[array_key_last($submissions)];

        return [
            'data' => $this->publicSubmission($submission),
            'request_id' => self::newRequestId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function proofTimeline(int $orderId, int $requestUserId): array
    {
        $order = $this->resolveOrder($orderId);
        $this->assertOrderOwnership($order, $requestUserId);

        return [
            'data' => array_map(
                static fn (PaymentSubmission $submission): array => [
                    'id' => $submission->id,
                    'order_id' => $submission->orderId,
                    'state' => $submission->state->value,
                    'channel' => $submission->channel,
                    'expected_amount' => $submission->expectedAmount,
                    'reported_amount' => $submission->reportedAmount,
                    'reported_paid_at' => $submission->reportedPaidAt,
                    'decision_code' => $submission->decisionCode,
                    'user_message' => $submission->userMessage,
                    'submitted_at' => $submission->submittedAt,
                    'reviewed_at' => $submission->reviewedAt,
                    'lock_version' => $submission->lockVersion,
                ],
                $this->repository->findByOrder($orderId),
            ),
            'request_id' => self::newRequestId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOrder(int $orderId): array
    {
        $order = ($this->orderResolver)($orderId);
        if (! is_array($order)) {
            throw new PaymentProofException('order_not_found', 'Order not found.');
        }

        if (! isset($order['id']) || ! is_numeric($order['id']) || (int) $order['id'] <= 0) {
            throw new PaymentProofException('order_not_found', 'Order payload is incomplete.');
        }

        return array_map(
            static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
            $order,
        );
    }

    private function assertOrderOwnership(array $order, int $requestUserId): void
    {
        if ((int) ($order['user_id'] ?? 0) !== $requestUserId) {
            throw new PaymentProofException('order_forbidden', 'order does not belong to current user.');
        }
    }

    private function assertOrderState(array $order): void
    {
        $status = self::sanitizeString($order['status'] ?? '');
        if ($status === '') {
            throw new PaymentProofException('order_not_found', 'Order status is missing.');
        }

        if (! in_array($status, $this->allowedStatuses, true)) {
            throw new PaymentProofException('order_not_reviewable', 'order status is not allowed for payment proof submission.');
        }
    }

    private static function readOrderAmount(array $order): string
    {
        $rawAmount = $order['total'] ?? $order['amount'] ?? null;
        if (! is_string($rawAmount) && ! is_numeric($rawAmount)) {
            throw new PaymentProofException('invalid_order_amount', 'order amount is missing.');
        }

        $amount = (string) $rawAmount;

        if (! preg_match('/^(0|[1-9][0-9]*)(\\.[0-9]{1,2})?$/', $amount)) {
            throw new PaymentProofException('invalid_order_amount', 'order amount must be a decimal with up to two places.');
        }

        return number_format((float) $amount, 2, '.', '');
    }

    private function now(): string
    {
        return (string) ($this->nowProvider)();
    }

    /**
     * @param array<string, mixed> $proof
     */
    private static function normalizeProofPayload(array $proof): string
    {
        if (isset($proof['content']) && is_string($proof['content'])) {
            return $proof['content'];
        }

        $base64 = $proof['base64'] ?? null;
        if (is_string($base64)) {
            $parts = explode(',', $base64, 2);
            $encoded = count($parts) === 2 && str_starts_with($parts[0], 'data:') ? $parts[1] : $base64;
            $decoded = base64_decode($encoded, true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        $tmpName = $proof['tmp_name'] ?? null;
        if (is_string($tmpName) && is_readable($tmpName)) {
            return (string) file_get_contents($tmpName);
        }

        throw new PaymentProofException('missing_proof', 'proof payload is invalid.');
    }

    /**
     * @param array<string, mixed> $proof
     */
    private static function normalizeProofMime(array $proof): ?string
    {
        $rawMime = strtolower(trim((string) ($proof['mime_type'] ?? $proof['type'] ?? $proof['content_type'] ?? '')));
        if ($rawMime !== '') {
            return $rawMime;
        }

        if (is_string($proof['base64'] ?? null) && str_starts_with((string) $proof['base64'], 'data:')) {
            $prefix = substr((string) $proof['base64'], 0, 64);
            $mimeEnd = strpos($prefix, ';');
            if ($mimeEnd !== false) {
                return strtolower(substr($prefix, 5, $mimeEnd - 5));
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $proof
     */
    private static function assertProofMime(array $proof, string $content): string
    {
        $declaredMime = self::normalizeMime(self::normalizeProofMime($proof));
        if ($declaredMime !== null && ! isset(self::ALLOWED_MIME[$declaredMime])) {
            throw new PaymentProofException('invalid_proof_type', 'proof mime type must be image/jpeg, image/jpg, image/png or application/pdf.');
        }

        $detectedMime = self::detectProofMime($content);
        if ($detectedMime === null || ! isset(self::ALLOWED_MIME[$detectedMime])) {
            throw new PaymentProofException('invalid_proof_type', 'proof content must be a valid JPEG, PNG or PDF.');
        }

        if ($declaredMime !== null && $declaredMime !== $detectedMime) {
            throw new PaymentProofException('invalid_proof_type', 'proof declared mime does not match file content.');
        }

        return $detectedMime;
    }

    private static function normalizeMime(?string $mime): ?string
    {
        if ($mime === null) {
            return null;
        }

        $mime = strtolower(trim($mime));
        return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
    }

    private static function detectProofMime(string $content): ?string
    {
        if (str_starts_with($content, "\xFF\xD8")) {
            return 'image/jpeg';
        }

        if (str_starts_with($content, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        if (str_starts_with($content, '%PDF-')) {
            return 'application/pdf';
        }

        return null;
    }

    private static function buildProofStorageKey(int $orderId, int $userId, string $proofHash, string $proofMime): string
    {
        $digest = substr($proofHash, 0, 16);
        $ext = self::ALLOWED_MIME[$proofMime];

        return sprintf('proof/%s/%s/%s.%s', $orderId, $userId, $digest, $ext);
    }

    private static function stripExif(string $content, string $mime): string
    {
        if ($mime !== 'image/jpeg' || ! str_starts_with($content, "\xFF\xD8")) {
            return $content;
        }

        $result = "\xFF\xD8";
        $len = strlen($content);
        $i = 2;

        while ($i + 3 < $len) {
            if ($content[$i] !== "\xFF") {
                $result .= substr($content, $i);
                break;
            }

            $marker = ord($content[$i + 1]);
            $segmentLen = (ord($content[$i + 2]) << 8) + ord($content[$i + 3]);
            if ($segmentLen < 2 || $i + 2 + $segmentLen > $len) {
                break;
            }

            if ($marker === 0xE1) {
                $i += 2 + $segmentLen;
                continue;
            }

            if ($marker === 0xDA || $segmentLen === 0) {
                $result .= substr($content, $i);
                break;
            }

            $result .= substr($content, $i, 2 + $segmentLen);
            $i += 2 + $segmentLen;
        }

        return $result;
    }

    private static function isMoney(string $value): bool
    {
        return preg_match('/^(0|[1-9][0-9]*)(\\.[0-9]{1,2})?$/', $value) === 1;
    }

    private static function isValidDateTime(string $value): bool
    {
        return strtotime($value) !== false;
    }

    private static function sanitizeString(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 255);
    }

    private static function sanitizeNullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private static function buildSubmissionKey(int $orderId, int $userId, string $idempotencyKey): string
    {
        $seed = hash('sha256', $orderId.'|'.$userId.'|'.$idempotencyKey.'|sr-payment-submission');
        $clock = hexdec(substr($seed, 16, 4));

        return sprintf(
            '%s-%s-4%s-%s-%s',
            substr($seed, 0, 8),
            substr($seed, 8, 4),
            substr($seed, 13, 3),
            str_pad(dechex((($clock & 0x3fff) | 0x8000), 4), 4, '0', STR_PAD_LEFT),
            substr($seed, 20, 12),
        );
    }

    private static function newRequestId(): string
    {
        return (string) bin2hex(random_bytes(16));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function persistProof(string $storageKey, string $content, string $mimeType, array $metadata): void
    {
        $stored = ($this->proofWriter)($storageKey, $content, $mimeType, $metadata);
        if ($stored !== true) {
            throw new PaymentProofException('proof_storage_failed', 'Payment proof could not be stored privately.');
        }
    }

    private function publicSubmission(PaymentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'order_id' => $submission->orderId,
            'state' => $submission->state->value,
            'channel' => $submission->channel,
            'expected_amount' => $submission->expectedAmount,
            'reported_amount' => $submission->reportedAmount,
            'reported_paid_at' => $submission->reportedPaidAt,
            'decision_code' => $submission->decisionCode,
            'user_message' => $submission->userMessage,
            'submitted_at' => $submission->submittedAt,
            'reviewed_at' => $submission->reviewedAt,
            'lock_version' => $submission->lockVersion,
        ];
    }
}

final class PaymentProofException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }
}
