<?php

declare(strict_types=1);

namespace DesignQa\Domain\Check;

use DesignQa\Domain\Check\Checks\FontFamilyCheck;
use DesignQa\Domain\Check\Checks\FontSizeCheck;
use DesignQa\Domain\Check\Checks\FontStyleCheck;
use DesignQa\Domain\Check\Checks\FontWeightCheck;
use DesignQa\Domain\Check\Checks\LetterSpacingCheck;
use DesignQa\Domain\Check\Checks\LineHeightCheck;
use DesignQa\Domain\Check\Checks\TextColorCheck;
use DesignQa\Domain\Check\Checks\TextContentCheck;
use InvalidArgumentException;

/**
 * All available checks. Which ones run, and how strictly, is decided by the RuleSet.
 */
final readonly class CheckRegistry
{
    /** @var array<string, Check|TextCheck> */
    private array $checks;

    public function __construct(Check|TextCheck ...$checks)
    {
        $byId = [];
        foreach ($checks as $check) {
            $byId[$check->id()] = $check;
        }
        $this->checks = $byId;
    }

    public static function standard(): self
    {
        return new self(
            new TextContentCheck(),
            new FontFamilyCheck(),
            new FontSizeCheck(),
            new FontWeightCheck(),
            new FontStyleCheck(),
            new TextColorCheck(),
            new LetterSpacingCheck(),
            new LineHeightCheck(),
        );
    }

    /**
     * The enabled checks with their rules, in the order of the config file.
     *
     * @return list<array{Check|TextCheck, CheckRule}>
     *
     * @throws InvalidArgumentException when a rule names a check that does not exist
     */
    public function configured(RuleSet $rules): array
    {
        return array_map(
            fn(CheckRule $rule): array => [
                $this->checks[$rule->checkId] ?? throw new InvalidArgumentException(sprintf(
                    'config/rules.php names check "%s", which does not exist. Known checks: %s.',
                    $rule->checkId,
                    implode(', ', array_keys($this->checks)),
                )),
                $rule,
            ],
            // Missing and extra texts are judged by PresenceRules, not by a check of a matched pair.
            array_values(array_filter($rules->enabled(), static fn(CheckRule $rule): bool => !in_array($rule->checkId, PresenceRules::IDS, true))),
        );
    }
}
