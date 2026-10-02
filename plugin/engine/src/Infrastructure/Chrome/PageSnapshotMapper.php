<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome;

use DesignQa\Application\Port\SourceException;
use DesignQa\Domain\Model\ExcludedText;
use DesignQa\Domain\Model\ExclusionReason;
use DesignQa\Domain\Model\Rect;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Domain\Model\StyleRuns;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Domain\Model\TextStyle;
use DesignQa\Domain\Model\Visibility;

/**
 * Turns the raw output of resources/js/collect-texts.js into a Screen.
 *
 * Decides, with the same Visibility rules as the design side, which text segments a visitor can
 * see: not rendered or visibility hidden, transparent, mostly outside an overflow-clipping
 * container, or icon-font glyphs are excluded with a reason.
 *
 * Whitespace is collapsed the way the browser displays it: runs of spaces become one space,
 * <br> becomes a line break, and leading/trailing whitespace is removed.
 *
 * Line height "normal" has no fixed CSS value; for texts that wrap, it is measured on the rendered
 * lines (the distance from one line to the next), so it can still be compared with Figma.
 */
final readonly class PageSnapshotMapper
{
    public function __construct(private CssStyleParser $css = new CssStyleParser()) {}

    /**
     * @param mixed $raw decoded collector result
     */
    public function map(mixed $raw, ScreenSpec $spec): Screen
    {
        if (!is_array($raw) || !is_array($raw['blocks'] ?? null)) {
            throw new SourceException('The page could not be read (unexpected collector result).');
        }

        $texts = [];
        $excluded = [];
        foreach ($raw['blocks'] as $block) {
            if (is_array($block) && ($text = $this->text($block, $excluded)) !== null) {
                $texts[] = $text;
            }
        }
        usort($texts, static fn(TextElement $a, TextElement $b): int => [$a->box->y, $a->box->x] <=> [$b->box->y, $b->box->x]);

        $height = $raw['documentHeight'] ?? 0;

        return new Screen($spec, is_int($height) || is_float($height) ? (float) $height : 0.0, $texts, $excluded);
    }

    /**
     * @param array<mixed>       $block
     * @param list<ExcludedText> $excluded collects the block's invisible segments
     */
    private function text(array $block, array &$excluded): ?TextElement
    {
        /** @var list<array{string, TextStyle}> $chars */
        $chars = [];
        $rects = [];
        $current = null;

        foreach (is_array($block['segments'] ?? null) ? $block['segments'] : [] as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            if (is_array($segment['style'] ?? null)) {
                $opacity = is_int($segment['opacity'] ?? null) || is_float($segment['opacity'] ?? null) ? (float) $segment['opacity'] : 1.0;
                $style = $this->css->parse($segment['style'], $opacity);
                $segmentRects = self::rects($segment['rects'] ?? null);
                $reason = $this->exclusion($segment, $style, $opacity, $segmentRects);
                if ($reason !== null) {
                    $excluded[] = new ExcludedText(
                        is_string($segment['id'] ?? null) ? $segment['id'] : '',
                        trim(is_string($segment['text'] ?? null) ? $segment['text'] : ''),
                        $reason,
                    );
                    continue;
                }
                $current = $style;
                array_push($rects, ...$segmentRects);
            }
            if ($current === null) {
                continue; // whitespace or line break before any visible text
            }
            $text = ($segment['lineBreak'] ?? false) === true ? "\n" : (is_string($segment['text'] ?? null) ? $segment['text'] : '');
            foreach (mb_str_split($text) as $char) {
                self::append($chars, $char, $current);
            }
        }

        while ($chars !== [] && in_array($chars[array_key_last($chars)][0], [' ', "\n"], true)) {
            array_pop($chars);
        }
        if ($chars === [] || $rects === []) {
            return null;
        }

        $path = is_array($block['path'] ?? null) ? array_values(array_filter($block['path'], is_string(...))) : [];
        $lineTops = self::lineTops($rects);
        $measured = self::measuredLineHeight($lineTops);
        $styles = array_map(
            static fn(TextStyle $style): TextStyle => $style->lineHeight === null && $measured !== null ? $style->withLineHeight($measured) : $style,
            array_column($chars, 1),
        );

        return new TextElement(
            is_string($block['id'] ?? null) ? $block['id'] : 'block',
            implode('', array_column($chars, 0)),
            StyleRuns::fromCharacters($styles),
            Rect::unionAll($rects),
            count($lineTops),
            $path,
        );
    }

    /**
     * The distance between consecutive lines: the most frequent gap, and the smallest one on a tie,
     * so a paragraph margin or blank line inside the text is not taken for the line height.
     * Null for a single line.
     *
     * @param non-empty-list<float> $lineTops
     */
    private static function measuredLineHeight(array $lineTops): ?float
    {
        /** @var list<array{float, int}> $gaps gap (rounded to 0.1px) and how often it occurs */
        $gaps = [];
        for ($i = 1, $n = count($lineTops); $i < $n; ++$i) {
            $gap = round($lineTops[$i] - $lineTops[$i - 1], 1);
            foreach ($gaps as $k => [$known]) {
                if (abs($known - $gap) < 0.05) {
                    ++$gaps[$k][1];
                    continue 2;
                }
            }
            $gaps[] = [$gap, 1];
        }
        if ($gaps === []) {
            return null;
        }
        usort($gaps, static fn(array $a, array $b): int => [$b[1], $a[0]] <=> [$a[1], $b[0]]);

        return $gaps[0][0];
    }

    /**
     * @param array<mixed> $segment
     * @param list<Rect>   $rects
     */
    private function exclusion(array $segment, TextStyle $style, float $opacity, array $rects): ?ExclusionReason
    {
        return match (true) {
            $rects === [] || ($segment['visibility'] ?? 'visible') !== 'visible' => ExclusionReason::Hidden,
            $opacity < Visibility::MIN_OPACITY || ($style->color !== null && $style->color->alpha < Visibility::MIN_OPACITY) => ExclusionReason::Transparent,
            self::visibleFraction($rects, $segment['clip'] ?? null) < Visibility::MIN_VISIBLE_FRACTION => ExclusionReason::Clipped,
            ($segment['covered'] ?? false) === true => ExclusionReason::Covered,
            Visibility::isIconOnly(is_string($segment['text'] ?? null) ? $segment['text'] : '') => ExclusionReason::Icon,
            default => null,
        };
    }

    /**
     * Share of the rects' area inside the clip area; null clip bounds are unlimited.
     *
     * @param non-empty-list<Rect> $rects
     */
    private static function visibleFraction(array $rects, mixed $clip): float
    {
        if (!is_array($clip)) {
            return 1.0;
        }
        $bound = static fn(string $key, float $unlimited): float => is_int($clip[$key] ?? null) || is_float($clip[$key] ?? null) ? (float) $clip[$key] : $unlimited;
        $area = new Rect(
            $x = $bound('x', -1e9),
            $y = $bound('y', -1e9),
            $bound('right', 1e9) - $x,
            $bound('bottom', 1e9) - $y,
        );

        $total = 0.0;
        $inside = 0.0;
        foreach ($rects as $rect) {
            $total += $rect->area();
            $inside += $rect->intersect($area)?->area() ?? 0.0;
        }

        return $total > 0.0 ? $inside / $total : 0.0;
    }

    /**
     * @param list<array{string, TextStyle}> $chars
     */
    private static function append(array &$chars, string $char, TextStyle $style): void
    {
        $last = $chars === [] ? null : $chars[array_key_last($chars)][0];
        if ($char === "\n") {
            if ($last === ' ') {
                array_pop($chars);
            }
            if ($chars !== []) {
                $chars[] = ["\n", $style];
            }

            return;
        }
        if (preg_match('/^\s$/u', $char) === 1) {
            if ($last !== null && $last !== ' ' && $last !== "\n") {
                $chars[] = [' ', $style];
            }

            return;
        }
        $chars[] = [$char, $style];
    }

    /**
     * The top of each rendered line: a rectangle whose vertical centre falls inside the current
     * line's band belongs to that line (so mixed font sizes on one line still count once).
     *
     * @param non-empty-list<Rect> $rects
     *
     * @return non-empty-list<float>
     */
    private static function lineTops(array $rects): array
    {
        usort($rects, static fn(Rect $a, Rect $b): int => $a->y <=> $b->y);
        $tops = [$rects[0]->y];
        $top = $rects[0]->y;
        $bottom = $rects[0]->bottom();
        foreach ($rects as $rect) {
            $centre = $rect->y + $rect->height / 2;
            if ($centre >= $top && $centre <= $bottom) {
                $bottom = max($bottom, $rect->bottom());
                continue;
            }
            [$top, $bottom] = [$rect->y, $rect->bottom()];
            $tops[] = $top;
        }

        return $tops;
    }

    /**
     * @return list<Rect>
     */
    private static function rects(mixed $raw): array
    {
        $rects = [];
        foreach (is_array($raw) ? $raw : [] as $item) {
            $rect = Rect::fromArray($item);
            if ($rect !== null && !$rect->isEmpty()) {
                $rects[] = $rect;
            }
        }

        return $rects;
    }
}
