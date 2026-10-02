<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

use DesignQa\Domain\Model\TextStyle;

/**
 * Compares one property of a design style with the page style. One class per property; severity
 * and tolerance come from the rule (config/rules.php), never from the check itself.
 */
interface Check
{
    /** The id used in config/rules.php, e.g. "font-weight". */
    public function id(): string;

    /** How the property is named in results, e.g. "Font weight". */
    public function label(): string;

    /**
     * @return Difference|null null when the values match within the rule's tolerance, or cannot be compared
     */
    public function compare(TextStyle $design, TextStyle $page, CheckContext $context, CheckRule $rule): ?Difference;
}
