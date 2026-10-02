<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

use InvalidArgumentException;

/**
 * A stretch of a text's characters that share one style.
 * Offsets count Unicode code points in the owning TextElement's content.
 */
final readonly class StyleRun
{
    public function __construct(
        public int $start,
        public int $length,
        public TextStyle $style,
    ) {
        if ($start < 0 || $length < 1) {
            throw new InvalidArgumentException(sprintf('Invalid style run %d+%d.', $start, $length));
        }
    }

    public function end(): int
    {
        return $this->start + $this->length;
    }
}
