<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Secrets;

/**
 * Returns the token from the first store that has one.
 */
final class ChainTokenStore implements TokenStore
{
    /** @var list<TokenStore> */
    private readonly array $stores;

    private ?TokenStore $source = null;

    public function __construct(TokenStore ...$stores)
    {
        $this->stores = array_values($stores);
    }

    public function fetch(): ?Secret
    {
        foreach ($this->stores as $store) {
            $secret = $store->fetch();
            if ($secret !== null) {
                $this->source = $store;

                return $secret;
            }
        }

        return null;
    }

    /**
     * Names the store the last token came from, or all stores when none had one.
     */
    public function describe(): string
    {
        if ($this->source !== null) {
            return $this->source->describe();
        }

        return implode(' or ', array_map(static fn(TokenStore $s): string => $s->describe(), $this->stores));
    }
}
