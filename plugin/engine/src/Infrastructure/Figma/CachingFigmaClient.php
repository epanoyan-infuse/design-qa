<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma;

use DesignQa\Infrastructure\Cache\JsonFileCache;

/**
 * Keeps full design reads until the Figma file changes.
 *
 * Full reads (GET nodes) are expensive: accounts without a Dev or Full seat get only a few per
 * month. Before each read, the cheap file version is fetched; a cached read of the same version
 * is reused. So "check again" after fixing the page costs no full read unless the design changed.
 */
final readonly class CachingFigmaClient implements FigmaClient
{
    public function __construct(
        private FigmaClient $inner,
        private JsonFileCache $cache,
        private bool $refresh = false,
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
        $key = sprintf('nodes:%s:%s:%s', $fileKey, $this->inner->fileVersion($fileKey), implode(',', $nodeIds));
        if (!$this->refresh && ($cached = $this->cache->get($key)) !== null) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }

        $data = $this->inner->nodes($fileKey, $nodeIds);
        $this->cache->put($key, $data);

        return $data;
    }
}
