<?php

declare(strict_types=1);

namespace DesignQa\Output;

use DesignQa\Application\Check\CheckReport;
use DesignQa\Application\Check\ScreenResult;
use DesignQa\Application\Check\TextStatus;
use DesignQa\Application\Read\SkippedScreen;
use DesignQa\Domain\Check\Finding;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\ScreenGeometry;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Matching\UncertainMatch;
use DesignQa\Domain\Model\TextElement;

/**
 * The check result as plain arrays for JSON (for Claude, and later the HTML report).
 */
final readonly class CheckReportSerializer
{
    public function __construct(private IssueGrouper $grouper = new IssueGrouper()) {}

    /**
     * @return array<string, mixed>
     */
    public function serialize(CheckReport $report): array
    {
        $screens = array_map(static fn(ScreenResult $s): string => $s->name(), $report->screens);
        $critical = $this->grouper->group($report, Severity::Critical);
        $nonCritical = $this->grouper->group($report, Severity::NonCritical);

        return [
            'design' => $report->designName,
            'page' => $report->pageUrl,
            'summary' => [
                'critical' => $this->grouper->count($report, Severity::Critical),
                'nonCritical' => $this->grouper->count($report, Severity::NonCritical),
                'checkManually' => $this->grouper->manualCount($report),
                'comparedTexts' => $report->comparedTexts(),
                'designTexts' => $report->designTexts(),
            ],
            'issues' => array_map(fn(Issue $issue): array => [
                ...$this->finding($issue->finding),
                'screens' => $issue->screens,
                'where' => $issue->where($screens),
            ], [...$critical, ...$nonCritical]),
            // Texts on only one side, grouped across screens (empty when the rule is switched off).
            'presenceIssues' => array_map(static fn(PresenceIssue $p): array => [
                'severity' => $p->severity->value,
                'check' => $p->kind->ruleId(),
                'text' => $p->text,
                'screens' => $p->screens,
                'where' => $p->where($screens),
            ], [...$this->grouper->presence($report, PresenceKind::Missing), ...$this->grouper->presence($report, PresenceKind::Extra)]),
            'screens' => array_map($this->screen(...), $report->screens),
            'skipped' => array_map(static fn(SkippedScreen $s): array => ['name' => $s->design->name(), 'width' => $s->design->width(), 'reason' => $s->reason], $report->skipped),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function screen(ScreenResult $screen): array
    {
        $statuses = $screen->statuses();
        $counts = array_fill_keys(array_map(static fn(TextStatus $s): string => $s->value, TextStatus::cases()), 0);
        $texts = [];
        foreach ($statuses as $id => $status) {
            ++$counts[$status->value];
            $texts[] = ['figmaTextId' => (string) $id, 'status' => $status->value];
        }

        return [
            'name' => $screen->name(),
            'width' => $screen->design->width(),
            'comparedTexts' => $screen->comparedTexts(),
            'designTexts' => $screen->designTexts(),
            'textStatus' => $counts,
            'texts' => $texts,
            'findings' => array_map($this->finding(...), $screen->findings),
            'checkManually' => array_map(static fn(UncertainMatch $u): array => [
                'text' => $u->design->content(),
                'candidates' => array_map(static fn(TextElement $t): string => $t->id, $u->candidates),
            ], $screen->matching->uncertain),
            'missingOnPage' => array_map(static fn(TextPart $p): string => $p->normalizedContent(), $screen->missingOnPage()),
            'extraOnPage' => array_map(static fn(TextElement $t): string => $t->normalizedContent(), $screen->extraOnPage()),
            'symbols' => array_map(static fn(TextElement $t): string => $t->content, [...$screen->matching->symbols, ...$screen->matching->pageSymbols]),
            'moved' => $this->moved($screen),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function moved(ScreenResult $screen): array
    {
        $matches = $screen->movedMatches();
        if ($matches === []) {
            return [];
        }
        $geometry = new ScreenGeometry($screen->design, $screen->page);

        return array_map(static fn(TextMatch $m): array => [
            'text' => $m->design->normalizedContent(),
            'figmaTextId' => $m->design->text->id,
            'pageTextId' => $m->page->id,
            'designPositionPercent' => (int) round($geometry->designShare($m->design) * 100),
            'pagePositionPercent' => (int) round($geometry->pageShare($m->page) * 100),
        ], $matches);
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(Finding $finding): array
    {
        return [
            'severity' => $finding->severity->value,
            'check' => $finding->checkId,
            'label' => $finding->label,
            'text' => $finding->text(),
            'part' => $finding->part,
            'figma' => $finding->difference->design,
            'page' => $finding->difference->page,
            'match' => $finding->match->kind->value,
            'confidence' => $finding->match->confidence,
            'figmaTextId' => $finding->match->design->text->id,
            'pageTextId' => $finding->match->page->id,
        ];
    }
}
