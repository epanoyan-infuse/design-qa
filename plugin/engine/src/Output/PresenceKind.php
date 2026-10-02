<?php

declare(strict_types=1);

namespace DesignQa\Output;

use DesignQa\Application\Check\ScreenResult;
use DesignQa\Domain\Check\PresenceRules;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Model\TextElement;

/**
 * A text found on only one side: in the design but not on the page, or the other way round.
 */
enum PresenceKind: string
{
    case Missing = 'missing';
    case Extra = 'extra';

    public function label(): string
    {
        return match ($this) {
            self::Missing => 'Missing on the page',
            self::Extra => 'Extra on the page',
        };
    }

    /** The rule id in config/rules.php. */
    public function ruleId(): string
    {
        return match ($this) {
            self::Missing => PresenceRules::MISSING_TEXT,
            self::Extra => PresenceRules::EXTRA_TEXT,
        };
    }

    public function severity(PresenceRules $rules): ?Severity
    {
        return match ($this) {
            self::Missing => $rules->missing,
            self::Extra => $rules->extra,
        };
    }

    /**
     * @return list<string> the texts of this kind on one screen, in page order
     */
    public function texts(ScreenResult $screen): array
    {
        return match ($this) {
            self::Missing => array_map(static fn(TextPart $p): string => $p->normalizedContent(), $screen->missingOnPage()),
            self::Extra => array_map(static fn(TextElement $t): string => $t->normalizedContent(), $screen->extraOnPage()),
        };
    }
}
