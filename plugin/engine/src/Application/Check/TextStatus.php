<?php

declare(strict_types=1);

namespace DesignQa\Application\Check;

/**
 * What happened to one design text. Every design text on a checked screen has exactly one.
 */
enum TextStatus: string
{
    /** Compared, everything matches. */
    case Ok = 'ok';

    /** Compared, at least one property differs. */
    case Issues = 'issues';

    /** Only some of its paragraphs were found on the page; those match. */
    case PartlyCompared = 'partly-compared';

    /** Only some of its paragraphs were found on the page, and at least one of those differs. */
    case IssuesPartlyCompared = 'issues-partly-compared';

    /** More than one equally good place on the page: not compared, check by hand. */
    case CheckManually = 'check-manually';

    /** No matching text on the page. */
    case NotFound = 'not-found';

    /** Only symbols (e.g. a decorative quote mark): not compared. */
    case Symbol = 'symbol';

    /**
     * "3 symbols", "1 symbol".
     */
    public function count(int $n): string
    {
        return sprintf('%d %s', $n, $this === self::Symbol && $n === 1 ? 'symbol' : $this->label());
    }

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'match',
            self::Issues => 'with issues',
            self::PartlyCompared => 'partly compared',
            self::IssuesPartlyCompared => 'with issues, partly compared',
            self::CheckManually => 'to check manually',
            self::NotFound => 'not found on the page',
            self::Symbol => 'symbols',
        };
    }
}
