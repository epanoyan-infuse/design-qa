<?php

declare(strict_types=1);

namespace DesignQa\Domain\Matching;

use DesignQa\Domain\Model\TextElement;

/**
 * A design text with more than one equally good page candidate. Never guessed: it goes to the
 * "check manually" list.
 */
final readonly class UncertainMatch
{
    /**
     * @param non-empty-list<TextElement> $candidates
     */
    public function __construct(
        public TextPart $design,
        public array $candidates,
    ) {}
}
