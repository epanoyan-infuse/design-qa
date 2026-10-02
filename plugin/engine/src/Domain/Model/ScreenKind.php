<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

enum ScreenKind: string
{
    /** The page as it loads. */
    case Page = 'page';

    /** The page with the navigation menu opened: only the first screenful, menu open, is compared. */
    case Menu = 'menu';
}
