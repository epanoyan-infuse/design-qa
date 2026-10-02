<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Output;

use DesignQa\Application\Check\CheckReport;
use DesignQa\Application\Check\ScreenResult;
use DesignQa\Domain\Check\Difference;
use DesignQa\Domain\Check\Finding;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\MatchKind;
use DesignQa\Domain\Matching\MatchResult;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Output\CheckConsoleWriter;
use DesignQa\Output\Issue;
use DesignQa\Output\IssueGrouper;
use DesignQa\Tests\Support\Texts as T;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(CheckConsoleWriter::class)]
#[CoversClass(IssueGrouper::class)]
#[CoversClass(Issue::class)]
final class CheckConsoleWriterTest extends TestCase
{
    public function testSameIssueOnEveryScreenIsShownOnceAsAllScreens(): void
    {
        $report = new CheckReport('Kesler', 'https://example.test/', [
            $this->screen('1920_Desktop', ['About the book' => ['#003867', '#94A3B8']]),
            $this->screen('390_Mobile', ['About the book' => ['#003867', '#94A3B8'], 'Alexander Kesler' => ['#012A4D', '#000000']]),
        ], []);

        $out = new BufferedOutput();
        (new CheckConsoleWriter())->write($report, $out);
        $text = $out->fetch();

        self::assertStringContainsString('🔴 2 critical   🟡 0 non-critical   ⚠️ 0 to check manually', $text);
        self::assertStringContainsString(' 1. "About the book"  Color: Figma #003867 → Page #94A3B8   [all screens]', $text);
        self::assertStringContainsString(' 2. "Alexander Kesler"  Color: Figma #012A4D → Page #000000   [390_Mobile]', $text);
        self::assertStringContainsString('Compared 3 of 3 design texts (100%).', $text);
    }

    public function testScreensNamedOnlyByWidthStillShowAllScreens(): void
    {
        $report = new CheckReport('Design', 'https://example.test/', [
            $this->screen('1440', ['Title' => ['#000000', '#FFFFFF']]),
            $this->screen('390', ['Title' => ['#000000', '#FFFFFF']]),
        ], []);

        $out = new BufferedOutput();
        (new CheckConsoleWriter())->write($report, $out);

        self::assertStringContainsString('"Title"  Color: Figma #000000 → Page #FFFFFF   [all screens]', $out->fetch());
    }

    /**
     * @param array<string, array{string, string}> $colors text => [figma, page]
     */
    private function screen(string $name, array $colors): ScreenResult
    {
        $matches = [];
        $findings = [];
        $texts = [];
        foreach ($colors as $content => [$figma, $page]) {
            $design = T::text($name . $content, $content, 0, 0);
            $texts[] = $design;
            $match = new TextMatch(TextPart::whole($design), T::text('p', $content, 0, 0), MatchKind::Exact, 1.0);
            $matches[] = $match;
            $findings[] = new Finding('color', 'Color', Severity::Critical, new Difference($figma, $page), $match, null, 1, 1);
        }
        $spec = new ScreenSpec($name, 390);

        return new ScreenResult(new Screen($spec, 1000, $texts), new Screen($spec, 1000, []), new MatchResult($matches, [], [], []), $findings);
    }
}
