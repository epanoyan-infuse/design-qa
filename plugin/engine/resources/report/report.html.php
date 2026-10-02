<?php

declare(strict_types=1);

/*
 * The HTML report. Receives $view (ReportView), $css and $js (inlined, so the report is one file
 * that works offline), and $e (escapes any text for HTML). Every value from Figma or the page
 * goes through $e.
 *
 * @var DesignQa\Output\Html\ReportView $view
 * @var string $css
 * @var string $js
 * @var string $scriptHash base64 SHA-256 of $js, allowed by the Content Security Policy
 * @var Closure(string): string $e
 */

use DesignQa\Domain\Check\Severity;
use DesignQa\Output\Html\MovedView;
use DesignQa\Output\Html\PropertyGroupView;
use DesignQa\Output\Html\RowView;
use DesignQa\Output\Html\SectionView;

$swatch = static function (?string $hex, ?float $alpha) use ($e): string {
    return $hex === null ? '' : sprintf('<span class="swatch" style="--swatch:%s;--swatch-alpha:%s" aria-hidden="true"></span>', $e($hex), $e((string) ($alpha ?? 1.0)));
};
$value = static function (string $side, string $text, ?string $hex, ?float $alpha) use ($e, $swatch): string {
    return sprintf('<span class="value value--%s"><span class="value-side">%s</span> %s<code>%s</code></span>', $side, $side === 'figma' ? 'Figma' : 'Page', $swatch($hex, $alpha), $e($text));
};
// The CSS selector (click to copy, for the page's own DevTools) and the Figma deep link for one
// text, however it's shown (an issue row, or the "found but moved" list).
$findIt = static function (string $pageElement, ?string $figmaLink) use ($e): string {
    if ($pageElement === '' && $figmaLink === null) {
        return '';
    }
    $selector = $pageElement === '' ? '' : sprintf(
        '<button type="button" class="copy-selector" data-copy="%1$s" title="Copy this CSS selector, to find the element in the page’s DevTools"><code>%2$s</code><span class="copy-icon" aria-hidden="true"></span><span class="copy-done" aria-live="polite"></span></button>',
        $e($pageElement),
        $e($pageElement),
    );
    $figma = $figmaLink === null ? '' : sprintf('<a class="figma-link" href="%s" target="_blank" rel="noreferrer noopener">Open in Figma <span aria-hidden="true">↗</span></a>', $e($figmaLink));

    return sprintf('<p class="issue-location">%s%s</p>', $selector, $figma);
};
// $showSubject is false for a row whose text, notes and page element are identical to the row
// right before it (several properties differing on the same element): the quote and element
// selector are then left out visually (not repeated for every property) but still read out to
// screen readers, so the row stays understandable on its own.
$row = static function (RowView $r, bool $showSubject = true) use ($e, $value, $findIt): string {
    $notes = [];
    if ($r->part !== null) {
        $notes[] = sprintf('only “%s”', $e($r->part));
    }
    if ($r->wordingDiffers) {
        $notes[] = 'wording differs on the page';
    }
    if ($r->times > 1) {
        $notes[] = sprintf('%d times on this size', $r->times);
    }
    $where = $r->alsoOn === [] ? '' : sprintf('<p class="issue-also">Also on %s</p>', $e(implode(', ', $r->alsoOn)));
    $severityLabel = $r->severity === Severity::Critical ? 'Critical' : 'Non-critical';
    $noteText = $notes === [] ? '' : ' (' . implode(', ', $notes) . ')';

    $text = $showSubject
        ? sprintf('<span class="visually-hidden">%s: </span>“%s”%s', $severityLabel, $e($r->text), $notes === [] ? '' : sprintf(' <span class="issue-note">%s</span>', implode(', ', $notes)))
        : sprintf('<span class="visually-hidden">%s: “%s”%s</span>', $severityLabel, $e($r->text), $e($noteText));

    return sprintf(
        '<li class="issue issue--%s%s"><p class="issue-text">%s</p><p class="issue-property">%s</p><p class="issue-values">%s%s</p>%s%s</li>',
        $r->severity === Severity::Critical ? 'critical' : 'minor',
        $showSubject ? '' : ' issue--continued',
        $text,
        $e($r->property),
        $value('figma', $r->figma, $r->figmaSwatch, $r->figmaAlpha),
        $value('page', $r->page, $r->pageSwatch, $r->pageAlpha),
        $where,
        $showSubject ? $findIt($r->pageElement, $r->figmaLink) : '',
    );
};
// A list of rows, as <li>s: consecutive rows for the same text/notes/element only show that once.
$rows = static function (array $rows) use ($row): string {
    $out = '';
    $previousKey = null;
    foreach ($rows as $r) {
        $key = $r->text . "\0" . ($r->part ?? '') . "\0" . ($r->wordingDiffers ? '1' : '0') . "\0" . $r->times . "\0" . $r->pageElement;
        $out .= $row($r, $key !== $previousKey);
        $previousKey = $key;
    }

    return $out;
};
// Issue counts as badges. In tabs a zero stays visible (muted); in section headers it is left out.
$counts = static function (int $critical, int $minor, bool $hideZero = false): string {
    $badge = static fn(string $kind, string $name, int $n): string => $n === 0 && $hideZero ? '' : sprintf(
        '<span class="tab-count tab-count--%s%s">%d<span class="visually-hidden"> %s</span></span>',
        $kind,
        $n === 0 ? ' tab-count--zero' : '',
        $n,
        $name,
    );

    return sprintf('<span class="tab-counts">%s%s</span>', $badge('critical', 'critical', $critical), $badge('minor', 'non-critical', $minor));
};
// One accordion: a labelled, collapsible group of issue rows (a page section, or a property's rows
// grouped by section). $items are anything with ->count(Severity) and a way to get a label and a body.
$accordion = static function (array $items, Closure $label, Closure $body) use ($e, $counts): string {
    return implode('', array_map(
        static fn(int $k, $item): string => sprintf(
            '<details class="page-section"%s><summary class="page-section-title"><span>%s</span>%s</summary>%s</details>',
            $k === 0 ? ' open' : '',
            $e($label($item)),
            $counts($item->count(Severity::Critical), $item->count(Severity::NonCritical), hideZero: true),
            $body($item),
        ),
        array_keys($items),
        $items,
    ));
};
$list = static function (array $items, string $empty) use ($e): string {
    if ($items === []) {
        return sprintf('<p class="quiet">%s</p>', $e($empty));
    }

    return sprintf('<ul class="text-list">%s</ul>', implode('', array_map(static fn(string $item): string => '<li>' . $e($item) . '</li>', $items)));
};
$counted = static function (array $items, string $empty) use ($e): string {
    if ($items === []) {
        return sprintf('<p class="empty">%s</p>', $e($empty));
    }

    return sprintf('<ul class="text-list text-list--single">%s</ul>', implode('', array_map(
        static fn(array $item): string => sprintf('<li>“%s”%s</li>', $e($item[0]), $item[1] > 1 ? sprintf(' <span class="differ-times">%d times</span>', $item[1]) : ''),
        $items,
    )));
};
// A view whose entries are issues of one severity (missing and extra texts) shows its count as that badge.
$viewTab = static function (string $sizeId, string $key, string $label, int $count, bool $selected, ?Severity $severity = null) use ($e): string {
    return sprintf(
        '<button class="view-tab" type="button" id="tab-%1$s-%2$s" aria-controls="panel-%1$s-%2$s"%3$s>%4$s <span class="view-count%6$s">%5$d%7$s</span></button>',
        $e($sizeId),
        $e($key),
        $selected ? ' data-selected' : '',
        $e($label),
        $count,
        $severity === null || $count === 0 ? '' : ($severity === Severity::Critical ? ' view-count--critical' : ' view-count--minor'),
        $severity === null || $count === 0 ? '' : sprintf('<span class="visually-hidden"> %s</span>', $severity->value),
    );
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; script-src 'sha256-<?= $e($scriptHash) ?>'; base-uri 'none'; form-action 'none'">
<meta name="referrer" content="no-referrer">
<meta name="generator" content="design-qa">
<title>Design check: <?= $e($view->title) ?></title>
<style><?= $css ?></style>
</head>
<body>
<header class="head">
    <p class="head-design"><?= $e($view->title) ?></p>
    <h1 class="verdict <?= $view->critical > 0 ? 'verdict--fail' : 'verdict--pass' ?>"><?= $e($view->verdict()) ?></h1>
    <p class="head-facts">
        <span class="tab-count tab-count--critical"><?= $view->critical ?></span> critical
        <span class="tab-count tab-count--minor head-facts-gap"><?= $view->nonCritical ?></span> non-critical
        <span class="head-facts-rest"><?= $view->manual ?> to check by hand. Compared <?= $view->compared ?> of <?= $view->total ?> design texts.</span>
    </p>
    <p class="head-meta">
        <a href="<?= $e($view->pageUrl) ?>" rel="noreferrer noopener" target="_blank"><?= $e($view->pageUrl) ?></a>
        <span>Checked <?= $e($view->checkedAt) ?></span>
    </p>
    <?php foreach ($view->notChecked as $note) { ?>
        <p class="head-note">Not checked: <?= $e($note) ?></p>
    <?php } ?>
</header>

<main>
    <div class="devices" data-tabs aria-label="Device">
        <?php foreach ($view->devices as $i => $device) { ?>
            <button class="device-tab" type="button" id="tab-<?= $e($device->type->value) ?>" data-link="<?= $e($device->type->value) ?>"
                    aria-controls="panel-<?= $e($device->type->value) ?>"<?= $i === 0 ? ' data-selected' : '' ?>>
                <span class="device-name"><?= $e($device->type->label()) ?></span>
                <?= $counts($device->critical, $device->nonCritical) ?>
            </button>
        <?php } ?>
    </div>

    <?php foreach ($view->devices as $device) { ?>
        <section class="device" id="panel-<?= $e($device->type->value) ?>" aria-labelledby="tab-<?= $e($device->type->value) ?>">
            <?php if (count($device->sizes) > 1) { ?>
                <div class="sizes" data-tabs aria-label="<?= $e($device->type->label()) ?> size">
                    <?php foreach ($device->sizes as $j => $size) { ?>
                        <button class="size-tab" type="button" id="tab-<?= $e($size->id) ?>" data-link="<?= $e($size->linkKey) ?>"
                                aria-controls="panel-<?= $e($size->id) ?>"<?= $j === 0 ? ' data-selected' : '' ?> title="<?= $e($size->name) ?>">
                            <?= $e($size->tabLabel) ?> <?= $counts($size->critical, $size->nonCritical) ?>
                        </button>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php foreach ($device->sizes as $size) { ?>
                <div class="size" id="panel-<?= $e($size->id) ?>"<?= count($device->sizes) > 1 ? ' aria-labelledby="tab-' . $e($size->id) . '"' : '' ?>>
                    <h2 class="size-title"><?= $e($device->type->label()) ?>, <?= $e($size->tabLabel) ?> <span class="size-frame"><?= $e($size->name) ?></span></h2>
                    <?php
                    $differ = array_sum(array_column($size->notMatching, 2));
                    // Distinct texts: a repeated text is one entry (its list line says how often).
                    $missing = count($size->missing);
                    $extra = count($size->extra);
                    ?>
                    <p class="size-facts">
                        <?= $differ ?> <?= $differ === 1 ? 'text differs' : 'texts differ' ?>,
                        <?= $missing ?> missing and <?= $extra ?> extra on the page<?= count($size->manual) > 0 ? sprintf(', %d to check by hand', count($size->manual)) : '' ?>.
                        Compared <?= $size->compared ?> of <?= $size->total ?> design texts.
                    </p>

                    <div class="views" data-tabs aria-label="<?= $e($size->tabLabel) ?> results">
                        <?= $viewTab($size->id, 'issues', 'Issues', $size->differences, true) ?>
                        <?= $viewTab($size->id, 'differ', "Don't match", $differ, false) ?>
                        <?= $viewTab($size->id, 'missing', 'Missing on the page', $missing, false, $size->missingSeverity) ?>
                        <?= $viewTab($size->id, 'extra', 'Extra on the page', $extra, false, $size->extraSeverity) ?>
                        <?= $viewTab($size->id, 'manual', 'Check by hand', count($size->manual), false) ?>
                        <?= $viewTab($size->id, 'moved', 'Found but moved', count($size->moved), false) ?>
                    </div>

                    <div class="view" id="panel-<?= $e($size->id) ?>-issues" aria-labelledby="tab-<?= $e($size->id) ?>-issues">
                        <h3 class="view-title">Issues</h3>
                        <?php
                        $alsoCritical = array_filter([
                            $size->missingSeverity !== null && $missing > 0 ? sprintf('%d missing on the page', $missing) : null,
                            $size->extraSeverity !== null && $extra > 0 ? sprintf('%d extra on the page', $extra) : null,
                        ]);
                        ?>
                        <?php if ($alsoCritical !== []) { ?>
                            <p class="view-intro">Differences in texts found on both sides. Also counted as issues: <?= $e(implode(' and ', $alsoCritical)) ?> (see their tabs).</p>
                        <?php } ?>
                        <?php if ($size->sections === []) { ?>
                            <p class="empty">No differences on this size.</p>
                        <?php } else { ?>
                            <div class="group-by" data-tabs aria-label="Group issues by">
                                <button class="group-by-tab" type="button" id="tab-<?= $e($size->id) ?>-by-section" aria-controls="panel-<?= $e($size->id) ?>-by-section" data-selected>Section</button>
                                <button class="group-by-tab" type="button" id="tab-<?= $e($size->id) ?>-by-property" aria-controls="panel-<?= $e($size->id) ?>-by-property">Property</button>
                            </div>

                            <div class="view" id="panel-<?= $e($size->id) ?>-by-section" aria-labelledby="tab-<?= $e($size->id) ?>-by-section">
                                <h4 class="view-title">Section</h4>
                                <p class="accordion-tools" hidden>
                                    <button type="button" class="link-button" data-accordions="open">Open all</button>
                                    <button type="button" class="link-button" data-accordions="close">Close all</button>
                                </p>
                                <?= $accordion(
                                    $size->sections,
                                    static fn(SectionView $s): string => $s->label,
                                    static fn(SectionView $s): string => sprintf('<ol class="issues">%s</ol>', $rows($s->rows)),
                                ) ?>
                            </div>

                            <div class="view" id="panel-<?= $e($size->id) ?>-by-property" aria-labelledby="tab-<?= $e($size->id) ?>-by-property">
                                <h4 class="view-title">Property</h4>
                                <p class="accordion-tools" hidden>
                                    <button type="button" class="link-button" data-accordions="open">Open all</button>
                                    <button type="button" class="link-button" data-accordions="close">Close all</button>
                                </p>
                                <?= $accordion(
                                    $size->byProperty,
                                    static fn(PropertyGroupView $g): string => $g->label,
                                    static fn(PropertyGroupView $g): string => implode('', array_map(
                                        static fn(SectionView $s): string => sprintf('<p class="property-section-label">%s</p><ol class="issues">%s</ol>', $e($s->label), $rows($s->rows)),
                                        $g->sections,
                                    )),
                                ) ?>
                            </div>
                        <?php } ?>
                    </div>

                    <div class="view" id="panel-<?= $e($size->id) ?>-manual" aria-labelledby="tab-<?= $e($size->id) ?>-manual">
                        <h3 class="view-title">Check by hand</h3>
                        <p class="view-intro">These texts fit more than one place on the page equally well, so they were not compared. Compare them by eye.</p>
                        <?= $list($size->manual, 'Nothing to check by hand on this size.') ?>
                    </div>

                    <div class="view" id="panel-<?= $e($size->id) ?>-moved" aria-labelledby="tab-<?= $e($size->id) ?>-moved">
                        <h3 class="view-title">Found but moved</h3>
                        <?php if ($size->moved === []) { ?>
                            <p class="empty">Nothing moved on this size.</p>
                        <?php } else { ?>
                            <p class="view-intro">This wording is unique on both sides, so it's unmistakably the same text — it just sits much further from its Figma position than usual, which usually means the page's real content (more or less of it above this point) shifted the layout. Compared normally: any property differences are in the Issues tab.</p>
                            <ul class="text-list text-list--single">
                                <?php foreach ($size->moved as $m) { ?>
                                    <li>
                                        <span class="differ-text">“<?= $e($m->text) ?>”</span>
                                        <span class="moved-note">expected near <?= $m->designPercent ?>% down the page, found near <?= $m->pagePercent ?>%</span>
                                        <?= $findIt($m->pageElement, $m->figmaLink) ?>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>

                    <div class="view" id="panel-<?= $e($size->id) ?>-missing" aria-labelledby="tab-<?= $e($size->id) ?>-missing">
                        <h3 class="view-title">Missing on the page</h3>
                        <p class="view-intro">In the design, but no matching text on the page: the text is missing, or its wording is completely different (then it also appears under Extra on the page).</p>
                        <?= $counted($size->missing, 'Every design text was found on the page.') ?>
                        <?php if ($size->symbols !== []) { ?>
                            <p class="quiet">Symbols without letters are not compared: <?= $e(implode('  ', $size->symbols)) ?></p>
                        <?php } ?>
                    </div>

                    <div class="view" id="panel-<?= $e($size->id) ?>-extra" aria-labelledby="tab-<?= $e($size->id) ?>-extra">
                        <h3 class="view-title">Extra on the page</h3>
                        <p class="view-intro">On the page, but not in the design.</p>
                        <?= $counted($size->extra, 'The page has no texts that are not in the design.') ?>
                    </div>

                    <div class="view" id="panel-<?= $e($size->id) ?>-differ" aria-labelledby="tab-<?= $e($size->id) ?>-differ">
                        <h3 class="view-title">Texts that don't match the design</h3>
                        <?php if ($size->notMatching === []) { ?>
                            <p class="empty">Every compared text matches the design on this size.</p>
                        <?php } else { ?>
                            <p class="view-intro">Each text once, with what differs. The Issues tab has the values.</p>
                            <ul class="text-list text-list--single">
                                <?php foreach ($size->notMatching as [$text, $properties, $times]) { ?>
                                    <li><span class="differ-text">“<?= $e($text) ?>”</span><?= $times > 1 ? sprintf(' <span class="differ-times">%d times</span>', $times) : '' ?> <span class="differ-properties"><?= $e(implode(', ', $properties)) ?></span></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </section>
    <?php } ?>
</main>

<footer class="foot">
    <p>Figma values are read from the design file; page values are measured in Chrome at each screen width. Created by design-qa.</p>
</footer>

<script type="application/json" id="design-qa-data"><?= $view->dataJson ?></script>
<script><?= $js ?></script>
</body>
</html>
