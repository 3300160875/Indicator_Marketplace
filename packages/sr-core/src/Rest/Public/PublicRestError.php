<?php
declare(strict_types=1);

namespace StockResource\Core\Rest\Public;

use RuntimeException;
use Throwable;

final class PublicRestError extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function invalidFilter(string $field): self
    {
        return new self('sr_invalid_filter', 'Invalid public resource filter.', 400, ['field' => $field]);
    }

    public static function resourceUnavailable(string $idOrSlug): self
    {
        return new self('sr_resource_unavailable', 'Resource is not available.', 404, ['id_or_slug' => $idOrSlug]);
    }

    public function code(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
