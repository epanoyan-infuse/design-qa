<?php

declare(strict_types=1);

namespace DesignQa\Application;

use DesignQa\Domain\Check\RuleSet;
use InvalidArgumentException;

/**
 * Loads and validates config/rules.php, shared by the doctor's health check and the CLI's
 * composition root so the two never drift apart.
 */
final class RulesConfigLoader
{
    /**
     * @throws InvalidArgumentException when the file is missing, not an array, or has invalid rules
     */
    public static function load(string $path): RuleSet
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('config file missing');
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new InvalidArgumentException('config file must return an array');
        }

        return RuleSet::fromArray($config);
    }
}
