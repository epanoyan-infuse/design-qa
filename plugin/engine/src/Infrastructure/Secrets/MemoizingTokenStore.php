<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Secrets;

/**
 * Reads the token once per run. Without it, every Figma call would start a new `security`
 * process and could make macOS ask for keychain access more than once.
 */
final class MemoizingTokenStore implements TokenStore
{
    private ?Secret $secret = null;

    /** Whether fetch() has already run once, so a "no token" result is cached too (not just a found one). */
    private bool $fetched = false;

    public function __construct(private readonly TokenStore $inner) {}

    public function fetch(): ?Secret
    {
        if (!$this->fetched) {
            $this->secret = $this->inner->fetch();
            $this->fetched = true;
        }

        return $this->secret;
    }

    public function describe(): string
    {
        return $this->inner->describe();
    }
}
