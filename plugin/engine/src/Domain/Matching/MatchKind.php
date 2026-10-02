<?php

declare(strict_types=1);

namespace DesignQa\Domain\Matching;

enum MatchKind: string
{
    /** Same wording (ignoring case, punctuation and spacing). */
    case Exact = 'exact';

    /** Clearly the same text, but the wording differs (typo, a changed word). */
    case Similar = 'similar';
}
