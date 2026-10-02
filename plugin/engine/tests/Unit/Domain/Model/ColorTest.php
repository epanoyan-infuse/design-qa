<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Model;

use DesignQa\Domain\Model\Color;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Color::class)]
final class ColorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cssColors(): iterable
    {
        yield 'rgb' => ['rgb(0, 56, 103)', '#003867'];
        yield 'rgba' => ['rgba(148, 163, 184, 0.5)', '#94A3B8 50%'];
        yield 'space syntax' => ['rgb(1 42 77 / 25%)', '#012A4D 25%'];
        yield 'fractional channels' => ['rgb(254.6, 0.4, 10)', '#FF000A'];
    }

    #[DataProvider('cssColors')]
    public function testParsesComputedCssColors(string $css, string $hex): void
    {
        self::assertSame($hex, Color::fromCss($css)->toHex());
    }

    public function testFigmaUnitFloatsRoundLikeFigma(): void
    {
        // "About the book" in the Kesler design: r 0, g 0.2196, b 0.4039 → #003867
        self::assertSame('#003867', Color::fromUnitFloats(0.0, 0.21960784494876862, 0.40392157435417175)->toHex());
    }

    public function testChannelDistanceIsTheLargestChannelDifference(): void
    {
        self::assertSame(1, Color::fromHex('#003867')->channelDistance(Color::fromHex('#013867')));
        self::assertSame(148, Color::fromHex('#003867')->channelDistance(Color::fromHex('#94A3B8')));
    }

    public function testOpacityMultipliesAlpha(): void
    {
        $color = Color::fromHex('#FFFFFF')->withAlphaMultiplied(0.5);

        self::assertFalse($color->isOpaque());
        self::assertSame(0.5, $color->alpha);
    }

    public function testRejectsUnsupportedColors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Color::fromCss('oklch(70% 0.1 200)');
    }
}
