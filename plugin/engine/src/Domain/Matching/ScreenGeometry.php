<?php

declare(strict_types=1);

namespace DesignQa\Domain\Matching;

use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\TextElement;

/**
 * Compares positions on a design screen and on the page, which have the same width but usually
 * slightly different heights: vertical positions are compared as a share of the height.
 *
 * Used by PositionalTextMatcher to decide matches, and by the Output layer (constructed fresh from
 * the same Screen pair) to describe a "found, but moved" match's position for a report.
 */
final readonly class ScreenGeometry
{
    /** Horizontal distance counts less: layouts shift sideways more than texts change order. */
    private const HORIZONTAL_WEIGHT = 0.25;

    private float $designHeight;
    private float $pageHeight;
    private float $width;

    public function __construct(Screen $design, Screen $page)
    {
        $this->designHeight = self::height($design);
        $this->pageHeight = self::height($page);
        $this->width = (float) max(1, $design->width());
    }

    /**
     * Distance between the relative positions, or null when too far apart to be the same text.
     *
     * @param bool $ignoreLimit skip the distance limit (an unambiguous exact match trusts the
     *                          wording over the position; see PositionalTextMatcher::assign())
     */
    public function distance(TextPart $part, TextElement $page, bool $ignoreLimit = false): ?float
    {
        [$vertical, $horizontal] = $this->offsets($part, $page);
        if (!$ignoreLimit && $vertical > PositionalTextMatcher::MAX_VERTICAL_DISTANCE) {
            return null;
        }

        return $vertical + self::HORIZONTAL_WEIGHT * $horizontal;
    }

    /**
     * How far apart the two texts are: vertically as a share of the screen height, horizontally as
     * a share of the width.
     *
     * @return array{float, float}
     */
    public function offsets(TextPart $part, TextElement $page): array
    {
        return [
            abs(self::designY($part) / $this->designHeight - $page->box->y / $this->pageHeight),
            abs($part->text->box->x - $page->box->x) / $this->width,
        ];
    }

    /**
     * Where a text part starts on the design screen: a paragraph of a longer text starts roughly its
     * share of the way down the text's box.
     */
    public static function designY(TextPart $part): float
    {
        $box = $part->text->box;

        return $box->y + $box->height * ($part->start / max(1, mb_strlen($part->text->content)));
    }

    /**
     * How far down its own screen the text sits, as a share of that screen's height (0 = top,
     * 1 = bottom): the same figures offsets()/distance() compare, exposed for display.
     */
    public function designShare(TextPart $part): float
    {
        return self::designY($part) / $this->designHeight;
    }

    public function pageShare(TextElement $page): float
    {
        return $page->box->y / $this->pageHeight;
    }

    private static function height(Screen $screen): float
    {
        $bottom = 0.0;
        foreach ($screen->texts as $text) {
            $bottom = max($bottom, $text->box->bottom());
        }

        return max(1.0, $screen->height, $bottom);
    }
}
