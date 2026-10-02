<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

use DesignQa\Domain\Check\Severity;

/**
 * One issue on one screen size.
 */
final readonly class RowView
{
    /**
     * @param string|null  $figmaSwatch "#RRGGBB" for color issues, validated
     * @param string|null  $pageSwatch  "#RRGGBB" for color issues, validated
     * @param list<string> $alsoOn      other screen sizes with the same issue, e.g. "Tablet 800px"
     * @param string       $pageElement the page element holding the text, e.g. "h2.elementor-heading-title"
     * @param int          $times       how often the same issue repeats on this size (e.g. 3 review cards)
     * @param string|null  $figmaLink   deep link to this text's own node in Figma, null when the design
     *                                  URL couldn't be parsed
     */
    public function __construct(
        public Severity $severity,
        public string $text,
        public string $property,
        public ?string $part,
        public string $figma,
        public string $page,
        public ?string $figmaSwatch,
        public ?float $figmaAlpha,
        public ?string $pageSwatch,
        public ?float $pageAlpha,
        public array $alsoOn,
        public bool $wordingDiffers,
        public int $times,
        public string $pageElement,
        public ?string $figmaLink,
    ) {}
}
