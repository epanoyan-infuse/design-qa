<?php

declare(strict_types=1);

namespace DesignQa\Output;

use DesignQa\Domain\Check\Finding;

/**
 * The same finding (same text, property and values) on one or more screens.
 */
final readonly class Issue
{
    /**
     * @param array<string, int> $screens screen name => how many times on that screen
     */
    public function __construct(
        public Finding $finding,
        public array $screens = [],
    ) {}

    /**
     * @param list<string> $allScreens every compared screen, in order
     */
    public function where(array $allScreens): string
    {
        return self::describeScreens($this->screens, $allScreens);
    }

    /**
     * "all screens", or the screen names with a count where a screen has it more than once.
     *
     * @param array<string, int> $screens    screen name => times
     * @param list<string>       $allScreens every compared screen, in order
     */
    public static function describeScreens(array $screens, array $allScreens): string
    {
        // Array keys that look like numbers ("390", "1440") come back as ints, so compare as strings.
        $names = array_map('strval', array_keys($screens));
        if (count($allScreens) > 1 && $names === $allScreens) {
            return 'all screens';
        }

        return implode(', ', array_map(
            static fn(string $name): string => $screens[$name] > 1 ? sprintf('%s (%d×)', $name, $screens[$name]) : $name,
            $names,
        ));
    }
}
