<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Output\Html;

use DateTimeImmutable;
use DesignQa\Application\Check\CheckReport;
use DesignQa\Application\Check\ScreenResult;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Check\Finding;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\MatchKind;
use DesignQa\Domain\Matching\MatchResult;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Model\Rect;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Domain\Model\StyleRun;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Output\Html\DeviceType;
use DesignQa\Output\Html\HtmlReportWriter;
use DesignQa\Output\Html\ReportViewBuilder;
use DesignQa\Output\Html\SectionResolver;
use DesignQa\Tests\Support\Styles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlReportWriter::class)]
#[CoversClass(ReportViewBuilder::class)]
#[CoversClass(SectionResolver::class)]
#[CoversClass(DeviceType::class)]
final class HtmlReportTest extends TestCase
{
    public function testDeviceTypesFollowElementorBreakpoints(): void
    {
        self::assertSame(DeviceType::Mobile, DeviceType::forWidth(390));
        self::assertSame(DeviceType::Mobile, DeviceType::forWidth(767));
        self::assertSame(DeviceType::Tablet, DeviceType::forWidth(768));
        self::assertSame(DeviceType::Tablet, DeviceType::forWidth(1024));
        self::assertSame(DeviceType::Desktop, DeviceType::forWidth(1025));
    }

    public function testThreeDeviceTabsAndSizeSubTabsOnlyWhereThereAreSeveralSizes(): void
    {
        $html = $this->render([1920, 1024, 800, 390]);

        self::assertSame(3, substr_count($html, 'class="device-tab"'));
        self::assertSame(2, substr_count($html, 'class="size-tab"'), '1024 and 800 under Tablet');
        self::assertStringContainsString('aria-label="Tablet size"', $html);
        self::assertStringNotContainsString('aria-label="Mobile size"', $html);
        self::assertStringContainsString('Not matching yet: 1 critical issue', $html);
        self::assertStringContainsString('Also on Tablet 1024px, Tablet 800px, Mobile 390px', $html);
        // Inside each size: result views as tabs (Issues, Don't match, Missing, Extra, Check by
        // hand, Found but moved), page sections as accordions (first one open), grouped by section
        // or by property (each grouping opens its own first accordion).
        self::assertSame(4 * 6, substr_count($html, 'class="view-tab"'));
        self::assertSame(4 * 2, substr_count($html, '<details class="page-section" open>'));
    }

