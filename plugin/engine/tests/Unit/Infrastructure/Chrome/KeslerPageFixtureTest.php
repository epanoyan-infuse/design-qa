<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Chrome;

use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Infrastructure\Chrome\PageSnapshotMapper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests on the recorded Kesler page (2026-09-29), including the per-width values the old
 * figma-checker got wrong because it captured the page only once, at desktop width.
 */
#[CoversNothing]
final class KeslerPageFixtureTest extends TestCase
{
    public function testEachWidthHasItsOwnValues(): void
    {
        self::assertSame(68.0, self::text(1920, 'The Invisible Buyer', 'h1')->dominantStyle()->fontSize);
        self::assertSame(44.0, self::text(390, 'The Invisible Buyer', 'h1')->dominantStyle()->fontSize);
        self::assertSame(44.0, self::text(1920, 'Alexander Kesler')->dominantStyle()->fontSize);
        self::assertSame(28.0, self::text(390, 'Alexander Kesler')->dominantStyle()->fontSize);
    }

    public function testRealMismatchesAreMeasured(): void
    {
        // Figma: #003867. The page uses the grey of "New Book".
        self::assertSame('#94A3B8', self::text(1920, 'About the book', fontSize: 11.0)->dominantStyle()->color?->toHex());
        // Figma: 700 on mobile.
        self::assertSame(600, self::text(390, 'Alexander Kesler')->dominantStyle()->fontWeight);
        // Figma: 32px.
        self::assertSame(30.6, self::text(1920, 'Preference forms early')->dominantStyle()->lineHeight);
        // Figma: 0.
        self::assertSame(-1.0, self::text(1920, 'Most B2B decisions')->dominantStyle()->letterSpacing);
    }

    public function testParagraphWithItalicTitleIsOneText(): void
    {
        $paragraph = self::text(1920, 'The Invisible Buyer teaches');

        self::assertCount(2, $paragraph->runs);
        self::assertSame('The Invisible Buyer', $paragraph->textOf($paragraph->runs[0]));
        self::assertTrue($paragraph->runs[0]->style->italic);
        self::assertFalse($paragraph->runs[1]->style->italic);
        self::assertTrue($paragraph->isMultiLine());
    }

    public function testOnlyVisibleSlidesAndNoIconGlyphs(): void
    {
        $names = static fn(int $width): int => count(array_filter(self::screen($width)->texts, static fn(TextElement $t): bool => $t->content === 'User Name'));

        self::assertSame(3, $names(1920));
        self::assertSame(1, $names(390));
        foreach (self::screen(1920)->texts as $text) {
            self::assertDoesNotMatchRegularExpression('/^[\x{E000}-\x{F8FF}]+$/u', $text->content);
        }
    }

    private static function screen(int $width): Screen
    {
        $json = file_get_contents(sprintf('%s/Fixtures/kesler/page-%d.json', dirname(__DIR__, 3), $width));
        self::assertIsString($json);

        return (new PageSnapshotMapper())->map(json_decode($json, true, flags: JSON_THROW_ON_ERROR), new ScreenSpec((string) $width, $width));
    }

    private static function text(int $width, string $startsWith, ?string $tag = null, ?float $fontSize = null): TextElement
    {
        foreach (self::screen($width)->texts as $text) {
            if (!str_starts_with($text->content, $startsWith)) {
                continue;
            }
            if ($tag !== null && !str_starts_with($text->path[array_key_last($text->path)] ?? '', $tag)) {
                continue;
            }
            if ($fontSize !== null && $text->dominantStyle()->fontSize !== $fontSize) {
                continue;
            }

            return $text;
        }
        self::fail(sprintf('Text "%s" not found at %dpx', $startsWith, $width));
    }
}
