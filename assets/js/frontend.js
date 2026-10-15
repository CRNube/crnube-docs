/**
 * CRNube Docs: search, year filter and expand/collapse for the document library.
 *
 * Progressive enhancement: without this script the groups still open and close (they
 * are <details> elements) and every link works; only the toolbar stays hidden.
 */

const normalize = (value) =>
    String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

const debounce = (fn, wait) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
};

function initLibrary(root) {
    if (root.dataset.crndReady) {
        return;
    }
    root.dataset.crndReady = '1';

    const toolbar = root.querySelector('[data-crnd-toolbar]');
    if (!toolbar) {
        return;
    }
    toolbar.hidden = false;

    const search = root.querySelector('[data-crnd-search]');
    const year = root.querySelector('[data-crnd-year]');
    const category = root.querySelector('[data-crnd-category]');
    const status = root.querySelector('[data-crnd-status]');
    const noResults = root.querySelector('[data-crnd-no-results]');
    const groups = [...root.querySelectorAll('[data-crnd-group]')];
    // Only <details> groups open and close; section headings (Sections, Grid) do not.
    const foldable = groups.filter((g) => g.tagName === 'DETAILS');
    const ownPart = (group, selector) =>
        group.querySelector(`:scope > summary ${selector}, :scope > header ${selector}`);
    const docs = [...root.querySelectorAll('[data-crnd-doc]')].map((el) => ({
        el,
        id: el.dataset.id,
        text: normalize(el.dataset.search),
        year: el.dataset.year,
        cats: (el.dataset.cats || '').split(' ').filter(Boolean),
        // The group titles above the document, so searching "riesgos" finds every
        // document inside "Informes de riesgos".
        path: (() => {
            const titles = [];
            let group = el.closest('[data-crnd-group]');
            while (group) {
                titles.push(normalize(ownPart(group, '[data-crnd-group-title]')?.textContent));
                group = group.parentElement?.closest('[data-crnd-group]');
            }
            return titles.join(' ');
        })(),
    }));
    const total = new Set(docs.map((d) => d.id)).size;

    // Which groups were open before a search, to restore them when it is cleared.
    let openBeforeFilter = null;

    const updateStatus = (shown) => {
        if (status) {
            status.textContent = (status.dataset.template || '%1$s / %2$s')
                .replace('%1$s', String(shown))
                .replace('%2$s', String(total));
        }
    };

    const apply = () => {
        const words = normalize(search?.value).split(/\s+/).filter(Boolean);
        const selectedYear = year?.value || '';
        const selectedCategory = category?.value || '';
        const filtering = words.length > 0 || selectedYear !== '' || selectedCategory !== '';
        const shownIds = new Set();

        for (const doc of docs) {
            const haystack = doc.text + ' ' + doc.path;
            const matches =
                words.every((word) => haystack.includes(word)) &&
                (selectedYear === '' || doc.year === selectedYear) &&
                (selectedCategory === '' || doc.cats.includes(selectedCategory));
            doc.el.hidden = !matches;
            if (matches) {
                shownIds.add(doc.id);
            }
        }

        // Children come after their parents in the DOM, so walk backwards: each group
        // then sees its subgroups already resolved.
        for (const group of [...groups].reverse()) {
            const visible = new Set(
                [...group.querySelectorAll('[data-crnd-doc]:not([hidden])')].map((el) => el.dataset.id)
            );
            group.hidden = visible.size === 0;
            const badge = ownPart(group, '[data-crnd-count]');
            if (badge) {
                const count = filtering ? visible.size : Number(badge.dataset.total);
                const template = count === 1 ? badge.dataset.templateOne : badge.dataset.template;
                badge.textContent = template ? template.replace('%s', String(count)) : String(count);
            }
        }

        if (filtering) {
            openBeforeFilter ??= new Map(foldable.map((g) => [g, g.open]));
            foldable.forEach((g) => {
                if (!g.hidden) {
                    g.open = true;
                }
            });
        } else if (openBeforeFilter) {
            foldable.forEach((g) => {
                g.open = openBeforeFilter.get(g) ?? false;
            });
            openBeforeFilter = null;
        }

        if (noResults) {
            noResults.hidden = !(filtering && shownIds.size === 0);
        }
        updateStatus(shownIds.size);
    };

    search?.addEventListener('input', debounce(apply, 120));
    search?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && search.value !== '') {
            search.value = '';
            apply();
        }
    });
    year?.addEventListener('change', apply);
    category?.addEventListener('change', apply);

    root.querySelectorAll('[data-crnd-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const open = button.dataset.crndToggle === 'open';
            foldable.forEach((g) => {
                if (!g.hidden) {
                    g.open = open;
                }
            });
        });
    });

    updateStatus(total);
}

const initAll = (scope = document) => scope.querySelectorAll('[data-crnd-library]').forEach(initLibrary);

initAll();

// The Elementor editor and other page builders insert the library after the page loads.
if (document.body.classList.contains('elementor-editor-active') || window.self !== window.top) {
    new MutationObserver(() => initAll()).observe(document.body, { childList: true, subtree: true });
}
