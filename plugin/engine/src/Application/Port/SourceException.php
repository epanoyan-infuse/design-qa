<?php

declare(strict_types=1);

namespace DesignQa\Application\Port;

use RuntimeException;

/**
 * A design or page could not be read. The message is shown to the developer as is.
 */
final class SourceException extends RuntimeException {}
