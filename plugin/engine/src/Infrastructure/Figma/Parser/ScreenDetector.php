<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma\Parser;

use DesignQa\Domain\Model\ScreenKind;
use DesignQa\Domain\Model\ScreenSpec;

/**
 * Finds the screens a Figma link points at.
 *
 * - A link to a frame is one screen.
 * - A link to a section, page or group: each visible top-level frame inside it is a screen.
 *
 * The width comes from the frame itself, never from its name. The kind is guessed from the name
 * ("390_Mobile Menu" is a menu screen).
 */
final class ScreenDetector
{
    private const SCREEN_TYPES = ['FRAME', 'COMPONENT', 'INSTANCE'];
    private const CONTAINER_TYPES = ['SECTION', 'CANVAS', 'GROUP'];

    /**
     * @return list<FigmaNode> widest first; page screens before menu screens of the same width
     */
    public function screens(FigmaNode $root): array
    {
        $candidates = match (true) {
            in_array($root->type(), self::SCREEN_TYPES, true) => [$root],
            in_array($root->type(), self::CONTAINER_TYPES, true) => array_values(array_filter(
                $root->children(),
                fn(FigmaNode $child): bool => $child->isVisible() && in_array($child->type(), self::SCREEN_TYPES, true),
            )),
            default => [],
        };
        $screens = array_values(array_filter($candidates, static function (FigmaNode $n): bool {
            $box = $n->box();

            return $box !== null && $box->width >= 1.0;
        }));

        usort($screens, fn(FigmaNode $a, FigmaNode $b): int => [$this->spec($b)->width, $this->spec($a)->kind === ScreenKind::Menu, $a->box()?->x]
            <=> [$this->spec($a)->width, $this->spec($b)->kind === ScreenKind::Menu, $b->box()?->x]);

        return $screens;
    }

    public function spec(FigmaNode $screen): ScreenSpec
    {
        $kind = preg_match('/menu/i', $screen->name()) === 1 ? ScreenKind::Menu : ScreenKind::Page;
        $box = $screen->box();
        $width = (int) round($box !== null ? $box->width : 0.0);

        // A menu screen shows one screenful with the menu open: only that viewport counts on the page.
        $height = $kind === ScreenKind::Menu && $box !== null ? (int) round($box->height) : null;

        return new ScreenSpec($screen->name(), $width, $kind, $height > 0 ? $height : null);
    }
}
