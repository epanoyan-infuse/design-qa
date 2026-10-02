<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

/**
 * Everything read from one screen of the design, or from the page rendered at that screen's width.
 */
final readonly class Screen
{
    /**
     * @param list<TextElement>  $texts    visible texts, in reading order (top to bottom)
     * @param list<ExcludedText> $excluded texts found but not compared, with the reason
     * @param string|null        $notCheckedReason set when the screen could not be read (e.g. the menu did not open)
     */
    public function __construct(
        public ScreenSpec $spec,
        public float $height,
        public array $texts,
        public array $excluded = [],
        public ?string $notCheckedReason = null,
    ) {}

    public function name(): string
    {
        return $this->spec->name;
    }

    public function width(): int
    {
        return $this->spec->width;
    }
}
