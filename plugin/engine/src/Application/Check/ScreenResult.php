<?php

declare(strict_types=1);

namespace DesignQa\Application\Check;

use DesignQa\Domain\Check\Finding;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\MatchResult;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\TextElement;

/**
 * The check of one screen: what was matched, what differs, and what could not be compared.
 */
final readonly class ScreenResult
{
    /**
     * @param list<Finding> $findings
     */
    public function __construct(
        public Screen $design,
        public Screen $page,
        public MatchResult $matching,
        public array $findings,
    ) {}

    public function name(): string
    {
        return $this->design->name();
    }

    /**
     * Design texts compared with a page text (fully, or at least one paragraph of them).
     */
    public function comparedTexts(): int
    {
        return count(array_unique(array_map(static fn(TextMatch $m): string => $m->design->text->id, $this->matching->matches)));
    }

    /**
     * Matches found well away from where Figma puts them: still compared normally (any property
     * differences show up in the usual findings), but worth a separate note since it means the real
     * page's layout diverges from the mockup's around this text.
     *
     * @return list<TextMatch>
     */
    public function movedMatches(): array
    {
        return array_values(array_filter($this->matching->matches, static fn(TextMatch $m): bool => $m->moved));
    }

    /**
     * The status of every design text, keyed by text id, in screen order.
     *
     * @return array<string, TextStatus>
     */
    public function statuses(): array
    {
        $matched = [];
        foreach ($this->matching->matches as $match) {
            $matched[$match->design->text->id] = true;
        }
        $withIssues = [];
        foreach ($this->findings as $finding) {
            $withIssues[$finding->match->design->text->id] = true;
        }
        $leftovers = [];
        foreach ($this->matching->unmatchedDesign as $part) {
            $leftovers[$part->text->id] = true;
        }
        $manual = [];
        foreach ($this->matching->uncertain as $uncertain) {
            $manual[$uncertain->design->text->id] = true;
        }
        $symbols = [];
        foreach ($this->matching->symbols as $text) {
            $symbols[$text->id] = true;
        }

        $statuses = [];
        foreach ($this->design->texts as $text) {
            $id = $text->id;
            $partly = isset($matched[$id]) && (isset($leftovers[$id]) || isset($manual[$id]));
            $statuses[$id] = match (true) {
                isset($symbols[$id]) => TextStatus::Symbol,
                isset($withIssues[$id]) && $partly => TextStatus::IssuesPartlyCompared,
                isset($withIssues[$id]) => TextStatus::Issues,
                $partly => TextStatus::PartlyCompared,
                isset($matched[$id]) => TextStatus::Ok,
                isset($manual[$id]) => TextStatus::CheckManually,
                default => TextStatus::NotFound,
            };
        }

        return $statuses;
    }

    /**
     * @return array<string, int> status value => number of design texts
     */
    public function statusCounts(): array
    {
        $counts = array_fill_keys(array_map(static fn(TextStatus $s): string => $s->value, TextStatus::cases()), 0);
        foreach ($this->statuses() as $status) {
            ++$counts[$status->value];
        }

        return $counts;
    }

    /**
     * Design texts (or paragraphs) with no matching text on the page.
     *
     * @return list<TextPart>
     */
    public function missingOnPage(): array
    {
        return $this->matching->unmatchedDesign;
    }

    /**
     * Page texts that match no design text. Candidates of an uncertain match are left out: they may
     * be the match, so calling them extra would be a guess.
     *
     * @return list<TextElement>
     */
    public function extraOnPage(): array
    {
        $candidates = [];
        foreach ($this->matching->uncertain as $uncertain) {
            foreach ($uncertain->candidates as $candidate) {
                $candidates[spl_object_id($candidate)] = true;
            }
        }

        return array_values(array_filter($this->matching->unmatchedPage, static fn(TextElement $t): bool => !isset($candidates[spl_object_id($t)])));
    }

    public function designTexts(): int
    {
        return count($this->design->texts);
    }

    public function count(Severity $severity): int
    {
        return count(array_filter($this->findings, static fn(Finding $f): bool => $f->severity === $severity));
    }
}
