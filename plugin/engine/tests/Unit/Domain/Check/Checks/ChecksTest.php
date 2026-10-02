<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Check\Checks;

use DesignQa\Domain\Check\Check;
use DesignQa\Domain\Check\CheckContext;
use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Checks\FontSizeCheck;
use DesignQa\Domain\Check\Checks\FontStyleCheck;
use DesignQa\Domain\Check\Checks\FontWeightCheck;
use DesignQa\Domain\Check\Checks\LetterSpacingCheck;
use DesignQa\Domain\Check\Checks\LineHeightCheck;
use DesignQa\Domain\Check\Checks\TextColorCheck;
use DesignQa\Domain\Check\Format;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Model\Color;
use DesignQa\Domain\Model\TextStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontSizeCheck::class)]
#[CoversClass(FontWeightCheck::class)]
#[CoversClass(FontStyleCheck::class)]
#[CoversClass(TextColorCheck::class)]
#[CoversClass(LetterSpacingCheck::class)]
#[CoversClass(LineHeightCheck::class)]
#[CoversClass(Format::class)]
final class ChecksTest extends TestCase
{
    /**
     * @return iterable<string, array{Check, float, array<string, mixed>, array<string, mixed>, ?array{string, string}, bool}>
     */
    public static function cases(): iterable
    {
        yield 'size within 0.5px' => [new FontSizeCheck(), 0.5, ['fontSize' => 18.0], ['fontSize' => 18.4], null, false];
        yield 'size off' => [new FontSizeCheck(), 0.5, ['fontSize' => 44.0], ['fontSize' => 68.0], ['44px', '68px'], false];
        yield 'weight exact' => [new FontWeightCheck(), 0, ['fontWeight' => 700], ['fontWeight' => 700], null, false];
        yield 'weight off' => [new FontWeightCheck(), 0, ['fontWeight' => 700], ['fontWeight' => 600], ['Bold (700)', 'SemiBold (600)'], false];
        yield 'italic off' => [new FontStyleCheck(), 0, ['italic' => true], ['italic' => false], ['Italic', 'Not italic'], false];
        yield 'color rounding ±1' => [new TextColorCheck(), 1, ['color' => '#003867'], ['color' => '#013868'], null, false];
        yield 'color off' => [new TextColorCheck(), 1, ['color' => '#003867'], ['color' => '#94A3B8'], ['#003867', '#94A3B8'], false];
        yield 'color transparency off' => [new TextColorCheck(), 1, ['color' => '#003867'], ['color' => '#00386780'], ['#003867', '#003867 50%'], false];
        yield 'gradient not compared' => [new TextColorCheck(), 1, ['color' => null], ['color' => '#000000'], null, false];
        yield 'letter spacing off' => [new LetterSpacingCheck(), 0.1, ['letterSpacing' => 0.0], ['letterSpacing' => -1.0], ['0px', '-1px'], false];
        yield 'letter spacing within' => [new LetterSpacingCheck(), 0.1, ['letterSpacing' => 2.0], ['letterSpacing' => 2.05], null, false];
        yield 'line height multi-line off' => [new LineHeightCheck(), 0.5, ['lineHeight' => 32.0], ['lineHeight' => 30.6], ['32px', '30.6px'], true];
        yield 'line height single line ignored' => [new LineHeightCheck(), 0.5, ['lineHeight' => 17.0], ['lineHeight' => 11.0], null, false];
        yield 'line height auto ignored' => [new LineHeightCheck(), 0.5, ['lineHeight' => null], ['lineHeight' => 30.6], null, true];
    }

    /**
     * @param array<string, mixed>       $design
     * @param array<string, mixed>       $page
     * @param array{string, string}|null $expected
     */
    #[DataProvider('cases')]
    public function testCheck(Check $check, float $tolerance, array $design, array $page, ?array $expected, bool $multiLine): void
    {
        $difference = $check->compare(self::style($design), self::style($page), new CheckContext($multiLine), new CheckRule($check->id(), Severity::Critical, $tolerance));

        self::assertSame($expected, $difference === null ? null : [$difference->design, $difference->page]);
    }

    public function testColorTransparencyToleranceComesFromTheRule(): void
    {
        $check = new TextColorCheck();
        $design = self::style(['color' => '#003867']);
        $page = self::style(['color' => '#003867E6']); // 90% opaque
        $context = new CheckContext(false);

        self::assertNotNull($check->compare($design, $page, $context, new CheckRule('color', Severity::Critical, 1)));
        self::assertNull($check->compare($design, $page, $context, new CheckRule('color', Severity::Critical, 1, options: ['alpha_tolerance' => 0.2])));
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function style(array $values): TextStyle
    {
        $color = array_key_exists('color', $values) ? $values['color'] : '#475569';

        return new TextStyle(
            'Plus Jakarta Sans',
            is_int($values['fontWeight'] ?? null) ? $values['fontWeight'] : 400,
            is_float($values['fontSize'] ?? null) ? $values['fontSize'] : 18.0,
            ($values['italic'] ?? false) === true,
            is_string($color) ? Color::fromHex($color) : null,
            is_float($values['letterSpacing'] ?? null) ? $values['letterSpacing'] : 0.0,
            array_key_exists('lineHeight', $values) ? (is_float($values['lineHeight']) ? $values['lineHeight'] : null) : 32.0,
        );
    }
}
