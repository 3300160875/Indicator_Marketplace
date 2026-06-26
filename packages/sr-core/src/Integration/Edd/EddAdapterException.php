<?php

declare(strict_types=1);

namespace StockResource\Core\Integration\Edd;

use RuntimeException;

final class EddAdapterException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function notFound(string $type, int $id): self
    {
        return new self('edd_not_found', 'EDD '.$type.' '.$id.' was not found.');
    }

    public static function invalidShape(string $type, string $reason): self
    {
        return new self('edd_invalid_shape', 'EDD '.$type.' shape is invalid: '.$reason);
    }
}
