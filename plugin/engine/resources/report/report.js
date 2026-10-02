/*
 * Tabs and accordions for the design-qa report: device tabs, size sub-tabs, result views
 * (Issues, Check by hand, ...), and "Open all / Close all" for the page-section accordions.
 *
 * The HTML works without this script: every panel is shown with its heading. The script turns
 * the [data-tabs] groups into ARIA tabs (roles, selection, roving tabindex, focusable panels).
 * The open device and size are kept in the address (#mobile, #tablet/800) so a link or a reload
 * opens the same view. Nothing is stored.
 */
(() => {
    document.documentElement.classList.add('js');

    const panelOf = (tab) => document.getElementById(tab.getAttribute('aria-controls'));

    const remember = () => {
        const device = document.querySelector('.devices > [aria-selected="true"]');
        if (!device) return;
        const size = panelOf(device).querySelector('.sizes > [aria-selected="true"]');
        history.replaceState(null, '', '#' + device.dataset.link + (size ? '/' + size.dataset.link : ''));
    };

    const select = (tab, { focus = false, keep = true } = {}) => {
        for (const other of tab.parentElement.querySelectorAll(':scope > [role="tab"]')) {
            const selected = other === tab;
            other.setAttribute('aria-selected', String(selected));
            other.tabIndex = selected ? 0 : -1;
            panelOf(other).hidden = !selected;
        }
        if (focus) tab.focus();
        if (keep) remember();
    };

    for (const list of document.querySelectorAll('[data-tabs]')) {
        list.setAttribute('role', 'tablist');
        const tabs = [...list.querySelectorAll(':scope > button')];
        tabs.forEach((tab, i) => {
            tab.setAttribute('role', 'tab');
            const panel = panelOf(tab);
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', tab.id);
            panel.tabIndex = 0;
            tab.addEventListener('click', () => select(tab));
            tab.addEventListener('keydown', (event) => {
                const move = { ArrowRight: 1, ArrowLeft: -1, Home: -i, End: tabs.length - 1 - i }[event.key];
                if (move === undefined) return;
                event.preventDefault();
                select(tabs[(i + move + tabs.length) % tabs.length], { focus: true });
            });
        });
        select(tabs.find((t) => t.hasAttribute('data-selected')) ?? tabs[0], { keep: false });
    }

    for (const tools of document.querySelectorAll('.accordion-tools')) {
        tools.hidden = false;
        tools.addEventListener('click', (event) => {
            const action = event.target.closest('[data-accordions]')?.dataset.accordions;
            if (!action) return;
            for (const section of tools.parentElement.querySelectorAll('details.page-section')) section.open = action === 'open';
        });
    }

    // Copy a row's CSS selector (to find the element in the page's own DevTools). Clipboard-write
    // needs a secure context, which a file:// report may not get, hence the execCommand fallback.
    const copyText = (text) => {
        if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(text).catch(() => legacyCopy(text));
        legacyCopy(text);
        return Promise.resolve();
    };
    const legacyCopy = (text) => {
        const field = document.createElement('textarea');
        field.value = text;
        field.style.cssText = 'position:fixed;top:-1000px;opacity:0';
        document.body.appendChild(field);
        field.select();
        try { document.execCommand('copy'); } catch { /* best effort */ }
        field.remove();
    };
    document.addEventListener('click', (event) => {
        const button = event.target.closest('.copy-selector');
        if (!button) return;
        const done = button.querySelector('.copy-done');
        copyText(button.dataset.copy).then(() => {
            if (!done) return;
            done.textContent = 'Copied';
            clearTimeout(button._copyTimer);
            button._copyTimer = setTimeout(() => { done.textContent = ''; }, 1500);
        });
    });

    // Printing shows everything: all panels (via CSS) and all sections.
    addEventListener('beforeprint', () => document.querySelectorAll('details').forEach((d) => { d.dataset.wasOpen = String(d.open); d.open = true; }));
    addEventListener('afterprint', () => document.querySelectorAll('details').forEach((d) => { d.open = d.dataset.wasOpen === 'true'; }));

    // Open the device and size named in the address, e.g. #tablet/800. A broken address is ignored.
    let hash = '';
    try {
        hash = decodeURIComponent(location.hash.slice(1));
    } catch {
        hash = '';
    }
    const [device, size] = hash.split('/');
    const deviceTab = [...document.querySelectorAll('.devices > [role="tab"]')].find((t) => t.dataset.link === device);
    if (deviceTab) {
        select(deviceTab, { keep: false });
        const sizeTab = [...panelOf(deviceTab).querySelectorAll('.sizes > [role="tab"]')].find((t) => t.dataset.link === size);
        if (sizeTab) select(sizeTab, { keep: false });
    }
})();
