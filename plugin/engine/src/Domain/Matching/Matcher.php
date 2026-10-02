<?php

declare(strict_types=1);

namespace DesignQa\Domain\Matching;

use DesignQa\Domain\Model\Screen;

/**
 * Finds, for each design text, the same text on the page. Implementations must be deterministic:
 * the same screens always give the same result. (An AI-assisted matcher can later decorate this
 * one for the unmatched texts.)
 */
interface Matcher
{
    public function match(Screen $design, Screen $page): MatchResult;
}
