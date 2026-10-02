<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome\Step;

use RuntimeException;

/**
 * The page's menu could not be found or did not open: the menu screen is not checked.
 */
final class MenuNotOpened extends RuntimeException {}
