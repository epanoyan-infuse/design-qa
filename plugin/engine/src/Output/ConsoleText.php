<?php

declare(strict_types=1);

namespace DesignQa\Output;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Makes text from a web page or a Figma file safe to print in a terminal.
 */
final class ConsoleText
{
    /**
     * Removes control characters (e.g. terminal escape codes a page could contain), keeping line breaks.
     */
    public static function clean(string $text): string
    {
        // Invalid UTF-8 (e.g. a cut multibyte character) is replaced, not dropped with the whole text.
        return (string) preg_replace('/[^\P{Cc}\n]/u', '', mb_scrub($text, 'UTF-8'));
    }

    /**
     * Whitespace collapsed, cut to $length characters with "…".
     */
    public static function shorten(string $text, int $length): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', mb_scrub($text, 'UTF-8')));

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }

    /**
     * Clean, and escaped so "<...>" in the text is not read as console formatting.
     */
    public static function forFormatted(string $text): string
    {
        return OutputFormatter::escape(self::clean($text));
    }
}
