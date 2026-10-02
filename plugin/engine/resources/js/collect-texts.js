/*
 * design-qa page collector: a function called in the page as (collector)({ viewportOnly }). It
 * returns raw browser values only. Every decision
 * (is a text visible, cut off, an icon, what is its color) is made in PHP (PageSnapshotMapper) with
 * the same rules as the design side, where it is unit-tested.
 *
 * Output: { url, viewport, documentHeight, blocks: [...] }
 * - A block is one piece of text as a reader sees it: all text of the nearest non-inline container
 *   (<p>, <h2>, <li>, a button...), so "<em>Title</em> teaches..." stays one text.
 * - A segment is one DOM text node, a whitespace node ({ text, style: null }) or a <br>
 *   ({ lineBreak: true }). Text segments carry their rects, computed style, combined opacity and the
 *   area left visible by ancestors that clip overflow.
 * - Form fields that show a placeholder (empty text inputs and textareas) are blocks too, with
 *   placeholder: true, the ::placeholder style and the placeholder's position inside the field.
 */
(options = {}) => {
    const viewportOnly = options.viewportOnly === true;
    if (viewportOnly) scrollTo(0, 0);

    const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'SVG', 'TEXTAREA', 'SELECT', 'OPTION', 'HEAD']);

    const viewportWidth = document.documentElement.clientWidth;
    const styleCache = new Map();
    const opacityCache = new Map();
    const clipCache = new Map();
    const colorCache = new Map();

    const style = (el) => {
        let s = styleCache.get(el);
        if (!s) {
            s = getComputedStyle(el);
            styleCache.set(el, s);
        }
        return s;
    };

    const docRect = (r) => ({ x: r.left + scrollX, y: r.top + scrollY, width: r.width, height: r.height });

    /* Product of the element's and all its ancestors' opacity. */
    const opacity = (el) => {
        if (!el || el.nodeType !== 1) return 1;
        if (opacityCache.has(el)) return opacityCache.get(el);
        const value = parseFloat(style(el).opacity) * opacity(el.parentElement);
        opacityCache.set(el, value);
        return value;
    };

    /* Area left visible by ancestors that clip overflow, starting from the page width.
       null means "no limit" (JSON has no Infinity). */
    const clip = (el) => {
        if (!el || el.nodeType !== 1 || el === document.documentElement || el === document.body) {
            return { x: 0, right: viewportWidth, y: viewportOnly ? 0 : null, bottom: viewportOnly ? innerHeight : null };
        }
        if (clipCache.has(el)) return clipCache.get(el);
        const area = { ...clip(el.parentElement) };
        const s = style(el);
        const r = docRect(el.getBoundingClientRect());
        if (s.overflowX !== 'visible') {
            area.x = Math.max(area.x, r.x);
            area.right = Math.min(area.right, r.x + r.width);
        }
        if (s.overflowY !== 'visible') {
            area.y = area.y === null ? r.y : Math.max(area.y, r.y);
            area.bottom = area.bottom === null ? r.y + r.height : Math.min(area.bottom, r.y + r.height);
        }
        clipCache.set(el, area);
        return area;
    };

    /* sRGB bytes of any CSS color (oklch, lab, color(display-p3 ...)), as the browser converts it. */
    const canvas = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    const srgb = (css) => {
        if (!css || /^rgba?\(/.test(css)) return null;
        if (colorCache.has(css)) return colorCache.get(css);
        let value = null;
        if (canvas) {
            canvas.clearRect(0, 0, 1, 1);
            canvas.fillStyle = '#000';
            canvas.fillStyle = css;
            canvas.fillRect(0, 0, 1, 1);
            const d = canvas.getImageData(0, 0, 1, 1).data;
            value = [d[0], d[1], d[2], d[3] / 255];
        }
        colorCache.set(css, value);
        return value;
    };

    /* Raw font measurement for each family of a font-family stack: can the browser draw the text's
       own characters with it (a web font that loaded, or an installed font)? Measured by comparing
       text widths against two generic fallbacks. PHP decides from this which font is drawn.
       Returns [{ family, draws }] in stack order, or null when nothing could be measured. */
    const fontCache = new Map();
    const baseCache = new Map();
    const fontProbe = document.createElement('canvas').getContext('2d');
    const width = (font, sample) => { fontProbe.font = font; return fontProbe.measureText(sample).width; };
    const baseWidth = (s, generic, sample) => {
        const key = s.fontStyle + '|' + s.fontWeight + '|' + generic + '|' + sample;
        if (!baseCache.has(key)) baseCache.set(key, width(`${s.fontStyle} ${s.fontWeight} 40px ${generic}`, sample));
        return baseCache.get(key);
    };
    const fontStack = (s, text) => {
        const sample = text.replace(/\s+/g, '').slice(0, 40);
        if (!fontProbe || sample === '') return null;
        const key = s.fontFamily + '|' + s.fontWeight + '|' + s.fontStyle + '|' + sample;
        if (!fontCache.has(key)) {
            fontCache.set(key, s.fontFamily.split(',').map((f) => f.trim()).filter(Boolean).map((family) => ({
                family,
                draws: ['monospace', 'serif'].some((generic) => width(`${s.fontStyle} ${s.fontWeight} 40px ${family}, ${generic}`, sample) !== baseWidth(s, generic, sample)),
            })));
        }
        return fontCache.get(key);
    };

    /* Viewport-only reads (menu screens): is the text under another element, e.g. the open menu?
     * Every rect is checked (a wrapped segment has one rect per line), each at 5 sample points
     * (center and inset corners) rather than one, and a rect only counts as covered when most of
     * its samples are hit; the segment is covered when that holds for most of its total area. A
     * single center-point test on the first rect only would wrongly decide a multi-line segment by
     * whichever line happens to come first. */
    const COVER_SAMPLE_INSET = 2;

    /* Alpha of a computed background-color: getComputedStyle always resolves it to rgb()/rgba()
       except for some wide-gamut colors, where srgb() converts it like any other CSS color. */
    const alpha = (css) => {
        const m = /^rgba?\(([^)]+)\)/.exec(css);
        if (m) {
            const parts = m[1].split(',').map((p) => parseFloat(p));
            return parts.length > 3 ? parts[3] : 1;
        }
        const rgb = srgb(css);
        return rgb ? rgb[3] : 0;
    };

    /* Only a layer that itself opaquely paints can cover a text: mirrors FigmaTextCollector's
       paintsOpaqueRectangle (normal blend, a fully opaque SOLID fill only — like Figma's own
       isOpaqueSolid, an image or gradient fill never counts) so both sides apply exactly the same
       covering rule (accuracy rule 3) — an invisible full-viewport click-catcher (a common pattern
       for closing a menu on outside click) must not count as covering. A DOM element's box is
       always axis-aligned, so no separate "rectangular" check is needed here. */
    const paintsOpaquely = (el) => {
        const s = style(el);
        if (s.mixBlendMode !== 'normal' || opacity(el) < 0.999) return false;
        return alpha(s.backgroundColor) >= 0.999;
    };
    const isPointCovered = (el, x, y) => {
        const stack = document.elementsFromPoint(x, y);
        for (const hit of stack) {
            if (el.contains(hit) || hit.contains(el)) return false;
            if (paintsOpaquely(hit)) return true;
        }
        return false;
    };
    const covered = (el, rects) => {
        if (!viewportOnly || rects.length === 0) return false;
        let coveredArea = 0;
        let totalArea = 0;
        for (const r of rects) {
            const area = r.width * r.height;
            if (area <= 0) continue;
            totalArea += area;
            const x0 = r.x - scrollX;
            const y0 = r.y - scrollY;
            const inX = Math.min(COVER_SAMPLE_INSET, r.width / 2);
            const inY = Math.min(COVER_SAMPLE_INSET, r.height / 2);
            const points = [
                [x0 + r.width / 2, y0 + r.height / 2],
                [x0 + inX, y0 + inY],
                [x0 + r.width - inX, y0 + inY],
                [x0 + inX, y0 + r.height - inY],
                [x0 + r.width - inX, y0 + r.height - inY],
            ];
            const hits = points.filter(([x, y]) => isPointCovered(el, x, y)).length;
            if (hits >= points.length / 2) coveredArea += area;
        }
        return totalArea > 0 && coveredArea / totalArea >= 0.5;
    };

    const container = (el) => {
        for (let e = el; e && e !== document.body; e = e.parentElement) {
            const display = style(e).display;
            if (display !== 'inline' && display !== 'contents') return e;
        }
        return document.body;
    };

    const describe = (el) => {
        let d = el.tagName.toLowerCase();
        if (el.id) d += '#' + el.id;
        const classes = [...el.classList].filter((c) => !c.startsWith('elementor-repeater-item')).slice(0, 3);
        if (classes.length) d += '.' + classes.join('.');
        return d;
    };

    const pathOf = (el) => {
        const path = [];
        for (let e = el; e && e !== document.body && path.length < 8; e = e.parentElement) path.unshift(describe(e));
        return path;
    };

    const blocks = new Map();
    const blockFor = (el) => {
        const owner = container(el);
        let block = blocks.get(owner);
        if (!block) {
            block = { id: 'b' + blocks.size, tag: owner.tagName.toLowerCase(), path: pathOf(owner), segments: [] };
            blocks.set(owner, block);
        }
        return block;
    };

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT, {
        acceptNode: (node) => {
            if (node.nodeType === Node.TEXT_NODE) return NodeFilter.FILTER_ACCEPT;
            if (SKIP_TAGS.has(node.tagName.toUpperCase())) return NodeFilter.FILTER_REJECT;
            return node.tagName === 'BR' ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
        },
    });

    const range = document.createRange();
    let id = 0;
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.parentElement) blockFor(node.parentElement).segments.push({ lineBreak: true });
            continue;
        }

        const el = node.parentElement;
        if (!el) continue;

        /* Whitespace between inline elements: kept even when it has no box (e.g. at a line wrap). */
        if (node.data.trim() === '') {
            blockFor(el).segments.push({ text: ' ', style: null });
            continue;
        }

        const s = style(el);
        range.selectNodeContents(node);
        const rects = [...range.getClientRects()].filter((r) => r.width > 0 && r.height > 0).map(docRect);
        blockFor(el).segments.push({
            id: 't' + id++,
            text: node.data,
            rects,
            covered: covered(el, rects),
            visibility: s.visibility,
            opacity: opacity(el),
            clip: clip(el),
            style: {
                fontFamily: s.fontFamily,
                fontWeight: s.fontWeight,
                fontSize: s.fontSize,
                fontStyle: s.fontStyle,
                color: s.color,
                colorSrgb: srgb(s.color),
                textFillColor: s.webkitTextFillColor,
                textFillColorSrgb: srgb(s.webkitTextFillColor),
                backgroundClip: s.backgroundClip || s.webkitBackgroundClip,
                fontStack: fontStack(s, node.data),
                letterSpacing: s.letterSpacing,
                lineHeight: s.lineHeight,
                textTransform: s.textTransform,
            },
        });
    }

    /* Placeholders: shown by empty text fields, styled by ::placeholder. */
    const PLACEHOLDER_TYPES = new Set(['text', 'email', 'tel', 'url', 'search', 'password', 'number']);
    let placeholders = 0;
    for (const field of document.querySelectorAll('input[placeholder], textarea[placeholder]')) {
        const text = field.getAttribute('placeholder') || '';
        const isTextarea = field.tagName === 'TEXTAREA';
        if (text.trim() === '' || field.value !== '' || (!isTextarea && !PLACEHOLDER_TYPES.has(field.type))) continue;

        const s = style(field);
        const ph = getComputedStyle(field, '::placeholder');
        const box = field.getBoundingClientRect();
        const px = (v) => parseFloat(v) || 0;
        const fontSize = px(ph.fontSize);
        const lineHeight = px(ph.lineHeight) || fontSize * 1.2;
        let rects = [];
        if (box.width > 0 && box.height > 0) {
            const font = `${ph.fontStyle} ${ph.fontWeight} ${ph.fontSize} ${ph.fontFamily}`;
            const textWidth = (t) => (fontProbe ? width(font, t) : Infinity);
            const left = box.left + px(s.borderLeftWidth) + px(s.paddingLeft);
            const innerWidth = box.width - px(s.borderLeftWidth) - px(s.borderRightWidth) - px(s.paddingLeft) - px(s.paddingRight);
            if (isTextarea) {
                /* A textarea placeholder keeps its line breaks and wraps at the field width. */
                let top = box.top + px(s.borderTopWidth) + px(s.paddingTop);
                for (const line of text.split('\n')) {
                    let rest = textWidth(line);
                    do {
                        rects.push(docRect({ left, top, width: Math.min(innerWidth, rest), height: lineHeight }));
                        rest -= innerWidth;
                        top += lineHeight;
                    } while (rest > 0 && Number.isFinite(rest));
                }
            } else {
                const top = box.top + (box.height - lineHeight) / 2;
                rects = [docRect({ left, top, width: Math.min(innerWidth, textWidth(text)), height: lineHeight })];
            }
        }
        blocks.set(field, {
            id: 'p' + placeholders++,
            tag: field.tagName.toLowerCase(),
            path: pathOf(field),
            placeholder: true,
            segments: [{
                id: 't' + id++,
                text,
 rects,
                covered: covered(field, rects),
                visibility: s.visibility,
                opacity: opacity(field) * (Number.isNaN(parseFloat(ph.opacity)) ? 1 : parseFloat(ph.opacity)),
                clip: clip(field.parentElement),
                style: {
                    fontFamily: ph.fontFamily,
                    fontWeight: ph.fontWeight,
                    fontSize: ph.fontSize,
                    fontStyle: ph.fontStyle,
                    color: ph.color,
                    colorSrgb: srgb(ph.color),
                    textFillColor: ph.webkitTextFillColor,
                    textFillColorSrgb: srgb(ph.webkitTextFillColor),
                    backgroundClip: ph.backgroundClip || ph.webkitBackgroundClip,
                    fontStack: fontStack(ph, text),
                    letterSpacing: ph.letterSpacing,
                    lineHeight: ph.lineHeight,
                    textTransform: ph.textTransform,
                },
            }],
        });
    }

    return {
        url: location.href,
        viewport: { width: viewportWidth, height: innerHeight },
        documentHeight: viewportOnly ? innerHeight : document.documentElement.scrollHeight,
        blocks: [...blocks.values()].filter((b) => b.segments.some((seg) => seg.id)),
    };
}
