<?php
declare(strict_types=1);

namespace StockResource\Contracts\Enum;

enum ErrorCode: string
{
    case ValidationError = 'VALIDATION_ERROR';
    case AuthRequired = 'AUTH_REQUIRED';
    case Forbidden = 'FORBIDDEN';
    case ResourceUnavailable = 'RESOURCE_UNAVAILABLE';
    case OrderStateConflict = 'ORDER_STATE_CONFLICT';
    case DuplicateTransaction = 'DUPLICATE_TRANSACTION';
    case IdempotencyConflict = 'IDEMPOTENCY_CONFLICT';
    case TokenExpired = 'TOKEN_EXPIRED';
    case TokenUsed = 'TOKEN_USED';
    case PurchaseRequired = 'PURCHASE_REQUIRED';
    case VipRequired = 'VIP_REQUIRED';
    case QuotaExhausted = 'QUOTA_EXHAUSTED';
    case RateLimited = 'RATE_LIMITED';
    case StorageUnavailable = 'STORAGE_UNAVAILABLE';
}
