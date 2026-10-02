<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

use InvalidArgumentException;

/**
 * One visible text, from either the design or the page: its characters, style runs and position.
 */
final readonly class TextElement
{
    /**
     * @param non-empty-list<StyleRun> $runs cover the content without gaps or overlaps, in order
     * @param list<string>             $path    where the text sits (Figma layer names or DOM selectors), outermost first
     * @param list<string>             $pathIds ids of the same layers when known (Figma), to tell apart layers with equal names
     */
    public function __construct(
        public string $id,
        public string $content,
        public array $runs,
        public Rect $box,
        public int $lineCount,
        public array $path = [],
        public array $pathIds = [],
    ) {
        $expected = 0;
        foreach ($runs as $run) {
            if ($run->start !== $expected) {
                throw new InvalidArgumentException(sprintf('Style runs of text "%s" have a gap or overlap at %d.', $id, $expected));
            }
            $expected = $run->end();
        }
        if ($expected !== mb_strlen($content)) {
            throw new InvalidArgumentException(sprintf('Style runs of text "%s" cover %d of %d characters.', $id, $expected, mb_strlen($content)));
        }
        if ($lineCount < 1) {
            throw new InvalidArgumentException(sprintf('Text "%s" must have at least one line.', $id));
        }
    }

    /**
     * The style that covers the most visible (non-whitespace) characters, counting equal styles in
     * separate runs together.
     */
    public function dominantStyle(): TextStyle
    {
        return TextStyle::mostCommon(array_map(fn(StyleRun $run): array => [$run->style, $this->visibleLength($run)], $this->runs));
    }

    public function hasMixedStyles(): bool
    {
        $dominant = $this->dominantStyle();
        foreach ($this->runs as $run) {
            if ($this->visibleLength($run) > 0 && !$run->style->equals($dominant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every character with its offset and style, in order.
     *
     * @return list<array{int, string, TextStyle}>
     */
    public function styledCharacters(): array
    {
        $characters = [];
        foreach ($this->runs as $run) {
            foreach (mb_str_split($this->textOf($run)) as $i => $char) {
                $characters[] = [$run->start + $i, $char, $run->style];
            }
        }

        return $characters;
    }

    public function isMultiLine(): bool
    {
        return $this->lineCount > 1;
    }

    public function textOf(StyleRun $run): string
    {
        return mb_substr($this->content, $run->start, $run->length);
    }

    /**
     * Content with whitespace collapsed, for display and matching.
     */
    public function normalizedContent(): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $this->content));
    }

    private function visibleLength(StyleRun $run): int
    {
        return mb_strlen((string) preg_replace('/\s+/u', '', $this->textOf($run)));
    }
}
