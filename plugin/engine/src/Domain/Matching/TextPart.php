<?php

declare(strict_types=1);

namespace DesignQa\Domain\Matching;

use DesignQa\Domain\Model\TextElement;
use DesignQa\Domain\Model\TextStyle;
use InvalidArgumentException;

/**
 * A text, or one paragraph of it. Figma often keeps several paragraphs in one text layer while the
 * page puts each in its own <p>; each paragraph is then matched on its own.
 */
final readonly class TextPart
{
    public function __construct(
        public TextElement $text,
        public int $start,
        public int $length,
    ) {
        if ($start < 0 || $length < 1 || $start + $length > mb_strlen($text->content)) {
            throw new InvalidArgumentException(sprintf('Invalid part %d+%d of text "%s".', $start, $length, $text->id));
        }
    }

    public static function whole(TextElement $text): self
    {
        return new self($text, 0, mb_strlen($text->content));
    }

    /**
     * The text's paragraphs (split at any line break: \n, \r, U+2028, U+2029), without surrounding
     * whitespace.
     *
     * @return list<self>
     */
    public static function paragraphs(TextElement $text): array
    {
        $parts = [];
        $start = null;
        $end = null;
        foreach (mb_str_split($text->content) as $i => $char) {
            if (in_array($char, ["\n", "\r", "\u{2028}", "\u{2029}"], true)) {
                if ($start !== null && $end !== null) {
                    $parts[] = new self($text, $start, $end - $start + 1);
                }
                [$start, $end] = [null, null];
                continue;
            }
            if (!self::isWhitespace($char)) {
                $start ??= $i;
                $end = $i;
            }
        }
        if ($start !== null && $end !== null) {
            $parts[] = new self($text, $start, $end - $start + 1);
        }

        return $parts;
    }

    /**
     * Unicode-aware whitespace: PHP's \s stays ASCII-only even with the /u modifier, so a non-
     * breaking space or other Unicode space separator (\p{Zs}) would otherwise count as content.
     */
    private static function isWhitespace(string $char): bool
    {
        return preg_match('/^[\s\p{Zs}]$/u', $char) === 1;
    }

    public function isWhole(): bool
    {
        return $this->start === 0 && $this->length === mb_strlen($this->text->content);
    }

    /**
     * Whether this part alone is likely to span more than one line. Figma only gives a line count
     * for the whole text layer (box height / line height), not per paragraph, so when this part is
     * one of several paragraphs in that layer, its own line count is estimated from its share of
     * the paragraphs' combined length (accuracy rule 6: line height only where visibly multi-line).
     */
    public function isMultiLine(): bool
    {
        if (!$this->text->isMultiLine() || $this->isWhole()) {
            return $this->text->isMultiLine();
        }
        $paragraphs = self::paragraphs($this->text);
        $total = array_sum(array_map(static fn(self $p): int => $p->length, $paragraphs));

        return $total > 0 && (int) round($this->text->lineCount * $this->length / $total) > 1;
    }

    public function content(): string
    {
        return mb_substr($this->text->content, $this->start, $this->length);
    }

    /**
     * Content with whitespace collapsed, for display and matching.
     */
    public function normalizedContent(): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $this->content()));
    }

    /**
     * Letters and digits with their offset in the text and their style: the characters used to line
     * up a design text with its page text (punctuation and spacing may differ).
     *
     * @return list<array{int, string, TextStyle}>
     */
    public function letters(): array
    {
        $end = $this->start + $this->length;
        $letters = [];
        foreach ($this->text->styledCharacters() as [$offset, $char, $style]) {
            if ($offset >= $this->start && $offset < $end && preg_match('/^[\p{L}\p{N}]$/u', $char) === 1) {
                $letters[] = [$offset, $char, $style];
            }
        }

        return $letters;
    }
}
