<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Model;

use DesignQa\Domain\Model\Rect;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rect::class)]
final class RectTest extends TestCase
{
    public function testIntersectionAndFractionInside(): void
    {
        $text = new Rect(0, 780, 100, 40);
        $frame = new Rect(0, 0, 390, 796);

        self::assertEquals(new Rect(0, 780, 100, 16), $text->intersect($frame));
        self::assertEqualsWithDelta(0.4, $text->fractionInside($frame), 1e-9);
        self::assertNull($text->intersect(new Rect(200, 0, 10, 10)));
        self::assertSame(0.0, $text->fractionInside(new Rect(200, 0, 10, 10)));
    }

    public function testUnion(): void
    {
        self::assertEquals(new Rect(0, 0, 30, 25), Rect::unionAll([new Rect(0, 0, 10, 10), new Rect(20, 15, 10, 10)]));
    }
}
