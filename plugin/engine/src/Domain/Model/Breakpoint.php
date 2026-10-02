<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

/**
 * Elementor's default responsive breakpoints (mobile up to 767px, tablet up to 1024px). Besides
 * grouping screens for the report, checks use this to tell a Tablet-width screen from Desktop and
 * Mobile: with CSS clamp() fluid typography, Desktop and Mobile are usually the two values a
 * developer set directly, while Tablet falls in between and only ever shows an interpolated value.
 */
enum Breakpoint
{
    case Desktop;
    case Tablet;
    case Mobile;

    public const MOBILE_MAX_WIDTH = 767;
    public const TABLET_MAX_WIDTH = 1024;

    public static function forWidth(int $width): self
    {
        return match (true) {
            $width <= self::MOBILE_MAX_WIDTH => self::Mobile,
            $width <= self::TABLET_MAX_WIDTH => self::Tablet,
            default => self::Desktop,
        };
    }
}
