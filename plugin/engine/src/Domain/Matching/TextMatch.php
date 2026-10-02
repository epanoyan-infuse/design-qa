<?php

declare(strict_types=1);

namespace DesignQa\Domain\Matching;

use DesignQa\Domain\Model\TextElement;

/**
 * A design text (or paragraph) and the page text found for it.
 */
final readonly class TextMatch
{
    /**
     * @param bool $moved true only when this match exists purely because its wording is unique on
     *                    both sides (PositionalTextMatcher): the page text sits far enough from its
     *                    Figma position that position alone would not have linked them.
     */
    public function __construct(
        public TextPart $design,
        public TextElement $page,
        public MatchKind $kind,
        public float $confidence,
        public bool $moved = false,
    ) {}
}
