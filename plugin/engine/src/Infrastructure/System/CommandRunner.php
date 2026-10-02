<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\System;

/**
 * Runs external programs. Behind an interface so callers can be tested without spawning processes.
 */
interface CommandRunner
{
    /**
     * Runs a command and captures its output.
     *
     * @param list<string> $command program and arguments, never passed through a shell
     */
    public function run(array $command): CommandResult;

    /**
     * Runs a command attached to the user's terminal, so it can prompt for hidden input itself.
     *
     * @param list<string> $command
     *
     * @return int exit code
     */
    public function runInteractive(array $command): int;
}
