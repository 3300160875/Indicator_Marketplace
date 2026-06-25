<?php
declare(strict_types=1);

namespace StockResource\Core\Admin\ResourceEditor;

final readonly class GateIssue
{
    public function __construct(
        public string $code,
        public string $field,
        public string $section,
        public string $message,
        public string $severity = 'error',
    ) {
    }
}
