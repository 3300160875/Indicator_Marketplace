<?php
declare(strict_types=1);

namespace StockResource\Platform\Dependency;

use StockResource\Platform\Runtime\Runtime;

final readonly class DependencyChecker
{
    /**
     * @param list<Requirement> $requirements
     */
    public function __construct(private array $requirements)
    {
    }

    public static function platformDefaults(): self
    {
        return new self([
            Requirement::php('8.3.0'),
            Requirement::wordpress('6.8.0'),
            Requirement::plugin('easy-digital-downloads/easy-digital-downloads.php', '3.6.0', 'Easy Digital Downloads'),
        ]);
    }

    public function check(Runtime $runtime): DependencyReport
    {
        $failures = [];

        foreach ($this->requirements as $requirement) {
            $failure = $requirement->failure($runtime);
            if ($failure !== null) {
                $failures[] = $failure;
            }
        }

        return new DependencyReport($failures);
    }
}
