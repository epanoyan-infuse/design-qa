<?php

declare(strict_types=1);

namespace DesignQa\Application\Doctor;

/**
 * One item of the setup check (`design-qa doctor`).
 */
interface HealthCheck
{
    public function name(): string;

    public function run(): HealthResult;
}
