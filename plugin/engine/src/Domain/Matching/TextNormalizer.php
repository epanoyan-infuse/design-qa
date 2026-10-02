<?php

declare(strict_types=1);

namespace DesignQa\Domain\Matching;

/**
 * Turns text into a matching key: lower case, letters and digits only, single spaces.
 *
 * Case and punctuation are ignored on purpose. Figma "ABOUT THE BOOK" and CSS text-transform
 * "About the book" are the same text; "Title, Company" vs "Title. Company" is a wording difference
 * for the (later) text content check, not a reason to lose the match.
 */
final class TextNormalizer
{
    public function key(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim($text);
    }

    /**
     * @return list<string>
     */
    public function words(string $text): array
    {
        return $this->wordsOfKey($this->key($text));
    }

    /**
     * @return list<string>
     */
    public function wordsOfKey(string $key): array
    {
        return $key === '' ? [] : explode(' ', $key);
    }
}
