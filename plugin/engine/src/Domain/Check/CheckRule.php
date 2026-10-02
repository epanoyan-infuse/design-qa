<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

use InvalidArgumentException;

/**
 * How one check is judged: whether it runs, how severe a mismatch is, and the allowed difference.
 */
final readonly class CheckRule
{
    /**
     * @param array<string, float> $options extra numeric settings of a check (e.g. "alpha_tolerance")
     */
    public function __construct(
        public string $checkId,
        public Severity $severity,
        public float $tolerance = 0.0,
        public bool $enabled = true,
        public array $options = [],
    ) {
        if ($tolerance < 0) {
            throw new InvalidArgumentException(sprintf('Tolerance of "%s" cannot be negative.', $checkId));
        }
        foreach ($options as $name => $value) {
            if ($value < 0) {
                throw new InvalidArgumentException(sprintf('"%s" of "%s" cannot be negative.', $name, $checkId));
            }
        }
    }

    /**
     * An extra setting of the check; the check's default when the config does not set it.
     */
    public function option(string $name, float $default): float
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * True when the difference between the design value and the page value is allowed.
     */
    public function allows(float $difference): bool
    {
        return self::within($difference, $this->tolerance);
    }

    /**
     * True when the difference is allowed under an arbitrary tolerance, e.g. a check's own
     * widened tolerance for a specific context instead of the rule's configured one.
     */
    public static function within(float $difference, float $tolerance): bool
    {
        // A small epsilon keeps float rounding (e.g. 30.600000001) from turning a match into a mismatch.
        return abs($difference) <= $tolerance + 1e-6;
    }
}
