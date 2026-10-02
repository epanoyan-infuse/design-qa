<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome;

use DesignQa\Domain\Model\ScreenKind;
use DesignQa\Domain\Model\ScreenSpec;

/**
 * File name of one recorded page capture: page-390.json, or page-390-menu.json for the same width
 * with the menu open.
 */
final class RawCaptureFile
{
    public static function name(ScreenSpec $spec): string
    {
        return sprintf('page-%d%s.json', $spec->width, $spec->kind === ScreenKind::Menu ? '-menu' : '');
    }
}
