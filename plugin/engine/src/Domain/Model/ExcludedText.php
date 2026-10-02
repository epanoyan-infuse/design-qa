<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

/**
 * A text that was found but deliberately not compared, with the reason. Keeps coverage honest:
 * nothing is dropped silently.
 */
final readonly class ExcludedText
{
    public function __construct(
        public string $id,
        public string $content,
        public ExclusionReason $reason,
    ) {}
}
