<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome\Step;

use DesignQa\Domain\Model\ScreenSpec;
use HeadlessChromium\Page;

/**
 * Something done to a loaded page before its texts are read (scrolling, waiting, opening a menu).
 * Steps run in the order they are registered.
 */
interface PageStep
{
    public function prepare(Page $page, ScreenSpec $spec): void;
}
