<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Model\Breakpoint;
use DesignQa\Domain\Model\TextStyle;

/**
 * Runs the configured checks on a matched pair of texts.
 *
 * When the wording is the same, design and page letters are lined up one to one, so every
 * letter is compared with its own style: a bold word inside a sentence is checked on its own and
 * reported with its words ("part"). When the wording differs, the main styles are compared.
 */
final readonly class StyleComparator
{
    private const PART_MAX_LENGTH = 60;

    /**
     * @param list<array{Check|TextCheck, CheckRule}> $checks
     */
    public function __construct(private array $checks) {}

    /**
     * @return list<Finding>
     */
    public function compare(TextMatch $match, int $screenWidth = 1440): array
    {
        $context = new CheckContext(
            $match->design->isMultiLine() && $match->page->isMultiLine(),
            Breakpoint::forWidth($screenWidth) === Breakpoint::Tablet,
        );
        $spans = $this->spans($match);
        $total = array_sum(array_column($spans, 'count'));

        $findings = [];
        foreach ($this->checks as [$check, $rule]) {
            if ($check instanceof TextCheck) {
                $difference = $check->compare($match, $rule);
                if ($difference !== null) {
                    $findings[] = new Finding($check->id(), $check->label(), $difference->severity ?? $rule->severity, $difference, $match, null, $total, $total);
                }
                continue;
            }
            /** @var array<string, array{difference: Difference, affected: int, stretches: list<array{int, int}>}> $groups */
            $groups = [];
            foreach ($spans as $span) {
                $difference = $check->compare($span['design'], $span['page'], $context, $rule);
                if ($difference === null) {
                    continue;
                }
                $key = $difference->design . "\0" . $difference->page;
                $groups[$key] ??= ['difference' => $difference, 'affected' => 0, 'stretches' => []];
                $groups[$key]['affected'] += $span['count'];
                $groups[$key]['stretches'][] = [$span['from'], $span['to']];
            }

            foreach ($groups as $group) {
                $findings[] = new Finding(
                    $check->id(),
                    $check->label(),
                    $group['difference']->severity ?? $rule->severity,
                    $group['difference'],
                    $match,
                    $group['affected'] < $total ? $this->snippet($match->design, $group['stretches']) : null,
                    $group['affected'],
                    $total,
                );
            }
        }

        return $findings;
    }

    /**
     * Stretches of letters where both the design style and the page style stay the same.
     *
     * @return list<array{design: TextStyle, page: TextStyle, from: int, to: int, count: int}>
     */
    private function spans(TextMatch $match): array
    {
        $design = $match->design->letters();
        $page = TextPart::whole($match->page)->letters();

        if (!self::sameLetters($design, $page)) {
            return [[
                'design' => self::dominant($design) ?? $match->design->text->dominantStyle(),
                'page' => $match->page->dominantStyle(),
                'from' => $match->design->start,
                'to' => $match->design->start + $match->design->length - 1,
                'count' => max(1, count($design)),
            ]];
        }

        $spans = [];
        foreach ($design as $i => [$offset, , $designStyle]) {
            $pageStyle = $page[$i][2];
            $last = array_key_last($spans);
            if ($last !== null && $spans[$last]['design']->equals($designStyle) && $spans[$last]['page']->equals($pageStyle)) {
                $spans[$last]['to'] = $offset;
                ++$spans[$last]['count'];
            } else {
                $spans[] = ['design' => $designStyle, 'page' => $pageStyle, 'from' => $offset, 'to' => $offset, 'count' => 1];
            }
        }

        return $spans;
    }

    /**
     * @param list<array{int, string, TextStyle}> $design
     * @param list<array{int, string, TextStyle}> $page
     */
    private static function sameLetters(array $design, array $page): bool
    {
        if ($design === [] || count($design) !== count($page)) {
            return false;
        }
        foreach ($design as $i => [, $char]) {
            if (mb_strtolower($char) !== mb_strtolower($page[$i][1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{int, string, TextStyle}> $letters
     */
    private static function dominant(array $letters): ?TextStyle
    {
        $weighted = array_map(static fn(array $letter): array => [$letter[2], 1], $letters);

        return $weighted === [] ? null : TextStyle::mostCommon($weighted);
    }

    /**
     * The affected words; separate stretches are joined with "…".
     *
     * @param list<array{int, int}> $stretches offsets of the first and last letter of each stretch
     */
    private function snippet(TextPart $part, array $stretches): string
    {
        $pieces = array_map(
            static fn(array $s): string => trim((string) preg_replace('/\s+/u', ' ', mb_substr($part->text->content, $s[0], $s[1] - $s[0] + 1))),
            $stretches,
        );
        $snippet = implode(' … ', array_values(array_unique($pieces)));

        return mb_strlen($snippet) > self::PART_MAX_LENGTH ? mb_substr($snippet, 0, self::PART_MAX_LENGTH - 1) . '…' : $snippet;
    }
}
