<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

use DesignQa\Domain\Check\Severity;

final readonly class SectionView
{
    /**
     * @param non-empty-list<RowView> $rows critical first, then in page order
     */
    public function __construct(
        public string $label,
        public array $rows,
    ) {}

    public function count(Severity $severity): int
    {
        return count(array_filter($this->rows, static fn(RowView $r): bool => $r->severity === $severity));
    }
}
