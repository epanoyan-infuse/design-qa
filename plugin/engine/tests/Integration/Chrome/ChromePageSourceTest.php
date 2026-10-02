<?php

declare(strict_types=1);

namespace DesignQa\Tests\Integration\Chrome;

use DesignQa\Application\Port\SourceException;
use DesignQa\Domain\Model\ExclusionReason;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Domain\Model\TextCase;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Infrastructure\Chrome\ChromeLocator;
use DesignQa\Infrastructure\Chrome\ChromePageSource;
use DesignQa\Infrastructure\Chrome\DeviceProfile;
use DesignQa\Infrastructure\Chrome\Step\DisableAnimationsStep;
use DesignQa\Infrastructure\Chrome\Step\ScrollThroughStep;
use DesignQa\Infrastructure\Chrome\Step\WaitForFontsStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real collector script in headless Chrome against tests/Fixtures/pages/collector.html.
 */
#[Group('browser')]
#[CoversClass(ChromePageSource::class)]
#[CoversClass(DeviceProfile::class)]
#[CoversClass(DisableAnimationsStep::class)]
#[CoversClass(ScrollThroughStep::class)]
#[CoversClass(WaitForFontsStep::class)]
final class ChromePageSourceTest extends TestCase
{
    /** @var array<int, Screen>|null */
    private static ?array $screens = null;

    public function testEachWidthIsRenderedSeparately(): void
    {
        self::assertSame(40.0, self::text(1000, 'Main heading')->dominantStyle()->fontSize);
        self::assertSame(24.0, self::text(390, 'Main heading')->dominantStyle()->fontSize);
        self::assertSame(48.0, self::text(1000, 'Main heading')->dominantStyle()->lineHeight);
        self::assertSame(-1.0, self::text(1000, 'Main heading')->dominantStyle()->letterSpacing);
    }

    public function testPhoneWidthsGetAPhoneUserAgent(): void
    {
        self::assertNotNull(self::find(1000, 'Desktop agent'));
        self::assertNotNull(self::find(390, 'Phone agent'));
    }

    public function testParagraphWithInlineElementsIsOneTextWithStyleRuns(): void
    {
        $intro = self::text(1000, 'The Book');

        self::assertSame("The Book teaches everything important\nSecond line", $intro->content);
        self::assertSame(['The Book', ' teaches everything ', "important\n", 'Second line'], array_map($intro->textOf(...), $intro->runs));
        self::assertTrue($intro->runs[0]->style->italic);
        self::assertSame(700, $intro->runs[2]->style->fontWeight);
        self::assertSame(2, $intro->lineCount);
    }

    public function testRawTextCaseIsKeptWithTransform(): void
    {
        $label = self::text(1000, 'About the book');

        self::assertSame(TextCase::Upper, $label->dominantStyle()->textCase);
        self::assertSame('#003867', $label->dominantStyle()->color?->toHex());
    }

    public function testInvisibleTextsAreExcludedWithReason(): void
    {
        $excluded = [];
        foreach (self::screen(1000)->excluded as $e) {
            $excluded[$e->content] = $e->reason;
        }

        self::assertSame(ExclusionReason::Clipped, $excluded['Skip to content'] ?? null);
        self::assertSame(ExclusionReason::Hidden, $excluded['Display none text'] ?? null);
        self::assertSame(ExclusionReason::Hidden, $excluded['Visibility hidden text'] ?? null);
        self::assertSame(ExclusionReason::Transparent, $excluded['Transparent text'] ?? null);
        self::assertSame(ExclusionReason::Clipped, $excluded['Slide two'] ?? null);
        self::assertSame(ExclusionReason::Icon, $excluded["\u{E934}\u{E934}"] ?? null);
        self::assertNotNull(self::find(1000, 'Slide one'));
    }

    public function testContentShownOnScrollIsReadInItsFinalState(): void
    {
        $reveal = self::text(1000, 'Appears on scroll');

        self::assertTrue($reveal->dominantStyle()->color?->isOpaque());
    }

