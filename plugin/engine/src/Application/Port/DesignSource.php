<?php

declare(strict_types=1);

namespace DesignQa\Application\Port;

use DesignQa\Domain\Model\Design;

/**
 * Where designs come from (Figma today).
 */
interface DesignSource
{
    /**
     * @throws SourceException when the design cannot be read
     */
    public function load(string $designUrl): Design;
}
