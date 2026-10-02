<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

use DateTimeInterface;
use DesignQa\Application\Check\CheckReport;
use DesignQa\Application\Check\ScreenResult;
use DesignQa\Domain\Check\Finding;
use DesignQa\Domain\Check\PresenceRules;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\MatchKind;
use DesignQa\Domain\Matching\ScreenGeometry;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Matching\UncertainMatch;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Infrastructure\Figma\FigmaUrl;
use DesignQa\Output\ConsoleText;
use DesignQa\Output\IssueGrouper;
use DesignQa\Output\PresenceKind;
use InvalidArgumentException;

/**
 * Turns a check report into what the HTML report shows: device tabs, size sub-tabs, and per size
 * the issues grouped by page section.
 */
final readonly class ReportViewBuilder
{
    private const TEXT_LENGTH = 90;

    /** The "group by property" view's fixed order: critical checks first, then non-critical. */
    private const PROPERTY_ORDER = ['Text', 'Font family', 'Font size', 'Font weight', 'Italic', 'Color', 'Line height', 'Letter spacing'];

    public function __construct(
        private IssueGrouper $grouper = new IssueGrouper(),
        private SectionResolver $sections = new SectionResolver(),
    ) {}

    public function build(CheckReport $report, DateTimeInterface $checkedAt, string $dataJson): ReportView
    {
        $labels = self::sizeLabels($report);
        $figmaFileKey = self::figmaFileKey($report->designUrl);

        /** @var array<string, list<int>> $screensByIssue issue key => indexes of the screens that have it */
        $screensByIssue = [];
        foreach ($report->screens as $index => $screen) {
            foreach ($screen->findings as $finding) {
                $key = $this->grouper->key($finding);
                if (!in_array($index, $screensByIssue[$key] ?? [], true)) {
                    $screensByIssue[$key][] = $index;
                }
            }
        }

        /** @var array<string, list<array{SizeView, list<string>, list<string>}>> $byDevice sizes with their critical and non-critical issue keys */
        $byDevice = [];
        foreach ($report->screens as $index => $screen) {
            $byDevice[DeviceType::forWidth($screen->design->width())->value][] = $this->size($screen, $index, $screensByIssue, $labels, $report->presence, $figmaFileKey);
        }

        $devices = [];
        foreach (DeviceType::cases() as $type) {
            $sizes = $byDevice[$type->value] ?? [];
            if ($sizes === []) {
                continue;
            }
            $devices[] = new DeviceView(
                $type,
                array_column($sizes, 0),
                count(array_unique(array_merge(...array_column($sizes, 1)))),
                count(array_unique(array_merge(...array_column($sizes, 2)))),
            );
        }

        return new ReportView(
            title: $report->designName,
            pageUrl: $report->pageUrl,
            checkedAt: $checkedAt->format('j M Y, H:i'),
            critical: $this->grouper->count($report, Severity::Critical),
            nonCritical: $this->grouper->count($report, Severity::NonCritical),
            manual: $this->grouper->manualCount($report),
            compared: $report->comparedTexts(),
            total: $report->designTexts(),
            devices: $devices,
            notChecked: array_map(static fn($s): string => sprintf('%s (%s)', $s->design->name(), $s->reason), $report->skipped),
            dataJson: $dataJson,
        );
    }

    /**
     * @param array<string, list<int>> $screensByIssue
     * @param list<array{string, string}> $labels  per screen: [long label "Mobile 390px", tab label "390px"]
     *
     * @return array{SizeView, list<string>, list<string>} the size, its critical and its non-critical issue keys
     *                                                     (missing and extra texts included)
     */
    private function size(ScreenResult $screen, int $index, array $screensByIssue, array $labels, PresenceRules $presence, ?string $figmaFileKey): array
    {
        $sectionOf = $this->sections->sections($screen->design);

        /** @var array<string, array{finding: Finding, times: int}> $issues */
        $issues = [];
        foreach ($screen->findings as $finding) {
            $key = $this->grouper->key($finding);
            $issues[$key] ??= ['finding' => $finding, 'times' => 0];
            ++$issues[$key]['times'];
        }

        // Sections are keyed by their number (never by label: labels repeat and can be numeric).
        /** @var array<int, array{label: string, critical: list<RowView>, other: list<RowView>}> $bySection */
        $bySection = [];
        foreach ($sectionOf as [$number, $label]) {
            $bySection[$number] ??= ['label' => $label, 'critical' => [], 'other' => []];
        }
        // Section number => label, rows: the same rows as $bySection, but keyed by property label first
        // (built alongside it so each row is only constructed once).
        /** @var array<string, array<int, array{label: string, rows: list<RowView>}>> $byPropertyRaw */
        $byPropertyRaw = [];
        $keys = ['critical' => [], 'other' => []];
        foreach ($issues as $key => ['finding' => $finding, 'times' => $times]) {
            [$number, $label] = $sectionOf[$finding->match->design->text->id] ?? [PHP_INT_MAX, 'Page'];
            $bucket = $finding->severity === Severity::Critical ? 'critical' : 'other';
            $bySection[$number] ??= ['label' => $label, 'critical' => [], 'other' => []];
            $alsoOn = array_map(static fn(int $i): string => $labels[$i][0], array_values(array_diff($screensByIssue[$key] ?? [], [$index])));
            $row = $this->row($finding, $times, $alsoOn, $figmaFileKey);
            $bySection[$number][$bucket][] = $row;
            $byPropertyRaw[$finding->label][$number] ??= ['label' => $label, 'rows' => []];
            $byPropertyRaw[$finding->label][$number]['rows'][] = $row;
            $keys[$bucket][] = (string) $key;
        }
        ksort($bySection);
        $differences = count($keys['critical']) + count($keys['other']);

        // A text missing on the page (or extra on it) is one issue, however often it appears.
        $presenceSeverity = [];
        foreach (PresenceKind::cases() as $kind) {
            $severity = $kind->severity($presence);
            $presenceSeverity[$kind->value] = $severity;
            if ($severity !== null) {
                foreach (array_unique(array_map(fn(string $t): string => $this->grouper->presenceKey($kind, $t), $kind->texts($screen))) as $key) {
                    $keys[$severity === Severity::Critical ? 'critical' : 'other'][] = $key;
                }
            }
        }

        $sections = [];
        foreach ($bySection as ['label' => $label, 'critical' => $critical, 'other' => $other]) {
            $rows = [...$critical, ...$other];
            if ($rows !== []) {
                $sections[] = new SectionView($label, $rows);
            }
        }

        $byProperty = [];
        $propertyLabels = [...self::PROPERTY_ORDER, ...array_diff(array_keys($byPropertyRaw), self::PROPERTY_ORDER)];
        foreach ($propertyLabels as $propertyLabel) {
            if (!isset($byPropertyRaw[$propertyLabel])) {
                continue;
            }
            $bySectionForProperty = $byPropertyRaw[$propertyLabel];
            ksort($bySectionForProperty);
            $propertySections = [];
            foreach ($bySectionForProperty as ['label' => $sectionLabel, 'rows' => $rows]) {
                if ($rows !== []) {
                    $propertySections[] = new SectionView($sectionLabel, $rows);
                }
            }
            if ($propertySections !== []) {
                $byProperty[] = new PropertyGroupView($propertyLabel, $propertySections);
            }
        }

        // Each text that differs, once, with the properties that differ (in check order).
        /** @var array<string, list<string>> $differing design text id => property labels */
        $differing = [];
        foreach ($screen->findings as $finding) {
            $id = $finding->match->design->text->id;
            if (!in_array($finding->label, $differing[$id] ?? [], true)) {
                $differing[$id][] = $finding->label;
            }
        }
        // Identical entries (e.g. the same card text 3 times) become one line with a count.
        /** @var array<string, array{string, list<string>, int}> $grouped */
        $grouped = [];
        foreach ($screen->design->texts as $text) {
            if (isset($differing[$text->id])) {
                $content = self::shorten($text->normalizedContent());
                $key = $content . "\0" . implode("\0", $differing[$text->id]);
                $grouped[$key] ??= [$content, $differing[$text->id], 0];
                ++$grouped[$key][2];
            }
        }
        $notMatching = array_values($grouped);
        $moved = $this->moved($screen, $figmaFileKey);

        $size = new SizeView(
            id: sprintf('s%d-%d', $index, $screen->design->width()),
            name: $screen->name(),
            width: $screen->design->width(),
            tabLabel: $labels[$index][1],
            linkKey: $labels[$index][1] === $screen->design->width() . 'px' ? (string) $screen->design->width() : sprintf('%d-%d', $screen->design->width(), $index),
            critical: count($keys['critical']),
            nonCritical: count($keys['other']),
            differences: $differences,
            missingSeverity: $presenceSeverity[PresenceKind::Missing->value],
            extraSeverity: $presenceSeverity[PresenceKind::Extra->value],
            compared: $screen->comparedTexts(),
            total: $screen->designTexts(),
            sections: $sections,
            byProperty: $byProperty,
            manual: array_map(static fn(UncertainMatch $u): string => self::shorten($u->design->content()), $screen->matching->uncertain),
            moved: $moved,
            missing: self::grouped(array_map(static fn(TextPart $p): string => self::shorten($p->content()), $screen->missingOnPage())),
            extra: self::grouped(array_map(static fn(TextElement $t): string => self::shorten($t->normalizedContent()), $screen->extraOnPage())),
            symbols: array_values(array_unique(array_map(static fn(TextElement $t): string => trim($t->content), [...$screen->matching->symbols, ...$screen->matching->pageSymbols]))),
            notMatching: $notMatching,
        );

        return [$size, $keys['critical'], $keys['other']];
    }

    /**
     * @param list<string> $alsoOn
     */
    private function row(Finding $finding, int $times, array $alsoOn, ?string $figmaFileKey): RowView
    {
        [$figmaSwatch, $figmaAlpha] = $finding->checkId === 'color' ? self::swatch($finding->difference->design) : [null, null];
        [$pageSwatch, $pageAlpha] = $finding->checkId === 'color' ? self::swatch($finding->difference->page) : [null, null];
        $page = $finding->match->page;

        return new RowView(
            severity: $finding->severity,
            text: self::shorten($finding->text()),
            property: $finding->label,
            part: $finding->part,
            figma: $finding->difference->design,
            page: $finding->difference->page,
            figmaSwatch: $figmaSwatch,
            figmaAlpha: $figmaAlpha,
            pageSwatch: $pageSwatch,
            pageAlpha: $pageAlpha,
            alsoOn: $alsoOn,
            wordingDiffers: $finding->match->kind === MatchKind::Similar,
            times: $times,
            pageElement: $page->path === [] ? '' : $page->path[array_key_last($page->path)],
            figmaLink: $figmaFileKey === null ? null : FigmaUrl::nodeLink($figmaFileKey, $finding->match->design->text->id),
        );
    }

    /**
     * @return list<MovedView>
     */
    private function moved(ScreenResult $screen, ?string $figmaFileKey): array
    {
        $matches = $screen->movedMatches();
        if ($matches === []) {
            return [];
        }
        $geometry = new ScreenGeometry($screen->design, $screen->page);

        return array_map(fn(TextMatch $m): MovedView => new MovedView(
            text: self::shorten($m->design->normalizedContent()),
            designPercent: (int) round($geometry->designShare($m->design) * 100),
            pagePercent: (int) round($geometry->pageShare($m->page) * 100),
            pageElement: $m->page->path === [] ? '' : $m->page->path[array_key_last($m->page->path)],
            figmaLink: $figmaFileKey === null ? null : FigmaUrl::nodeLink($figmaFileKey, $m->design->text->id),
        ), $matches);
    }

    /**
     * The Figma file key parsed from the design URL given for this check, so each row can deep-link
     * to its own node. Null when the URL is unknown (test fixtures) or doesn't parse as a Figma link.
     */
    private static function figmaFileKey(string $designUrl): ?string
    {
        if ($designUrl === '') {
            return null;
        }
        try {
            return FigmaUrl::parse($designUrl)->fileKey;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * How the report names each screen: "Tablet 800px" (in "also on") and "800px" (sub-tab). When
     * two checked screens share a width, the Figma frame name is added so they stay distinguishable.
     *
     * @return list<array{string, string}>
     */
    private static function sizeLabels(CheckReport $report): array
    {
        $widths = array_count_values(array_map(static fn(ScreenResult $s): int => $s->design->width(), $report->screens));

        return array_map(static function (ScreenResult $s) use ($widths): array {
            $width = $s->design->width();
            $suffix = $widths[$width] > 1 ? sprintf(' (%s)', $s->name()) : '';

            return [sprintf('%s %dpx%s', DeviceType::forWidth($width)->label(), $width, $suffix), sprintf('%dpx%s', $width, $suffix)];
        }, $report->screens);
    }

    /**
     * "#003867" or "#003867 50%" → ["#003867", 0.5]; anything else → no swatch.
     *
     * @return array{?string, ?float}
     */
    private static function swatch(string $value): array
    {
        if (preg_match('/^(#[0-9A-F]{6})(?: (\d{1,3})%)?$/', $value, $m) !== 1) {
            return [null, null];
        }

        return [$m[1], isset($m[2]) ? min(100, (int) $m[2]) / 100 : 1.0];
    }

    /**
     * @param list<string> $texts
     *
     * @return list<array{string, int}> each distinct text once, with how often it occurs, in order
     */
    private static function grouped(array $texts): array
    {
        $counts = [];
        foreach ($texts as $text) {
            $counts[$text] = ($counts[$text] ?? 0) + 1;
        }

        return array_map(static fn(string|int $text, int $n): array => [(string) $text, $n], array_keys($counts), $counts);
    }

    private static function shorten(string $text): string
    {
        return ConsoleText::shorten($text, self::TEXT_LENGTH);
    }
}
