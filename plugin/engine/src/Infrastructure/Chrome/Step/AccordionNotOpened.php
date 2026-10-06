<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome\Step;

use RuntimeException;

/**
 * The item the design shows open could not be found or opened on the page: the screen is not checked.
 */
final class AccordionNotOpened extends RuntimeException {}
