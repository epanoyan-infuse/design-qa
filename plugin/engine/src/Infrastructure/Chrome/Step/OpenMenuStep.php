<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome\Step;

use DesignQa\Domain\Model\ScreenKind;
use DesignQa\Domain\Model\ScreenSpec;
use HeadlessChromium\Page;

/**
 * For menu screens: finds the menu button near the top of the page, clicks it, and waits for
 * something to open (the element in the middle of the screen changes, or the button reports it is
 * expanded) and for the page's visible text to stop changing for $settleMs, so a menu whose content
 * loads in (e.g. an Elementor popup built by JS after the click) is not read half-built. Buttons
 * are tried in this order: Elementor popup links, Elementor menu toggles, buttons that control an
 * expandable area, "menu" labels, common toggle and hamburger classes.
 *
 * @throws MenuNotOpened when no button is found or nothing opens within $timeoutMs
 */
final readonly class OpenMenuStep implements PageStep
{
    private const BUTTONS = [
        'a[href*="elementor-action"][href*="popup"]',
        '.elementor-menu-toggle',
        '.e-n-menu-toggle',
        'button[aria-expanded="false"][aria-controls]',
        '[aria-label*="menu" i]',
        '.menu-toggle',
        '.navbar-toggler',
        '[class*="hamburger"]',
        '[class*="burger"]',
    ];

    /** How often the page's visible text is re-checked while waiting for it to settle. */
    private const POLL_MS = 150;

    public function __construct(
        /** How long the visible text must stay unchanged before the menu counts as open. */
        private int $settleMs = 800,
        private int $timeoutMs = 20_000,
    ) {}

    public function prepare(Page $page, ScreenSpec $spec): void
    {
        if ($spec->kind !== ScreenKind::Menu) {
            return;
        }

        $selectors = json_encode(self::BUTTONS, JSON_THROW_ON_ERROR);
        $pollMs = self::POLL_MS;
        $result = $page->evaluate(<<<JS
            (async () => {
                scrollTo(0, 0);
                const nearTop = (e) => {
                    const r = e.getBoundingClientRect();
                    const s = getComputedStyle(e);
                    return r.width > 0 && r.height > 0 && s.visibility === 'visible' && r.bottom > 0 && r.top < innerHeight * 0.25;
                };
                let button = null;
                for (const selector of {$selectors}) {
                    button = [...document.querySelectorAll(selector)].find(nearTop) || null;
                    if (button) break;
                }
                if (!button) return 'no-button';
                const before = document.elementFromPoint(innerWidth / 2, innerHeight / 2);
                button.click();
                // Wait for something to open, then for the page's visible text to stop changing for
                // settleMs (a popup built by JS after the click is not read while still filling in).
                // Leave headroom under the outer PHP-side call timeout so this always returns in time.
                const deadline = Date.now() + {$this->timeoutMs} - 500;
                let opened = false;
                let lastText = null;
                let stableSince = null;
                while (Date.now() < deadline) {
                    await new Promise((resolve) => setTimeout(resolve, {$pollMs}));
                    if (!opened) {
                        const after = document.elementFromPoint(innerWidth / 2, innerHeight / 2);
                        opened = after !== before || button.getAttribute('aria-expanded') === 'true';
                    }
                    if (!opened) continue;
                    const text = document.body.innerText;
                    if (text === lastText) {
                        if (stableSince !== null && Date.now() - stableSince >= {$this->settleMs}) return 'opened';
                    } else {
                        lastText = text;
                        stableSince = Date.now();
                    }
                }
                // Timed out: if something at least opened, read it as is rather than discard it.
                return opened ? 'opened' : 'not-opened';
            })()
            JS)->getReturnValue($this->timeoutMs);

        match ($result) {
            'opened' => null,
            'no-button' => throw new MenuNotOpened('no menu button was found at the top of the page'),
            default => throw new MenuNotOpened('the menu button did not open a menu'),
        };
    }
}
