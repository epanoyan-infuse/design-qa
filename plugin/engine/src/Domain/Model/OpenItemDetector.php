<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

/**
 * Finds the one item a screen's design shows "open" when it depicts an accordion, tabs, or a
 * similar component with repeated sibling items of which one is visually active (e.g. an open
 * accordion item's title painted a different color than its closed siblings).
 *
 * A screen like this needs that same item opened on the live page before it is read, or every
 * closed-vs-open sibling it shares a style with would show a false style difference. Detected
 * purely from the design: a group of at least three texts that share both their style (font
 * family, size, weight, italic) and their container (same layer path, one level up) — "this looks
 * like repeated items of one list" — where exactly one member's color differs from the others.
 *
 * Unsure → null, never a guess (accuracy rule 8): zero such groups, more than one, or a group with
 * more than one outlier all mean nothing is opened before capture.
 */
final class OpenItemDetector
{
    /** Fewer than this many same-style, same-container texts could just be coincidence. */
    private const MIN_GROUP_SIZE = 3;

    public function detect(Screen $screen): ?string
    {
        /** @var array<string, list<TextElement>> $groups */
        $groups = [];
        foreach ($screen->texts as $text) {
            $groups[$this->key($text)][] = $text;
        }

        $candidates = [];
        foreach ($groups as $group) {
            if (count($group) < self::MIN_GROUP_SIZE) {
                continue;
            }
            $outlier = $this->soleColorOutlier($group);
            if ($outlier !== null) {
                $candidates[] = $outlier;
            }
        }

        return count($candidates) === 1 ? $candidates[0]->normalizedContent() : null;
    }

    private function key(TextElement $text): string
    {
        $style = $text->dominantStyle();
        $container = implode('›', array_slice($text->path, 0, -1));

        return sprintf('%s|%s|%d|%s|%s', $style->fontFamily, $style->fontWeight, $style->italic ? 1 : 0, (string) $style->fontSize, $container);
    }

    /**
     * @param non-empty-list<TextElement> $group
     */
    private function soleColorOutlier(array $group): ?TextElement
    {
        /** @var list<array{Color, TextElement}> $colored */
        $colored = [];
        foreach ($group as $text) {
            $color = $text->dominantStyle()->color;
            if ($color === null) {
                return null; // a gradient/image fill in the group: too unlike the rest to judge
            }
            $colored[] = [$color, $text];
        }

        /** @var list<array{Color, list<TextElement>}> $byColor */
        $byColor = [];
        foreach ($colored as [$color, $text]) {
            foreach ($byColor as $i => [$seen]) {
                if ($seen->equals($color)) {
                    $byColor[$i][1][] = $text;
                    continue 2;
                }
            }
            $byColor[] = [$color, [$text]];
        }

        if (count($byColor) !== 2) {
            return null;
        }
        usort($byColor, static fn(array $a, array $b): int => count($a[1]) <=> count($b[1]));
        [, $minority] = $byColor[0];

        return count($minority) === 1 ? $minority[0] : null;
    }
}
