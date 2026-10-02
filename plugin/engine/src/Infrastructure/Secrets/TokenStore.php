<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Secrets;

/**
 * A place the Figma token can be read from (keychain, environment, ...).
 */
interface TokenStore
{
    /**
     * @return Secret|null null when this store has no token
     */
    public function fetch(): ?Secret;

    /**
     * Human-readable name of the store, e.g. "macOS keychain". Never contains the token.
     */
    public function describe(): string;
}
