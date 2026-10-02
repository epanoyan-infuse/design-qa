<?php

declare(strict_types=1);

namespace DesignQa\Tests\Support;

use DesignQa\Infrastructure\Secrets\Secret;
use DesignQa\Infrastructure\Secrets\TokenStore;

final class InMemoryTokenStore implements TokenStore
{
    public int $fetches = 0;

    public function __construct(
        private readonly ?string $token,
        private readonly string $name = 'memory',
    ) {}

    public function fetch(): ?Secret
    {
        ++$this->fetches;

        return $this->token === null ? null : new Secret($this->token);
    }

    public function describe(): string
    {
        return $this->name;
    }
}
