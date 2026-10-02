<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Matching;

use DesignQa\Domain\Matching\MatchKind;
use DesignQa\Domain\Matching\MatchResult;
use DesignQa\Domain\Matching\PositionalTextMatcher;
use DesignQa\Domain\Matching\ScreenGeometry;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextNormalizer;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Tests\Support\Texts as T;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PositionalTextMatcher::class)]
#[CoversClass(ScreenGeometry::class)]
#[CoversClass(TextNormalizer::class)]
#[CoversClass(TextPart::class)]
#[CoversClass(MatchResult::class)]
final class PositionalTextMatcherTest extends TestCase
{
    public function testSameWordingIgnoringCaseAndPunctuation(): void
    {
        $result = $this->match(
            [T::text('f1', 'ABOUT THE BOOK', 0, 100), T::text('f2', 'Title, Company', 0, 300)],
            [T::text('p1', 'About the book', 0, 105), T::text('p2', 'Title. Company', 0, 290)],
        );

        self::assertSame(['f1' => 'p1', 'f2' => 'p2'], self::pairs($result));
        self::assertSame(MatchKind::Exact, $result->matches[0]->kind);
    }

    public function testRepeatedTextsGoToTheNearestPlace(): void
    {
        // "Reviews" in the header menu and as a section eyebrow.
        $result = $this->match(
            [T::text('menu', 'Reviews', 800, 20), T::text('section', 'Reviews', 100, 500)],
            [T::text('p-section', 'Reviews', 100, 520), T::text('p-menu', 'Reviews', 790, 22)],
        );

        self::assertSame(['menu' => 'p-menu', 'section' => 'p-section'], self::pairs($result));
    }

    public function testCardsSideBySideArePairedByHorizontalPosition(): void
    {
        $result = $this->match(
            [T::text('a', 'User Name', 0, 500), T::text('b', 'User Name', 340, 500), T::text('c', 'User Name', 680, 500)],
            [T::text('pc', 'User Name', 690, 510), T::text('pa', 'User Name', 10, 510), T::text('pb', 'User Name', 350, 510)],
        );

        self::assertSame(['a' => 'pa', 'b' => 'pb', 'c' => 'pc'], self::pairs($result));
    }

    public function testFigmaParagraphsSplitIntoSeveralPageTexts(): void
    {
        $design = T::text('f', "The Invisible Buyer teaches B2B.\r\n\nGrounded in current research.", 0, 100, lines: 6);
        $result = $this->match([$design], [T::text('p1', 'The Invisible Buyer teaches B2B.', 0, 100), T::text('p2', 'Grounded in current research.', 0, 180)]);

        self::assertCount(2, $result->matches);
        self::assertSame(['The Invisible Buyer teaches B2B.', 'Grounded in current research.'], array_map(static fn(TextMatch $m) => $m->design->content(), $result->matches));
        self::assertSame(['p1', 'p2'], array_map(static fn(TextMatch $m) => $m->page->id, $result->matches));
        self::assertSame([], $result->unmatchedDesign);
    }

    public function testSimilarWordingIsMatchedAndMarked(): void
    {
        $result = $this->match(
            [T::text('f', 'The Appendices equip you with the tools needed to forge your path through the dark funnel', 0, 100)],
            [T::text('p', 'These worksheets equip you with the tools needed to forge your path through the dark funnel', 0, 100)],
        );

        self::assertSame(['f' => 'p'], self::pairs($result));
        self::assertSame(MatchKind::Similar, $result->matches[0]->kind);
    }

    public function testMultiParagraphTextWithAChangedWordIsMatchedAsAWhole(): void
    {
        $design = T::text('f', "Our story\n\nFounded in 1990 by two friends who loved books", 0, 100, lines: 3);
        $page = T::text('p', "Our story\nFounded in 1991 by two friends who loved books", 0, 100, lines: 3);

        $result = $this->match([$design], [$page]);

        self::assertSame(['f' => 'p'], self::pairs($result));
        self::assertSame(MatchKind::Similar, $result->matches[0]->kind);
        self::assertTrue($result->matches[0]->design->isWhole());
    }

