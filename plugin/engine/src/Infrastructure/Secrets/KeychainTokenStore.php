<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Secrets;

use DesignQa\Infrastructure\System\CommandRunner;
use InvalidArgumentException;

/**
 * Reads and saves the token in the macOS keychain through the `security` tool.
 */
final readonly class KeychainTokenStore implements TokenStore
{
    public const DEFAULT_SERVICE = 'design-qa-figma';
    public const DEFAULT_ACCOUNT = 'design-qa';

    public function __construct(
        private CommandRunner $runner,
        private string $service = self::DEFAULT_SERVICE,
        private string $account = self::DEFAULT_ACCOUNT,
    ) {}

    public function fetch(): ?Secret
    {
        $result = $this->runner->run(['security', 'find-generic-password', '-a', $this->account, '-s', $this->service, '-w']);
        if (!$result->isSuccessful()) {
            return null;
        }

        try {
            return new Secret($result->output);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Lets `security` prompt the user for the token in the terminal, so the token never passes
     * through this process's arguments, output or memory.
     */
    public function promptAndSave(): bool
    {
        // "-U" updates an existing item; a trailing "-w" without a value makes `security` prompt.
        return $this->runner->runInteractive(['security', 'add-generic-password', '-U', '-a', $this->account, '-s', $this->service, '-w']) === 0;
    }

    /**
     * Command a user can run themselves to save the token.
     */
    public function saveCommandHint(): string
    {
        return sprintf('security add-generic-password -U -a %s -s %s -w', $this->account, $this->service);
    }

    public function describe(): string
    {
        return 'macOS keychain';
    }
}
