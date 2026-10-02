<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma\Parser;

use DesignQa\Domain\Model\ExcludedText;
use DesignQa\Domain\Model\ExclusionReason;
use DesignQa\Domain\Model\Rect;
use DesignQa\Domain\Model\Screen;
use DesignQa\Domain\Model\ScreenSpec;
use DesignQa\Domain\Model\TextElement;
use DesignQa\Domain\Model\Visibility;

/**
 * Collects the texts a person can actually see on one Figma screen.
 *
 * Excluded (with reason, never silently), using the shared Visibility rules:
 * - hidden layers (visible: false on the text or any ancestor), fully transparent layers;
 * - texts mostly outside a clipping frame or mask, e.g. the page behind a 796px-tall menu screen;
 * - texts covered by a rectangular layer painted on top with an opaque solid fill, e.g. the menu overlay;
 * - icon-font glyphs;
 * - texts Figma sent with no bounding box (not expected, but its API does not guarantee one).
 */
final readonly class FigmaTextCollector
{
    /** Layer types whose bounding box is their painted shape (so they can cover a text). */
    private const RECTANGULAR_TYPES = ['RECTANGLE', 'FRAME', 'COMPONENT', 'INSTANCE'];

    /** Node blend modes that paint normally (others let what is underneath show through). */
    private const NORMAL_BLEND_MODES = ['PASS_THROUGH', 'NORMAL'];

    public function __construct(private FigmaStyleResolver $styles = new FigmaStyleResolver()) {}

    public function collect(FigmaNode $screen, ScreenSpec $spec): Screen
    {
        $frame = $screen->box() ?? new Rect(0, 0, $spec->width, 0);
        $walk = new FigmaWalkState();
        foreach ($screen->children() as $child) {
            $this->walk($child, $walk, 1.0, $frame, [], [], false);
        }

        $texts = [];
        $excluded = [];
        foreach ($walk->texts as ['order' => $order, 'node' => $node, 'box' => $box, 'opacity' => $opacity, 'path' => $path, 'pathIds' => $pathIds, 'reason' => $reason]) {
            if ($box === null) {
                $excluded[] = new ExcludedText($node->id(), $node->characters(), $reason ?? ExclusionReason::NoGeometry);
                continue;
            }
            $reason ??= $this->coveringReason($order, $box, $walk->occluders);
            if ($reason !== null) {
                $excluded[] = new ExcludedText($node->id(), $node->characters(), $reason);
                continue;
            }
            $texts[] = $this->element($node, $box->translate(-$frame->x, -$frame->y), $opacity, $path, $pathIds);
        }

        usort($texts, static fn(TextElement $a, TextElement $b): int => [$a->box->y, $a->box->x] <=> [$b->box->y, $b->box->x]);

        return new Screen($spec, $frame->height, $texts, $excluded);
    }

    /**
     * @param list<string> $path    names of the ancestor layers
     * @param list<string> $pathIds ids of the same layers
     */
    private function walk(FigmaNode $node, FigmaWalkState $walk, float $opacity, Rect $clip, array $path, array $pathIds, bool $hidden): void
    {
        $order = $walk->nextOrder();
        $hidden = $hidden || !$node->isVisible();
        $opacity *= $node->opacity();
        $box = $node->box();

        if ($node->isText()) {
            if (trim($node->characters()) !== '') {
                $walk->texts[] = [
                    'order' => $order,
                    'node' => $node,
                    'box' => $box,
                    'opacity' => $opacity,
                    'path' => $path,
                    'pathIds' => $pathIds,
                    // A text with no bounding box (unexpected, but Figma's contract does not
                    // guarantee one) is excluded with a reason, never silently dropped.
                    'reason' => match (true) {
                        $box === null => ExclusionReason::NoGeometry,
                        $hidden => ExclusionReason::Hidden,
                        $opacity < Visibility::MIN_OPACITY => ExclusionReason::Transparent,
                        $box->fractionInside($clip) < Visibility::MIN_VISIBLE_FRACTION => ExclusionReason::Clipped,
                        Visibility::isIconOnly($node->characters()) => ExclusionReason::Icon,
                        default => null,
                    },
                ];
            }

            return;
        }

        if (!$hidden && $box !== null && $this->paintsOpaqueRectangle($node, $opacity)) {
            $visible = $box->intersect($clip);
            if ($visible !== null) {
                $walk->occluders[] = ['order' => $order, 'box' => $visible];
            }
        }

        $childClip = $node->clipsContent() && $box !== null ? self::narrow($clip, $box) : $clip;
        foreach ($node->children() as $child) {
            $this->walk($child, $walk, $opacity, $childClip, [...$path, $node->name()], [...$pathIds, $node->id()], $hidden);
            // A mask clips the siblings painted after it.
            if ($child->isMask() && $child->isVisible() && ($maskBox = $child->box()) !== null) {
                $childClip = self::narrow($childClip, $maskBox);
            }
        }
    }

    private function paintsOpaqueRectangle(FigmaNode $node, float $opacity): bool
    {
        return !$node->isMask()
            && in_array($node->type(), self::RECTANGULAR_TYPES, true)
            && in_array($node->blendMode(), self::NORMAL_BLEND_MODES, true)
            && $opacity >= 0.999
            && FigmaPaint::isOpaqueSolid($node->fills());
    }

    private static function narrow(Rect $clip, Rect $box): Rect
    {
        return $clip->intersect($box) ?? new Rect($box->x, $box->y, 0, 0);
    }

    /**
     * Layers are painted in document order, so an occluder later in the walk lies on top.
     *
     * @param list<array{order: int, box: Rect}> $occluders
     */
    private function coveringReason(int $textOrder, Rect $textBox, array $occluders): ?ExclusionReason
    {
        foreach ($occluders as ['order' => $order, 'box' => $box]) {
            if ($order > $textOrder && $textBox->fractionInside($box) >= Visibility::COVERED_FRACTION) {
                return ExclusionReason::Covered;
            }
        }

        return null;
    }

    /**
     * @param list<string> $path
     * @param list<string> $pathIds
     */
    private function element(FigmaNode $node, Rect $box, float $opacity, array $path, array $pathIds): TextElement
    {
        $runs = $this->styles->runs($node, $opacity);
        // lineHeightPx is also set for "auto" line height, so it counts lines better than a guess.
        $lineHeight = $node->lineHeightPx() ?? $runs[0]->style->fontSize * 1.2;
        $lines = $lineHeight > 0 ? max(1, (int) round($box->height / $lineHeight)) : 1;

        return new TextElement($node->id(), $node->characters(), $runs, $box, $lines, $path, $pathIds);
    }
}
