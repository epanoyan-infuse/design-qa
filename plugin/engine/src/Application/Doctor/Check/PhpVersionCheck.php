<?php

declare(strict_types=1);

namespace DesignQa\Application\Doctor\Check;

use DesignQa\Application\Doctor\HealthCheck;
use DesignQa\Application\Doctor\HealthResult;

final readonly class PhpVersionCheck implements HealthCheck
{
    public const MINIMUM = '8.2.0';

    public function __construct(
        private string $currentVersion = PHP_VERSION,
        private string $minimumVersion = self::MINIMUM,
    ) {}

    public function name(): string
    {
        return 'PHP';
    }

    public function run(): HealthResult
    {
        if (version_compare($this->currentVersion, $this->minimumVersion, '>=')) {
            return HealthResult::pass($this->currentVersion);
        }

        return HealthResult::fail(
            sprintf('%s is too old, %s or newer is needed', $this->currentVersion, $this->minimumVersion),
            'Install a newer PHP (see the design-qa README, "Install PHP without admin rights").',
        );
    }
}
