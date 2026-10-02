<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

use DesignQa\Domain\Matching\TextMatch;

/**
 * Compares something about a matched text as a whole (e.g. its wording), not letter by letter
 * like a style Check. Severity comes from the rule in config/rules.php.
 */
interface TextCheck
{
    /** The id used in config/rules.php, e.g. "text". */
    public function id(): string;

    /** How the check is named in results, e.g. "Text". */
    public function label(): string;

    public function compare(TextMatch $match, CheckRule $rule): ?Difference;
}
