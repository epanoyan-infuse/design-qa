<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check\Checks;

use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Check\TextCheck;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Model\TextCase;
use DesignQa\Domain\Model\Visibility;

/**
 * The wording as people see it: typos, missing or extra words, changed punctuation or case.
 *
 * Both texts are compared as displayed: each letter gets the case transform of its own style
 * (Figma textCase, CSS text-transform), so one uppercase word inside a sentence is handled; title
 * case only raises the first letter of each word, like CSS "capitalize". Spacing and line breaks
 * are ignored. Typographic variants WordPress creates automatically (curly quotes and
 * apostrophes, dashes, "..." as "…") count as the same. Only the differing part is shown, with a
 * little context around it.
 */
final class TextContentCheck implements TextCheck
{
    /** Characters of unchanged text shown around a change. */
    private const CONTEXT = 18;

    /** A remainder this short is shown instead of being cut to "…". */
    private const KEEP_SHORT_REST = 10;

    public function id(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Text';
    }

    public function compare(TextMatch $match, CheckRule $rule): ?Difference
    {
        $design = self::displayed($match->design);
        $page = self::displayed(TextPart::whole($match->page));
        if (self::comparable($design) === self::comparable($page)) {
            return null;
        }

        [$designPart, $pagePart] = self::changedParts(mb_str_split($design), mb_str_split($page));

        return new Difference(sprintf('“%s”', $designPart), sprintf('“%s”', $pagePart));
    }

    /**
     * The part's characters as shown: each with the case transform of its own style run.
     */
    private static function displayed(TextPart $part): string
    {
        $end = $part->start + $part->length;
        $shown = '';
        $previousIsLetter = false;
        foreach ($part->text->styledCharacters() as [$offset, $char, $style]) {
            if ($offset < $part->start || $offset >= $end) {
                continue;
            }
            // Icon-font glyphs (accuracy rule 7) are ignored even inline with real words, e.g. an
            // icon bullet followed by a label: the page's equivalent icon is a separate element.
            if (Visibility::isIconGlyph($char)) {
                continue;
            }
            // An apostrophe inside a word ("don't") does not start a new word.
            $isLetter = preg_match('/^[\p{L}\p{N}]$/u', $char) === 1 || ($previousIsLetter && in_array($char, ["'", '’'], true));
            $shown .= match ($style->textCase) {
                TextCase::Upper => mb_strtoupper($char),
                TextCase::Lower => mb_strtolower($char),
                TextCase::Title => $isLetter && !$previousIsLetter ? mb_strtoupper($char) : $char,
                TextCase::None => $char,
            };
            $previousIsLetter = $isLetter;
        }
        $shown = str_replace(["\u{00AD}", "\u{200B}", "\u{FEFF}"], '', $shown);

        // "..." and "…" are the same text; use one form so the changed part is found in the right place.
        return str_replace('...', '…', trim((string) preg_replace('/\s+/u', ' ', $shown)));
    }

    private static function comparable(string $text): string
    {
        return strtr($text, [
            '’' => "'", '‘' => "'", '′' => "'",
            '“' => '"', '”' => '"', '″' => '"', '„' => '"',
            '–' => '-', '—' => '-', '‑' => '-',
        ]);
    }

    /**
     * The changed region of each text, with shared context around it and "…" where cut.
     *
     * @param list<string> $a
     * @param list<string> $b
     *
     * @return array{string, string}
     */
    private static function changedParts(array $a, array $b): array
    {
        $prefix = 0;
        $max = min(count($a), count($b));
        while ($prefix < $max && self::comparable($a[$prefix]) === self::comparable($b[$prefix])) {
            ++$prefix;
        }
        $suffix = 0;
        while ($suffix < $max - $prefix && self::comparable($a[count($a) - 1 - $suffix]) === self::comparable($b[count($b) - 1 - $suffix])) {
            ++$suffix;
        }

        // Start of the context: at a word start, or the text start when little is left before it.
        $from = $prefix - self::CONTEXT;
        if ($from <= self::KEEP_SHORT_REST) {
            $from = 0;
        } else {
            while ($from < $prefix && $a[$from - 1] !== ' ') {
                ++$from;
            }
        }

        $cut = static function (array $chars) use ($from, $suffix): string {
            $changedEnd = count($chars) - $suffix;
            $to = $changedEnd + self::CONTEXT;
            if (count($chars) - $to <= self::KEEP_SHORT_REST) {
                $to = count($chars);
            } else {
                while ($to > $changedEnd && $chars[$to] !== ' ') {
                    --$to;
                }
            }
            $piece = trim(implode('', array_slice($chars, $from, $to - $from)));

            return ($from > 0 ? '…' : '') . $piece . ($to < count($chars) ? '…' : '');
        };

        return [$cut($a), $cut($b)];
    }
}
