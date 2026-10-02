<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check\Checks;

use DesignQa\Domain\Check\Check;
use DesignQa\Domain\Check\CheckContext;
use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Model\TextStyle;

/**
 * Tolerance is per color channel (0-255), so ±1 absorbs rounding. Transparency must match too,
 * within the rule's "alpha_tolerance" (0-1). Texts without one solid color (gradients) are not compared.
 */
final class TextColorCheck implements Check
{
    /** Used when config/rules.php does not set "alpha_tolerance". */
    public const DEFAULT_ALPHA_TOLERANCE = 0.02;

    public function id(): string
    {
        return 'color';
    }

    public function label(): string
    {
        return 'Color';
    }

    public function compare(TextStyle $design, TextStyle $page, CheckContext $context, CheckRule $rule): ?Difference
    {
        if ($design->color === null || $page->color === null) {
            return null;
        }
        $same = $rule->allows($design->color->channelDistance($page->color))
            && abs($design->color->alpha - $page->color->alpha) <= $rule->option('alpha_tolerance', self::DEFAULT_ALPHA_TOLERANCE) + 1e-6;

        return $same ? null : new Difference($design->color->toHex(), $page->color->toHex());
    }
}
