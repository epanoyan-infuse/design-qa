<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Cache;

use DesignQa\Infrastructure\Cache\JsonFileCache;
use DesignQa\Infrastructure\Figma\CachingFigmaClient;
use DesignQa\Infrastructure\Figma\FigmaApiException;
use DesignQa\Infrastructure\Figma\FigmaClient;
use DesignQa\Tests\Support\FakeFigmaClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonFileCache::class)]
#[CoversClass(CachingFigmaClient::class)]
final class JsonFileCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/design-qa-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testStoresPrivatelyAndExpires(): void
    {
        $now = 1_000_000;
        $cache = new JsonFileCache($this->dir, 60, function () use (&$now): int {
            return $now;
        });

        self::assertNull($cache->get('k'));
        $cache->put('k', ['a' => 1]);
        self::assertSame(['a' => 1], $cache->get('k'));

        $files = glob($this->dir . '/*.json') ?: [];
        self::assertCount(1, $files);
        self::assertSame('0600', substr(sprintf('%o', fileperms($files[0])), -4));
        self::assertSame('0700', substr(sprintf('%o', fileperms($this->dir)), -4));

        $now += 61;
        self::assertNull($cache->get('k'));
    }

    public function testUnencodableDataIsSkippedNotThrown(): void
    {
        $cache = new JsonFileCache($this->dir, 60);
        $cache->put('k', ['value' => NAN]);

        self::assertNull($cache->get('k'));
    }

    public function testCachingClientReadsFullDesignOncePerFileVersion(): void
    {
        $inner = new class implements FigmaClient {
            public int $nodeReads = 0;
            public string $version = 'v1';

            public function me(): array
            {
                return [];
            }

            public function fileVersion(string $fileKey): string
            {
                return $this->version;
            }

            public function nodes(string $fileKey, array $nodeIds): array
            {
                ++$this->nodeReads;

                return ['version' => $this->version];
            }
        };
        $cache = new JsonFileCache($this->dir, 3600);

        $client = new CachingFigmaClient($inner, $cache);
        $first = $client->nodes('F', ['1:2']);
        $second = $client->nodes('F', ['1:2']);
        self::assertEquals($first, $second);
        self::assertSame(1, $inner->nodeReads, '"check again" costs no full read');

        $inner->version = 'v2';
        $edited = $client->nodes('F', ['1:2']);
        self::assertNotEquals($first, $edited);
        self::assertSame(2, $inner->nodeReads, 'an edited design is read again');

        (new CachingFigmaClient($inner, $cache, refresh: true))->nodes('F', ['1:2']);
        self::assertSame(3, $inner->nodeReads, '--refresh always reads');
    }

    public function testVersionFailureIsNotHidden(): void
    {
        $inner = new FakeFigmaClient(failure: FigmaApiException::forStatus(403, '/v1/files/F/meta'));

        $this->expectException(FigmaApiException::class);
        (new CachingFigmaClient($inner, new JsonFileCache($this->dir, 60)))->nodes('F', ['1:2']);
    }
}
