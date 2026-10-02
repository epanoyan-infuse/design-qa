<?php

declare(strict_types=1);

namespace DesignQa\Domain\Matching;

use DesignQa\Domain\Model\TextElement;

final readonly class MatchResult
{
    /**
     * @param list<TextMatch>      $matches
     * @param list<UncertainMatch> $uncertain       more than one equally good candidate: check manually
     * @param list<TextPart>     $unmatchedDesign design texts with no page text found
     * @param list<TextElement>    $unmatchedPage   page texts no design text was matched to
     * @param list<TextElement>    $symbols         design texts without letters or digits (e.g. "”"), not matched
     * @param list<TextElement>    $pageSymbols     page texts without letters or digits, not matched
     */
    public function __construct(
        public array $matches,
        public array $uncertain,
        public array $unmatchedDesign,
        public array $unmatchedPage,
        public array $symbols = [],
        public array $pageSymbols = [],
    ) {}
}
