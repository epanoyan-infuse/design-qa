<?php

declare(strict_types=1);

namespace DesignQa\Application\Doctor;

use Throwable;

/**
 * Runs every health check. A check that crashes is reported as failed instead of stopping the others.
 */
final readonly class Doctor
{
    /** @var list<HealthCheck> */
    private array $checks;

    public function __construct(HealthCheck ...$checks)
    {
        $this->checks = array_values($checks);
    }

    /**
     * @return list<array{name: string, result: HealthResult}>
     */
    public function examine(): array
    {
        return array_map(static function (HealthCheck $check): array {
            try {
                $result = $check->run();
            } catch (Throwable $e) {
                $result = HealthResult::fail(sprintf('check crashed: %s', $e->getMessage()), 'Report this to the design-qa maintainers.');
            }

            return ['name' => $check->name(), 'result' => $result];
        }, $this->checks);
    }
}
