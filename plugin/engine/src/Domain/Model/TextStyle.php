<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

/**
 * The rendered style of a piece of text. Lengths are CSS pixels.
 */
final readonly class TextStyle
{
    /**
     * @param Color|null  $color      null when the text is not painted with one solid color (gradient, image)
     * @param float|null  $lineHeight null for "auto" / "normal"
     * @param string|null $renderedFontFamily page only: the font actually drawn (the first font of the
     *                                        CSS stack the browser can use), '' when only a generic
     *                                        fallback font is left; null when unknown or for the design
     */
    public function __construct(
        public string $fontFamily,
        public int $fontWeight,
        public float $fontSize,
        public bool $italic,
        public ?Color $color,
        public float $letterSpacing,
        public ?float $lineHeight,
        public TextCase $textCase = TextCase::None,
        public ?string $renderedFontFamily = null,
    ) {}

    /**
     * The style with the largest total weight; equal styles count together wherever they occur.
     * Ties go to the style seen first.
     *
     * @param non-empty-list<array{self, int}> $weighted style and weight (e.g. number of letters)
     */
    public static function mostCommon(array $weighted): self
    {
        /** @var list<array{self, int}> $groups */
        $groups = [];
        foreach ($weighted as [$style, $weight]) {
            foreach ($groups as $i => [$known]) {
                if ($known === $style || $known->equals($style)) {
                    $groups[$i][1] += $weight;
                    continue 2;
                }
            }
            $groups[] = [$style, $weight];
        }
        $best = $groups[0];
        foreach ($groups as $group) {
            if ($group[1] > $best[1]) {
                $best = $group;
            }
        }

        return $best[0];
    }

    public function withLineHeight(?float $lineHeight): self
    {
        return new self($this->fontFamily, $this->fontWeight, $this->fontSize, $this->italic, $this->color, $this->letterSpacing, $lineHeight, $this->textCase, $this->renderedFontFamily);
    }

    public function equals(self $other): bool
    {
        $sameColor = $this->color === null || $other->color === null
            ? $this->color === $other->color
            : $this->color->equals($other->color);

        return $sameColor
            && $this->fontFamily === $other->fontFamily
            && $this->fontWeight === $other->fontWeight
            && abs($this->fontSize - $other->fontSize) < 0.001
            && $this->italic === $other->italic
            && abs($this->letterSpacing - $other->letterSpacing) < 0.001
            && ($this->lineHeight === null ? $other->lineHeight === null : $other->lineHeight !== null && abs($this->lineHeight - $other->lineHeight) < 0.001)
            && $this->textCase === $other->textCase
            && $this->renderedFontFamily === $other->renderedFontFamily;
    }
}
