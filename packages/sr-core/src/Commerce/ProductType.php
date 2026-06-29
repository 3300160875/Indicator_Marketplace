<?php

declare(strict_types=1);

namespace StockResource\Core\Commerce;

enum ProductType: string
{
    case Resource = 'resource';
    case MembershipPlan = 'membership_plan';
}
