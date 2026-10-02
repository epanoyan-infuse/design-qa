<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Application\Check;

use DesignQa\Application\Check\ScreenResult;
use DesignQa\Application\Check\TextStatus;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Check\Finding;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\MatchKind;
use DesignQa\Domain\Matching\MatchResult;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Matching\UncertainMatch;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Tests\Support\Texts as T;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScreenResult::class)]
#[CoversClass(TextStatus::class)]
final class ScreenResultTest extends TestCase
{
    public function testEveryTextGetsTheHonestStatus(): void
    {
        $ok = T::text('ok', 'Matches', 0, 0);
        $issues = T::text('issues', 'Differs', 0, 30);
        $partlyOk = T::text('partly', "First\nSecond", 0, 60, lines: 2);
        $partlyIssues = T::text('partly-issues', "One\nTwo", 0, 90, lines: 2);
        $manual = T::text('manual', 'User Name', 0, 120);
        $missing = T::text('missing', 'Lorem', 0, 150);
        $symbol = T::text('symbol', '”', 0, 180);

        [$partlyFirst, $partlySecond] = TextPart::paragraphs($partlyOk);
        [$one, $two] = TextPart::paragraphs($partlyIssues);
        $page = T::text('p', 'x', 0, 0);
        $issueMatch = new TextMatch(TextPart::whole($issues), $page, MatchKind::Exact, 1);
        $oneMatch = new TextMatch($one, $page, MatchKind::Exact, 1);
        $matching = new MatchResult(
            [new TextMatch(TextPart::whole($ok), $page, MatchKind::Exact, 1), $issueMatch, new TextMatch($partlyFirst, $page, MatchKind::Exact, 1), $oneMatch],
            [new UncertainMatch(TextPart::whole($manual), [$page, $page])],
            [$partlySecond, $two, TextPart::whole($missing)],
            [],
            [$symbol],
        );
        $finding = static fn(TextMatch $m): Finding => new Finding('color', 'Color', Severity::Critical, new Difference('a', 'b'), $m, null, 1, 1);
        $spec = new ScreenSpec('S', 390);
        $result = new ScreenResult(new Screen($spec, 1000, [$ok, $issues, $partlyOk, $partlyIssues, $manual, $missing, $symbol]), new Screen($spec, 1000, []), $matching, [$finding($issueMatch), $finding($oneMatch)]);

        self::assertSame([
            'ok' => TextStatus::Ok,
            'issues' => TextStatus::Issues,
            'partly' => TextStatus::PartlyCompared,
            'partly-issues' => TextStatus::IssuesPartlyCompared,
            'manual' => TextStatus::CheckManually,
            'missing' => TextStatus::NotFound,
            'symbol' => TextStatus::Symbol,
        ], $result->statuses());
        self::assertSame(7, array_sum($result->statusCounts()));
    }
}
