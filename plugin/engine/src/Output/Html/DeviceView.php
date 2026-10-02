<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

/**
 * One main tab (Desktop, Tablet, Mobile) with its screen sizes.
 */
final readonly class DeviceView
{
    /**
     * @param non-empty-list<SizeView> $sizes widest first; sub-tabs when there is more than one
     */
    public function __construct(
        public DeviceType $type,
        public array $sizes,
        public int $critical,
        public int $nonCritical,
    ) {}
}
