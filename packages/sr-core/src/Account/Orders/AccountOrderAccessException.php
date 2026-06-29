<?php

declare(strict_types=1);

namespace StockResource\Core\Account\Orders;

use RuntimeException;

final class AccountOrderAccessException extends RuntimeException
{
    public function __construct(public readonly string $codeName, string $message)
    {
        parent::__construct($message);
    }

    public static function loginRequired(): self
    {
        return new self('login_required', 'A logged-in user is required to view account orders.');
    }
}
