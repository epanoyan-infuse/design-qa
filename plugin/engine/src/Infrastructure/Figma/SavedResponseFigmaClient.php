<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma;

use JsonException;

/**
 * Serves a saved GET nodes response from disk instead of calling Figma. For development and
 * tests (e.g. while the Figma read limit is reached); never used in a normal check.
 */
final readonly class SavedResponseFigmaClient implements FigmaClient
{
    public function __construct(private string $responseFile) {}

    public function me(): array
    {
        return ['handle' => 'saved response'];
    }

    public function fileVersion(string $fileKey): string
    {
        return 'saved:' . hash_file('sha256', $this->responseFile);
    }

    public function nodes(string $fileKey, array $nodeIds): array
    {
        $contents = is_file($this->responseFile) ? file_get_contents($this->responseFile) : false;
        if ($contents === false) {
            throw new FigmaApiException(sprintf('Saved Figma response not found: %s', $this->responseFile));
        }

        try {
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw FigmaApiException::invalidResponse($this->responseFile);
        }
        if (!is_array($data)) {
            throw FigmaApiException::invalidResponse($this->responseFile);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
