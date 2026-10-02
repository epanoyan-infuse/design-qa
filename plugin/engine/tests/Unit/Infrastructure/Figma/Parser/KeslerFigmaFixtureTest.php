<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Figma\Parser;

use DesignQa\Domain\Model\Design;
use DesignQa\Domain\Model\ExcludedText;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Infrastructure\Figma\Parser\FigmaDocumentParser;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests on the real Kesler design (recorded 2026-09-28), covering every false result of
 * the old figma-checker that comes from reading Figma.
 */
#[CoversNothing]
final class KeslerFigmaFixtureTest extends TestCase
{
    private static ?Design $design = null;

    public function testFindsAllFiveScreensByWidth(): void
    {
        self::assertSame(
            ['1920_Desktop' => 1920, '1024_Tablet' => 1024, '800_Tablet' => 800, '390_Mobile' => 390, '390_Mobile Menu' => 390],
            array_combine(array_map(static fn(Screen $s) => $s->name(), self::design()->screens), array_map(static fn(Screen $s) => $s->width(), self::design()->screens)),
        );
    }

    public function testHiddenReviewPlaceholdersAreNotCompared(): void
    {
        $desktop = self::screen('1920_Desktop');

        self::assertCount(65, $desktop->texts);
        self::assertSame(['★★★★★', 'Review title'], array_values(array_unique(array_map(static fn(ExcludedText $e) => $e->content, $desktop->excluded))));
    }

    public function testMenuScreenHasOnlyTheTextsVisibleOnTheMenu(): void
    {
        // The old checker compared 58 texts here, most of them the page hidden behind the menu.
        $menu = self::screen('390_Mobile Menu');

        self::assertSame(
            ['The Invisible Buyer', 'About', 'Author', 'Worksheets', 'Reviews', 'Get the book'],
            array_map(static fn(TextElement $t) => $t->normalizedContent(), $menu->texts),
        );
    }

    public function testEffectiveWeightOfListItemsIsBold(): void
    {
        $audit = self::text('1920_Desktop', 'Audit frameworks');

        self::assertSame(700, $audit->dominantStyle()->fontWeight);
        self::assertFalse($audit->hasMixedStyles());
    }

    public function testEffectiveColorOfReviewerName(): void
    {
        self::assertSame('#012A4D', self::text('1920_Desktop', 'User Name')->dominantStyle()->color?->toHex());
    }

    public function testRealItalicParagraphIsKept(): void
    {
        // In Figma the whole first paragraph is italic; on the page only the book title is.
        $paragraph = self::text('1920_Desktop', 'The Invisible Buyer teaches');

        self::assertTrue($paragraph->runs[0]->style->italic);
        self::assertSame(283, $paragraph->runs[0]->length);
        self::assertFalse($paragraph->runs[1]->style->italic);
    }

    public function testMobileUsesMobileSizes(): void
    {
        self::assertSame(28.0, self::text('390_Mobile', 'Alexander Kesler')->dominantStyle()->fontSize);
        self::assertSame(44.0, self::text('1920_Desktop', 'Alexander Kesler')->dominantStyle()->fontSize);
    }

    public function testEyebrowLabelKeepsUppercaseAsTextCase(): void
    {
        $label = self::text('1920_Desktop', 'About the book', 11.0);

        self::assertSame('#003867', $label->dominantStyle()->color?->toHex());
        self::assertSame(2.0, $label->dominantStyle()->letterSpacing);
        self::assertSame('upper', $label->dominantStyle()->textCase->value);
    }

    private static function design(): Design
    {
        if (self::$design === null) {
            $json = file_get_contents(dirname(__DIR__, 4) . '/Fixtures/kesler/figma-nodes.json');
            self::assertIsString($json);
            $response = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($response);
            self::$design = (new FigmaDocumentParser())->parse($response, '2421:637');
        }

        return self::$design;
    }

    private static function screen(string $name): Screen
    {
        foreach (self::design()->screens as $screen) {
            if ($screen->name() === $name) {
                return $screen;
            }
        }
        self::fail(sprintf('Screen %s not found', $name));
    }

    private static function text(string $screen, string $startsWith, ?float $fontSize = null): TextElement
    {
        foreach (self::screen($screen)->texts as $text) {
            if (str_starts_with($text->content, $startsWith) && ($fontSize === null || $text->dominantStyle()->fontSize === $fontSize)) {
                return $text;
            }
        }
        self::fail(sprintf('Text "%s" not found on %s', $startsWith, $screen));
    }
}
