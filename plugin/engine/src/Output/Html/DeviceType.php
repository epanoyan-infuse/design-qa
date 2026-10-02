<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

use DesignQa\Domain\Model\Breakpoint;

/**
 * The main report tabs. A screen size belongs to a device type by its width, using Elementor's
 * default breakpoints (mobile up to 767px, tablet up to 1024px), because that is where developers
 * fix the issues. Same breakpoints as Domain\Model\Breakpoint, which checks use for the same reason.
 */
enum DeviceType: string
{
    case Desktop = 'desktop';
    case Tablet = 'tablet';
    case Mobile = 'mobile';

    public const MOBILE_MAX_WIDTH = Breakpoint::MOBILE_MAX_WIDTH;
    public const TABLET_MAX_WIDTH = Breakpoint::TABLET_MAX_WIDTH;

    public static function forWidth(int $width): self
    {
        return match (Breakpoint::forWidth($width)) {
            Breakpoint::Mobile => self::Mobile,
            Breakpoint::Tablet => self::Tablet,
            Breakpoint::Desktop => self::Desktop,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Desktop => 'Desktop',
            self::Tablet => 'Tablet',
            self::Mobile => 'Mobile',
        };
    }
}
