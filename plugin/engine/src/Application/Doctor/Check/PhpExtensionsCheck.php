<?php

declare(strict_types=1);

namespace DesignQa\Application\Doctor\Check;

use Closure;
use DesignQa\Application\Doctor\HealthCheck;
use DesignQa\Application\Doctor\HealthResult;

final readonly class PhpExtensionsCheck implements HealthCheck
{
    /** Extensions the engine needs at runtime (HTTP, JSON, text handling, Chrome connection). */
    public const REQUIRED = ['json', 'mbstring', 'openssl', 'sockets'];

    /** @var Closure(string): bool */
    private Closure $isLoaded;

    /**
     * @param list<string>                  $required
     * @param (callable(string): bool)|null $isLoaded defaults to extension_loaded()
     */
    public function __construct(
        private array $required = self::REQUIRED,
        ?callable $isLoaded = null,
    ) {
        $this->isLoaded = Closure::fromCallable($isLoaded ?? extension_loaded(...));
    }

    public function name(): string
    {
        return 'PHP extensions';
    }

    public function run(): HealthResult
    {
        $missing = array_values(array_filter($this->required, fn(string $ext): bool => !($this->isLoaded)($ext)));
        if ($missing === []) {
            return HealthResult::pass(implode(', ', $this->required));
        }

        return HealthResult::fail(
            sprintf('missing: %s', implode(', ', $missing)),
            'Install a PHP build that includes these extensions (the static-php-cli "common" build has them all).',
        );
    }
}
