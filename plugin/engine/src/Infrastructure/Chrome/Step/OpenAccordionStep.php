<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome\Step;

use DesignQa\Domain\Model\ScreenSpec;
use HeadlessChromium\Page;

/**
 * When the design shows one item of an accordion, tabs, or similar component open
 * (ScreenSpec::$openText, from OpenItemDetector), finds that item's trigger near the top of a short
 * list of known selectors, clicks it, and waits for the page's visible text to settle — the same
 * click-and-settle approach as OpenMenuStep, for the same reason: a panel that fills in by JS after
 * the click must not be read half-built.
 *
 * @throws AccordionNotOpened when no matching trigger is found or nothing visibly changes in time
 */
final readonly class OpenAccordionStep implements PageStep
{
    /** Tried in this order: Elementor's Nested Accordion first (the one seen in practice), then
     *  generic accordion/tab/disclosure patterns. */
    private const TRIGGERS = [
        '.e-n-accordion-item-title',
        '.elementor-tab-title',
        '.elementor-accordion-title',
        '.accordion-header',
        '.accordion-title',
        'summary',
        '[aria-expanded]',
    ];

    private const POLL_MS = 150;

    public function __construct(
        private int $settleMs = 800,
        private int $timeoutMs = 20_000,
    ) {}

    public function prepare(Page $page, ScreenSpec $spec): void
    {
        if ($spec->openText === null) {
            return;
        }

        $target = json_encode($spec->openText, JSON_THROW_ON_ERROR);
        $selectors = json_encode(self::TRIGGERS, JSON_THROW_ON_ERROR);
        $pollMs = self::POLL_MS;
        $result = $page->evaluate(<<<JS
            (async () => {
                // Same normalization as the domain's TextNormalizer: letters/digits only, single spaces.
                const key = (s) => s.toLowerCase().replace(/[^\\p{L}\\p{N}]+/gu, ' ').trim();
                const wanted = key({$target});
                let trigger = null;
                for (const selector of {$selectors}) {
                    trigger = [...document.querySelectorAll(selector)].find((e) => key(e.textContent || '') === wanted) || null;
                    if (trigger) break;
                }
                if (!trigger) return 'no-trigger';
                const before = document.body.innerText;
                trigger.click();
                const deadline = Date.now() + {$this->timeoutMs} - 500;
                let changed = false;
                let lastText = null;
                let stableSince = null;
                while (Date.now() < deadline) {
                    await new Promise((resolve) => setTimeout(resolve, {$pollMs}));
                    const text = document.body.innerText;
                    if (!changed) {
                        changed = text !== before || trigger.getAttribute('aria-expanded') === 'true';
                    }
                    if (!changed) continue;
                    if (text === lastText) {
                        if (stableSince !== null && Date.now() - stableSince >= {$this->settleMs}) return 'opened';
                    } else {
                        lastText = text;
                        stableSince = Date.now();
                    }
                }
                return changed ? 'opened' : 'not-opened';
            })()
            JS)->getReturnValue($this->timeoutMs);

        match ($result) {
            'opened' => null,
            'no-trigger' => throw new AccordionNotOpened(sprintf('no trigger found for "%s"', $spec->openText)),
            default => throw new AccordionNotOpened(sprintf('clicking the trigger for "%s" opened nothing', $spec->openText)),
        };
    }
}
