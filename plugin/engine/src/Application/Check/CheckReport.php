<?php

declare(strict_types=1);

namespace DesignQa\Application\Check;

use DesignQa\Application\Read\SkippedScreen;
use DesignQa\Domain\Check\PresenceRules;
use DesignQa\Domain\Check\Severity;

final readonly class CheckReport
{
    /**
     * @param list<ScreenResult>  $screens
     * @param list<SkippedScreen> $skipped
     * @param PresenceRules       $presence how texts missing on the page or extra on it are judged
     * @param string              $designUrl the Figma link given for this check, '' when unknown
     *                                       (e.g. in a test fixture); used only to deep-link to a text's
     *                                       own node in the HTML report
     */
    public function __construct(
        public string $designName,
        public string $pageUrl,
        public array $screens,
        public array $skipped,
        public PresenceRules $presence = new PresenceRules(),
        public string $designUrl = '',
    ) {}

    /**
     * Findings of this severity on all screens, not grouped (missing and extra texts not included).
     */
    public function count(Severity $severity): int
    {
        return array_sum(array_map(static fn(ScreenResult $s): int => $s->count($severity), $this->screens));
    }

    public function comparedTexts(): int
    {
        return array_sum(array_map(static fn(ScreenResult $s): int => $s->comparedTexts(), $this->screens));
    }

    public function designTexts(): int
    {
        return array_sum(array_map(static fn(ScreenResult $s): int => $s->designTexts(), $this->screens));
    }
}
