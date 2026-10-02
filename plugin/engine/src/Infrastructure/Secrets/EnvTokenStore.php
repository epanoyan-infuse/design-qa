<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Secrets;

use InvalidArgumentException;

/**
 * Reads the token from an environment variable (for CI and non-macOS machines).
 */
final readonly class EnvTokenStore implements TokenStore
{
    public const DEFAULT_VARIABLE = 'FIGMA_TOKEN';

    /**
     * @param array<string, mixed> $environment usually getenv()
     */
    public function __construct(
        private array $environment,
        private string $variable = self::DEFAULT_VARIABLE,
    ) {}

    public function fetch(): ?Secret
    {
        $value = $this->environment[$this->variable] ?? null;
        if (!is_string($value)) {
            return null;
        }

        try {
            return new Secret($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function describe(): string
    {
        return sprintf('environment variable %s', $this->variable);
    }
}
