<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Gateway\ManualQr;

final readonly class PaymentSubmission
{
    private function __construct(
        public int $orderId,
        public int $userId,
        public string $amount,
        public string $currency,
        public string $proofHash,
        public string $state,
        public int $lockVersion,
        public ?string $billFingerprint,
        public ?string $billAmount,
        public ?string $approvalIdempotencyKey,
    ) {}

    public static function draft(
        int $orderId,
        int $userId,
        string $amount,
        string $currency,
        string $proofHash,
    ): self {
        return new self(
            orderId: $orderId,
            userId: $userId,
            amount: $amount,
            currency: strtoupper($currency),
            proofHash: $proofHash,
            state: 'draft',
            lockVersion: 0,
            billFingerprint: null,
            billAmount: null,
            approvalIdempotencyKey: null,
        );
    }

    public function withState(string $state): self
    {
        return new self(
            orderId: $this->orderId,
            userId: $this->userId,
            amount: $this->amount,
            currency: $this->currency,
            proofHash: $this->proofHash,
            state: $state,
            lockVersion: $this->lockVersion + 1,
            billFingerprint: $this->billFingerprint,
            billAmount: $this->billAmount,
            approvalIdempotencyKey: $this->approvalIdempotencyKey,
        );
    }

    public function withApproval(string $billFingerprint, string $billAmount, string $idempotencyKey): self
    {
        return new self(
            orderId: $this->orderId,
            userId: $this->userId,
            amount: $this->amount,
            currency: $this->currency,
            proofHash: $this->proofHash,
            state: 'approved',
            lockVersion: $this->lockVersion + 1,
            billFingerprint: $billFingerprint,
            billAmount: $billAmount,
            approvalIdempotencyKey: $idempotencyKey,
        );
    }
}
