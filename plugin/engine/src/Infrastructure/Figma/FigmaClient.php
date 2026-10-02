<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma;

/**
 * Read-only access to the Figma REST API.
 */
interface FigmaClient
{
    /**
     * The account the token belongs to. Used to verify the token works.
     *
     * @return array<string, mixed>
     *
     * @throws FigmaApiException
     */
    public function me(): array;

    /**
     * The file's current version id (GET /v1/files/:key/meta). A cheap call: it changes whenever
     * the design is edited, so full reads can be cached until then.
     *
     * @throws FigmaApiException
     */
    public function fileVersion(string $fileKey): string;

    /**
     * Full document trees of the given nodes in a file (GET /v1/files/:key/nodes).
     *
     * @param list<string> $nodeIds
     *
     * @return array<string, mixed> decoded response body
     *
     * @throws FigmaApiException
     *
     * @phpstan-impure
     */
    public function nodes(string $fileKey, array $nodeIds): array;
}
