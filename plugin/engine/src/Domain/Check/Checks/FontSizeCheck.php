<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check\Checks;

use DesignQa\Domain\Check\Check;
use DesignQa\Domain\Check\CheckContext;
use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Check\Format;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Model\TextStyle;

/**
 * On Tablet-width screens, a CSS clamp() fluid size only ever shows a value interpolated between
 * Mobile and Desktop, not a value anyone chose for Tablet directly — so a mismatch there uses the
 * rule's "tablet_tolerance" (wider than normal) and is reported non-critical, never silently
 * ignored, since a Tablet size that is wildly off can still mean a broken clamp() config.
 */
final class FontSizeCheck implements Check
{
    public function id(): string
    {
        return 'font-size';
    }

    public function label(): string
    {
        return 'Font size';
    }

    public function compare(TextStyle $design, TextStyle $page, CheckContext $context, CheckRule $rule): ?Difference
    {
        $difference = $page->fontSize - $design->fontSize;

        if ($context->tabletBreakpoint) {
            return CheckRule::within($difference, $rule->option('tablet_tolerance', $rule->tolerance))
                ? null
                : new Difference(Format::pixels($design->fontSize), Format::pixels($page->fontSize), Severity::NonCritical);
        }

        return $rule->allows($difference)
            ? null
            : new Difference(Format::pixels($design->fontSize), Format::pixels($page->fontSize));
    }
}
