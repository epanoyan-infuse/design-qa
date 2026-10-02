<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Figma\Parser;

use DesignQa\Application\Port\SourceException;
use DesignQa\Domain\Model\ExcludedText;
use DesignQa\Domain\Model\ExclusionReason;
use DesignQa\Domain\Model\ScreenKind;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Infrastructure\Figma\Parser\FigmaDocumentParser;
use DesignQa\Infrastructure\Figma\Parser\FigmaTextCollector;
use DesignQa\Infrastructure\Figma\Parser\FigmaWalkState;
use DesignQa\Infrastructure\Figma\Parser\ScreenDetector;
use DesignQa\Tests\Support\FigmaNodes as N;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FigmaDocumentParser::class)]
#[CoversClass(FigmaTextCollector::class)]
#[CoversClass(FigmaWalkState::class)]
#[CoversClass(ScreenDetector::class)]
final class FigmaDocumentParserTest extends TestCase
{
    public function testSectionChildrenBecomeScreensWidestFirstMenuLast(): void
    {
        $design = $this->parse(N::section('Adaptive Designs', [
            N::frame('390_Mobile Menu', 1500, 0, 390, 796),
            N::frame('390_Mobile', 1000, 0, 390, 7700),
            N::frame('1920_Desktop', -3000, 0, 1920, 6700),
            N::frame('Hidden', 0, 0, 800, 100, extra: ['visible' => false]),
        ]));

        self::assertSame(['1920_Desktop', '390_Mobile', '390_Mobile Menu'], array_map(static fn($s) => $s->name(), $design->screens));
        self::assertSame([1920, 390, 390], array_map(static fn($s) => $s->width(), $design->screens));
        self::assertSame(ScreenKind::Menu, $design->screens[2]->spec->kind);
        self::assertSame('Test file / Adaptive Designs', $design->name);
    }

    public function testFrameLinkIsOneScreen(): void
    {
        $design = $this->parse(N::frame('1920_Desktop', 0, 0, 1920, 100, [N::text('t1', 'Hello', 10, 10)]));

        self::assertCount(1, $design->screens);
        self::assertSame('Hello', $design->screens[0]->texts[0]->content);
    }

    public function testPositionsAreRelativeToTheScreenAndSortedTopToBottom(): void
    {
        $design = $this->parse(N::frame('S', 1000, 500, 400, 300, [
            N::text('b', 'Second', 1010, 700),
            N::text('a', 'First', 1020, 520),
        ]));
        $texts = $design->screens[0]->texts;

        self::assertSame(['First', 'Second'], array_map(static fn(TextElement $t) => $t->content, $texts));
        self::assertSame([20.0, 20.0], [$texts[0]->box->x, $texts[0]->box->y]);
    }

    public function testHiddenTransparentAndClippedTextsAreExcludedWithReason(): void
    {
        $design = $this->parse(N::frame('390_Mobile Menu', 0, 0, 390, 796, [
            N::text('visible', 'About', 10, 100),
            N::text('hidden', 'Review title', 10, 200, extra: ['visible' => false]),
            N::frame('Group', 0, 300, 390, 100, [N::text('in-hidden-parent', '★★★★★', 10, 310)], ['visible' => false, 'clipsContent' => false]),
            N::text('transparent', 'Ghost', 10, 400, extra: ['opacity' => 0]),
            N::text('clipped', 'Below the fold', 10, 900),
        ]));
        $screen = $design->screens[0];

        self::assertSame(['About'], array_map(static fn(TextElement $t) => $t->content, $screen->texts));
        self::assertSame([
            'hidden' => ExclusionReason::Hidden,
            'in-hidden-parent' => ExclusionReason::Hidden,
            'transparent' => ExclusionReason::Transparent,
            'clipped' => ExclusionReason::Clipped,
        ], $this->reasons($screen->excluded));
    }

    public function testNestedClippingFrameCutsOffTexts(): void
    {
        // A slider: only the first slide is inside the clipping container.
        $design = $this->parse(N::frame('S', 0, 0, 1000, 500, [
            N::frame('Slider', 0, 0, 400, 100, [
                N::text('slide-1', 'Review one', 10, 10),
                N::text('slide-2', 'Review two', 450, 10),
            ]),
        ]));

        self::assertSame(['Review one'], array_map(static fn(TextElement $t) => $t->content, $design->screens[0]->texts));
        self::assertSame(['slide-2' => ExclusionReason::Clipped], $this->reasons($design->screens[0]->excluded));
    }

    public function testTextUnderAnOpaqueLayerPaintedLaterIsCovered(): void
    {
        // The Kesler menu: the page content lies under a white menu panel painted on top.
        $white = [N::solid(1, 1, 1)];
        $design = $this->parse(N::frame('390_Mobile Menu', 0, 0, 390, 796, [
            N::frame('App', 0, 0, 390, 796, [N::text('hero', 'The Invisible Buyer', 10, 200)], ['clipsContent' => false]),
            N::frame('Menu', 0, 65, 390, 731, [N::text('item', 'About', 10, 100)], ['fills' => $white]),
        ]));
        $screen = $design->screens[0];

        self::assertSame(['About'], array_map(static fn(TextElement $t) => $t->content, $screen->texts));
        self::assertSame(['hero' => ExclusionReason::Covered], $this->reasons($screen->excluded));
    }

