<?php

declare(strict_types=1);

namespace DesignQa\Application\Read;

use DesignQa\Domain\Model\Screen;

/**
 * One design screen and the page rendered at the same width.
 */
final readonly class ScreenPair
{
    public function __construct(
        public Screen $design,
        public Screen $page,
    ) {}
}
