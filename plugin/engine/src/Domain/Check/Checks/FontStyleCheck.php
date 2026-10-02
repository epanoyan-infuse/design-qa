<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check\Checks;

use DesignQa\Domain\Check\Check;
use DesignQa\Domain\Check\CheckContext;
use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Model\TextStyle;

final class FontStyleCheck implements Check
{
    public function id(): string
    {
        return 'font-style';
    }

    public function label(): string
    {
        return 'Italic';
    }

    public function compare(TextStyle $design, TextStyle $page, CheckContext $context, CheckRule $rule): ?Difference
    {
        $name = static fn(bool $italic): string => $italic ? 'Italic' : 'Not italic';

        return $design->italic === $page->italic ? null : new Difference($name($design->italic), $name($page->italic));
    }
}
