<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

use DesignQa\Domain\Check\Severity;

/**
 * One checked screen size: its issues by page section, and what was not compared.
 */
final readonly class SizeView
{
    /**
     * @param string               $id              safe for HTML ids, e.g. "s3-390"
     * @param string               $tabLabel        "800px", or "390px (390_Mobile Menu)" when two screens share a width
     * @param string               $linkKey         how the size appears in the report link (#tablet/800), unique per report
     * @param int                  $critical        critical issues on this size, missing and extra texts included
     * @param int                  $differences     issues in matched texts (the rows of the Issues view)
     * @param Severity|null        $missingSeverity how a missing text counts (null: not counted as an issue)
     * @param list<SectionView>    $sections        sections with issues, in page order
     * @param list<PropertyGroupView> $byProperty   the same issues grouped by property instead, in
     *                                               check order (critical checks first)
     * @param list<string>         $manual          texts to check by hand
     * @param list<MovedView>      $moved           texts matched well away from their Figma position
     * @param list<array{string, int}> $missing   design texts not on the page, with how often (repeats grouped)
     * @param list<array{string, int}> $extra     page texts not in the design, with how often
     * @param list<string>         $symbols         symbol-only texts, not compared
     * @param list<array{string, list<string>, int}> $notMatching texts that differ from the design: text, the properties that differ, how many such texts
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $width,
        public string $tabLabel,
        public string $linkKey,
        public int $critical,
        public int $nonCritical,
        public int $differences,
        public ?Severity $missingSeverity,
        public ?Severity $extraSeverity,
        public int $compared,
        public int $total,
        public array $sections,
        public array $byProperty,
        public array $manual,
        public array $moved,
        public array $missing,
        public array $extra,
        public array $symbols,
        public array $notMatching,
    ) {}
}
