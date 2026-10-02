<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Chrome;

use DesignQa\Infrastructure\Chrome\ChromeLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChromeLocator::class)]
final class ChromeLocatorTest extends TestCase
{
    private const MAC_CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

    public function testFindsChromeInApplications(): void
    {
        $locator = new ChromeLocator([], 'Darwin', static fn(string $p): bool => $p === self::MAC_CHROME);

        self::assertSame(self::MAC_CHROME, $locator->locate());
    }

    public function testEnvironmentOverrideComesFirst(): void
    {
        $locator = new ChromeLocator(['CHROME_PATH' => '/opt/chrome'], 'Darwin', static fn(): bool => true);

        self::assertSame('/opt/chrome', $locator->locate());
    }

    public function testFindsChromeInUserApplicationsWithoutAdminInstall(): void
    {
        $userChrome = '/Users/dev/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
        $locator = new ChromeLocator(['HOME' => '/Users/dev'], 'Darwin', static fn(string $p): bool => $p === $userChrome);

        self::assertSame($userChrome, $locator->locate());
    }

    public function testLinuxCandidates(): void
    {
        $locator = new ChromeLocator([], 'Linux', static fn(string $p): bool => $p === '/usr/bin/chromium');

        self::assertSame('/usr/bin/chromium', $locator->locate());
    }

    public function testReturnsNullWhenNothingIsInstalled(): void
    {
        self::assertNull((new ChromeLocator([], 'Darwin', static fn(): bool => false))->locate());
    }
}
