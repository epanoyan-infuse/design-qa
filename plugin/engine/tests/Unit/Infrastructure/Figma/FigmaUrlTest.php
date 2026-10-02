<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Figma;

use DesignQa\Infrastructure\Figma\FigmaUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FigmaUrl::class)]
final class FigmaUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function validLinks(): iterable
    {
        yield 'design link with node (Kesler)' => [
            'https://www.figma.com/design/BAJsKFuGKXBxu2aV44JIT8/Kesler.com-%257C-Book-Landing?node-id=2421-637&t=8A5k9CMgCL8kE7AS-0',
            'BAJsKFuGKXBxu2aV44JIT8',
            '2421:637',
        ];
        yield 'file link without node' => ['https://figma.com/file/AbC123/Name', 'AbC123', null];
        yield 'url-encoded colon' => ['https://www.figma.com/design/AbC123/N?node-id=1%3A2', 'AbC123', '1:2'];
        yield 'proto link' => ['https://www.figma.com/proto/AbC123/N?node-id=10-20', 'AbC123', '10:20'];
        yield 'branch link reads the branch' => ['https://www.figma.com/design/MainKey1/branch/BranchKey2/Name?node-id=1-2', 'BranchKey2', '1:2'];
        yield 'instance node id' => ['https://www.figma.com/design/AbC123/N?node-id=I1-2;3-4', 'AbC123', 'I1:2;3:4'];
    }

    #[DataProvider('validLinks')]
    public function testParsesValidLinks(string $url, string $fileKey, ?string $nodeId): void
    {
        $parsed = FigmaUrl::parse($url);

        self::assertSame($fileKey, $parsed->fileKey);
        self::assertSame($nodeId, $parsed->nodeId);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLinks(): iterable
    {
        yield 'other host' => ['https://www.example.com/design/AbC123/N'];
        yield 'look-alike host' => ['https://figma.com.evil.test/design/AbC123/N'];
        yield 'no file key' => ['https://www.figma.com/files/recent'];
        yield 'bad node id' => ['https://www.figma.com/design/AbC123/N?node-id=<script>'];
        yield 'not a url' => ['figma'];
    }

    #[DataProvider('invalidLinks')]
    public function testRejectsInvalidLinks(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        FigmaUrl::parse($url);
    }
}
