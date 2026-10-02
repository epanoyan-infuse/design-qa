<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

/**
 * How texts found on only one side are judged: a design text missing on the page, a page text
 * that is not in the design. They are not checks of a matched pair, so they have their own ids in
 * config/rules.php. A severity of null means the rule is switched off (not counted as an issue;
 * the texts are still listed).
 */
final readonly class PresenceRules
{
    public const MISSING_TEXT = 'missing-text';
    public const EXTRA_TEXT = 'extra-text';
    public const IDS = [self::MISSING_TEXT, self::EXTRA_TEXT];

    public function __construct(
        public ?Severity $missing = Severity::Critical,
        public ?Severity $extra = Severity::Critical,
    ) {}

    public static function fromRuleSet(RuleSet $rules): self
    {
        $severity = static fn(string $id): ?Severity => $rules->has($id) && $rules->get($id)->enabled ? $rules->get($id)->severity : null;

        return new self($severity(self::MISSING_TEXT), $severity(self::EXTRA_TEXT));
    }
}
