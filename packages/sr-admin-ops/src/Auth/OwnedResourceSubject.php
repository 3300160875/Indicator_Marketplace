<?php

declare(strict_types=1);

namespace StockResource\AdminOps\Auth;

final readonly class OwnedResourceSubject
{
    public function __construct(
        public int $resourceId,
        public int $ownerUserId,
    ) {}
}
