<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

use InvalidArgumentException;

/**
 * An sRGB color with 0-255 channels and 0-1 alpha.
 */
final readonly class Color
{
    public function __construct(
        public int $red,
        public int $green,
        public int $blue,
        public float $alpha = 1.0,
    ) {
        foreach ([$red, $green, $blue] as $channel) {
            if ($channel < 0 || $channel > 255) {
                throw new InvalidArgumentException(sprintf('Color channel %d is outside 0-255.', $channel));
            }
        }
        if ($alpha < 0.0 || $alpha > 1.0) {
            throw new InvalidArgumentException(sprintf('Alpha %s is outside 0-1.', $alpha));
        }
    }

    /**
     * From 0-1 floats, as Figma stores colors.
     */
    public static function fromUnitFloats(float $red, float $green, float $blue, float $alpha = 1.0): self
    {
        $channel = static fn(float $v): int => (int) round(max(0.0, min(1.0, $v)) * 255);

        return new self($channel($red), $channel($green), $channel($blue), round(max(0.0, min(1.0, $alpha)), 4));
    }

    public static function fromHex(string $hex): self
    {
        if (preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})?$/i', $hex, $m) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid hex color "%s".', $hex));
        }
        $alpha = isset($m[4]) ? round(hexdec($m[4]) / 255, 4) : 1.0;

        return new self((int) hexdec($m[1]), (int) hexdec($m[2]), (int) hexdec($m[3]), $alpha);
    }

    /**
     * From a computed CSS color: "rgb(1, 2, 3)", "rgba(1, 2, 3, 0.5)" or "rgb(1 2 3 / 50%)".
     */
    public static function fromCss(string $css): self
    {
        $pattern = '/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:\s*[,\/]\s*([\d.]+)(%?))?\s*\)$/i';
        if (preg_match($pattern, trim($css), $m) !== 1) {
            throw new InvalidArgumentException(sprintf('Unsupported CSS color "%s".', $css));
        }
        $alpha = 1.0;
        if (isset($m[4]) && $m[4] !== '') {
            $alpha = (float) $m[4] / (($m[5] ?? '') === '%' ? 100 : 1);
        }
        $channel = static fn(string $v): int => (int) round(max(0.0, min(255.0, (float) $v)));

        return new self($channel($m[1]), $channel($m[2]), $channel($m[3]), round(max(0.0, min(1.0, $alpha)), 4));
    }

    public function withAlphaMultiplied(float $factor): self
    {
        return new self($this->red, $this->green, $this->blue, round(max(0.0, min(1.0, $this->alpha * $factor)), 4));
    }

    public function isOpaque(): bool
    {
        return $this->alpha >= 0.999;
    }

    /**
     * Largest difference of any one channel (0-255), the unit of the color tolerance.
     */
    public function channelDistance(self $other): int
    {
        return max(abs($this->red - $other->red), abs($this->green - $other->green), abs($this->blue - $other->blue));
    }

    /**
     * "#012A4D", or "#012A4D 50%" for a translucent color.
     */
    public function toHex(): string
    {
        $hex = sprintf('#%02X%02X%02X', $this->red, $this->green, $this->blue);

        return $this->isOpaque() ? $hex : sprintf('%s %d%%', $hex, (int) round($this->alpha * 100));
    }

    public function equals(self $other): bool
    {
        return $this->channelDistance($other) === 0 && abs($this->alpha - $other->alpha) < 0.001;
    }
}
