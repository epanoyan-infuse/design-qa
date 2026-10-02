<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Figma\Parser;

use DesignQa\Domain\Model\TextCase;
use DesignQa\Infrastructure\Figma\Parser\FigmaNode;
use DesignQa\Infrastructure\Figma\Parser\FigmaPaint;
use DesignQa\Infrastructure\Figma\Parser\FigmaStyleResolver;
use DesignQa\Tests\Support\FigmaNodes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FigmaStyleResolver::class)]
#[CoversClass(FigmaPaint::class)]
#[CoversClass(FigmaNode::class)]
final class FigmaStyleResolverTest extends TestCase
{
    private FigmaStyleResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new FigmaStyleResolver();
    }

    public function testOverrideCoveringTheWholeTextWinsOverBaseStyle(): void
    {
        // Regression: the old checker reported "Audit frameworks" as weight 400 (base) instead of 700.
        $node = new FigmaNode(FigmaNodes::text('1', 'Audit frameworks', 0, 0, extra: [
            'characterStyleOverrides' => array_fill(0, 16, 1),
            'styleOverrideTable' => ['1' => ['fontWeight' => 700]],
        ]));

        $runs = $this->resolver->runs($node);

        self::assertCount(1, $runs);
        self::assertSame(700, $runs[0]->style->fontWeight);
        self::assertSame(16, $runs[0]->length);
    }

    public function testOverrideFillsChangeTheColor(): void
    {
        // Regression: "User Name" base fill #003867, override fill #012A4D.
        $node = new FigmaNode(FigmaNodes::text('1', 'User Name', 0, 0, extra: [
            'characterStyleOverrides' => array_fill(0, 9, 7),
            'styleOverrideTable' => ['7' => ['fills' => [FigmaNodes::solid(1 / 255, 42 / 255, 77 / 255)]]],
        ]));

        self::assertSame('#012A4D', $this->resolver->runs($node)[0]->style->color?->toHex());
    }

    public function testPartialOverrideProducesSeparateRuns(): void
    {
        $overrides = [...array_fill(0, 3, 0), ...array_fill(0, 4, 2)];
        $node = new FigmaNode(FigmaNodes::text('1', 'Buy now', 0, 0, extra: [
            'characterStyleOverrides' => $overrides,
            'styleOverrideTable' => ['2' => ['italic' => true]],
        ]));

        $runs = $this->resolver->runs($node);

        self::assertCount(2, $runs);
        self::assertSame([0, 3, false], [$runs[0]->start, $runs[0]->length, $runs[0]->style->italic]);
        self::assertSame([3, 4, true], [$runs[1]->start, $runs[1]->length, $runs[1]->style->italic]);
    }

    public function testShortOverrideListMeansBaseStyleForTheRest(): void
    {
        // Figma omits trailing zeros: the Kesler paragraph has 283 overrides for 403 characters.
        $node = new FigmaNode(FigmaNodes::text('1', 'abcdef', 0, 0, extra: [
            'characterStyleOverrides' => [5, 5],
            'styleOverrideTable' => ['5' => ['fontWeight' => 700]],
        ]));

        $runs = $this->resolver->runs($node);

        self::assertSame([[0, 2, 700], [2, 4, 400]], array_map(static fn($r): array => [$r->start, $r->length, $r->style->fontWeight], $runs));
    }

    public function testOverridesAreIndexedInUtf16CodeUnits(): void
    {
        // "😀" is one code point but two UTF-16 units, so "ab" starts at unit 2.
        $node = new FigmaNode(FigmaNodes::text('1', '😀ab', 0, 0, extra: [
            'characterStyleOverrides' => [0, 0, 3, 3],
            'styleOverrideTable' => ['3' => ['fontWeight' => 700]],
        ]));

        $runs = $this->resolver->runs($node);

        self::assertSame([[0, 1, 400], [1, 2, 700]], array_map(static fn($r): array => [$r->start, $r->length, $r->style->fontWeight], $runs));
    }

    public function testOverrideEqualToBaseMergesIntoOneRun(): void
    {
        $node = new FigmaNode(FigmaNodes::text('1', 'abcd', 0, 0, extra: [
            'characterStyleOverrides' => [0, 0, 4, 4],
            'styleOverrideTable' => ['4' => ['fontWeight' => 400]],
        ]));

        self::assertCount(1, $this->resolver->runs($node));
    }

    public function testStyleDetails(): void
    {
        $node = new FigmaNode(FigmaNodes::text('1', 'New Book', 0, 0, extra: [
            'style' => ['fontFamily' => 'Plus Jakarta Sans', 'fontWeight' => 700, 'fontSize' => 11, 'letterSpacing' => 2, 'lineHeightPx' => 17, 'textCase' => 'UPPER'],
            'fills' => [FigmaNodes::solid(1, 0, 0, opacity: 0.5)],
        ]));

        $style = $this->resolver->runs($node, 0.8)[0]->style;

        self::assertSame(['Plus Jakarta Sans', 700, 11.0, 2.0, 17.0, TextCase::Upper], [$style->fontFamily, $style->fontWeight, $style->fontSize, $style->letterSpacing, $style->lineHeight, $style->textCase]);
        self::assertSame('#FF0000 40%', $style->color?->toHex(), 'paint opacity x layer opacity');
    }

    public function testAutoLineHeightIsNull(): void
    {
        $node = new FigmaNode(FigmaNodes::text('1', 'x', 0, 0, extra: [
            'style' => ['fontFamily' => 'A', 'fontSize' => 10, 'lineHeightPx' => 12.1, 'lineHeightUnit' => 'INTRINSIC_%'],
        ]));

        self::assertNull($this->resolver->runs($node)[0]->style->lineHeight);
    }

    public function testGradientTextHasNoSolidColor(): void
    {
        $node = new FigmaNode(FigmaNodes::text('1', 'x', 0, 0, extra: ['fills' => [['type' => 'GRADIENT_LINEAR']]]));

        self::assertNull($this->resolver->runs($node)[0]->style->color);
    }

    public function testTopmostVisiblePaintWins(): void
    {
        $fills = [FigmaNodes::solid(1, 0, 0), FigmaNodes::solid(0, 0, 1), [...FigmaNodes::solid(0, 1, 0), 'visible' => false]];

        self::assertSame('#0000FF', FigmaPaint::topSolidColor($fills)?->toHex());
    }
}
