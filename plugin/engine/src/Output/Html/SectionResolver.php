<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Output\ConsoleText;

/**
 * Splits a design screen into page sections (Hero, Worksheets, Reviews...) using the Figma layers,
 * so a report can list issues in the order a developer meets them on the page.
 *
 * The section level is the shallowest layer depth where the screen splits into at least three
 * groups; consecutive texts (top to bottom) under the same layer form one section. A section is
 * named after its own layer when the layer has a real name, otherwise after its largest text
 * ("Frame 2147225107" becomes "Your buyers are already choosing"). Sections are told apart by
 * their position, never by their name, so two sections with the same label stay separate.
 */
final class SectionResolver
{
    private const MIN_SECTIONS = 3;
    private const MAX_DEPTH = 4;
    private const LABEL_LENGTH = 40;

    /** Layer names Figma or designers give by default; they say nothing about the section. */
    private const DEFAULT_NAME = '/^(frame|group|container|rectangle|image|vector|ellipse|section|auto layout|app|component|instance|wrapper|div)(\s*\d+|:\S+)?$/i';

    /**
     * @return array<string, array{int, string}> text id => [section number (0, 1, ...), label], in screen order
     */
    public function sections(Screen $design): array
    {
        $depth = $this->depth($design->texts);
        $sections = [];
        $groups = [];
        $currentKey = null;
        foreach ($design->texts as $text) {
            // Layer ids when known: two sibling layers can both be called "Container:margin".
            $key = $text->pathIds[$depth] ?? $text->path[$depth]
                ?? ($text->pathIds !== [] ? $text->pathIds[array_key_last($text->pathIds)] : ($text->path === [] ? '' : $text->path[array_key_last($text->path)]));
            if ($currentKey === null || $key !== $currentKey) {
                $groups[] = [];
            }
            $currentKey = $key;
            $groups[array_key_last($groups)][] = $text;
        }

        foreach ($groups as $number => $group) {
            $label = $this->meaningful($group[0]->path[$depth] ?? '') ?? $this->largestText($group);
            foreach ($group as $text) {
                $sections[$text->id] = [$number, $label];
            }
        }

        return $sections;
    }

    /**
     * @param list<TextElement> $texts
     */
    private function depth(array $texts): int
    {
        for ($depth = 0; $depth < self::MAX_DEPTH; ++$depth) {
            $names = [];
            foreach ($texts as $text) {
                $layer = $text->pathIds[$depth] ?? $text->path[$depth] ?? null;
                if ($layer !== null) {
                    $names[$layer] = true;
                }
            }
            if (count($names) >= self::MIN_SECTIONS) {
                return $depth;
            }
        }

        return 0;
    }

    private function meaningful(string $name): ?string
    {
        $name = trim((string) preg_replace('/^section\s*[-–—:]\s*/iu', '', trim($name)));
        if ($name === '' || preg_match(self::DEFAULT_NAME, $name) === 1 || preg_match('/\d{4,}|:/', $name) === 1) {
            return null;
        }

        return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }

    /**
     * @param non-empty-list<TextElement> $group
     */
    private function largestText(array $group): string
    {
        $largest = $group[0];
        foreach ($group as $text) {
            if ($text->dominantStyle()->fontSize > $largest->dominantStyle()->fontSize) {
                $largest = $text;
            }
        }

        return ConsoleText::shorten($largest->content, self::LABEL_LENGTH);
    }
}
