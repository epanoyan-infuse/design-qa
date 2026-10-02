<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Model;

use DesignQa\Domain\Model\StyleRuns;
use DesignQa\Domain\Model\Visibility;
use DesignQa\Tests\Support\Styles;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Visibility::class)]
#[CoversClass(StyleRuns::class)]
final class SharedRulesTest extends TestCase
{
    public function testIconOnly(): void
    {
        self::assertTrue(Visibility::isIconOnly("\u{E934}\u{E934}"));
        self::assertTrue(Visibility::isIconOnly(" \u{F101} "));
        self::assertFalse(Visibility::isIconOnly("\u{E934} Next"));
        self::assertFalse(Visibility::isIconOnly('★★★★★'), 'real characters are text, not icons');
        self::assertFalse(Visibility::isIconOnly('  '));
    }

    public function testRunsMergeEqualNeighbours(): void
    {
        $bold = Styles::body(700);
        $sameBold = Styles::body(700);
        $regular = Styles::body();

        $runs = StyleRuns::fromCharacters([$bold, $sameBold, $regular, $regular, $bold]);

        self::assertSame([[0, 2], [2, 2], [4, 1]], array_map(static fn($r): array => [$r->start, $r->length], $runs));
    }

    public function testRunsNeedCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StyleRuns::fromCharacters([]);
    }
}
