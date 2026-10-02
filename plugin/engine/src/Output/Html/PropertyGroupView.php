<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

use DesignQa\Domain\Check\Severity;

/**
 * One property (e.g. "Font family", "Color"), with its issues across every page section, in page
 * order. The "group by property" view of the Issues tab: the same rows as the "group by section"
 * view, regrouped.
 */
final readonly class PropertyGroupView
{
    /**
     * @param non-empty-list<SectionView> $sections this property's rows, by page section, in page order
     */
    public function __construct(
        public string $label,
        public array $sections,
    ) {}

    public function count(Severity $severity): int
    {
        return array_sum(array_map(static fn(SectionView $s): int => $s->count($severity), $this->sections));
    }
}
