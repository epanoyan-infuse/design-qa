<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome\Step;

use DesignQa\Domain\Model\ScreenSpec;
use HeadlessChromium\Page;

/**
 * Scrolls through the whole page and back to the top, so lazy-loaded content and
 * "appear on scroll" elements (e.g. Elementor entrance animations) become visible.
 *
 * Stops after $maxScrollPx, so pages that keep growing (infinite scroll) still finish.
 */
final readonly class ScrollThroughStep implements PageStep
{
    public function __construct(
        private int $pauseMs = 150,
        private int $settleMs = 1_000,
        private int $timeoutMs = 60_000,
        private int $maxScrollPx = 40_000,
    ) {}

    public function prepare(Page $page, ScreenSpec $spec): void
    {
        $page->evaluate(<<<JS
            (async () => {
                const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
                const step = Math.max(200, Math.floor(innerHeight * 0.8));
                for (let y = 0; y < Math.min(document.documentElement.scrollHeight, {$this->maxScrollPx}); y += step) {
                    scrollTo(0, y);
                    await wait({$this->pauseMs});
                }
                scrollTo(0, 0);
                await wait({$this->settleMs});
                return true;
            })()
            JS)->getReturnValue($this->timeoutMs);
    }
}
