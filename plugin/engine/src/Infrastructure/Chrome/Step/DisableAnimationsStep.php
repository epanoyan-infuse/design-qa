<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome\Step;

use DesignQa\Domain\Model\ScreenSpec;
use HeadlessChromium\Page;

/**
 * Makes CSS animations and transitions finish instantly, so texts are read in their final state
 * (not half-faded or mid-slide). Entrance animations still run their JS triggers.
 */
final readonly class DisableAnimationsStep implements PageStep
{
    private const CSS = '*,*::before,*::after{animation-duration:0s!important;animation-delay:0s!important;'
        . 'transition-duration:0s!important;transition-delay:0s!important;scroll-behavior:auto!important}';

    public function __construct(private int $timeoutMs = 10_000) {}

    public function prepare(Page $page, ScreenSpec $spec): void
    {
        $css = json_encode(self::CSS, JSON_THROW_ON_ERROR);
        $page->evaluate(<<<JS
            (() => {
                const style = document.createElement('style');
                style.setAttribute('data-design-qa', '');
                style.textContent = {$css};
                document.head.appendChild(style);
            })()
            JS)->getReturnValue($this->timeoutMs);
    }
}
