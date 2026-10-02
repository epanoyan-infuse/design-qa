<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

/**
 * A text matched despite sitting far from its Figma position: compared normally (see the Issues
 * view for any property differences on it), shown here separately so a real layout shift isn't
 * mistaken for a missing text.
 */
final readonly class MovedView
{
    public function __construct(
        public string $text,
        public int $designPercent,
        public int $pagePercent,
        public string $pageElement,
        public ?string $figmaLink,
    ) {}
}
