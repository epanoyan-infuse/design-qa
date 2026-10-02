<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check\Checks;

use DesignQa\Domain\Check\Check;
use DesignQa\Domain\Check\CheckContext;
use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Check\Format;
use DesignQa\Domain\Model\TextStyle;

final class FontWeightCheck implements Check
{
    public function id(): string
    {
        return 'font-weight';
    }

    public function label(): string
    {
        return 'Font weight';
    }

    public function compare(TextStyle $design, TextStyle $page, CheckContext $context, CheckRule $rule): ?Difference
    {
        return $rule->allows($page->fontWeight - $design->fontWeight)
            ? null
            : new Difference(Format::weight($design->fontWeight), Format::weight($page->fontWeight));
    }
}
