<?php

declare(strict_types=1);

namespace DesignQa\Tests\Support;

use DesignQa\Domain\Model\Rect;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Domain\Model\StyleRun;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Domain\Model\TextStyle;

/**
 * Builds texts and screens for matching and checking tests.
 */
final class Texts
{
    public static function text(string $id, string $content, float $x, float $y, ?TextStyle $style = null, int $lines = 1): TextElement
    {
        return new TextElement($id, $content, [new StyleRun(0, mb_strlen($content), $style ?? Styles::body())], new Rect($x, $y, 200, 20 * $lines), $lines);
    }

    /**
     * @param non-empty-list<array{string, TextStyle}> $pieces content and style of each run, in order
     */
    public static function styled(string $id, array $pieces, float $x = 0, float $y = 0, int $lines = 1): TextElement
    {
        $runs = [];
        $content = '';
        foreach ($pieces as [$text, $style]) {
            $runs[] = new StyleRun(mb_strlen($content), mb_strlen($text), $style);
            $content .= $text;
        }

        return new TextElement($id, $content, $runs, new Rect($x, $y, 200, 20 * $lines), $lines);
    }

    /**
     * @param list<TextElement> $texts
     */
    public static function screen(array $texts, float $height = 1000, int $width = 1000): Screen
    {
        return new Screen(new ScreenSpec('Screen', $width), $height, $texts);
    }
}
