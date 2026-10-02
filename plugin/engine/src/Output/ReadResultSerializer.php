<?php

declare(strict_types=1);

namespace DesignQa\Output;

use DesignQa\Application\Read\ReadResult;
use DesignQa\Application\Read\ScreenPair;
use DesignQa\Application\Read\SkippedScreen;
use DesignQa\Domain\Model\ExcludedText;
use DesignQa\Domain\Model\Rect;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\StyleRun;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Domain\Model\TextStyle;

/**
 * Converts read results into plain arrays for JSON output.
 */
final class ReadResultSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(ReadResult $result): array
    {
        return [
            'design' => $result->design->name,
            'page' => $result->pageUrl,
            'screens' => array_map(fn(ScreenPair $pair): array => [
                'name' => $pair->design->name(),
                'width' => $pair->design->width(),
                'kind' => $pair->design->spec->kind->value,
                'design' => $this->screen($pair->design),
                'page' => $this->screen($pair->page),
            ], $result->pairs),
            'skipped' => array_map(static fn(SkippedScreen $s): array => [
                'name' => $s->design->name(),
                'width' => $s->design->width(),
                'reason' => $s->reason,
            ], $result->skipped),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function screen(Screen $screen): array
    {
        return [
            'height' => $screen->height,
            'texts' => array_map($this->text(...), $screen->texts),
            'excluded' => array_map(static fn(ExcludedText $e): array => [
                'id' => $e->id,
                'content' => $e->content,
                'reason' => $e->reason->value,
            ], $screen->excluded),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function text(TextElement $text): array
    {
        return [
            'id' => $text->id,
            'content' => $text->content,
            'box' => self::rect($text->box),
            'lines' => $text->lineCount,
            'path' => $text->path,
            'runs' => array_map(fn(StyleRun $run): array => [
                'start' => $run->start,
                'length' => $run->length,
                'text' => $text->textOf($run),
                'style' => $this->style($run->style),
            ], $text->runs),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function style(TextStyle $style): array
    {
        return [
            'fontFamily' => $style->fontFamily,
            'fontWeight' => $style->fontWeight,
            'fontSize' => $style->fontSize,
            'italic' => $style->italic,
            'color' => $style->color?->toHex(),
            'letterSpacing' => $style->letterSpacing,
            'lineHeight' => $style->lineHeight,
            'textCase' => $style->textCase->value,
        ];
    }

    /**
     * @return array{x: float, y: float, width: float, height: float}
     */
    private static function rect(Rect $rect): array
    {
        return ['x' => round($rect->x, 2), 'y' => round($rect->y, 2), 'width' => round($rect->width, 2), 'height' => round($rect->height, 2)];
    }
}
