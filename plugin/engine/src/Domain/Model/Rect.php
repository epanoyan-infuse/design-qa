<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

/**
 * An axis-aligned rectangle in CSS pixels, relative to the top-left of its screen.
 */
final readonly class Rect
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {}

    /**
     * From an {x, y, width, height} array with numeric values, as Figma and the page script send it.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }
        foreach (['x', 'y', 'width', 'height'] as $key) {
            if (!is_int($data[$key] ?? null) && !is_float($data[$key] ?? null)) {
                return null;
            }
        }

        return new self((float) $data['x'], (float) $data['y'], (float) $data['width'], (float) $data['height']);
    }

    public function right(): float
    {
        return $this->x + $this->width;
    }

    public function bottom(): float
    {
        return $this->y + $this->height;
    }

    public function area(): float
    {
        return max(0.0, $this->width) * max(0.0, $this->height);
    }

    public function isEmpty(): bool
    {
        return $this->width <= 0.0 || $this->height <= 0.0;
    }

    public function translate(float $dx, float $dy): self
    {
        return new self($this->x + $dx, $this->y + $dy, $this->width, $this->height);
    }

    public function intersect(self $other): ?self
    {
        $x = max($this->x, $other->x);
        $y = max($this->y, $other->y);
        $right = min($this->right(), $other->right());
        $bottom = min($this->bottom(), $other->bottom());

        return $right > $x && $bottom > $y ? new self($x, $y, $right - $x, $bottom - $y) : null;
    }

    /**
     * Share of this rectangle's area that lies inside the other one (0-1).
     */
    public function fractionInside(self $other): float
    {
        $area = $this->area();
        if ($area <= 0.0) {
            return 0.0;
        }

        return ($this->intersect($other)?->area() ?? 0.0) / $area;
    }

    public function union(self $other): self
    {
        $x = min($this->x, $other->x);
        $y = min($this->y, $other->y);

        return new self($x, $y, max($this->right(), $other->right()) - $x, max($this->bottom(), $other->bottom()) - $y);
    }

    /**
     * @param non-empty-list<self> $rects
     */
    public static function unionAll(array $rects): self
    {
        $union = array_shift($rects);
        foreach ($rects as $rect) {
            $union = $union->union($rect);
        }

        return $union;
    }
}
