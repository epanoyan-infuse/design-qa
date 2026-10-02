<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Chrome;

use DesignQa\Domain\Model\TextCase;
use DesignQa\Infrastructure\Chrome\CssStyleParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CssStyleParser::class)]
final class CssStyleParserTest extends TestCase
{
    public function testParsesComputedStyle(): void
    {
        $style = (new CssStyleParser())->parse([
            'fontFamily' => '"Plus Jakarta Sans", sans-serif',
            'fontWeight' => '700',
            'fontSize' => '11px',
            'fontStyle' => 'normal',
            'color' => 'rgb(148, 163, 184)',
            'letterSpacing' => '2px',
            'lineHeight' => '11px',
            'textTransform' => 'uppercase',
        ]);

        self::assertSame('Plus Jakarta Sans', $style->fontFamily);
        self::assertSame(700, $style->fontWeight);
        self::assertSame(11.0, $style->fontSize);
        self::assertFalse($style->italic);
        self::assertSame('#94A3B8', $style->color?->toHex());
        self::assertSame(2.0, $style->letterSpacing);
        self::assertSame(11.0, $style->lineHeight);
        self::assertSame(TextCase::Upper, $style->textCase);
    }

    public function testNormalValuesAndOpacity(): void
    {
        $style = (new CssStyleParser())->parse([
            'fontFamily' => 'Newsreader',
            'fontWeight' => 'bold',
            'fontSize' => '44px',
            'fontStyle' => 'italic',
            'color' => 'rgba(1, 42, 77, 0.5)',
            'letterSpacing' => 'normal',
            'lineHeight' => 'normal',
            'textTransform' => 'none',
        ], opacity: 0.5);

        self::assertSame(700, $style->fontWeight);
        self::assertTrue($style->italic);
        self::assertSame(0.0, $style->letterSpacing);
        self::assertNull($style->lineHeight);
        self::assertSame('#012A4D 25%', $style->color?->toHex());
        self::assertSame(TextCase::None, $style->textCase);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function families(): iterable
    {
        yield 'double quotes' => ['"Plus Jakarta Sans", sans-serif', 'Plus Jakarta Sans'];
        yield 'single quotes' => ["'Newsreader', serif", 'Newsreader'];
        yield 'unquoted' => ['Georgia, serif', 'Georgia'];
        yield 'system' => ['-apple-system, BlinkMacSystemFont', '-apple-system'];
    }

    #[DataProvider('families')]
    public function testFirstFamily(string $css, string $expected): void
    {
        self::assertSame($expected, (new CssStyleParser())->firstFamily($css));
    }

    public function testPixels(): void
    {
        $parser = new CssStyleParser();

        self::assertSame(30.6, $parser->pixels('30.6px'));
        self::assertSame(-1.0, $parser->pixels('-1px'));
        self::assertNull($parser->pixels('normal'));
        self::assertNull($parser->pixels('1.5em'));
    }
}
