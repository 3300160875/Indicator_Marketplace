<?php
declare(strict_types=1);

namespace StockResource\Contracts\Dto;

use StockResource\Contracts\Exception\ValidationException;

final readonly class Pagination
{
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
        public int $totalPages,
    ) {
        if ($page < 1) {
            throw new ValidationException('Pagination page must be at least 1.');
        }
        if ($perPage < 1 || $perPage > 100) {
            throw new ValidationException('Pagination perPage must be between 1 and 100.');
        }
        if ($total < 0 || $totalPages < 0) {
            throw new ValidationException('Pagination totals must be non-negative.');
        }
    }

    /** @return array{page:int,per_page:int,total:int,total_pages:int} */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'total_pages' => $this->totalPages,
        ];
    }
}
