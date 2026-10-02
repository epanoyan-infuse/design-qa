<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Model;

use DesignQa\Domain\Model\Rect;
use DesignQa\Domain\Model\StyleRun;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Tests\Support\Styles;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextElement::class)]
#[CoversClass(StyleRun::class)]
final class TextElementTest extends TestCase
{
    public function testDominantStyleCountsVisibleCharacters(): void
    {
        // "The Invisible Buyer" italic (17 visible chars) + " teaches everything" regular (17 visible chars + spaces)
        $text = new TextElement('t', 'The Invisible Buyer teaches everything', [
            new StyleRun(0, 19, Styles::body(italic: true)),
            new StyleRun(19, 19, Styles::body()),
        ], new Rect(0, 0, 100, 20), 1);

        self::assertTrue($text->hasMixedStyles());
        self::assertTrue($text->dominantStyle()->italic, 'ties go to the first run');
        self::assertSame(' teaches everything', $text->textOf($text->runs[1]));
    }

    public function testEqualStylesInSeparateRunsCountTogether(): void
    {
        // "aaa BBBB aaa": regular covers 6 letters in two runs, bold 4 in one.
        $text = new TextElement('t', 'aaa BBBB aaa', [
            new StyleRun(0, 4, Styles::body()),
            new StyleRun(4, 4, Styles::body(700)),
            new StyleRun(8, 4, Styles::body()),
        ], new Rect(0, 0, 1, 1), 1);

        self::assertSame(400, $text->dominantStyle()->fontWeight);
    }

    public function testSingleStyleIsNotMixed(): void
    {
        $text = new TextElement('t', "Audit\n frameworks", [new StyleRun(0, 17, Styles::body(700))], new Rect(0, 0, 1, 1), 2);

        self::assertFalse($text->hasMixedStyles());
        self::assertTrue($text->isMultiLine());
        self::assertSame('Audit frameworks', $text->normalizedContent());
    }

    public function testRunsMustCoverTheWholeContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TextElement('t', 'Hello', [new StyleRun(0, 3, Styles::body())], new Rect(0, 0, 1, 1), 1);
    }

    public function testRunsMustNotOverlap(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TextElement('t', 'Hello', [new StyleRun(0, 3, Styles::body()), new StyleRun(2, 3, Styles::body())], new Rect(0, 0, 1, 1), 1);
    }
}