    public function testTextsFromThePageCannotInjectHtml(): void
    {
        $html = $this->render([390], '<script>alert("page")</script> <img src=x onerror=alert(1)>');

        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;page&quot;)&lt;/script&gt;', $html);
        // The embedded data cannot close its <script> element either.
        self::assertMatchesRegularExpression('~<script type="application/json" id="design-qa-data">[^<]*</script>~', $html);
        self::assertStringContainsString("Content-Security-Policy\" content=\"default-src 'none'", $html);
    }

    public function testNoCriticalIssuesIsSaidPlainly(): void
    {
        $html = $this->render([1920], null, withFinding: false);

        self::assertStringContainsString('No critical issues', $html);
        self::assertStringContainsString('No differences on this size.', $html);
    }

    public function testSectionsUseRealLayerNamesOrTheLargestText(): void
    {
        $text = self::sectionText(...);
        $screen = new Screen(new ScreenSpec('S', 390), 1000, [
            $text('a', 'Welcome', ['App', 'Hero', 'Frame 1'], 10),
            $text('b', 'Your buyers are already choosing', ['App', 'Frame 2147225107'], 100, 44),
            $text('c', 'Small print', ['App', 'Frame 2147225107'], 150),
            $text('d', 'Old titles', ['App', 'Section - Previous publications'], 200),
            $text('e', 'Second hero line', ['App', 'Hero'], 300),
        ]);

        self::assertSame(
            // The second "Hero" is a separate section further down: sections are told apart by position.
            ['a' => [0, 'Hero'], 'b' => [1, 'Your buyers are already choosing'], 'c' => [1, 'Your buyers are already choosing'], 'd' => [2, 'Previous publications'], 'e' => [3, 'Hero']],
            (new SectionResolver())->sections($screen),
        );
    }

    public function testSectionsWithDefaultNamesInsideANamedWrapperAreNotMerged(): void
    {
        $text = self::sectionText(...);
        $screen = new Screen(new ScreenSpec('S', 390), 1000, [
            $text('a', 'First headline', ['Content', 'Frame 2147225107'], 10, 40),
            $text('b', 'Second headline', ['Content', 'Frame 2147225108'], 100, 40),
            $text('c', 'Third headline', ['Content', 'Frame 2147225109'], 200, 40),
        ]);

        self::assertSame(
            ['a' => [0, 'First headline'], 'b' => [1, 'Second headline'], 'c' => [2, 'Third headline']],
            (new SectionResolver())->sections($screen),
        );
    }

    public function testSiblingLayersWithTheSameNameAreSeparateSections(): void
    {
        $style = new \DesignQa\Domain\Model\TextStyle('A', 400, 18, false, Styles::body()->color, 0, 20);
        $text = static fn(string $id, string $content, string $layerId, float $y): TextElement => new TextElement(
            $id,
            $content,
            [new StyleRun(0, mb_strlen($content), $style)],
            new Rect(0, $y, 100, 20),
            1,
            ['App', 'Container:margin'],
            ['1:1', $layerId],
        );
        $screen = new Screen(new ScreenSpec('S', 800), 1000, [
            $text('a', 'Premise text', '1:2', 10),
            $text('b', 'Author text', '1:3', 100),
            $text('c', 'Other', '1:4', 200),
        ]);

        self::assertSame([0, 1, 2], array_map(static fn(array $s): int => $s[0], array_values((new SectionResolver())->sections($screen))));
    }

    public function testNumericSectionLabelsDoNotBreakTheReport(): void
    {
        // A section named after its largest text "500": PHP would turn it into an int key.
        $html = $this->render([390], '500');

        self::assertStringContainsString('<span>500</span>', $html);
    }

    public function testTwoScreensOfTheSameWidthStayDistinguishable(): void
    {
        $html = $this->render([390, 390]);

        self::assertStringContainsString('390px (390)', $html);
        self::assertStringContainsString('data-link="390-0"', $html);
        self::assertStringContainsString('data-link="390-1"', $html);
        self::assertStringContainsString('Also on Mobile 390px (390)', $html);
    }

    public function testBadgesAreReadableWithoutColor(): void
    {
        $html = $this->render([1920]);

        self::assertMatchesRegularExpression('~<span class="tab-count tab-count--critical">1<span class="visually-hidden"> critical</span></span>~', $html);
    }

    public function testTabRolesComeFromTheScriptSoThePageWorksWithoutIt(): void
    {
        $html = $this->render([1024, 800]);
        $markup = (string) preg_replace('~<(script|style)[^>]*>.*?</\1>~s', '', $html);

        self::assertStringNotContainsString('role="tab', $markup, 'roles are added by report.js');
        self::assertStringContainsString('data-tabs', $html);
        self::assertStringContainsString("<h3 class=\"view-title\">Texts that don't match the design</h3>", $html, 'every view keeps a heading (no JS, print)');
    }

    public function testOnlyTheReportsOwnScriptMayRun(): void
    {
        $html = $this->render([390]);

        preg_match('~<script>(.*)</script>\s*</body>~s', $html, $script);
        preg_match("~script-src 'sha256-([^']+)'~", $html, $hash);
        self::assertSame(base64_encode(hash('sha256', $script[1] ?? '', true)), $hash[1] ?? null);
        self::assertStringContainsString("base-uri 'none'; form-action 'none'", $html);
        self::assertStringNotContainsString("script-src 'unsafe-inline'", $html);
    }

    public function testInvalidUtf8InATextDoesNotBreakTheReport(): void
    {
        $html = $this->render([390], "Caf\xC3");

        self::assertStringContainsString('Caf', $html);
    }

    /**
     * @param list<string> $path
     */
    private static function sectionText(string $id, string $content, array $path, float $y, float $size = 18): TextElement
    {
        $style = new \DesignQa\Domain\Model\TextStyle('A', 400, $size, false, Styles::body()->color, 0, 20);

        return new TextElement($id, $content, [new StyleRun(0, mb_strlen($content), $style)], new Rect(0, $y, 100, 20), 1, $path);
    }

    /**
     * @param list<int> $widths
     */
    private function render(array $widths, ?string $pageText = null, bool $withFinding = true): string
    {
        $screens = [];
        foreach ($widths as $width) {
            $content = $pageText ?? 'About the book';
            $design = new TextElement('f' . $width, $content, [new StyleRun(0, mb_strlen($content), Styles::body())], new Rect(0, 0, 100, 20), 1, ['App', 'Hero']);
            $page = new TextElement('p' . $width, $content, [new StyleRun(0, mb_strlen($content), Styles::body())], new Rect(0, 0, 100, 20), 1, ['section', 'p.elementor-heading-title']);
            $match = new TextMatch(TextPart::whole($design), $page, MatchKind::Exact, 1.0);
            $findings = $withFinding ? [new Finding('color', 'Color', Severity::Critical, new Difference('#003867', '#94A3B8'), $match, null, 12, 12)] : [];
            $spec = new ScreenSpec((string) $width, $width);
            $screens[] = new ScreenResult(new Screen($spec, 1000, [$design]), new Screen($spec, 1000, [$page]), new MatchResult([$match], [], [], []), $findings);
        }

        return (new HtmlReportWriter(dirname(__DIR__, 4) . '/resources/report'))->render(
            new CheckReport('Kesler <Book>', 'https://invisible-buyer.kesler.com/', $screens, []),
            new DateTimeImmutable('2026-09-29 13:19'),
        );
    }
}
