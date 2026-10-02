<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\System;

final readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $errorOutput = '',
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }
}
