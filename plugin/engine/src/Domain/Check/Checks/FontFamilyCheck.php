<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check\Checks;

use DesignQa\Domain\Check\Check;
use DesignQa\Domain\Check\CheckContext;
use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Model\TextStyle;

/**
 * The font the page actually draws, compared with the design's font. CSS only says which fonts
 * are asked for; when the first one did not load, the browser uses the next one in the list, or a
 * generic fallback font.
 */
final class FontFamilyCheck implements Check
{
    /**
     * A page font is sometimes loaded as a separate named family per weight (e.g. "Inter SemiBold"
     * instead of "Inter" + font-weight: 600) rather than as one variable/multi-weight family. That
     * weight is already compared on its own by FontWeightCheck, so a trailing weight word here is
     * ignored: otherwise the same weight would be reported twice, once correctly and once as a
     * false "different font" because of how the page happened to name the font file.
     */
    private const WEIGHT_WORDS = [
        'thin', 'hairline', 'extralight', 'ultralight', 'light', 'regular', 'normal',
        'medium', 'semibold', 'demibold', 'bold', 'extrabold', 'ultrabold', 'black', 'heavy',
    ];

    public function id(): string
    {
        return 'font-family';
    }

    public function label(): string
    {
        return 'Font family';
    }

    public function compare(TextStyle $design, TextStyle $page, CheckContext $context, CheckRule $rule): ?Difference
    {
        $asked = $page->fontFamily;
        $drawn = $page->renderedFontFamily ?? $asked; // unknown: assume the first font was drawn

        if ($drawn !== '' && self::normalize($drawn) === self::normalize($design->fontFamily)) {
            return null;
        }

        $shown = match (true) {
            $drawn === '' => sprintf('%s (not loaded, a fallback font is shown)', $asked),
            self::normalize($drawn) !== self::normalize($asked) => sprintf('%s (%s did not load)', $drawn, $asked),
            default => $drawn,
        };

        return new Difference($design->fontFamily, $shown);
    }

    private static function normalize(string $family): string
    {
        $family = mb_strtolower(trim(str_replace(['"', "'"], '', $family)));
        // A space, hyphen or underscore all separate words in a font file's own name
        // ("Inter SemiBold", "Lora-Medium", "Lora_Medium").
        $words = array_values(array_filter((array) preg_split('/[\s\-_]+/u', $family)));
        if (count($words) > 1 && in_array(end($words), self::WEIGHT_WORDS, true)) {
            array_pop($words);
        }

        return implode(' ', $words);
    }
}
