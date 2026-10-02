<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Check;

use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\RuleSet;
use DesignQa\Domain\Check\Severity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleSet::class)]
#[CoversClass(CheckRule::class)]
final class RuleSetTest extends TestCase
{
    public function testShippedConfigMatchesTheSpec(): void
    {
        $rules = RuleSet::fromArray(require dirname(__DIR__, 4) . '/config/rules.php');

        foreach (['text', 'font-family', 'font-size', 'font-weight', 'font-style', 'color', 'missing-text', 'extra-text'] as $critical) {
            self::assertSame(Severity::Critical, $rules->get($critical)->severity, $critical);
        }
        self::assertSame(Severity::NonCritical, $rules->get('line-height')->severity);
        self::assertSame(Severity::NonCritical, $rules->get('letter-spacing')->severity);
        self::assertSame(0.5, $rules->get('font-size')->tolerance);
        self::assertSame(1.0, $rules->get('color')->tolerance);
        self::assertSame(0.02, $rules->get('color')->option('alpha_tolerance', 1.0));
        self::assertSame(0.1, $rules->get('letter-spacing')->tolerance);
        self::assertCount(10, $rules->enabled());
    }

    public function testToleranceIsInclusiveAndIgnoresFloatNoise(): void
    {
        $rule = new CheckRule('line-height', Severity::NonCritical, 0.5);

        self::assertTrue($rule->allows(0.5));
        self::assertTrue($rule->allows(-0.5000000001));
        self::assertFalse($rule->allows(1.4));
    }

    public function testDisabledRulesAreSkipped(): void
    {
        $rules = RuleSet::fromArray([
            'color' => ['severity' => 'critical'],
            'line-height' => ['severity' => 'non-critical', 'enabled' => false],
        ]);

        self::assertSame(['color'], array_map(static fn(CheckRule $r): string => $r->checkId, $rules->enabled()));
        self::assertTrue($rules->has('line-height'));
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'unknown severity' => [['color' => ['severity' => 'blocker']]];
        yield 'missing severity' => [['color' => []]];
        yield 'negative tolerance' => [['color' => ['severity' => 'critical', 'tolerance' => -1]]];
        yield 'text tolerance' => [['color' => ['severity' => 'critical', 'tolerance' => '1']]];
        yield 'list instead of map' => [[['severity' => 'critical']]];
        yield 'text option' => [['color' => ['severity' => 'critical', 'alpha_tolerance' => 'low']]];
        yield 'negative option' => [['color' => ['severity' => 'critical', 'alpha_tolerance' => -0.1]]];
    }

    /**
     * @param array<mixed> $config
     */
    #[DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfig(array $config): void
    {
        $this->expectException(InvalidArgumentException::class);
        RuleSet::fromArray($config);
    }

    public function testUnknownCheckIsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RuleSet::fromArray([])->get('hover-color');
    }
}
