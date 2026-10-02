<?php

declare(strict_types=1);

namespace DesignQa\Tests\Support;

use DesignQa\Infrastructure\System\CommandResult;
use DesignQa\Infrastructure\System\CommandRunner;

final class FakeCommandRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<list<string>> */
    public array $interactiveCommands = [];

    public function __construct(
        private readonly CommandResult $result = new CommandResult(0, ''),
        private readonly int $interactiveExitCode = 0,
    ) {}

    public function run(array $command): CommandResult
    {
        $this->commands[] = $command;

        return $this->result;
    }

    public function runInteractive(array $command): int
    {
        $this->interactiveCommands[] = $command;

        return $this->interactiveExitCode;
    }
}