    public function testSpaceAtALineWrapBetweenElementsIsKept(): void
    {
        $text = self::text(390, 'Hello');

        self::assertSame('Hello World', $text->content);
        self::assertSame(2, $text->lineCount);
    }

    public function testGradientTextHasNoSolidColor(): void
    {
        self::assertNull(self::text(1000, 'Gradient heading')->dominantStyle()->color);
    }

    public function testTextFillColorWinsOverColor(): void
    {
        self::assertSame('#012A4D', self::text(1000, 'Filled text')->dominantStyle()->color?->toHex());
    }

    public function testModernColorSyntaxIsConvertedToSrgb(): void
    {
        $color = self::text(1000, 'Modern color')->dominantStyle()->color;

        self::assertNotNull($color);
        self::assertTrue($color->isOpaque());
        self::assertGreaterThan($color->blue, $color->red, 'oklch hue 30 is a red-orange');
    }

    public function testAFontThatIsNotAvailableIsDetected(): void
    {
        self::assertSame('', self::text(1000, 'Missing font')->dominantStyle()->renderedFontFamily);
        self::assertSame('NoSuchFont DesignQa', self::text(1000, 'Missing font')->dominantStyle()->fontFamily);
        self::assertSame('Georgia', self::text(1000, 'Main heading')->dominantStyle()->renderedFontFamily, 'Georgia is installed');
    }

    public function testInfiniteScrollPageStillFinishes(): void
    {
        $source = new ChromePageSource(
            new ChromeLocator(getenv()),
            self::script(),
            [new ScrollThroughStep(pauseMs: 20, settleMs: 100, timeoutMs: 20_000, maxScrollPx: 6_000)],
            allowedSchemes: ['http', 'https', 'file'],
        );

        $start = microtime(true);
        [$screen] = $source->capture('file://' . dirname(__DIR__, 2) . '/Fixtures/pages/infinite.html', [new ScreenSpec('Desktop', 1000)]);

        self::assertSame('Endless list', $screen->texts[0]->content);
        self::assertLessThan(20, microtime(true) - $start);
    }

    public function testRejectsNonWebAddresses(): void
    {
        $this->expectException(SourceException::class);
        self::source()->capture('javascript:alert(1)', [new ScreenSpec('S', 390)]);
    }

    public function testRealChecksNeverOpenLocalFiles(): void
    {
        $source = new ChromePageSource(new ChromeLocator(getenv()), self::script(), []);

        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Not a web page address');
        $source->capture('file:///etc/hosts', [new ScreenSpec('S', 390)]);
    }

    private static function source(): ChromePageSource
    {
        $locator = new ChromeLocator(getenv());
        if ($locator->locate() === null) {
            self::markTestSkipped('Google Chrome is not installed.');
        }

        return new ChromePageSource($locator, self::script(), [new DisableAnimationsStep(), new ScrollThroughStep(50, 300), new WaitForFontsStep()], allowedSchemes: ['http', 'https', 'file']);
    }

    private static function script(): string
    {
        $script = file_get_contents(dirname(__DIR__, 3) . '/resources/js/collect-texts.js');
        self::assertIsString($script);

        return $script;
    }

    private static function screen(int $width): Screen
    {
        if (self::$screens === null) {
            $url = 'file://' . dirname(__DIR__, 2) . '/Fixtures/pages/collector.html';
            [$wide, $phone] = self::source()->capture($url, [new ScreenSpec('Desktop', 1000), new ScreenSpec('Mobile', 390)]);
            self::$screens = [1000 => $wide, 390 => $phone];
        }

        return self::$screens[$width];
    }

    private static function find(int $width, string $startsWith): ?TextElement
    {
        foreach (self::screen($width)->texts as $text) {
            if (str_starts_with($text->content, $startsWith)) {
                return $text;
            }
        }

        return null;
    }

    private static function text(int $width, string $startsWith): TextElement
    {
        return self::find($width, $startsWith) ?? self::fail(sprintf('Text "%s" not found at %dpx', $startsWith, $width));
    }
}