    public function testTranslucentLayerOnTopDoesNotCover(): void
    {
        $design = $this->parse(N::frame('S', 0, 0, 390, 796, [
            N::text('hero', 'Title', 10, 200),
            N::frame('Overlay', 0, 0, 390, 796, [], ['fills' => [N::solid(0, 0, 0, 0.4)]]),
        ]));

        self::assertCount(1, $design->screens[0]->texts);
    }

    public function testBackgroundBehindTextDoesNotCoverIt(): void
    {
        $design = $this->parse(N::frame('S', 0, 0, 390, 796, [
            N::frame('Button', 0, 0, 200, 50, [N::text('label', 'Get the book', 10, 10)], ['fills' => [N::solid(0, 0, 0)]]),
        ]));

        self::assertCount(1, $design->screens[0]->texts);
    }

    public function testIconGlyphTextIsExcludedLikeOnThePage(): void
    {
        $design = $this->parse(N::frame('S', 0, 0, 390, 796, [
            N::text('icon', "\u{E900}", 10, 10, 20, 20),
            N::text('label', 'Next', 40, 10),
        ]));

        self::assertSame(['Next'], array_map(static fn(TextElement $t) => $t->content, $design->screens[0]->texts));
        self::assertSame(['icon' => ExclusionReason::Icon], $this->reasons($design->screens[0]->excluded));
    }

    public function testMaskIsNotPaintedAndClipsTheSiblingsAboveIt(): void
    {
        $white = [N::solid(1, 1, 1)];
        $design = $this->parse(N::frame('S', 0, 0, 390, 796, [
            N::frame('Group', 0, 0, 390, 796, [
                N::text('under-mask', 'Under the mask', 10, 10),
                ['id' => 'm', 'name' => 'Mask', 'type' => 'RECTANGLE', 'isMask' => true, 'fills' => $white, 'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 100]],
                N::text('inside', 'Inside the mask', 10, 50),
                N::text('outside', 'Outside the mask', 250, 50),
            ], ['clipsContent' => false]),
        ]));

        self::assertSame(['Under the mask', 'Inside the mask'], array_map(static fn(TextElement $t) => $t->content, $design->screens[0]->texts));
        self::assertSame(['outside' => ExclusionReason::Clipped], $this->reasons($design->screens[0]->excluded));
    }

    public function testRoundShapesAndBlendedLayersDoNotCover(): void
    {
        $design = $this->parse(N::frame('S', 0, 0, 390, 796, [
            N::text('a', 'Under an ellipse', 10, 10),
            N::text('b', 'Under a multiply layer', 10, 100),
            ['id' => 'e', 'name' => 'Blob', 'type' => 'ELLIPSE', 'fills' => [N::solid(1, 1, 1)], 'absoluteBoundingBox' => ['x' => 0, 'y' => 0, 'width' => 300, 'height' => 50]],
            N::frame('Tint', 0, 90, 390, 50, [], ['fills' => [N::solid(1, 1, 0)], 'blendMode' => 'MULTIPLY']),
        ]));

        self::assertCount(2, $design->screens[0]->texts);
    }

    public function testAutoLineHeightStillCountsLines(): void
    {
        $design = $this->parse(N::frame('S', 0, 0, 390, 796, [N::text('p', 'Auto line height', 0, 0, 300, 72, [
            'style' => ['fontFamily' => 'A', 'fontSize' => 20, 'lineHeightPx' => 24, 'lineHeightUnit' => 'INTRINSIC_%'],
        ])]));
        $text = $design->screens[0]->texts[0];

        self::assertNull($text->dominantStyle()->lineHeight, 'auto line height is not compared');
        self::assertSame(3, $text->lineCount);
    }

    public function testLineCountFromBoxHeightAndLineHeight(): void
    {
        $design = $this->parse(N::frame('S', 0, 0, 390, 796, [N::text('p', 'Long paragraph', 0, 0, 300, 96)]));

        self::assertSame(5, $design->screens[0]->texts[0]->lineCount); // 96 / 20px line height ≈ 5
    }

    public function testMissingNodeOrNoScreensIsAClearError(): void
    {
        $parser = new FigmaDocumentParser();

        try {
            $parser->parse(['nodes' => []], '1:2');
            self::fail('Expected SourceException');
        } catch (SourceException $e) {
            self::assertStringContainsString('did not return node 1:2', $e->getMessage());
        }

        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('contains no screens');
        $parser->parse(N::response('1:2', ['id' => '1:2', 'name' => 'Logo', 'type' => 'VECTOR']), '1:2');
    }

    /**
     * @param array<mixed> $document
     */
    private function parse(array $document): \DesignQa\Domain\Model\Design
    {
        return (new FigmaDocumentParser())->parse(N::response('9:9', $document), '9:9');
    }

    /**
     * @param list<ExcludedText> $excluded
     *
     * @return array<string, ExclusionReason>
     */
    private function reasons(array $excluded): array
    {
        $reasons = [];
        foreach ($excluded as $e) {
            $reasons[$e->id] = $e->reason;
        }

        return $reasons;
    }
}
