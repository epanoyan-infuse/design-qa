<?php

declare(strict_types=1);

namespace DesignQa\Tests\Support;

use DesignQa\Domain\Model\Color;
use DesignQa\Domain\Model\TextStyle;

final class Styles
{
    public static function body(int $weight = 400, bool $italic = false, string $color = '#475569'): TextStyle
    {
        return new TextStyle('Plus Jakarta Sans', $weight, 18.0, $italic, Color::fromHex($color), 0.0, 32.0);
    }
}
