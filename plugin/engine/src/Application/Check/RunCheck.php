<?php

declare(strict_types=1);

namespace DesignQa\Application\Check;

use DesignQa\Application\Port\SourceException;
use DesignQa\Application\Read\ReadDesignAndPage;
use DesignQa\Application\Read\ReadRequest;
use DesignQa\Domain\Check\PresenceRules;
use DesignQa\Domain\Check\StyleComparator;
use DesignQa\Domain\Matching\Matcher;
use DesignQa\Domain\Matching\TextMatch;

/**
 * Use case: read the design and the page, match texts per screen, and run the checks.
 */
final readonly class RunCheck
{
    public function __construct(
        private ReadDesignAndPage $reader,
        private Matcher $matcher,
        private StyleComparator $comparator,
        private PresenceRules $presence = new PresenceRules(),
    ) {}

    /**
     * @throws SourceException
     */
    public function execute(ReadRequest $request): CheckReport
    {
        $read = $this->reader->execute($request);

        $screens = [];
        foreach ($read->pairs as $pair) {
            $matching = $this->matcher->match($pair->design, $pair->page);
            $findings = array_merge(...array_map(fn(TextMatch $m): array => $this->comparator->compare($m, $pair->design->width()), $matching->matches));
            $screens[] = new ScreenResult($pair->design, $pair->page, $matching, $findings);
        }

        return new CheckReport($read->design->name, $read->pageUrl, $screens, $read->skipped, $this->presence, $request->designUrl);
    }
}
