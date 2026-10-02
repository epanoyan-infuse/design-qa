<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

use DesignQa\Domain\Matching\TextMatch;

/**
 * One property of one matched text that does not match the design.
 */
final readonly class Finding
{
    /**
     * @param string|null $part     the affected words, when only part of the text differs
     * @param int         $affected letters with this difference
     * @param int         $total    letters compared in the text
     */
    public function __construct(
        public string $checkId,
        public string $label,
        public Severity $severity,
        public Difference $difference,
        public TextMatch $match,
        public ?string $part,
        public int $affected,
        public int $total,
    ) {}

    /** The design text, whitespace collapsed. */
    public function text(): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $this->match->design->content()));
    }
}
