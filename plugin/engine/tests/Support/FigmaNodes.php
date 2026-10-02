<?php

declare(strict_types=1);

namespace DesignQa\Tests\Support;

/**
 * Builds minimal Figma REST API node arrays for tests.
 */
final class FigmaNodes
{
    /**
     * @param list<array<mixed>> $children
     * @param array<mixed>       $extra
     *
     * @return array<mixed>
     */
    public static function frame(string $name, float $x, float $y, float $w, float $h, array $children = [], array $extra = []): array
    {
        return [
            'id' => 'f:' . $name,
            'name' => $name,
            'type' => 'FRAME',
            'absoluteBoundingBox' => ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h],
            'clipsContent' => true,
            'fills' => [],
            'children' => $children,
            ...$extra,
        ];
    }

    /**
     * @param array<mixed> $extra
     *
     * @return array<mixed>
     */
    public static function text(string $id, string $characters, float $x, float $y, float $w = 100, float $h = 20, array $extra = []): array
    {
        return [
            'id' => $id,
            'name' => $characters,
            'type' => 'TEXT',
            'characters' => $characters,
            'absoluteBoundingBox' => ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h],
            'style' => [
                'fontFamily' => 'Plus Jakarta Sans',
                'fontWeight' => 400,
                'fontSize' => 16,
                'letterSpacing' => 0,
                'lineHeightPx' => 20,
                'lineHeightUnit' => 'PIXELS',
            ],
            'fills' => [self::solid(0, 0.2196, 0.4039)],
            ...$extra,
        ];
    }

    /**
     * @return array<mixed>
     */
    public static function solid(float $r, float $g, float $b, float $a = 1.0, ?float $opacity = null): array
    {
        return ['type' => 'SOLID', 'blendMode' => 'NORMAL', 'color' => ['r' => $r, 'g' => $g, 'b' => $b, 'a' => $a], ...($opacity === null ? [] : ['opacity' => $opacity])];
    }

    /**
     * @param list<array<mixed>> $children
     *
     * @return array<mixed>
     */
    public static function section(string $name, array $children): array
    {
        return ['id' => 's:1', 'name' => $name, 'type' => 'SECTION', 'children' => $children];
    }

    /**
     * @param array<mixed> $document
     *
     * @return array<mixed>
     */
    public static function response(string $nodeId, array $document, string $fileName = 'Test file'): array
    {
        return ['name' => $fileName, 'nodes' => [$nodeId => ['document' => $document]]];
    }
}
