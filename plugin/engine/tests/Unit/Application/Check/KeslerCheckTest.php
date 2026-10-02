<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Application\Check;

use DesignQa\Application\Check\CheckReport;
use DesignQa\Application\Check\RunCheck;
use DesignQa\Application\Port\DesignSource;
use DesignQa\Application\Port\PageSource;
use DesignQa\Application\Read\ReadDesignAndPage;
use DesignQa\Application\Read\ReadRequest;
use DesignQa\Domain\Check\CheckRegistry;
use DesignQa\Domain\Check\PresenceRules;
use DesignQa\Domain\Check\RuleSet;
use DesignQa\Domain\Check\StyleComparator;
use DesignQa\Domain\Matching\PositionalTextMatcher;
use DesignQa\Domain\Model\Design;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Infrastructure\Chrome\PageSnapshotMapper;
use DesignQa\Infrastructure\Chrome\RawCaptureFile;
use DesignQa\Infrastructure\Figma\Parser\FigmaDocumentParser;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The answer key: the full check of the recorded Kesler design and page must find exactly the
 * issues verified by hand (2026-09-29), and none of the old figma-checker's false ones.
 */
#[CoversNothing]
final class KeslerCheckTest extends TestCase
{
    private const EXPECTED_CRITICAL = [
        'About the book|color|#003867|#94A3B8' => ['1920_Desktop', '1024_Tablet', '800_Tablet', '390_Mobile'],
        'What readers are saying|color|#27364D|#012A4D' => ['1920_Desktop', '1024_Tablet', '800_Tablet', '390_Mobile'],
        'Leave a review|color|#FFFFFF|#003867' => ['1920_Desktop', '1024_Tablet', '800_Tablet', '390_Mobile'],
        '250+ Best Practices for B2B Marketing Success|font-weight|Bold (700)|SemiBold (600)' => ['1920_Desktop', '1024_Tablet', '800_Tablet'],
        '150+ Best Practices for B2B Marketing Success: Ne|font-weight|Bold (700)|SemiBold (600)' => ['1920_Desktop', '1024_Tablet', '800_Tablet'],
        'The Invisible Buyer teaches B2B demand generation|font-style|Italic|Not italic' => ['1920_Desktop'],
        'Alexander Kesler|font-weight|Bold (700)|SemiBold (600)' => ['390_Mobile'],
        'Worksheets|font-weight|Bold (700)|SemiBold (600)' => ['390_Mobile'],
        'What readers are saying|font-weight|Bold (700)|SemiBold (600)' => ['390_Mobile'],
        'Previous books|font-weight|Bold (700)|SemiBold (600)' => ['390_Mobile'],
        'Most B2B decisions are shaped away from vendor vi|font-weight|SemiBold (600)|Bold (700)' => ['390_Mobile'],
        // Wording (verified on the page 2026-09-29).
        'Title, Company|text|“Title, Company”|“Title. Company”' => ['1920_Desktop', '1024_Tablet', '800_Tablet', '390_Mobile'],
        'The Invisible Buyer’s Appendices equip you with t|text|“The Invisible Buyer’s Appendices equip you with…”|“Comprising the book’s Appendices, these worksheets equip you with…”' => ['1920_Desktop', '1024_Tablet', '800_Tablet', '390_Mobile'],
        // Form placeholder (verified on the page 2026-09-29): the email field is "Work Email*".
        'Company Email *|text|“Company Email *”|“Work Email*”' => ['1920_Desktop', '1024_Tablet', '800_Tablet', '390_Mobile'],
        // Mobile menu opened on the page (verified 2026-09-29): the typo and the link color.
        'Worksheets|text|“Worksheets”|“Woorksheets”' => ['390_Mobile Menu'],
        'About|color|#012A4D|#003867' => ['390_Mobile Menu'],
        'Author|color|#012A4D|#003867' => ['390_Mobile Menu'],
        'Worksheets|color|#012A4D|#003867' => ['390_Mobile Menu'],
        'Reviews|color|#012A4D|#003867' => ['390_Mobile Menu'],
    ];

    private static ?CheckReport $report = null;

    public function testFindsExactlyTheVerifiedCriticalIssues(): void
    {
        self::assertEquals(self::EXPECTED_CRITICAL, self::grouped('critical'));
    }

    public function testBodyLineHeightIsNonCritical(): void
    {
        $nonCritical = self::grouped('non-critical');

        // Only Desktop and Mobile here: the 1.4px gap is within the Tablet screens' widened
        // tolerance for a CSS clamp() fluid line height, which only interpolates on Tablet.
        self::assertSame(['1920_Desktop', '390_Mobile'], $nonCritical['Revenue leaders worldwide are using The Invisible|line-height|32px|30.6px'] ?? null);
        foreach (array_keys($nonCritical) as $key) {
            self::assertMatchesRegularExpression('/\|(line-height|letter-spacing)\|/', $key);
        }
        self::assertSame(['1920_Desktop', '1024_Tablet', '800_Tablet', '390_Mobile'], $nonCritical['Most B2B decisions are shaped away from vendor vi|letter-spacing|0px|-1px'] ?? null);
    }

