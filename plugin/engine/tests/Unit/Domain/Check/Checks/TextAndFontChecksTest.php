<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Domain\Check\Checks;

use DesignQa\Domain\Check\CheckContext;
use DesignQa\Domain\Check\CheckRule;
use DesignQa\Domain\Check\Checks\FontFamilyCheck;
use DesignQa\Domain\Check\Checks\TextContentCheck;
use DesignQa\Domain\Check\Severity;
use DesignQa\Domain\Matching\MatchKind;
use DesignQa\Domain\Matching\TextMatch;
use DesignQa\Domain\Matching\TextPart;
use DesignQa\Domain\Model\TextCase;
use DesignQa\Domain\Model\TextStyle;
use DesignQa\Tests\Support\Texts as T;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextContentCheck::class)]
#[CoversClass(FontFamilyCheck::class)]
final class TextAndFontChecksTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ?array{string, string}}>
     */
    public static function wording(): iterable
    {
        yield 'same' => ['Get the book on Amazon', 'Get the book on Amazon', null];
        yield 'spacing and line breaks' => ["Get the book\non  Amazon", 'Get the book on Amazon', null];
        yield 'curly apostrophe from WordPress' => ["The Invisible Buyer's Appendices", 'The Invisible Buyer’s Appendices', null];
        yield 'dash and ellipsis variants' => ['2020 - 2026...', '2020 – 2026…', null];
        yield 'punctuation typo' => ['Title, Company', 'Title. Company', ['“Title, Company”', '“Title. Company”']];
        yield 'different word' => ['Get the book on Amazon', 'Buy the book on Amazon', ['“Get the book on Amazon”', '“Buy the book on Amazon”']];
        yield 'missing word, long text shows only the change' => [
            'Preference forms early. If a buyer only finds you once they are looking, you are already losing.',
            'Preference forms early. If a buyer finds you once they are looking, you are already losing.',
            ['“…early. If a buyer only finds you once…”', '“…early. If a buyer finds you once…”'],
        ];
    }

    /**
     * @param array{string, string}|null $expected
     */
    #[DataProvider('wording')]
    public function testWording(string $figma, string $page, ?array $expected): void
    {
        self::assertSame($expected, $this->text(T::text('f', $figma, 0, 0), T::text('p', $page, 0, 0)));
    }

    public function testCaseIsComparedAsDisplayed(): void
    {
        $upper = new TextStyle('A', 700, 11, false, null, 2, 17, TextCase::Upper);

        // Figma "About the book" in UPPER, page "About the book" with text-transform: uppercase.
        self::assertNull($this->text(T::text('f', 'About the book', 0, 0, $upper), T::text('p', 'About the book', 0, 0, $upper)));
        // Figma shows "ABOUT THE BOOK", the page shows "About the book".
        self::assertSame(['“ABOUT THE BOOK”', '“About the book”'], $this->text(T::text('f', 'About the book', 0, 0, $upper), T::text('p', 'About the book', 0, 0)));
    }

    public function testFontFamilyNameAndLoading(): void
    {
        $check = new FontFamilyCheck();
        $rule = new CheckRule('font-family', Severity::Critical);
        $context = new CheckContext(false);
        $font = static fn(string $family, ?string $drawn = null): TextStyle => new TextStyle($family, 400, 18, false, null, 0, 32, TextCase::None, $drawn);

        self::assertNull($check->compare($font('Plus Jakarta Sans'), $font('"Plus Jakarta Sans"', 'Plus Jakarta Sans'), $context, $rule));
        self::assertNull($check->compare($font('Plus Jakarta Sans'), $font('plus jakarta sans'), $context, $rule), 'unknown loading is not an error');

        $wrong = $check->compare($font('Plus Jakarta Sans'), $font('Georgia', 'Georgia'), $context, $rule);
        self::assertSame(['Plus Jakarta Sans', 'Georgia'], [$wrong?->design, $wrong?->page]);

        $fallback = $check->compare($font('Plus Jakarta Sans'), $font('Plus Jakarta Sans', ''), $context, $rule);
        self::assertSame('Plus Jakarta Sans (not loaded, a fallback font is shown)', $fallback?->page);
    }

    /**
     * @return array{string, string}|null
     */
    private function text(\DesignQa\Domain\Model\TextElement $figma, \DesignQa\Domain\Model\TextElement $page): ?array
    {
        $difference = (new TextContentCheck())->compare(new TextMatch(TextPart::whole($figma), $page, MatchKind::Exact, 1), new CheckRule('text', Severity::Critical));

        return $difference === null ? null : [$difference->design, $difference->page];
    }
}
