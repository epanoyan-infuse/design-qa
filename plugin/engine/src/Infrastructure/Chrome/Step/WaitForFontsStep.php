<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome\Step;

use DesignQa\Domain\Model\ScreenSpec;
use HeadlessChromium\Page;

/**
 * Waits until web fonts have loaded, so text is measured in its real font.
 */
final readonly class WaitForFontsStep implements PageStep
{
    public function __construct(private int $timeoutMs = 15_000) {}

    public function prepare(Page $page, ScreenSpec $spec): void
    {
        $page->evaluate('document.fonts.ready.then(() => true)')->getReturnValue($this->timeoutMs);
    }
}
