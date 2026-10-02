<?php

declare(strict_types=1);

namespace DesignQa\Application\Doctor\Check;

use DesignQa\Application\Doctor\HealthCheck;
use DesignQa\Application\Doctor\HealthResult;
use DesignQa\Application\RulesConfigLoader;
use InvalidArgumentException;

final readonly class RulesConfigCheck implements HealthCheck
{
    public function __construct(private string $configFile) {}

    public function name(): string
    {
        return 'Check rules';
    }

    public function run(): HealthResult
    {
        $fix = sprintf('Fix %s (see the comment at its top).', $this->configFile);

        try {
            $rules = RulesConfigLoader::load($this->configFile);
        } catch (InvalidArgumentException $e) {
            return HealthResult::fail($e->getMessage(), $fix);
        }

        return HealthResult::pass(sprintf('%d checks enabled', count($rules->enabled())));
    }
}
