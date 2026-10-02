<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Check;

use DesignQa\Domain\Check\CheckRegistry;
use DesignQa\Domain\Check\Finding;
use DesignQa\Domain\Check\RuleSet;
use DesignQa\Domain\Check\StyleComparator;
use DesignQa\Domain\Matching\MatchKind;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Tests\Support\Styles;
use DesignQa\Tests\Support\Texts as T;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StyleComparator::class)]
#[CoversClass(CheckRegistry::class)]
#[CoversClass(Finding::class)]
final class StyleComparatorTest extends TestCase
{
    public function testMatchingStylesGiveNoFindings(): void
    {
        self::assertSame([], $this->compare(T::text('f', 'Audit frameworks', 0, 0, Styles::body(700)), T::text('p', 'Audit frameworks', 0, 0, Styles::body(700))));
    }

    public function testBoldWordInsideASentenceIsComparedOnItsOwn(): void
    {
        // Figma: "Get the book" regular with "book" bold. Page: all regular.
        $design = T::styled('f', [['Get the ', Styles::body()], ['book', Styles::body(700)]]);
        $page = T::text('p', 'Get the book', 0, 0);

        $findings = $this->compare($design, $page);

        self::assertCount(1, $findings);
        self::assertSame('font-weight', $findings[0]->checkId);
        self::assertSame('book', $findings[0]->part);
        self::assertSame([4, 10], [$findings[0]->affected, $findings[0]->total]);
        self::assertSame(['Bold (700)', 'Regular (400)'], [$findings[0]->difference->design, $findings[0]->difference->page]);
    }

    public function testSameDifferenceInSeparatePlacesListsEveryPlace(): void
    {
        $design = T::styled('f', [['Buy ', Styles::body(700)], ['the book ', Styles::body()], ['now', Styles::body(700)]]);

        $findings = $this->compare($design, T::text('p', 'Buy the book now', 0, 0));

        self::assertCount(1, $findings);
        self::assertSame('Buy … now', $findings[0]->part);
        self::assertSame(6, $findings[0]->affected);
    }

    public function testSameDifferenceOnTheWholeTextHasNoPart(): void
    {
        $findings = $this->compare(T::text('f', 'About the book', 0, 0, Styles::body(color: '#003867')), T::text('p', 'About the book', 0, 0, Styles::body(color: '#94A3B8')));

        self::assertCount(1, $findings);
        self::assertSame('color', $findings[0]->checkId);
        self::assertNull($findings[0]->part);
    }

    public function testDifferentWordingComparesTheMainStyles(): void
    {
        $design = T::text('f', 'The Appendices equip you with the tools', 0, 0, Styles::body(700));
        $page = T::text('p', 'These worksheets equip you with the tools', 0, 0, Styles::body());

        $findings = $this->compare($design, $page, MatchKind::Similar);

        self::assertSame(['text', 'font-weight'], array_map(static fn(Finding $f) => $f->checkId, $findings));
        self::assertNull($findings[1]->part, 'main styles compared, no letter-level part');
    }

    public function testLineHeightOnlyWhenBothWrap(): void
    {
        $single = $this->compare(T::text('f', 'New Book', 0, 0, Styles::body()), T::text('p', 'New Book', 0, 0, new \DesignQa\Domain\Model\TextStyle('Plus Jakarta Sans', 400, 18.0, false, Styles::body()->color, 0.0, 11.0)));
        $multi = $this->compare(T::text('f', 'Body text', 0, 0, Styles::body(), 3), T::text('p', 'Body text', 0, 0, new \DesignQa\Domain\Model\TextStyle('Plus Jakarta Sans', 400, 18.0, false, Styles::body()->color, 0.0, 30.6), 3));

        self::assertSame([], $single);
        self::assertSame(['line-height'], array_map(static fn(Finding $f) => $f->checkId, $multi));
    }

    public function testUnknownCheckInConfigIsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('names check "hover-color", which does not exist');
        CheckRegistry::standard()->configured(RuleSet::fromArray(['hover-color' => ['severity' => 'critical']]));
    }

    /**
     * @return list<Finding>
     */
    private function compare(TextElement $design, TextElement $page, MatchKind $kind = MatchKind::Exact): array
    {
        $rules = RuleSet::fromArray(require dirname(__DIR__, 4) . '/config/rules.php');
        $comparator = new StyleComparator(CheckRegistry::standard()->configured($rules));

        return $comparator->compare(new TextMatch(TextPart::whole($design), $page, $kind, 1.0));
    }
}
