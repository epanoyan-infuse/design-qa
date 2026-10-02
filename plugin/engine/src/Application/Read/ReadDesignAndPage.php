<?php

declare(strict_types=1);

namespace DesignQa\Application\Read;

use DesignQa\Application\Port\DesignSource;
use DesignQa\Application\Port\PageSource;
use DesignQa\Application\Port\SourceException;
use DesignQa\Domain\Model\Screen;

/**
 * Use case: read every design screen, and the page at each screen's width.
 * The comparison (next step) builds on this result.
 */
final readonly class ReadDesignAndPage
{
    public function __construct(
        private DesignSource $designs,
        private PageSource $pages,
    ) {}

    /**
     * @throws SourceException
     */
    public function execute(ReadRequest $request): ReadResult
    {
        $design = $this->designs->load($request->designUrl);

        $selected = array_values(array_filter($design->screens, static fn(Screen $s): bool => $request->wants($s->spec)));
        if ($selected === []) {
            $available = implode(', ', array_map(static fn(Screen $s): string => sprintf('%s (%d)', $s->name(), $s->width()), $design->screens));
            throw new SourceException(sprintf('No design screen matches "%s". Available: %s.', implode(', ', $request->screens), $available));
        }

        $pairs = [];
        $skipped = [];
        $pages = $this->pages->capture($request->pageUrl, array_map(static fn(Screen $s) => $s->spec, $selected));
        foreach ($selected as $i => $screen) {
            $page = $pages[$i] ?? throw new SourceException(sprintf('The page was not rendered for %s.', $screen->name()));
            if ($page->notCheckedReason !== null) {
                $skipped[] = new SkippedScreen($screen, $page->notCheckedReason);
            } else {
                $pairs[] = new ScreenPair($screen, $page);
            }
        }

        return new ReadResult($design, $request->pageUrl, $pairs, $skipped);
    }
}
