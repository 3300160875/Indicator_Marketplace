<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Admin\ReviewQueue;

use DateTimeImmutable;
use StockResource\PaymentGateways\Submission\PaymentSubmission;
use StockResource\PaymentGateways\Submission\PaymentSubmissionException;
use StockResource\PaymentGateways\Submission\PaymentSubmissionRepository;
use StockResource\PaymentGateways\Submission\PaymentSubmissionStateMachine;
use StockResource\PaymentGateways\Submission\SubmissionState;

final readonly class ReviewQueueService
{
    private mixed $nowProvider;

    /**
     * @param callable(int $reviewerId, PaymentSubmission $submission, int $nowTimestamp): bool $canReview
     * @param callable(): string|null $nowProvider
     */
    public function __construct(
        private PaymentSubmissionRepository $repository,
        private PaymentSubmissionStateMachine $stateMachine,
        private int $lockTtlMinutes,
        private mixed $canReview,
        mixed $nowProvider = null,
    ) {
        if (! is_callable($this->canReview)) {
            throw new \RuntimeException('canReview callback is required.');
        }

        $this->nowProvider = $nowProvider ?? static fn (): string => (new DateTimeImmutable('now'))->format(DATE_ATOM);
    }

    public function claim(int $submissionId, int $reviewerId, int $expectedLockVersion): PaymentSubmission
    {
        $submission = $this->requireSubmission($submissionId);
        $this->assertReviewerPermission($submission, $reviewerId);

        return match ($submission->state) {
            SubmissionState::Submitted => $this->claimSubmitted($submission, $reviewerId, $expectedLockVersion),
            SubmissionState::UnderReview => $this->claimUnderReview($submission, $reviewerId, $expectedLockVersion),
            default => throw new ReviewQueueException('invalid_state', 'Only submitted or under_review submissions can be claimed.'),
        };
    }

    public function releaseTimedOutClaim(int $submissionId, string $now): PaymentSubmission
    {
        $submission = $this->requireSubmission($submissionId);
        if ($submission->state !== SubmissionState::UnderReview) {
            throw new ReviewQueueException('invalid_state', 'Only under_review submissions can release claim timeout.');
        }

        if ($submission->claimedAt === null) {
            throw new ReviewQueueException('state_conflict', 'No reviewer claim to release.');
        }

        if (! $this->isExpired($submission->claimedAt, $now)) {
            throw new ReviewQueueException('not_expired', 'Current claim has not reached timeout threshold.');
        }

        $released = PaymentSubmission::fromArray(array_replace(
            $submission->toArray(),
            [
                'lock_version' => $submission->lockVersion + 1,
                'reviewer_id' => null,
                'claimed_at' => null,
            ],
        ));

        return $this->repository->update($released, $submission->lockVersion);
    }

    public function isExpired(string $claimedAt, string $now): bool
    {
        return $this->isClaimExpired($claimedAt, $now);
    }

    private function claimSubmitted(PaymentSubmission $submission, int $reviewerId, int $expectedLockVersion): PaymentSubmission
    {
        $next = $this->stateMachine->transition($submission, 'claim_review', $expectedLockVersion);
        $claimed = $this->stampClaim($next, $reviewerId);

        return $this->repository->update($claimed, $submission->lockVersion);
    }

    private function claimUnderReview(PaymentSubmission $submission, int $reviewerId, int $expectedLockVersion): PaymentSubmission
    {
        if ($submission->claimedAt === null) {
            throw new ReviewQueueException('invalid_state', 'Submission is under review but has no reviewer claim.');
        }

        if ($submission->lockVersion !== $expectedLockVersion) {
            throw new ReviewQueueException('lock_version_mismatch', 'Expected lock version '.$expectedLockVersion.' but got '.$submission->lockVersion.'.');
        }

        $isExpired = $this->isClaimExpired($submission->claimedAt, $this->now());
        if (! $isExpired) {
            if ($submission->reviewerId === $reviewerId) {
                return $submission;
            }

            throw new ReviewQueueException('state_conflict', 'submission is claimed by another reviewer.');
        }

        return $this->repository->update(
            $this->stampClaim($submission, $reviewerId, $submission->lockVersion + 1),
            $submission->lockVersion,
        );
    }

    private function stampClaim(
        PaymentSubmission $submission,
        int $reviewerId,
        ?int $nextLockVersion = null,
    ): PaymentSubmission {
        $data = $submission->toArray();
        $data['reviewer_id'] = $reviewerId;
        $data['claimed_at'] = $this->now();

        if ($nextLockVersion !== null) {
            $data['lock_version'] = $nextLockVersion;
        }

        return PaymentSubmission::fromArray($data);
    }

    private function requireSubmission(int $submissionId): PaymentSubmission
    {
        $submission = $this->repository->findById($submissionId);
        if ($submission === null) {
            throw new ReviewQueueException('submission_not_found', 'Submission not found.');
        }

        return $submission;
    }

    private function assertReviewerPermission(PaymentSubmission $submission, int $reviewerId): void
    {
        if ($reviewerId <= 0) {
            throw new ReviewQueueException('permission_denied', 'reviewerId must be positive.');
        }

        if (! ($this->canReview)($reviewerId, $submission, time())) {
            throw new ReviewQueueException('permission_denied', 'Reviewer lacks financial review capability.');
        }
    }

    private function now(): string
    {
        return (string) ($this->nowProvider)();
    }

    private function isClaimExpired(string $claimedAt, string $now): bool
    {
        $claimedTime = strtotime($claimedAt);
        $nowTime = strtotime($now);

        if ($claimedTime === false || $nowTime === false) {
            throw new ReviewQueueException('invalid_state', 'Invalid review timestamp.');
        }

        return $nowTime - $claimedTime >= $this->lockTtlMinutes * 60;
    }
}

final class ReviewQueueException extends \RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }
}