    public function testNoneOfTheOldCheckersFalseResults(): void
    {
        foreach (self::report()->screens as $screen) {
            foreach ($screen->findings as $finding) {
                $text = $finding->text();
                self::assertFalse(in_array($text, ['Audit frameworks', 'Signal and content checklists', 'Playbooks and SOPs', 'Sample SLA and KPI dashboards'], true) && $finding->checkId === 'font-weight', 'mixed-style weight');
                self::assertFalse($text === 'User Name' && $finding->checkId === 'color', 'override color');
                self::assertNotSame('font-size', $finding->checkId, sprintf('font size on %s: %s', $screen->name(), $text));
                self::assertFalse($finding->checkId === 'line-height' && !$finding->match->design->text->isMultiLine(), 'single-line line height');
            }
        }
    }

    public function testItalicReportsOnlyTheAffectedWords(): void
    {
        foreach (self::report()->screens[0]->findings as $finding) {
            if ($finding->checkId === 'font-style') {
                self::assertStringStartsWith('teaches B2B demand generation', (string) $finding->part);

                return;
            }
        }
        self::fail('Italic finding missing');
    }

    public function testCoverageAndHonestLeftovers(): void
    {
        $report = self::report();

        self::assertSame(239, $report->designTexts());
        self::assertGreaterThanOrEqual(190, $report->comparedTexts());
        self::assertSame([], $report->skipped, 'every Figma size is checked, the mobile menu too');
        $notFound = array_map(static fn($p) => $p->content(), $report->screens[0]->matching->unmatchedDesign);
        self::assertContains('Company*', $notFound);
    }

    public function testEveryDesignTextHasExactlyOneStatus(): void
    {
        foreach (self::report()->screens as $screen) {
            $statuses = $screen->statuses();

            self::assertSame(array_map(static fn($t) => $t->id, $screen->design->texts), array_map('strval', array_keys($statuses)), $screen->name());
            self::assertSame($screen->designTexts(), array_sum($screen->statusCounts()));
        }
        $desktop = self::report()->screens[0]->statusCounts();
        self::assertSame(5, $desktop['symbol'], '3 review quote marks and 2 slider arrows');
        self::assertGreaterThan(30, $desktop['ok']);
    }

    /**
     * @return array<string, list<string>> "text|check|figma|page" => screens
     */
    private static function grouped(string $severity): array
    {
        $grouped = [];
        foreach (self::report()->screens as $screen) {
            foreach ($screen->findings as $finding) {
                if ($finding->severity->value !== $severity) {
                    continue;
                }
                $key = implode('|', [mb_substr($finding->text(), 0, 49), $finding->checkId, $finding->difference->design, $finding->difference->page]);
                if (!in_array($screen->name(), $grouped[$key] ?? [], true)) {
                    $grouped[$key][] = $screen->name();
                }
            }
        }

        return $grouped;
    }

    private static function report(): CheckReport
    {
        if (self::$report !== null) {
            return self::$report;
        }
        $fixtures = dirname(__DIR__, 3) . '/Fixtures/kesler';

        $design = new class ($fixtures) implements DesignSource {
            public function __construct(private string $fixtures) {}

            public function load(string $designUrl): Design
            {
                $response = json_decode((string) file_get_contents($this->fixtures . '/figma-nodes.json'), true, flags: JSON_THROW_ON_ERROR);

                return (new FigmaDocumentParser())->parse(is_array($response) ? $response : [], '2421:637');
            }
        };
        $pages = new class ($fixtures) implements PageSource {
            public function __construct(private string $fixtures) {}

            public function capture(string $pageUrl, array $specs): array
            {
                return array_map(fn(ScreenSpec $spec) => (new PageSnapshotMapper())->map(
                    json_decode((string) file_get_contents($this->fixtures . '/' . RawCaptureFile::name($spec)), true, flags: JSON_THROW_ON_ERROR),
                    $spec,
                ), $specs);
            }
        };

        $rules = RuleSet::fromArray(require dirname(__DIR__, 4) . '/config/rules.php');
        $check = new RunCheck(
            new ReadDesignAndPage($design, $pages),
            new PositionalTextMatcher(),
            new StyleComparator(CheckRegistry::standard()->configured($rules)),
            PresenceRules::fromRuleSet($rules),
        );

        return self::$report = $check->execute(new ReadRequest('https://invisible-buyer.kesler.com/', 'figma'));
    }
}
