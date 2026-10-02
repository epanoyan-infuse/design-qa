<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma\Parser;

use DesignQa\Domain\Model\Rect;

/**
 * Typed, read-only access to one node of a Figma REST API document.
 */
final readonly class FigmaNode
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(private array $data) {}

    public function id(): string
    {
        return $this->string('id');
    }

    public function type(): string
    {
        return $this->string('type');
    }

    public function name(): string
    {
        return $this->string('name');
    }

    public function characters(): string
    {
        return $this->string('characters');
    }

    public function isText(): bool
    {
        return $this->type() === 'TEXT';
    }

    public function isVisible(): bool
    {
        return ($this->data['visible'] ?? true) !== false;
    }

    public function opacity(): float
    {
        $opacity = $this->data['opacity'] ?? 1.0;

        return is_int($opacity) || is_float($opacity) ? (float) $opacity : 1.0;
    }

    public function clipsContent(): bool
    {
        return ($this->data['clipsContent'] ?? false) === true;
    }

    public function blendMode(): string
    {
        return $this->string('blendMode', 'PASS_THROUGH');
    }

    /**
     * Absolute position on the Figma canvas.
     */
    public function box(): ?Rect
    {
        return Rect::fromArray($this->data['absoluteBoundingBox'] ?? null);
    }

    /**
     * A mask layer is not painted; it clips the siblings above it.
     */
    public function isMask(): bool
    {
        return ($this->data['isMask'] ?? false) === true;
    }

    /**
     * The raw line height in pixels, also for "auto" line height (used to count lines).
     */
    public function lineHeightPx(): ?float
    {
        $value = $this->style()['lineHeightPx'] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /**
     * @return list<self>
     */
    public function children(): array
    {
        $children = $this->data['children'] ?? [];
        if (!is_array($children)) {
            return [];
        }

        return array_values(array_map(
            static fn(array $child): self => new self($child),
            array_filter($children, is_array(...)),
        ));
    }

    /**
     * Paints, bottom first (Figma paints later entries on top).
     *
     * @return list<array<mixed>>
     */
    public function fills(): array
    {
        return self::listOfArrays($this->data['fills'] ?? []);
    }

    /**
     * @return array<mixed>
     */
    public function style(): array
    {
        $style = $this->data['style'] ?? [];

        return is_array($style) ? $style : [];
    }

    /**
     * Style override id per character (UTF-16 code unit); may be shorter than the text.
     *
     * @return list<int>
     */
    public function characterStyleOverrides(): array
    {
        $overrides = $this->data['characterStyleOverrides'] ?? [];
        if (!is_array($overrides)) {
            return [];
        }

        return array_values(array_map(static fn(mixed $v): int => is_int($v) ? $v : 0, $overrides));
    }

    /**
     * @return array<int, array<mixed>> keyed by override id
     */
    public function styleOverrideTable(): array
    {
        $table = $this->data['styleOverrideTable'] ?? [];
        if (!is_array($table)) {
            return [];
        }
        $result = [];
        foreach ($table as $id => $style) {
            if (is_array($style) && is_numeric($id)) {
                $result[(int) $id] = $style;
            }
        }

        return $result;
    }

    /**
     * @return list<array<mixed>>
     */
    public static function listOfArrays(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    private function string(string $key, string $default = ''): string
    {
        $value = $this->data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
