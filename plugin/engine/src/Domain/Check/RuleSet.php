<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

use InvalidArgumentException;
use ValueError;

/**
 * All check rules, built from config/rules.php.
 */
final readonly class RuleSet
{
    /**
     * @param array<string, CheckRule> $rules keyed by check id
     */
    private function __construct(private array $rules) {}

    /**
     * @param array<mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $rules = [];
        foreach ($config as $checkId => $options) {
            if (!is_string($checkId) || !is_array($options)) {
                throw new InvalidArgumentException('Each rule must be "check-id" => [options].');
            }
            $rules[$checkId] = self::rule($checkId, $options);
        }

        return new self($rules);
    }

    public function has(string $checkId): bool
    {
        return isset($this->rules[$checkId]);
    }

    public function get(string $checkId): CheckRule
    {
        return $this->rules[$checkId] ?? throw new InvalidArgumentException(sprintf('No rule configured for check "%s".', $checkId));
    }

    /**
     * @return list<CheckRule>
     */
    public function enabled(): array
    {
        return array_values(array_filter($this->rules, static fn(CheckRule $rule): bool => $rule->enabled));
    }

    /**
     * @param array<mixed> $options
     */
    private static function rule(string $checkId, array $options): CheckRule
    {
        $severity = $options['severity'] ?? null;
        $tolerance = $options['tolerance'] ?? 0.0;
        $enabled = $options['enabled'] ?? true;

        if (!is_string($severity)) {
            throw new InvalidArgumentException(sprintf('Rule "%s" needs a severity.', $checkId));
        }
        if (!is_int($tolerance) && !is_float($tolerance)) {
            throw new InvalidArgumentException(sprintf('Tolerance of "%s" must be a number.', $checkId));
        }
        if (!is_bool($enabled)) {
            throw new InvalidArgumentException(sprintf('"enabled" of "%s" must be true or false.', $checkId));
        }

        try {
            $severity = Severity::from($severity);
        } catch (ValueError) {
            throw new InvalidArgumentException(sprintf('Unknown severity "%s" for "%s".', $severity, $checkId));
        }

        $extra = [];
        foreach ($options as $name => $value) {
            if (in_array($name, ['severity', 'tolerance', 'enabled'], true)) {
                continue;
            }
            if (!is_string($name) || (!is_int($value) && !is_float($value))) {
                throw new InvalidArgumentException(sprintf('Setting "%s" of "%s" must be a number.', $name, $checkId));
            }
            $extra[$name] = (float) $value;
        }

        return new CheckRule($checkId, $severity, (float) $tolerance, $enabled, $extra);
    }
}
