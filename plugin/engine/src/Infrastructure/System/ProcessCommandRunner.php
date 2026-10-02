<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\System;

use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

final readonly class ProcessCommandRunner implements CommandRunner
{
    public function __construct(private float $timeoutSeconds = 30.0) {}

    public function run(array $command): CommandResult
    {
        $process = new Process($command, timeout: $this->timeoutSeconds);

        try {
            $process->run();
        } catch (ExceptionInterface $e) {
            return new CommandResult(127, '', $e->getMessage());
        }

        return new CommandResult($process->getExitCode() ?? 1, $process->getOutput(), $process->getErrorOutput());
    }

    public function runInteractive(array $command): int
    {
        $process = new Process($command, timeout: null);

        try {
            $process->setTty(Process::isTtySupported());

            return $process->run();
        } catch (ExceptionInterface) {
            return 127;
        }
    }
}
