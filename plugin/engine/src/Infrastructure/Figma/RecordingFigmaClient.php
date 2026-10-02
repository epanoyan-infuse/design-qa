<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma;

/**
 * Saves Figma answers to a folder, to create offline test fixtures. Responses never contain the token.
 */
final readonly class RecordingFigmaClient implements FigmaClient
{
    public function __construct(
        private FigmaClient $inner,
        private string $directory,
    ) {}

    public function me(): array
    {
        return $this->inner->me();
    }

    public function fileVersion(string $fileKey): string
    {
        return $this->inner->fileVersion($fileKey);
    }

    public function nodes(string $fileKey, array $nodeIds): array
    {
        $data = $this->inner->nodes($fileKey, $nodeIds);
        if (is_dir($this->directory) || mkdir($this->directory, 0o755, true)) {
            file_put_contents($this->directory . '/figma-nodes.json', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return $data;
    }
}
