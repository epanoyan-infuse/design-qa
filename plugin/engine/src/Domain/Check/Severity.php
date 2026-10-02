<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

enum Severity: string
{
    case Critical = 'critical';
    case NonCritical = 'non-critical';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::NonCritical => 'Non-critical',
        };
    }
}
