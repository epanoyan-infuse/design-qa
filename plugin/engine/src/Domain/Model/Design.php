<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

/**
 * All screens of a design (e.g. 1920 desktop, 1024 and 800 tablet, 390 mobile, 390 menu).
 */
final readonly class Design
{
    /**
     * @param list<Screen> $screens widest first
     */
    public function __construct(
        public string $name,
        public array $screens,
    ) {}

    /**
     * @return list<ScreenSpec>
     */
    public function specs(): array
    {
        return array_map(static fn(Screen $s): ScreenSpec => $s->spec, $this->screens);
    }
}
