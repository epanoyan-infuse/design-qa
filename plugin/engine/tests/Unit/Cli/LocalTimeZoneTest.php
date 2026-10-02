<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Cli;

use DesignQa\Cli\LocalTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalTimeZone::class)]
final class LocalTimeZoneTest extends TestCase
{
    public function testBuiltInUtcWithoutPhpIniDoesNotCount(): void
    {
        // The static PHP build: no php.ini, but date.timezone reports "UTC".
        self::assertSame('Asia/Yerevan', LocalTimeZone::detect(false, '/var/db/timezone/zoneinfo/Asia/Yerevan', false));
    }

    public function testTzVariableWinsAndMayUseAliasesOrAColon(): void
    {
        self::assertSame('US/Pacific', LocalTimeZone::detect('US/Pacific', '/usr/share/zoneinfo/Europe/Berlin', false));
        self::assertSame('America/New_York', LocalTimeZone::detect(':America/New_York', false, false));
    }

    public function testInvalidTzFallsBackToTheSystemZone(): void
    {
        self::assertSame('Europe/Berlin', LocalTimeZone::detect('Not/AZone', '/usr/share/zoneinfo/Europe/Berlin', false));
    }

    public function testConfiguredPhpIniIsRespected(): void
    {
        self::assertNull(LocalTimeZone::detect('Asia/Yerevan', '/usr/share/zoneinfo/Asia/Yerevan', 'Europe/Paris'));
    }

    public function testNothingKnownKeepsPhpsSetting(): void
    {
        self::assertNull(LocalTimeZone::detect(false, false, false));
    }
}
