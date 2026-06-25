<?php
declare(strict_types=1);

namespace StockResource\Contracts\Dto;

use StockResource\Contracts\Enum\ErrorCode;
use StockResource\Contracts\Exception\ValidationException;
use StockResource\Contracts\Value\RequestId;

final readonly class ErrorResponse
{
    /**
     * @param array<string,list<string>> $fieldErrors
     * @param array<string,mixed> $meta
     */
    public function __construct(
        public ErrorCode $errorCode,
        public string $message,
        public RequestId $requestId,
        public array $fieldErrors = [],
        public ?int $retryAfter = null,
        public array $meta = [],
    ) {
        if ('' === trim($message)) {
            throw new ValidationException('Error message must not be empty.');
        }
        if (null !== $retryAfter && $retryAfter < 0) {
            throw new ValidationException('retryAfter must be non-negative.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode->value,
            'message' => $this->message,
            'request_id' => $this->requestId->toString(),
            'field_errors' => $this->fieldErrors,
            'retry_after' => $this->retryAfter,
            'meta' => $this->meta,
        ];
    }
}
