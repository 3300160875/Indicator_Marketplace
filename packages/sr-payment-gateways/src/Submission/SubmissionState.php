<?php

declare(strict_types=1);

namespace StockResource\PaymentGateways\Submission;

enum SubmissionState: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case NeedsMoreInfo = 'needs_more_info';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public static function initial(): self
    {
        return self::Submitted;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Cancelled], true);
    }
}
