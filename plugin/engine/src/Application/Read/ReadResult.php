<?php

declare(strict_types=1);

namespace DesignQa\Application\Read;

use DesignQa\Domain\Model\Design;

final readonly class ReadResult
{
    /**
     * @param list<ScreenPair>    $pairs
     * @param list<SkippedScreen> $skipped
     */
    public function __construct(
        public Design $design,
        public string $pageUrl,
        public array $pairs,
        public array $skipped,
    ) {}
}
