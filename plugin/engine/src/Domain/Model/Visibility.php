<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

/**
 * When a text counts as visible. One set of rules for both the design and the page, so the two
 * sides are always judged the same way.
 */
final class Visibility
{
    /** A text counts as cut off when less than this share of it is inside its clipping area. */
    public const MIN_VISIBLE_FRACTION = 0.5;

    /** Combined opacity below this is invisible. */
    public const MIN_OPACITY = 0.01;

    /** A text counts as covered when this share of it lies under one opaque layer. */
    public const COVERED_FRACTION = 0.9;

    /** Unicode Private Use Area ranges: where icon fonts put their glyphs. */
    private const ICON_GLYPH_PATTERN = '/^[\x{E000}-\x{F8FF}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}]$/u';

    /**
     * True for one character from an icon font (Unicode Private Use Area), on either side.
     */
    public static function isIconGlyph(string $char): bool
    {
        return preg_match(self::ICON_GLYPH_PATTERN, $char) === 1;
    }

    /**
     * True for texts made only of icon-font glyphs and whitespace.
     */
    public static function isIconOnly(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }
        foreach (mb_str_split($text) as $char) {
            // \s stays ASCII-only even with /u, so \p{Zs} covers a non-breaking space too.
            if (!self::isIconGlyph($char) && preg_match('/^[\s\p{Zs}]$/u', $char) !== 1) {
                return false;
            }
        }

        return true;
    }
}
