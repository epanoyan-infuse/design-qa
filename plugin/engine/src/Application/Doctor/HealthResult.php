<?php

declare(strict_types=1);

namespace DesignQa\Application\Doctor;

final readonly class HealthResult
{
    private function __construct(
        public bool $passed,
        public string $detail,
        public ?string $fix,
    ) {}

    public static function pass(string $detail): self
    {
        return new self(true, $detail, null);
    }

    public static function fail(string $detail, string $fix): self
    {
        return new self(false, $detail, $fix);
    }
}
