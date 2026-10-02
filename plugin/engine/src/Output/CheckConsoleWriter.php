<?php

declare(strict_types=1);

namespace DesignQa\Output;

use DesignQa\Application\Check\CheckReport;
use DesignQa\Application\Check\ScreenResult;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\MatchKind;
use DesignQa\Domain\Matching\ScreenGeometry;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Writes the check result as plain text for the terminal and for Claude to explain.
 */
final readonly class CheckConsoleWriter
{
    private const TEXT_LENGTH = 50;

    public function __construct(private IssueGrouper $grouper = new IssueGrouper()) {}

    public function write(CheckReport $report, OutputInterface $out): void
    {
        $screens = array_map(static fn(ScreenResult $s): string => $s->name(), $report->screens);
        $critical = $this->grouper->group($report, Severity::Critical);
        $nonCritical = $this->grouper->group($report, Severity::NonCritical);
        $manual = $this->grouper->manualCount($report);

        $out->writeln(sprintf('Design: %s', ConsoleText::clean($report->designName)), OutputInterface::OUTPUT_RAW);
        $out->writeln(sprintf('Page:   %s', ConsoleText::clean($report->pageUrl)), OutputInterface::OUTPUT_RAW);
        $out->writeln('');
        $out->writeln(sprintf('Checked %d screen sizes: %s', count($screens), ConsoleText::clean(implode(', ', $screens))), OutputInterface::OUTPUT_RAW);
        foreach ($report->skipped as $skipped) {
            $out->writeln(sprintf('Not checked: %s (%s)', ConsoleText::clean($skipped->design->name()), $skipped->reason), OutputInterface::OUTPUT_RAW);
        }
        $out->writeln(sprintf(
            'Compared %d of %d design texts (%s).',
            $report->comparedTexts(),
            $report->designTexts(),
            self::percent($report->comparedTexts(), $report->designTexts()),
        ), OutputInterface::OUTPUT_RAW);
        $out->writeln('');
        $out->writeln(sprintf(
            '🔴 %d critical   🟡 %d non-critical   ⚠️ %d to check manually',
            $this->grouper->count($report, Severity::Critical),
            $this->grouper->count($report, Severity::NonCritical),
            $manual,
        ), OutputInterface::OUTPUT_RAW);
        $parts = [sprintf('%d differences in matched texts', count($critical) + count($nonCritical))];
        foreach (PresenceKind::cases() as $kind) {
            if ($kind->severity($report->presence) !== null) {
                $parts[] = sprintf('%d %s', count($this->grouper->presence($report, $kind)), lcfirst($kind->label()));
            }
        }
        $out->writeln('   ' . implode(', ', $parts) . ' (the same issue on several screens counts once)', OutputInterface::OUTPUT_RAW);

        $this->issues('🔴 CRITICAL', $critical, $screens, $out);
        $this->issues('🟡 NON-CRITICAL', $nonCritical, $screens, $out);
        $this->manual($report, $out);
        $this->moved($report, $out);
        $this->differenceLists($report, $out);
    }

    /**
     * @param list<Issue>  $issues
     * @param list<string> $screens
     */
    private function issues(string $title, array $issues, array $screens, OutputInterface $out): void
    {
        if ($issues === []) {
            return;
        }
        $out->writeln('');
        $out->writeln($title, OutputInterface::OUTPUT_RAW);
        foreach ($issues as $n => $issue) {
            $f = $issue->finding;
            $out->writeln(sprintf(
                '%2d. "%s"%s  %s%s: Figma %s → Page %s   [%s]',
                $n + 1,
                self::shorten($f->text()),
                $f->match->kind === MatchKind::Similar ? ' (wording differs on the page)' : '',
                $f->label,
                $f->part !== null ? sprintf(' (only "%s")', ConsoleText::clean($f->part)) : '',
                $f->difference->design,
                $f->difference->page,
                ConsoleText::clean($issue->where($screens)),
            ), OutputInterface::OUTPUT_RAW);
        }
    }

    private function manual(CheckReport $report, OutputInterface $out): void
    {
        $lines = [];
        foreach ($report->screens as $screen) {
            foreach ($screen->matching->uncertain as $uncertain) {
                $lines[] = sprintf('   %s: "%s" (%d equally good places on the page)', ConsoleText::clean($screen->name()), self::shorten($uncertain->design->content()), count($uncertain->candidates));
            }
        }
        if ($lines !== []) {
            $out->writeln('');
            $out->writeln('⚠️ CHECK MANUALLY (not compared, the matching page text is not certain)', OutputInterface::OUTPUT_RAW);
            foreach ($lines as $line) {
                $out->writeln($line, OutputInterface::OUTPUT_RAW);
            }
        }
    }

    /**
     * Texts found far from their Figma position: still compared normally (see the critical/
     * non-critical sections above for any property differences), just flagged so a real layout
     * shift isn't mistaken for a missing text.
     */
    private function moved(CheckReport $report, OutputInterface $out): void
    {
        $lines = [];
        foreach ($report->screens as $screen) {
            $moved = $screen->movedMatches();
            if ($moved === []) {
                continue;
            }
            $geometry = new ScreenGeometry($screen->design, $screen->page);
            foreach ($moved as $match) {
                $lines[] = sprintf(
                    '   %s: "%s" (expected near %d%% down the page, found near %d%%)',
                    ConsoleText::clean($screen->name()),
                    self::shorten($match->design->content()),
                    (int) round($geometry->designShare($match->design) * 100),
                    (int) round($geometry->pageShare($match->page) * 100),
                );
            }
        }
        if ($lines !== []) {
            $out->writeln('');
            $out->writeln('↕️ FOUND BUT MOVED (position differs a lot from the design; other properties compared normally)', OutputInterface::OUTPUT_RAW);
            foreach ($lines as $line) {
                $out->writeln($line, OutputInterface::OUTPUT_RAW);
            }
        }
    }

    /**
     * Texts that exist on only one side, per screen: missing on the page, extra on the page.
     */
    private function differenceLists(CheckReport $report, OutputInterface $out): void
    {
        $sections = [
            [PresenceKind::Missing, 'MISSING ON THE PAGE (in the design, no matching text on the page)'],
            [PresenceKind::Extra, 'EXTRA ON THE PAGE (on the page, not in the design)'],
        ];
        foreach ($sections as [$kind, $title]) {
            $severity = $kind->severity($report->presence);
            $out->writeln('');
            $out->writeln(match ($severity) {
                Severity::Critical => '🔴 ',
                Severity::NonCritical => '🟡 ',
                null => '➕ ',
            } . $title, OutputInterface::OUTPUT_RAW);
            foreach ($report->screens as $screen) {
                $counts = [];
                foreach ($kind->texts($screen) as $text) {
                    $short = self::shorten($text, 30);
                    $counts[$short] = ($counts[$short] ?? 0) + 1;
                }
                $listed = array_map(
                    static fn(string|int $t, int $n): string => sprintf('"%s"%s', $t, $n > 1 ? sprintf(' (%d×)', $n) : ''),
                    array_keys($counts),
                    $counts,
                );
                $out->writeln(sprintf('   %s: %s', ConsoleText::clean($screen->name()), $listed === [] ? 'none' : implode(', ', $listed)), OutputInterface::OUTPUT_RAW);
            }
        }

        $symbols = [];
        foreach ($report->screens as $screen) {
            foreach ([...$screen->matching->symbols, ...$screen->matching->pageSymbols] as $symbol) {
                $symbols[trim(ConsoleText::clean($symbol->content))] = true;
            }
        }
        if ($symbols !== []) {
            $out->writeln('');
            $out->writeln(sprintf('Symbols without letters are not compared: %s', implode(' ', array_keys($symbols))), OutputInterface::OUTPUT_RAW);
        }
    }

    private static function shorten(string $text, int $length = self::TEXT_LENGTH): string
    {
        return ConsoleText::shorten(ConsoleText::clean($text), $length);
    }

    private static function percent(int $part, int $whole): string
    {
        return $whole === 0 ? '0%' : sprintf('%d%%', (int) floor(100 * $part / $whole));
    }
}
