<?php

declare(strict_types=1);

namespace DesignQa\Tests\Support;

use DesignQa\Infrastructure\Figma\FigmaApiException;
use DesignQa\Infrastructure\Figma\FigmaClient;

final readonly class FakeFigmaClient implements FigmaClient
{
    /**
     * @param array<string, mixed> $me
     * @param array<string, mixed> $nodes
     */
    public function __construct(
        private array $me = ['handle' => 'design-qa-bot'],
        private array $nodes = [],
        private ?FigmaApiException $failure = null,
    ) {}

    public function me(): array
    {
        return $this->failure !== null ? throw $this->failure : $this->me;
    }

    public function fileVersion(string $fileKey): string
    {
        return $this->failure !== null ? throw $this->failure : 'v1';
    }

    public function nodes(string $fileKey, array $nodeIds): array
    {
        return $this->failure !== null ? throw $this->failure : $this->nodes;
    }
}
