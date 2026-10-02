<?php

declare(strict_types=1);

namespace DesignQa\Application\Port;

use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;

/**
 * Renders a live page and reads what the browser shows (Chrome today).
 */
interface PageSource
{
    /**
     * Renders the page once per spec, at that spec's width.
     *
     * @param non-empty-list<ScreenSpec> $specs
     *
     * @return list<Screen> one per spec, same order
     *
     * @throws SourceException when the page cannot be rendered
     */
    public function capture(string $pageUrl, array $specs): array;
}
