<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma\Parser;

use DesignQa\Domain\Model\Color;

/**
 * Reads colors from Figma paint lists.
 */
final class FigmaPaint
{
    /**
     * The color of the topmost visible paint, or null when that paint is not a solid color
     * (gradient, image) or there is no visible paint.
     *
     * @param list<array<mixed>> $fills
     */
    public static function topSolidColor(array $fills, float $layerOpacity = 1.0): ?Color
    {
        $top = self::topVisible($fills);
        if ($top === null || ($top['type'] ?? null) !== 'SOLID' || !is_array($top['color'] ?? null)) {
            return null;
        }

        $c = $top['color'];
        $channel = static fn(string $k, float $default = 0.0): float => is_int($c[$k] ?? null) || is_float($c[$k] ?? null) ? (float) $c[$k] : $default;
        $paintOpacity = is_int($top['opacity'] ?? null) || is_float($top['opacity'] ?? null) ? (float) $top['opacity'] : 1.0;

        return Color::fromUnitFloats($channel('r'), $channel('g'), $channel('b'), $channel('a', 1.0) * $paintOpacity * $layerOpacity);
    }

    /**
     * True when the fills paint a fully opaque solid color with normal blending, i.e. hide whatever
     * lies underneath. The node's own shape, blend mode and mask flag are checked by the caller.
     *
     * @param list<array<mixed>> $fills
     */
    public static function isOpaqueSolid(array $fills): bool
    {
        $top = self::topVisible($fills);
        $blend = $top['blendMode'] ?? 'NORMAL';

        return ($blend === 'NORMAL' || $blend === 'PASS_THROUGH') && (self::topSolidColor($fills)?->isOpaque() ?? false);
    }

    /**
     * @param list<array<mixed>> $fills
     *
     * @return array<mixed>|null
     */
    private static function topVisible(array $fills): ?array
    {
        for ($i = count($fills) - 1; $i >= 0; --$i) {
            if (($fills[$i]['visible'] ?? true) !== false) {
                return $fills[$i];
            }
        }

        return null;
    }
}
