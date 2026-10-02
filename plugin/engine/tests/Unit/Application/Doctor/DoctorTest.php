<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Application\Doctor;

use DesignQa\Application\Doctor\Check\FigmaKeyCheck;
use DesignQa\Application\Doctor\Check\PhpExtensionsCheck;
use DesignQa\Application\Doctor\Check\PhpVersionCheck;
use DesignQa\Application\Doctor\Check\RulesConfigCheck;
use DesignQa\Application\Doctor\Doctor;
use DesignQa\Application\Doctor\HealthCheck;
use DesignQa\Application\Doctor\HealthResult;
use DesignQa\Infrastructure\Figma\FigmaApiException;
use DesignQa\Tests\Support\FakeFigmaClient;
use DesignQa\Tests\Support\InMemoryTokenStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Doctor::class)]
#[CoversClass(HealthResult::class)]
#[CoversClass(PhpVersionCheck::class)]
#[CoversClass(PhpExtensionsCheck::class)]
#[CoversClass(FigmaKeyCheck::class)]
#[CoversClass(RulesConfigCheck::class)]
final class DoctorTest extends TestCase
{
    private const SAVE = 'security add-generic-password -U -a design-qa -s design-qa-figma -w';

    public function testPhpVersion(): void
    {
        self::assertTrue((new PhpVersionCheck('8.2.0'))->run()->passed);
        self::assertTrue((new PhpVersionCheck('8.4.23'))->run()->passed);
        self::assertFalse((new PhpVersionCheck('8.1.29'))->run()->passed);
    }

    public function testExtensionsListsOnlyMissingOnes(): void
    {
        $result = (new PhpExtensionsCheck(['json', 'sockets'], static fn(string $e): bool => $e === 'json'))->run();

        self::assertFalse($result->passed);
        self::assertSame('missing: sockets', $result->detail);
    }

    public function testFigmaKeyMissingGivesTheSaveCommand(): void
    {
        $result = (new FigmaKeyCheck(new InMemoryTokenStore(null, 'macOS keychain'), new FakeFigmaClient(), self::SAVE))->run();

        self::assertFalse($result->passed);
        self::assertSame('not found in macOS keychain', $result->detail);
        self::assertStringContainsString(self::SAVE, (string) $result->fix);
    }

    public function testFigmaKeyRejected(): void
    {
        $figma = new FakeFigmaClient(failure: FigmaApiException::forStatus(403, '/v1/me'));
        $result = (new FigmaKeyCheck(new InMemoryTokenStore('figd_x'), $figma, self::SAVE))->run();

        self::assertFalse($result->passed);
        self::assertStringContainsString('Figma rejected the key', $result->detail);
    }

    public function testFigmaKeyWorksAndNeverShowsTheKey(): void
    {
        $result = (new FigmaKeyCheck(new InMemoryTokenStore('figd_x', 'macOS keychain'), new FakeFigmaClient(['handle' => 'qa-bot']), self::SAVE))->run();

        self::assertTrue($result->passed);
        self::assertSame('works (account: qa-bot, from macOS keychain)', $result->detail);
        self::assertStringNotContainsString('figd_x', $result->detail);
    }

    public function testRulesConfig(): void
    {
        self::assertTrue((new RulesConfigCheck(dirname(__DIR__, 4) . '/config/rules.php'))->run()->passed);
        self::assertFalse((new RulesConfigCheck('/nonexistent/rules.php'))->run()->passed);
    }

    public function testCrashingCheckIsReportedAndOthersStillRun(): void
    {
        $crashing = new class implements HealthCheck {
            public function name(): string
            {
                return 'broken';
            }

            public function run(): HealthResult
            {
                throw new RuntimeException('boom');
            }
        };

        $report = (new Doctor($crashing, new PhpVersionCheck('8.4.0')))->examine();

        self::assertCount(2, $report);
        self::assertFalse($report[0]['result']->passed);
        self::assertStringContainsString('boom', $report[0]['result']->detail);
        self::assertTrue($report[1]['result']->passed);
    }
}
