<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Chrome;

use DesignQa\Application\Port\SourceException;
use DesignQa\Domain\Model\ExclusionReason;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Infrastructure\Chrome\PageSnapshotMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageSnapshotMapper::class)]
final class PageSnapshotMapperTest extends TestCase
{
    public function testParagraphSplitAcrossElementsIsOneTextWithRuns(): void
    {
        // <p><em>The Invisible Buyer</em> teaches B2B</p>
        $screen = $this->map([$this->block('b0', [
            $this->segment("\n  The Invisible Buyer", ['fontStyle' => 'italic'], [[0, 100, 150, 30]]),
            ['text' => ' ', 'style' => null],
            $this->segment(' teaches   B2B ', [], [[150, 100, 120, 30]]),
        ])]);
        $text = $screen->texts[0];

        self::assertSame('The Invisible Buyer teaches B2B', $text->content);
        self::assertCount(2, $text->runs);
        // Collapsed whitespace between elements keeps the style of the text before it.
        self::assertSame('The Invisible Buyer ', $text->textOf($text->runs[0]));
        self::assertTrue($text->runs[0]->style->italic);
        self::assertSame('teaches B2B', $text->textOf($text->runs[1]));
        self::assertSame(1, $text->lineCount);
    }

    public function testLineBreakAndLineCount(): void
    {
        $screen = $this->map([$this->block('b0', [
            $this->segment('Line one ', [], [[0, 0, 80, 30]]),
            ['lineBreak' => true],
            $this->segment('line two', [], [[0, 30, 70, 30], [0, 60, 40, 30]]),
        ])]);
        $text = $screen->texts[0];

        self::assertSame("Line one\nline two", $text->content);
        self::assertSame(3, $text->lineCount);
        self::assertCount(1, $text->runs, 'identical styles merge into one run');
        self::assertEqualsWithDelta(90.0, $text->box->height, 0.001);
    }

    public function testNormalLineHeightIsMeasuredOnTheRenderedLines(): void
    {
        $screen = $this->map([$this->block('b0', [
            $this->segment('A paragraph that wraps onto three lines', ['lineHeight' => 'normal'], [[0, 100, 300, 22], [0, 124.5, 300, 22], [0, 149, 120, 22]]),
        ])]);
        $style = $screen->texts[0]->dominantStyle();

        self::assertSame(3, $screen->texts[0]->lineCount);
        self::assertSame(24.5, $style->lineHeight);
    }

    public function testParagraphGapIsNotTakenForTheLineHeight(): void
    {
        // Three lines, the last one after a blank line: gaps 24 and 40.
        $screen = $this->map([$this->block('b0', [
            $this->segment('Wrapping text', ['lineHeight' => 'normal'], [[0, 0, 300, 22], [0, 24, 300, 22], [0, 64, 120, 22]]),
        ])]);

        self::assertSame(24.0, $screen->texts[0]->dominantStyle()->lineHeight);
    }

    public function testNormalLineHeightOnOneLineStaysUnknown(): void
    {
        $screen = $this->map([$this->block('b0', [$this->segment('One line', ['lineHeight' => 'normal'], [[0, 0, 80, 22]])])]);

        self::assertNull($screen->texts[0]->dominantStyle()->lineHeight);
    }

    public function testMixedFontSizesOnOneLineCountOnce(): void
    {
        $screen = $this->map([$this->block('b0', [
            $this->segment('Big', ['fontSize' => '28px'], [[0, 100, 60, 36]]),
            $this->segment(' small', ['fontSize' => '14px'], [[60, 115, 40, 18]]),
        ])]);

        self::assertSame(1, $screen->texts[0]->lineCount);
    }

    public function testTextsSortedTopToBottom(): void
    {
        $screen = $this->map([
            $this->block('low', [$this->segment('Footer', [], [[0, 900, 50, 20]])]),
            $this->block('high', [$this->segment('Header', [], [[0, 10, 50, 20]])]),
        ]);

        self::assertSame(['Header', 'Footer'], array_map(static fn($t) => $t->content, $screen->texts));
        self::assertSame(7000.0, $screen->height);
    }

    public function testVisibilityIsDecidedWithTheSharedRules(): void
    {
        $screen = $this->map([$this->block('b0', [
            $this->segment('Visible', [], [[0, 0, 100, 20]]),
            $this->segment('Display none', [], []),
            $this->segment('Visibility hidden', [], [[0, 30, 100, 20]], ['visibility' => 'hidden']),
            $this->segment('Faded', [], [[0, 60, 100, 20]], ['opacity' => 0]),
            $this->segment('Transparent paint', ['color' => 'rgba(0, 0, 0, 0)'], [[0, 90, 100, 20]]),
            $this->segment('Skip to content', [], [[-9999, 0, 100, 20]]),
            $this->segment('Slide two', [], [[400, 120, 100, 20]], ['clip' => ['x' => 0, 'right' => 300, 'y' => null, 'bottom' => null]]),
            $this->segment("\u{E934}\u{E934}", [], [[0, 150, 20, 20]]),
        ])]);

        self::assertSame('Visible', $screen->texts[0]->content);
        self::assertSame([
            'Display none' => ExclusionReason::Hidden,
            'Visibility hidden' => ExclusionReason::Hidden,
            'Faded' => ExclusionReason::Transparent,
            'Transparent paint' => ExclusionReason::Transparent,
            'Skip to content' => ExclusionReason::Clipped,
            'Slide two' => ExclusionReason::Clipped,
            "\u{E934}\u{E934}" => ExclusionReason::Icon,
        ], array_combine(array_map(static fn($e) => $e->content, $screen->excluded), array_map(static fn($e) => $e->reason, $screen->excluded)));
    }

