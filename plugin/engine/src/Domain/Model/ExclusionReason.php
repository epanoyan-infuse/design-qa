<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

enum ExclusionReason: string
{
    case Hidden = 'hidden';
    case Transparent = 'transparent';
    case Clipped = 'clipped';
    case Covered = 'covered';
    case Icon = 'icon';
    case NoGeometry = 'no-geometry';

    public function describe(): string
    {
        return match ($this) {
            self::Hidden => 'hidden layer or element',
            self::Transparent => 'fully transparent',
            self::Clipped => 'cut off by its frame or container',
            self::Covered => 'covered by another layer',
            self::Icon => 'icon font glyph',
            self::NoGeometry => 'Figma sent no position for it',
        };
    }
}
