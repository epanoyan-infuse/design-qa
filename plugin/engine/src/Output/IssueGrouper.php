<?php

declare(strict_types=1);

namespace DesignQa\Output;

use DesignQa\Application\Check\CheckReport;
use DesignQa\Domain\Check\Finding;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\TextNormalizer;

/**
 * Groups findings that are the same on several screens (or repeated on one screen), so a report
 * says "About the book, Color #003867 → #94A3B8, all screens" once instead of four times.
 */
final readonly class IssueGrouper
{
    public function __construct(private TextNormalizer $normalizer = new TextNormalizer()) {}

    /**
     * Findings with the same key are the same issue (same text, property, values and words).
     */
    public function key(Finding $finding): string
    {
        return implode("\0", [
            $this->normalizer->key($finding->text()),
            $finding->checkId,
            $finding->difference->design,
            $finding->difference->page,
            $finding->part ?? '',
        ]);
    }

    /**
     * @return list<Issue> in the order they first appear (screen by screen, top to bottom)
     */
    public function group(CheckReport $report, Severity $severity): array
    {
        /** @var array<string, Finding> $findings */
        $findings = [];
        /** @var array<string, array<string, int>> $tallies */
        $tallies = [];
        foreach ($report->screens as $screen) {
            foreach ($screen->findings as $finding) {
                if ($finding->severity !== $severity) {
                    continue;
                }
                $key = $this->key($finding);
                $findings[$key] ??= $finding;
                $tallies[$key][$screen->name()] = ($tallies[$key][$screen->name()] ?? 0) + 1;
            }
        }

        return array_map(static fn(string $key): Issue => new Issue($findings[$key], $tallies[$key]), array_keys($findings));
    }

    public function presenceKey(PresenceKind $kind, string $text): string
    {
        return $kind->value . "\0" . $this->normalizer->key($text);
    }

    /**
     * Texts missing on the page (or extra on it), the same text on several screens grouped once.
     * Empty when the rule is switched off.
     *
     * @return list<PresenceIssue> in the order they first appear
     */
    public function presence(CheckReport $report, PresenceKind $kind): array
    {
        $severity = $kind->severity($report->presence);
        if ($severity === null) {
            return [];
        }
        /** @var array<string, string> $texts */
        $texts = [];
        /** @var array<string, array<string, int>> $tallies */
        $tallies = [];
        foreach ($report->screens as $screen) {
            foreach ($kind->texts($screen) as $text) {
                $key = $this->presenceKey($kind, $text);
                $texts[$key] ??= $text;
                $tallies[$key][$screen->name()] = ($tallies[$key][$screen->name()] ?? 0) + 1;
            }
        }

        return array_map(static fn(string $key): PresenceIssue => new PresenceIssue($kind, $texts[$key], $severity, $tallies[$key]), array_keys($texts));
    }

    /**
     * How many distinct texts need a manual check, across all screens: the same ambiguous text on
     * several screens is one thing for a person to review, so it counts once, like every other
     * total in the report.
     */
    public function manualCount(CheckReport $report): int
    {
        $keys = [];
        foreach ($report->screens as $screen) {
            foreach ($screen->matching->uncertain as $uncertain) {
                $keys[$this->normalizer->key($uncertain->design->content())] = true;
            }
        }

        return count($keys);
    }

    /**
     * Every issue of this severity, grouped: differences of matched texts, and missing and extra texts.
     */
    public function count(CheckReport $report, Severity $severity): int
    {
        $presence = [...$this->presence($report, PresenceKind::Missing), ...$this->presence($report, PresenceKind::Extra)];

        return count($this->group($report, $severity))
            + count(array_filter($presence, static fn(PresenceIssue $p): bool => $p->severity === $severity));
    }
}
