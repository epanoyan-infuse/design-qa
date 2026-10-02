<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check\Checks;

use DesignQa\Domain\Check\Check;
use DesignQa\Domain\Check\CheckContext;
use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Check\Format;
use DesignQa\Domain\Model\TextStyle;

final class LetterSpacingCheck implements Check
{
    public function id(): string
    {
        return 'letter-spacing';
    }

    public function label(): string
    {
        return 'Letter spacing';
    }

    public function compare(TextStyle $design, TextStyle $page, CheckContext $context, CheckRule $rule): ?Difference
    {
        return $rule->allows($page->letterSpacing - $design->letterSpacing)
            ? null
            : new Difference(Format::pixels($design->letterSpacing), Format::pixels($page->letterSpacing));
    }
}
