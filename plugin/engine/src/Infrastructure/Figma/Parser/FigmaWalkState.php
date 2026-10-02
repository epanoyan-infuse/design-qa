<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma\Parser;

use DesignQa\Domain\Model\ExclusionReason;
use DesignQa\Domain\Model\Rect;

/**
 * Mutable state of one walk over a Figma screen: paint order, found texts and opaque layers.
 *
 * @internal used by FigmaTextCollector only
 */
final class FigmaWalkState
{
    /** @var list<array{order: int, node: FigmaNode, box: ?Rect, opacity: float, path: list<string>, pathIds: list<string>, reason: ?ExclusionReason}> */
    public array $texts = [];

    /** @var list<array{order: int, box: Rect}> */
    public array $occluders = [];

    private int $order = 0;

    public function nextOrder(): int
    {
        return $this->order++;
    }
}
