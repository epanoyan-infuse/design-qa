<?php

declare(strict_types=1);

namespace DesignQa\Output;

use DesignQa\Domain\Check\Severity;

/**
 * The same text missing on the page (or extra on it) on one or more screens.
 */
final readonly class PresenceIssue
{
    /**
     * @param array<string, int> $screens screen name => how many times on that screen
     */
    public function __construct(
        public PresenceKind $kind,
        public string $text,
        public Severity $severity,
        public array $screens = [],
    ) {}

    /**
     * @param list<string> $allScreens every compared screen, in order
     */
    public function where(array $allScreens): string
    {
        return Issue::describeScreens($this->screens, $allScreens);
    }
}
