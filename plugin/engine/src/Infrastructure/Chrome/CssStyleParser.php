<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome;

use DesignQa\Domain\Model\Color;
use DesignQa\Domain\Model\TextCase;
use DesignQa\Domain\Model\TextStyle;
use InvalidArgumentException;

/**
 * Converts computed CSS values (as returned by getComputedStyle) into a TextStyle.
 *
 * The text color is what paints the glyphs: -webkit-text-fill-color when set, else color. Text
 * painted by its background (background-clip: text with a transparent fill, e.g. gradient
 * headings) has no solid color (null), like gradient text in Figma.
 */
final class CssStyleParser
{
    /**
     * @param array<mixed> $css     fontFamily, fontWeight, fontSize, fontStyle, color, colorSrgb, textFillColor,
     *                              textFillColorSrgb, backgroundClip, letterSpacing, lineHeight, textTransform
     * @param float        $opacity combined opacity of the element and its ancestors
     */
    public function parse(array $css, float $opacity = 1.0): TextStyle
    {
        return new TextStyle(
            fontFamily: $this->firstFamily(self::string($css, 'fontFamily')),
            fontWeight: $this->weight(self::string($css, 'fontWeight')),
            fontSize: $this->pixels(self::string($css, 'fontSize')) ?? 0.0,
            italic: $this->italic(self::string($css, 'fontStyle')),
            color: $this->paint($css)?->withAlphaMultiplied($opacity),
            letterSpacing: $this->pixels(self::string($css, 'letterSpacing')) ?? 0.0,
            lineHeight: $this->pixels(self::string($css, 'lineHeight')),
            textCase: match (self::string($css, 'textTransform')) {
                'uppercase' => TextCase::Upper,
                'lowercase' => TextCase::Lower,
                'capitalize' => TextCase::Title,
                default => TextCase::None,
            },
            renderedFontFamily: $this->renderedFamily($css['fontStack'] ?? null),
        );
    }

    /**
     * '"Plus Jakarta Sans", sans-serif' → 'Plus Jakarta Sans'
     */
    public function firstFamily(string $fontFamily): string
    {
        if (preg_match('/^\s*(?:"([^"]*)"|\'([^\']*)\'|([^,]*))/', $fontFamily, $m) !== 1) {
            return '';
        }

        return trim($m[1] !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '')));
    }

    /**
     * "16px" → 16.0; "normal" and anything that is not pixels → null.
     */
    public function pixels(string $value): ?float
    {
        return preg_match('/^(-?\d+(?:\.\d+)?(?:e-?\d+)?)px$/i', trim($value), $m) === 1 ? (float) $m[1] : null;
    }

    private function weight(string $value): int
    {
        return match ($value) {
            'normal' => 400,
            'bold' => 700,
            default => is_numeric($value) ? (int) round((float) $value) : 400,
        };
    }

    /** CSS generic and system font keywords: the browser picks a font itself. */
    private const GENERIC_FAMILIES = [
        'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'math', 'emoji', 'fangsong',
        'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', '-apple-system', 'blinkmacsystemfont',
    ];

    /**
     * The font actually drawn, from the page script's raw per-family measurements: the first family
     * of the stack that draws the text; '' when none of the named fonts does (a generic fallback is
     * shown); null when unknown or when the stack starts with a generic/system font.
     */
    private function renderedFamily(mixed $stack): ?string
    {
        if (!is_array($stack) || $stack === []) {
            return null;
        }
        foreach (array_values($stack) as $i => $entry) {
            $family = is_array($entry) && is_string($entry['family'] ?? null) ? trim($entry['family'], " \t\"'") : '';
            if ($family === '') {
                return null;
            }
            if (in_array(strtolower($family), self::GENERIC_FAMILIES, true)) {
                return $i === 0 ? null : '';
            }
            if (($entry['draws'] ?? false) === true) {
                return $family;
            }
        }

        return '';
    }

    private function italic(string $value): bool
    {
        return $value === 'italic' || str_starts_with($value, 'oblique');
    }

    /**
     * @param array<mixed> $css
     */
    private function paint(array $css): ?Color
    {
        $fill = $this->color(self::string($css, 'textFillColor'), $css['textFillColorSrgb'] ?? null);
        $color = $fill ?? $this->color(self::string($css, 'color'), $css['colorSrgb'] ?? null);

        if ($color !== null && $color->alpha < 0.01 && self::string($css, 'backgroundClip') === 'text') {
            return null; // glyphs show the background (gradient or image)
        }

        return $color;
    }

    /**
     * @param mixed $srgb [r, g, b, alpha] as converted by the browser, for colors not written as rgb()
     */
    private function color(string $css, mixed $srgb): ?Color
    {
        if ($css !== '') {
            try {
                return Color::fromCss($css);
            } catch (InvalidArgumentException) {
                // not rgb(): fall back to the browser's sRGB conversion
            }
        }
        if (is_array($srgb) && count($srgb) === 4 && array_filter($srgb, static fn(mixed $v): bool => is_int($v) || is_float($v)) === $srgb) {
            [$r, $g, $b, $a] = array_map(floatval(...), $srgb);

            return new Color((int) round($r), (int) round($g), (int) round($b), round(max(0.0, min(1.0, $a)), 4));
        }

        return null;
    }

    /**
     * @param array<mixed> $css
     */
    private static function string(array $css, string $key): string
    {
        $value = $css[$key] ?? '';

        return is_string($value) ? trim($value) : (is_int($value) || is_float($value) ? (string) $value : '');
    }
}
