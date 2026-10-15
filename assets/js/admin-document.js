/* global wp, crndDocument */
/**
 * The "File" box: pick the document's file from the media library, or drop it on the
 * box to upload it. Dropping several files explains that the Library takes them all.
 * The server checks the file's real content when the document is saved.
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
    const status = box.querySelector('[data-crnd-status]');
    const title = document.getElementById('title');
    const t = crndDocument.i18n;
    let frame = null;

    const show = (hasFile) => {
        empty.hidden = hasFile;
        card.hidden = !hasFile;
    };

    const say = (text, isError) => {
        if (!status) {
            return;
        }
        status.textContent = text;
        status.hidden = text === '';
        status.classList.toggle('is-error', Boolean(isError));
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

    /** Shows the chosen file and fills an empty title with the file's title. */
    const useFile = (file) => {
        const ext = (file.filename.split('.').pop() || '').toLowerCase();
        input.value = String(file.id);
        name.textContent = file.filename;
        meta.textContent = [ext.toUpperCase(), file.size].filter(Boolean).join(' · ');
        icon.replaceChildren(genericIcon(ext));
        show(true);
        say('', false);

        // The year in the file name ("Estados-Financieros-2024.pdf"), like the Library does.
        const yearField = document.getElementById('crnd-year');
        const year = file.filename.match(/(?:^|\D)((?:19|20)\d{2})(?!\d)/);
        if (yearField && yearField.value === '' && year) {
            yearField.value = year[1];
        }

        if (title && title.value.trim() === '' && file.title) {
            title.value = file.title;
            title.dispatchEvent(new Event('input'));
            document.getElementById('title-prompt-text')?.classList.add('screen-reader-text');
        }
    };

    const select = () => {
        if (!frame) {
            frame = wp.media({
                title: t.frameTitle,
                button: { text: t.frameButton },
                library: { type: crndDocument.mimes },
                multiple: false,
            });
            frame.on('select', () => {
                const file = frame.state().get('selection').first().toJSON();
                if (!crndDocument.mimes.includes(file.mime)) {
                    window.alert(t.notAllowed);
                    return;
                }
                useFile({ id: file.id, filename: file.filename, size: file.filesizeHumanReadable, title: file.title });
            });
        }
        frame.open();
    };

    const humanSize = (bytes) => {
        if (!bytes) {
            return '';
        }
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        let n = bytes;
        while (n >= 1024 && i < units.length - 1) {
            n /= 1024;
            i++;
        }
        return (i === 0 ? n : n.toFixed(1)) + ' ' + units[i];
    };

    /** "Informe-Anual_2024.pdf" -> "Informe Anual 2024", like the Library does. */
    const titleFrom = (filename) =>
        filename.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim();

    const upload = async (file) => {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (!crndDocument.extensions.includes(ext)) {
            say(t.notAllowed, true);
            return;
        }
        if (file.size > crndDocument.maxSize) {
            say(t.tooBig, true);
            return;
        }
        say(t.uploading.replace('%s', file.name), false);
        box.classList.add('is-busy');
        try {
            const body = new FormData();
            body.append('file', file, file.name);
            body.append('title', titleFrom(file.name));
            const response = await fetch(crndDocument.mediaUrl, {
                method: 'POST',
                headers: { 'X-WP-Nonce': crndDocument.nonce },
                credentials: 'same-origin',
                body,
            });
            const data = await response.json();
            if (!response.ok || !data.id) {
                throw new Error(data && data.message ? data.message : t.uploadFailed);
            }
            useFile({ id: data.id, filename: file.name, size: humanSize(file.size), title: titleFrom(file.name) });
        } catch (error) {
            say(error.message || t.uploadFailed, true);
        } finally {
            box.classList.remove('is-busy');
        }
    };

    // Dropping a file anywhere on this screen must never open it in the browser.
    const hasFiles = (event) => Array.from(event.dataTransfer?.types || []).includes('Files');
    ['dragover', 'drop'].forEach((type) =>
        window.addEventListener(type, (event) => {
            if (hasFiles(event)) {
                event.preventDefault();
            }
        })
    );

    let depth = 0;
    box.addEventListener('dragenter', (event) => {
        if (hasFiles(event)) {
            depth++;
            box.classList.add('is-dragover');
        }
    });
    box.addEventListener('dragleave', () => {
        depth = Math.max(0, depth - 1);
        if (depth === 0) {
            box.classList.remove('is-dragover');
        }
    });
    box.addEventListener('drop', (event) => {
        depth = 0;
        box.classList.remove('is-dragover');
        const files = Array.from(event.dataTransfer?.files || []);
        if (files.length === 0) {
            return;
        }
        if (files.length > 1) {
            say(t.several, true);
            return;
        }
        if (box.querySelector('input[name="crnd_source"]:checked')?.value === 'link') {
            box.querySelector('input[name="crnd_source"][value="file"]')?.click();
        }
        upload(files[0]);
    });

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
