<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

use InvalidArgumentException;

/**
 * Builds style runs from one style per character, merging neighbours with equal styles.
 */
final class StyleRuns
{
    /**
     * @param list<TextStyle> $perCharacter style of each code point, in order
     *
     * @return non-empty-list<StyleRun>
     */
    public static function fromCharacters(array $perCharacter): array
    {
        if ($perCharacter === []) {
            throw new InvalidArgumentException('A text needs at least one character.');
        }

        $runs = [];
        $start = 0;
        $current = $perCharacter[0];
        foreach ($perCharacter as $i => $style) {
            if ($style !== $current && !$style->equals($current)) {
                $runs[] = new StyleRun($start, $i - $start, $current);
                [$start, $current] = [$i, $style];
            }
        }
        $runs[] = new StyleRun($start, count($perCharacter) - $start, $current);

        return $runs;
    }
}
