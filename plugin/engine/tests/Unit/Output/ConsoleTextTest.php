<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Output;

use DesignQa\Output\ConsoleText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsoleText::class)]
final class ConsoleTextTest extends TestCase
{
    public function testRemovesTerminalEscapeCodesButKeepsText(): void
    {
        self::assertSame('[31mRed text', ConsoleText::clean("\e[31mRed text\x07"));
        self::assertSame("Line one\nLine two", ConsoleText::clean("Line one\nLine two"));
        self::assertSame('Café ★ “quote”', ConsoleText::clean('Café ★ “quote”'));
    }

    public function testEscapesConsoleFormattingTags(): void
    {
        self::assertSame('\\<fg=red\\>Not styled', ConsoleText::forFormatted('<fg=red>Not styled'));
    }
}
