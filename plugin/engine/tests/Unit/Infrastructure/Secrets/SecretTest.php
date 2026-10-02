<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Secrets;

use DesignQa\Infrastructure\Secrets\Secret;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Secret::class)]
final class SecretTest extends TestCase
{
    private const TOKEN = 'figd_example-token-value';

    public function testRevealsTrimmedValue(): void
    {
        self::assertSame(self::TOKEN, (new Secret("  " . self::TOKEN . "\n"))->reveal());
    }

    public function testRejectsEmptyValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Secret("  \n");
    }

    public function testDoesNotLeakThroughStringDumpOrExport(): void
    {
        $secret = new Secret(self::TOKEN);

        self::assertSame('***', (string) $secret);
        self::assertStringNotContainsString(self::TOKEN, print_r($secret, true));
        ob_start();
        var_dump($secret);
        self::assertStringNotContainsString(self::TOKEN, (string) ob_get_clean());
    }

    public function testCannotBeSerialized(): void
    {
        $this->expectException(LogicException::class);
        serialize(new Secret(self::TOKEN));
    }
}
