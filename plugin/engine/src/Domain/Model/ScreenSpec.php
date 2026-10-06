<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

use InvalidArgumentException;

/**
 * Which screen to render: taken from a design screen, used to capture the page the same way.
 */
final readonly class ScreenSpec
{
    /**
     * @param int|null    $viewportHeight the screen's own height when only one screenful counts
     *                                    (menu screens); null to read the whole page
     * @param string|null $openText       the one item (e.g. an accordion item's title) this
     *                                    screen's design shows open; null when it shows none, or
     *                                    more than one, open (see OpenItemDetector)
     */
    public function __construct(
        public string $name,
        public int $width,
        public ScreenKind $kind = ScreenKind::Page,
        public ?int $viewportHeight = null,
        public ?string $openText = null,
    ) {
        if ($width < 1) {
            throw new InvalidArgumentException(sprintf('Screen "%s" needs a positive width.', $name));
        }
    }
}
