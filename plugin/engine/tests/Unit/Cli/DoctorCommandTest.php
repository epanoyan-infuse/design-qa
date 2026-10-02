<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Cli;

use DesignQa\Application\Doctor\Check\PhpVersionCheck;
use DesignQa\Application\Doctor\Doctor;
use DesignQa\Cli\Command\DoctorCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(DoctorCommand::class)]
final class DoctorCommandTest extends TestCase
{
    public function testAllPassing(): void
    {
        $tester = new CommandTester(new DoctorCommand(new Doctor(new PhpVersionCheck('8.4.23'))));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('✅ PHP: 8.4.23', $tester->getDisplay());
        self::assertStringContainsString('design-qa is ready.', $tester->getDisplay());
    }

    public function testFailureShowsFixAndExitCode(): void
    {
        $tester = new CommandTester(new DoctorCommand(new Doctor(new PhpVersionCheck('8.1.0'))));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('❌ PHP: 8.1.0 is too old', $tester->getDisplay());
        self::assertStringContainsString('Fix: ', $tester->getDisplay());
        self::assertStringContainsString('design-qa is not ready yet', $tester->getDisplay());
    }
}
