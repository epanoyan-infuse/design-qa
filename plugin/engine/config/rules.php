<?php

declare(strict_types=1);

/*
 * How each check is judged. Source: figma-design-qa-investigation.md, sections 10 and 12.
 *
 *   severity         "critical" or "non-critical"
 *   tolerance        largest allowed difference, in the check's unit (px, or 0-255 per color channel)
 *   enabled          false switches a check off without touching code
 *   alpha_tolerance  (color) largest allowed transparency difference, 0-1
 *
 * To add a check: implement it, then add its id here. "missing-text" and "extra-text" are not
 * checks of a matched pair: they judge texts found on only one side (Domain/Check/PresenceRules).
 */

return [
    // Typos, missing or extra words, different wording (Figma "Get the book", page "Buy the book").
    'text' => ['severity' => 'critical'],
    // Also reports a web font that did not load (the page shows a fallback font).
    'font-family' => ['severity' => 'critical'],
    // tablet_tolerance: wider, and reported non-critical, on Elementor's Tablet breakpoint (768-1024px),
    // where a CSS clamp() fluid size only ever shows a value interpolated between mobile and desktop.
    'font-size' => ['severity' => 'critical', 'tolerance' => 0.5, 'tablet_tolerance' => 3],
    'font-weight' => ['severity' => 'critical'],
    'font-style' => ['severity' => 'critical'],
    'color' => ['severity' => 'critical', 'tolerance' => 1, 'alpha_tolerance' => 0.02],
    // A design text with no matching text on the page, and a page text that is not in the design.
    'missing-text' => ['severity' => 'critical'],
    'extra-text' => ['severity' => 'critical'],
    // Non-critical (user decision 2026-09-29; it was critical before, as it can change the number of lines).
    'letter-spacing' => ['severity' => 'non-critical', 'tolerance' => 0.1],
    // Compared only on texts that wrap onto several lines. tablet_tolerance: same clamp()
    // reasoning as font-size above (line height commonly scales with a fluid font size).
    'line-height' => ['severity' => 'non-critical', 'tolerance' => 0.5, 'tablet_tolerance' => 4],
];
