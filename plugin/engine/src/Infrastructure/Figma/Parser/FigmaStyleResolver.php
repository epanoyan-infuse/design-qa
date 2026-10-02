<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma\Parser;

use DesignQa\Domain\Model\StyleRun;
use DesignQa\Domain\Model\StyleRuns;
use DesignQa\Domain\Model\TextCase;
use DesignQa\Domain\Model\TextStyle;
use InvalidArgumentException;

/**
 * Turns a Figma TEXT node into style runs using the *effective* style of every character:
 * the base "style" merged with its entry in "styleOverrideTable".
 *
 * The old figma-checker read only the base style and reported false errors, e.g. "Audit frameworks"
 * has base weight 400 but every character overridden to 700.
 */
final class FigmaStyleResolver
{
    /**
     * @return non-empty-list<StyleRun>
     */
    public function runs(FigmaNode $node, float $layerOpacity = 1.0): array
    {
        $characters = mb_str_split($node->characters());
        if ($characters === []) {
            throw new InvalidArgumentException(sprintf('Text node %s is empty.', $node->id()));
        }

        $table = $node->styleOverrideTable();
        $styles = [];
        $perCharacter = [];
        foreach ($this->overrideIdPerCharacter($characters, $node->characterStyleOverrides()) as $id) {
            $perCharacter[] = $styles[$id] ??= $this->style($node, $id === 0 ? [] : ($table[$id] ?? []), $layerOpacity);
        }

        return StyleRuns::fromCharacters($perCharacter);
    }

    /**
     * Figma indexes overrides per UTF-16 code unit, so characters outside the Basic Multilingual
     * Plane (emoji) take two entries.
     *
     * @param non-empty-list<string> $characters
     * @param list<int>              $overrides
     *
     * @return non-empty-list<int> one override id per code point
     */
    private function overrideIdPerCharacter(array $characters, array $overrides): array
    {
        $ids = [];
        $unit = 0;
        foreach ($characters as $character) {
            $ids[] = $overrides[$unit] ?? 0;
            $unit += mb_ord($character) > 0xFFFF ? 2 : 1;
        }

        return $ids;
    }

    /**
     * @param array<mixed> $override
     */
    private function style(FigmaNode $node, array $override, float $layerOpacity): TextStyle
    {
        $s = array_merge($node->style(), $override);
        $fills = array_key_exists('fills', $override) ? FigmaNode::listOfArrays($override['fills']) : $node->fills();

        $lineHeight = ($s['lineHeightUnit'] ?? null) === 'INTRINSIC_%' ? null : self::number($s['lineHeightPx'] ?? null);

        return new TextStyle(
            fontFamily: is_string($s['fontFamily'] ?? null) ? $s['fontFamily'] : '',
            fontWeight: (int) (self::number($s['fontWeight'] ?? null) ?? 400),
            fontSize: self::number($s['fontSize'] ?? null) ?? 0.0,
            italic: ($s['italic'] ?? false) === true,
            color: FigmaPaint::topSolidColor($fills, $layerOpacity),
            letterSpacing: self::number($s['letterSpacing'] ?? null) ?? 0.0,
            lineHeight: $lineHeight,
            textCase: match ($s['textCase'] ?? null) {
                'UPPER' => TextCase::Upper,
                'LOWER' => TextCase::Lower,
                'TITLE' => TextCase::Title,
                default => TextCase::None,
            },
        );
    }

    private static function number(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }
}
