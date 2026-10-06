<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Model;

use DesignQa\Domain\Model\Color;
use DesignQa\Domain\Model\OpenItemDetector;
use DesignQa\Domain\Model\TextStyle;
use DesignQa\Tests\Support\Styles;
use DesignQa\Tests\Support\Texts as T;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenItemDetector::class)]
final class OpenItemDetectorTest extends TestCase
{
    public function testDetectsTheLoneColorOutlier(): void
    {
        // fortifyiq.com's accordion: three closed (blue) titles, one open (white) title.
        $blue = Styles::body(color: '#3E68AF');
        $white = Styles::body(color: '#FFFFFF');
        $screen = T::screen([
            T::text('a', 'FortiPQC', 0, 0, $blue),
            T::text('b', 'EDA Tools for Security', 0, 50, $white),
            T::text('c', 'Security Cryptographic HW IP Cores', 0, 100, $blue),
            T::text('d', 'Cryptographic Software Libraries', 0, 150, $blue),
        ]);

        self::assertSame('EDA Tools for Security', (new OpenItemDetector())->detect($screen));
    }

    public function testNullWhenEveryoneAgrees(): void
    {
        $blue = Styles::body(color: '#3E68AF');
        $screen = T::screen([
            T::text('a', 'One', 0, 0, $blue),
            T::text('b', 'Two', 0, 50, $blue),
            T::text('c', 'Three', 0, 100, $blue),
        ]);

        self::assertNull((new OpenItemDetector())->detect($screen));
    }

    public function testNullWhenTheGroupIsTooSmallToBeSureItIsNotCoincidence(): void
    {
        $blue = Styles::body(color: '#3E68AF');
        $white = Styles::body(color: '#FFFFFF');
        $screen = T::screen([
            T::text('a', 'One', 0, 0, $blue),
            T::text('b', 'Two', 0, 50, $white),
        ]);

        self::assertNull((new OpenItemDetector())->detect($screen));
    }

    public function testNullWhenMoreThanOneTextDiffers(): void
    {
        $blue = Styles::body(color: '#3E68AF');
        $white = Styles::body(color: '#FFFFFF');
        $screen = T::screen([
            T::text('a', 'One', 0, 0, $blue),
            T::text('b', 'Two', 0, 50, $white),
            T::text('c', 'Three', 0, 100, $white),
            T::text('d', 'Four', 0, 150, $blue),
        ]);

        self::assertNull((new OpenItemDetector())->detect($screen));
    }

    public function testNullWhenTwoSeparateGroupsEachHaveTheirOwnOutlier(): void
    {
        $blue = Styles::body(color: '#3E68AF');
        $white = Styles::body(color: '#FFFFFF');
        // A second, differently-styled group (bigger font) with its own, unrelated outlier.
        $green = new TextStyle('Plus Jakarta Sans', 700, 28.0, false, Color::fromHex('#2E7D32'), 0.0, 36.0);
        $yellow = new TextStyle('Plus Jakarta Sans', 700, 28.0, false, Color::fromHex('#F9A825'), 0.0, 36.0);
        $screen = T::screen([
            T::text('a', 'One', 0, 0, $blue),
            T::text('b', 'Two', 0, 50, $white),
            T::text('c', 'Three', 0, 100, $blue),
            T::text('x', 'Heading One', 0, 200, $green),
            T::text('y', 'Heading Two', 0, 260, $yellow),
            T::text('z', 'Heading Three', 0, 320, $green),
        ]);

        self::assertNull((new OpenItemDetector())->detect($screen));
    }

    public function testNullWhenAGradientFillMakesTheGroupUnjudgeable(): void
    {
        $blue = Styles::body(color: '#3E68AF');
        $noColor = new TextStyle('Plus Jakarta Sans', 400, 18.0, false, null, 0.0, 32.0);
        $screen = T::screen([
            T::text('a', 'One', 0, 0, $blue),
            T::text('b', 'Two', 0, 50, $noColor),
            T::text('c', 'Three', 0, 100, $blue),
        ]);

        self::assertNull((new OpenItemDetector())->detect($screen));
    }
}
