<?php

declare(strict_types=1);

namespace DesignQa\Output;

use DesignQa\Application\Read\ReadResult;
use DesignQa\Domain\Check\Format;
use DesignQa\Domain\Model\ExcludedText;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\TextElement;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shows what was read from the design and the page, per screen, as tables.
 */
final class ReadConsoleWriter
{
    private const TEXT_WIDTH = 40;

    public function write(ReadResult $result, OutputInterface $output): void
    {
        $output->writeln(sprintf('Design: %s', ConsoleText::forFormatted($result->design->name)));
        $output->writeln(sprintf('Page:   %s', ConsoleText::forFormatted($result->pageUrl)));

        foreach ($result->pairs as $pair) {
            $output->writeln('');
            $output->writeln(sprintf('<info>== %s (%dpx)</info>', ConsoleText::forFormatted($pair->design->name()), $pair->design->width()));
            $this->screen('Figma', $pair->design, $output);
            $this->screen('Page', $pair->page, $output);
        }

        foreach ($result->skipped as $skipped) {
            $output->writeln('');
            $output->writeln(sprintf('<comment>== %s (%dpx): skipped, %s</comment>', ConsoleText::forFormatted($skipped->design->name()), $skipped->design->width(), $skipped->reason));
        }
    }

    private function screen(string $label, Screen $screen, OutputInterface $output): void
    {
        $output->writeln(sprintf('%s: %d texts%s', $label, count($screen->texts), $this->excludedSummary($screen->excluded)));

        $table = new Table($output);
        $table->setHeaders(['#', 'Text', 'Font', 'Weight', 'Size', 'Italic', 'Color', 'Letter sp.', 'Line h.', 'Lines', 'Mixed']);
        foreach ($screen->texts as $i => $text) {
            $table->addRow($this->row($i + 1, $text));
        }
        $table->render();
    }

    /**
     * @return list<string|int>
     */
    private function row(int $number, TextElement $text): array
    {
        $style = $text->dominantStyle();
        $content = $text->normalizedContent();
        if (mb_strlen($content) > self::TEXT_WIDTH) {
            $content = mb_substr($content, 0, self::TEXT_WIDTH - 1) . '…';
        }

        return [
            $number,
            ConsoleText::forFormatted($content),
            ConsoleText::forFormatted($style->fontFamily),
            $style->fontWeight,
            self::number($style->fontSize),
            $style->italic ? 'yes' : '',
            $style->color?->toHex() ?? 'not solid',
            self::number($style->letterSpacing),
            $style->lineHeight === null ? 'auto' : self::number($style->lineHeight),
            $text->lineCount,
            $text->hasMixedStyles() ? sprintf('%d runs', count($text->runs)) : '',
        ];
    }

    /**
     * @param list<ExcludedText> $excluded
     */
    private function excludedSummary(array $excluded): string
    {
        if ($excluded === []) {
            return '';
        }
        $counts = [];
        foreach ($excluded as $e) {
            $counts[$e->reason->describe()] = ($counts[$e->reason->describe()] ?? 0) + 1;
        }
        $parts = [];
        foreach ($counts as $reason => $count) {
            $parts[] = sprintf('%d %s', $count, $reason);
        }

        return sprintf(' (not compared: %s)', implode(', ', $parts));
    }

    private static function number(float $value): string
    {
        return Format::number($value);
    }
}