    public function testSymbolOnlyParagraphIsNotReportedAsMissing(): void
    {
        $design = T::text('f', "Intro line here\n* * *\nMore text follows", 0, 100, lines: 3);
        $result = $this->match([$design], [T::text('p1', 'Intro line here', 0, 100), T::text('p2', 'More text follows', 0, 140)]);

        self::assertCount(2, $result->matches);
        self::assertSame([], $result->unmatchedDesign);
    }

    public function testShortOrDifferentTextsAreNotGuessed(): void
    {
        $result = $this->match(
            [T::text('f1', 'Get the book', 0, 100), T::text('f2', 'Lorem Ipsum is simply dummy text of the printing', 0, 300)],
            [T::text('p1', 'Buy the book', 0, 100), T::text('p2', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit', 0, 300)],
        );

        // A short button in the same place is the same button with changed wording; long, different
        // placeholder copy is not guessed.
        self::assertSame(['f1' => 'p1'], self::pairs($result));
        self::assertSame(MatchKind::Similar, $result->matches[0]->kind);
        self::assertSame(['Lorem Ipsum is simply dummy text of the printing'], array_map(static fn(TextPart $p) => $p->content(), $result->unmatchedDesign));
        self::assertCount(1, $result->unmatchedPage);
    }

    public function testSameUniqueTextFarAwayIsStillTheSameText(): void
    {
        // Appearing once on each side, this wording cannot be confused with any other text: the
        // real page is free to lay it out much further down than Figma's own mockup (a shorter
        // card above it, a feature tab rendering less content, etc.), unlike a repeated text
        // ("Reviews" in the nav and as a section title), where position is what tells them apart.
        $result = $this->match([T::text('f', 'Buy now on Amazon', 0, 100)], [T::text('p', 'Buy now on Amazon', 0, 900)]);

        self::assertSame(['f' => 'p'], self::pairs($result));
        self::assertSame(MatchKind::Exact, $result->matches[0]->kind);
    }

    public function testTwoEquallyGoodPlacesAreLeftForAManualCheck(): void
    {
        $result = $this->match(
            [T::text('f', 'User Name', 500, 500)],
            [T::text('left', 'User Name', 300, 500), T::text('right', 'User Name', 700, 500)],
        );

        self::assertSame([], $result->matches);
        self::assertCount(1, $result->uncertain);
        self::assertCount(2, $result->uncertain[0]->candidates);
    }

    public function testSymbolsAreSetAside(): void
    {
        $result = $this->match([T::text('q', '”', 0, 100), T::text('arrow', '‹', 0, 200)], [T::text('p', 'Hello', 0, 100)]);

        self::assertSame(['”', '‹'], array_map(static fn(TextElement $t) => $t->content, $result->symbols));
    }

    public function testDeterministic(): void
    {
        $design = [T::text('a', 'Buy now', 0, 100), T::text('b', 'Buy now', 400, 100)];
        $page = [T::text('x', 'Buy now', 390, 105), T::text('y', 'Buy now', 5, 105)];

        self::assertSame(self::pairs($this->match($design, $page)), self::pairs($this->match($design, $page)));
    }

    /**
     * @param list<TextElement> $design
     * @param list<TextElement> $page
     */
    private function match(array $design, array $page): MatchResult
    {
        return (new PositionalTextMatcher())->match(T::screen($design), T::screen($page));
    }

    /**
     * @return array<string, string> design text id => page text id
     */
    private static function pairs(MatchResult $result): array
    {
        $pairs = [];
        foreach ($result->matches as $match) {
            $pairs[$match->design->text->id] = $match->page->id;
        }
        ksort($pairs);

        return $pairs;
    }
}
