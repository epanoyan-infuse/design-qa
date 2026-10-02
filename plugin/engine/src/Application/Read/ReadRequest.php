<?php

declare(strict_types=1);

namespace DesignQa\Application\Read;

use DesignQa\Domain\Model\ScreenSpec;

final readonly class ReadRequest
{
    /**
     * @param list<string> $screens only these screens (by name or width, e.g. "390" or "1920_Desktop"); empty = all
     */
    public function __construct(
        public string $pageUrl,
        public string $designUrl,
        public array $screens = [],
    ) {}

    public function wants(ScreenSpec $spec): bool
    {
        if ($this->screens === []) {
            return true;
        }
        foreach ($this->screens as $wanted) {
            if (strcasecmp($wanted, $spec->name) === 0 || $wanted === (string) $spec->width) {
                return true;
            }
        }

        return false;
    }
}
