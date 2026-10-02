<?php

declare(strict_types=1);

namespace DesignQa\Application\Read;

use DesignQa\Domain\Model\Screen;

/**
 * A design screen that was not rendered, with the reason (e.g. menu screens in the 2-week version).
 */
final readonly class SkippedScreen
{
    public function __construct(
        public Screen $design,
        public string $reason,
    ) {}
}
