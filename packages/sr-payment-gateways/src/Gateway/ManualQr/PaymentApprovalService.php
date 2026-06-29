<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Gateway\ManualQr;

final readonly class PaymentApprovalService
{
    public function __construct(private PaymentSubmissionStateMachine $stateMachine) {}

    /**
     * @return array{submission:PaymentSubmission,complete_edd_order:false,idempotency_key:string,idempotent_replay:bool}
     */
    public function approve(
        PaymentSubmission $submission,
        int $expectedLockVersion,
        string $idempotencyKey,
        string $proofHash,
        ?string $billFingerprint,
        ?string $billAmount,
    ): array {
        if ($submission->lockVersion !== $expectedLockVersion) {
            throw ManualQrException::lockVersionMismatch($expectedLockVersion, $submission->lockVersion);
        }

        if ($billFingerprint === null || $billFingerprint === '' || $billAmount === null || $proofHash === '') {
            throw ManualQrException::realBillRequired();
        }

        if ($submission->approvalIdempotencyKey === $idempotencyKey && $submission->state === 'approved') {
            if ($submission->billFingerprint !== $billFingerprint || $submission->billAmount !== trim($billAmount)) {
                throw ManualQrException::realBillRequired();
            }

            return [
                'submission' => $submission,
                'complete_edd_order' => false,
                'idempotency_key' => $idempotencyKey,
                'idempotent_replay' => true,
            ];
        }

        if ($submission->state !== 'under_review') {
            throw ManualQrException::invalidTransition($submission->state, 'approve');
        }

        if (trim($billAmount) !== $submission->amount) {
            throw ManualQrException::amountMismatch($submission->amount, trim($billAmount));
        }

        return [
            'submission' => $submission->withApproval($billFingerprint, trim($billAmount), $idempotencyKey),
            'complete_edd_order' => false,
            'idempotency_key' => $idempotencyKey,
            'idempotent_replay' => false,
        ];
    }
}
