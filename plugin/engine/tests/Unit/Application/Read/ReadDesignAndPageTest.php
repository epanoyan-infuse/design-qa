<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Application\Read;

use DesignQa\Application\Port\DesignSource;
use DesignQa\Application\Port\PageSource;
use DesignQa\Application\Port\SourceException;
use DesignQa\Application\Read\ReadDesignAndPage;
use DesignQa\Application\Read\ReadRequest;
use DesignQa\Domain\Model\Design;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenKind;
use DesignQa\Domain\Model\ScreenSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReadDesignAndPage::class)]
#[CoversClass(ReadRequest::class)]
final class ReadDesignAndPageTest extends TestCase
{
    public function testRendersPageOncePerScreenAndSkipsScreensThePageCouldNotShow(): void
    {
        $pages = $this->pages();
        $result = (new ReadDesignAndPage($this->design(), $pages))->execute(new ReadRequest('https://example.test/', 'figma'));

        self::assertSame([1920, 390, 390], array_map(static fn(ScreenSpec $s) => $s->width, $pages->requested));
        self::assertSame(['1920_Desktop', '390_Mobile'], array_map(static fn($p) => $p->design->name(), $result->pairs));
        self::assertSame(['1920_Desktop', '390_Mobile'], array_map(static fn($p) => $p->page->name(), $result->pairs));
        self::assertSame('390_Mobile Menu', $result->skipped[0]->design->name());
        self::assertSame('the menu could not be opened', $result->skipped[0]->reason);
    }

    public function testScreenFilterByWidthOrName(): void
    {
        $pages = $this->pages();
        (new ReadDesignAndPage($this->design(), $pages))->execute(new ReadRequest('https://example.test/', 'figma', ['390_mobile']));
        self::assertSame([390], array_map(static fn(ScreenSpec $s) => $s->width, $pages->requested));

        $pages = $this->pages();
        (new ReadDesignAndPage($this->design(), $pages))->execute(new ReadRequest('https://example.test/', 'figma', ['1920']));
        self::assertSame([1920], array_map(static fn(ScreenSpec $s) => $s->width, $pages->requested));
    }

    public function testUnknownScreenListsTheAvailableOnes(): void
    {
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('No design screen matches "768". Available: 1920_Desktop (1920), 390_Mobile (390), 390_Mobile Menu (390).');
        (new ReadDesignAndPage($this->design(), $this->pages()))->execute(new ReadRequest('https://example.test/', 'figma', ['768']));
    }

    private function design(): DesignSource
    {
        return new class implements DesignSource {
            public function load(string $designUrl): Design
            {
                return new Design('Kesler', [
                    new Screen(new ScreenSpec('1920_Desktop', 1920), 6000, []),
                    new Screen(new ScreenSpec('390_Mobile', 390), 7000, []),
                    new Screen(new ScreenSpec('390_Mobile Menu', 390, ScreenKind::Menu), 796, []),
                ]);
            }
        };
    }

    /**
     * @return PageSource&object{requested: list<ScreenSpec>}
     */
    private function pages(): PageSource
    {
        return new class implements PageSource {
            /** @var list<ScreenSpec> */
            public array $requested = [];

            public function capture(string $pageUrl, array $specs): array
            {
                $this->requested = $specs;

                return array_map(static fn(ScreenSpec $s): Screen => $s->kind === ScreenKind::Menu
                    ? new Screen($s, 0, [], [], 'the menu could not be opened')
                    : new Screen($s, 1000, []), $specs);
            }
        };
    }
}
