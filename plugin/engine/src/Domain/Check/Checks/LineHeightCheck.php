<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check\Checks;

use DesignQa\Domain\Check\Check;
use DesignQa\Domain\Check\CheckContext;
use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Check\Format;
use DesignQa\Domain\Model\TextStyle;

/**
 * Only compared on texts that wrap onto several lines on both sides: on a single line the value
 * only changes the box height (layout, not text), which the old checker wrongly reported.
 * "Auto" / "normal" line height has no fixed value and is not compared.
 *
 * On Tablet-width screens, line height commonly scales with a CSS clamp() fluid font size, so it
 * only ever shows a value interpolated between Mobile and Desktop there: the rule's
 * "tablet_tolerance" (wider than normal) applies instead of the normal tolerance.
 */
final class LineHeightCheck implements Check
{
    public function id(): string
    {
        return 'line-height';
    }

    public function label(): string
    {
        return 'Line height';
    }

    public function compare(TextStyle $design, TextStyle $page, CheckContext $context, CheckRule $rule): ?Difference
    {
        if (!$context->multiLine || $design->lineHeight === null || $page->lineHeight === null) {
            return null;
        }

        $difference = $page->lineHeight - $design->lineHeight;
        $tolerance = $context->tabletBreakpoint ? $rule->option('tablet_tolerance', $rule->tolerance) : $rule->tolerance;

        return CheckRule::within($difference, $tolerance)
            ? null
            : new Difference(Format::pixels($design->lineHeight), Format::pixels($page->lineHeight));
    }
}
