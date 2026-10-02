<?php

declare(strict_types=1);

namespace DesignQa\Application\Doctor\Check;

use DesignQa\Application\Doctor\HealthCheck;
use DesignQa\Application\Doctor\HealthResult;
use DesignQa\Infrastructure\Chrome\ChromeLocator;

final readonly class ChromeCheck implements HealthCheck
{
    public function __construct(private ChromeLocator $locator) {}

    public function name(): string
    {
        return 'Google Chrome';
    }

    public function run(): HealthResult
    {
        $path = $this->locator->locate();
        if ($path !== null) {
            return HealthResult::pass($path);
        }

        return HealthResult::fail(
            'not found',
            sprintf('Install Google Chrome, or set %s to the Chrome executable.', ChromeLocator::ENV_VARIABLE),
        );
    }
}
