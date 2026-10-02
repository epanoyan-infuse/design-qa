<?php

declare(strict_types=1);

namespace DesignQa\Application\Doctor\Check;

use DesignQa\Application\Doctor\HealthCheck;
use DesignQa\Application\Doctor\HealthResult;
use DesignQa\Infrastructure\Figma\FigmaApiException;
use DesignQa\Infrastructure\Figma\FigmaClient;
use DesignQa\Infrastructure\Secrets\TokenStore;

/**
 * Checks that a Figma key is saved and that Figma accepts it. Never prints the key.
 */
final readonly class FigmaKeyCheck implements HealthCheck
{
    public function __construct(
        private TokenStore $tokens,
        private FigmaClient $figma,
        private string $saveCommand,
    ) {}

    public function name(): string
    {
        return 'Figma key';
    }

    public function run(): HealthResult
    {
        if ($this->tokens->fetch() === null) {
            return HealthResult::fail(
                sprintf('not found in %s', $this->tokens->describe()),
                sprintf('Run in your terminal: %s   (then paste the key into the hidden prompt)', $this->saveCommand),
            );
        }

        try {
            $me = $this->figma->me();
        } catch (FigmaApiException $e) {
            return HealthResult::fail(
                $e->getMessage(),
                sprintf('Create a new read-only key in Figma, then save it in your terminal with: %s', $this->saveCommand),
            );
        }

        $handle = is_string($me['handle'] ?? null) ? $me['handle'] : 'unknown account';

        return HealthResult::pass(sprintf('works (account: %s, from %s)', $handle, $this->tokens->describe()));
    }
}
