/* global wp, crndDocument */
/**
 * The "File" box: opens the media library to pick or upload the document's file.
 */
(function () {
    'use strict';

    const box = document.querySelector('[data-crnd-filebox]');
    if (!box || typeof wp === 'undefined' || !wp.media) {
        return;
    }

    const input = box.querySelector('[data-crnd-file-id]');
    const empty = box.querySelector('[data-crnd-empty]');
    const card = box.querySelector('[data-crnd-file]');
    const name = box.querySelector('[data-crnd-name]');
    const meta = box.querySelector('[data-crnd-meta]');
    const icon = box.querySelector('[data-crnd-icon]');
    const title = document.getElementById('title');
    let frame = null;

    const show = (hasFile) => {
        empty.hidden = hasFile;
        card.hidden = !hasFile;
    };

    // A generic icon while the real one (drawn by PHP) is shown after saving.
    const genericIcon = (ext) => {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 32 40');
        svg.setAttribute('class', 'crnd-file-icon');
        svg.innerHTML =
            '<path d="M4 0h17l11 11v25a4 4 0 0 1-4 4H4a4 4 0 0 1-4-4V4a4 4 0 0 1 4-4z" fill="#2271b1" opacity=".14"/>' +
            '<rect x="0" y="21" width="28" height="13" rx="3" fill="#2271b1"/>';
        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        text.setAttribute('x', '14');
        text.setAttribute('y', '30.5');
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('font-size', ext.length > 3 ? '7' : '8.5');
        text.setAttribute('font-weight', '700');
        text.setAttribute('fill', '#fff');
        text.textContent = ext.toUpperCase().slice(0, 4);
        svg.appendChild(text);
        return svg;
    };

    const select = () => {
        if (!frame) {
            frame = wp.media({
                title: crndDocument.i18n.frameTitle,
                button: { text: crndDocument.i18n.frameButton },
                library: { type: crndDocument.mimes },
                multiple: false,
            });
            frame.on('select', () => {
                const file = frame.state().get('selection').first().toJSON();
                if (!crndDocument.mimes.includes(file.mime)) {
                    window.alert(crndDocument.i18n.notAllowed);
                    return;
                }
                const ext = (file.filename.split('.').pop() || '').toLowerCase();
                input.value = String(file.id);
                name.textContent = file.filename;
                meta.textContent = [ext.toUpperCase(), file.filesizeHumanReadable].filter(Boolean).join(' · ');
                icon.replaceChildren(genericIcon(ext));
                show(true);

                // Prefill an empty title with the file's title.
                if (title && title.value.trim() === '' && file.title) {
                    title.value = file.title;
                    title.dispatchEvent(new Event('input'));
                    document.getElementById('title-prompt-text')?.classList.add('screen-reader-text');
                }
            });
        }
        frame.open();
    };

    box.querySelectorAll('[data-crnd-select]').forEach((button) => button.addEventListener('click', select));

    box.querySelector('[data-crnd-remove]')?.addEventListener('click', () => {
        input.value = '0';
        show(false);
    });

    box.querySelectorAll('input[name="crnd_source"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            box.querySelectorAll('[data-crnd-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.crndPanel !== radio.value;
            });
        });
    });
})();
