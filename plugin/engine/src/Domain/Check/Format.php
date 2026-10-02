<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

/**
 * Formats values the way developers read them in Figma and in the browser.
 */
final class Format
{
    private const WEIGHT_NAMES = [
        100 => 'Thin',
        200 => 'ExtraLight',
        300 => 'Light',
        400 => 'Regular',
        500 => 'Medium',
        600 => 'SemiBold',
        700 => 'Bold',
        800 => 'ExtraBold',
        900 => 'Black',
    ];

    public static function pixels(float $value): string
    {
        return self::number($value) . 'px';
    }

    public static function weight(int $weight): string
    {
        $name = self::WEIGHT_NAMES[$weight] ?? null;

        return $name === null ? (string) $weight : sprintf('%s (%d)', $name, $weight);
    }

    public static function number(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
