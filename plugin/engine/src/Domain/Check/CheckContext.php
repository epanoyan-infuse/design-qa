<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

/**
 * Facts about the matched texts that some checks need.
 */
final readonly class CheckContext
{
    public function __construct(
        /** Both the design and the page text wrap onto more than one line. */
        public bool $multiLine,
        /**
         * The screen is Elementor's Tablet breakpoint (768-1024px). With CSS clamp() fluid
         * typography, Tablet only ever shows a value interpolated between Mobile and Desktop, so
         * checks that scale with font size may relax here instead of expecting an exact match.
         */
        public bool $tabletBreakpoint = false,
    ) {}
}