    public function testSpaceWithoutABoxAtALineWrapKeepsWordsApart(): void
    {
        // <span>Hello</span> <span>World</span> wrapping at the space: the space node has no rect.
        $screen = $this->map([$this->block('b0', [
            $this->segment('Hello', [], [[0, 0, 50, 20]]),
            ['text' => ' ', 'style' => null],
            $this->segment('World', [], [[0, 20, 50, 20]]),
        ])]);

        self::assertSame('Hello World', $screen->texts[0]->content);
        self::assertSame(2, $screen->texts[0]->lineCount);
    }

    public function testTextFillColorPaintsTheGlyphs(): void
    {
        $screen = $this->map([$this->block('b0', [
            $this->segment('Filled', ['color' => 'rgb(51, 51, 51)', 'textFillColor' => 'rgb(1, 42, 77)'], [[0, 0, 50, 20]]),
        ])]);

        self::assertSame('#012A4D', $screen->texts[0]->dominantStyle()->color?->toHex());
    }

    public function testGradientTextHasNoSolidColor(): void
    {
        $screen = $this->map([$this->block('b0', [
            $this->segment('Gradient', ['color' => 'rgb(0, 0, 0)', 'textFillColor' => 'rgba(0, 0, 0, 0)', 'backgroundClip' => 'text'], [[0, 0, 50, 20]]),
        ])]);

        self::assertCount(1, $screen->texts);
        self::assertNull($screen->texts[0]->dominantStyle()->color);
    }

    public function testModernColorSyntaxUsesTheBrowsersSrgbConversion(): void
    {
        $screen = $this->map([$this->block('b0', [
            $this->segment('Oklch', ['color' => 'oklch(0.3 0.1 250)', 'colorSrgb' => [1, 42, 77, 1], 'textFillColor' => 'oklch(0.3 0.1 250)', 'textFillColorSrgb' => [1, 42, 77, 1]], [[0, 0, 50, 20]]),
        ])]);

        self::assertSame('#012A4D', $screen->texts[0]->dominantStyle()->color?->toHex());
    }

    public function testBlocksWithoutVisibleTextAreDropped(): void
    {
        $screen = $this->map([$this->block('b0', [['text' => '   ', 'style' => null]])]);

        self::assertSame([], $screen->texts);
    }

    public function testRejectsUnexpectedResult(): void
    {
        $this->expectException(SourceException::class);
        (new PageSnapshotMapper())->map('boom', new ScreenSpec('S', 390));
    }

    /**
     * @param list<array<mixed>> $blocks
     */
    private function map(array $blocks): \DesignQa\Domain\Model\Screen
    {
        return (new PageSnapshotMapper())->map(['blocks' => $blocks, 'documentHeight' => 7000], new ScreenSpec('390_Mobile', 390));
    }

    /**
     * @param list<array<mixed>> $segments
     *
     * @return array<mixed>
     */
    private function block(string $id, array $segments): array
    {
        return ['id' => $id, 'tag' => 'p', 'path' => ['section.hero', 'p'], 'segments' => $segments];
    }

    /**
     * @param array<string, mixed>                                    $style
     * @param list<array{int|float, int|float, int|float, int|float}> $rects
     * @param array<string, mixed>                                    $segment visibility, opacity, clip overrides
     *
     * @return array<mixed>
     */
    private function segment(string $text, array $style, array $rects, array $segment = []): array
    {
        return [
            'id' => 't' . crc32($text),
            'text' => $text,
            'rects' => array_map(static fn(array $r): array => ['x' => $r[0], 'y' => $r[1], 'width' => $r[2], 'height' => $r[3]], $rects),
            'visibility' => 'visible',
            'opacity' => 1,
            'clip' => ['x' => 0, 'right' => 390, 'y' => null, 'bottom' => null],
            ...$segment,
            'style' => [
                'fontFamily' => '"Plus Jakarta Sans", sans-serif',
                'fontWeight' => '400',
                'fontSize' => '18px',
                'fontStyle' => 'normal',
                'color' => 'rgb(71, 85, 105)',
                'textFillColor' => $style['color'] ?? 'rgb(71, 85, 105)',
                'letterSpacing' => 'normal',
                'lineHeight' => '30.6px',
                'textTransform' => 'none',
                ...$style,
            ],
        ];
    }
}
