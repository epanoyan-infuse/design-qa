<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

/**
 * A mismatch of one property, with both values formatted for people ("Bold (700)", "#003867").
 */
final readonly class Difference
{
    public function __construct(
        public string $design,
        public string $page,
        /** Overrides the rule's configured severity for this one difference; null keeps it. */
        public ?Severity $severity = null,
    ) {}
}
