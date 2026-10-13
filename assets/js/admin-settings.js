/* global crndSettings */
/**
 * Settings page: live preview of the design, layout switcher, brand presets and the
 * export / import of a design as a JSON file.
 *
 * Presets and imports only fill the form; nothing is stored until the form is saved,
 * and the server validates every value again (Settings::sanitize()).
 */
(function () {
    'use strict';

    const form = document.querySelector('.crnd-settings__grid');
    const previewBox = document.querySelector('[data-crnd-preview]');
    if (!form || !previewBox) {
        return;
    }
    const config = window.crndSettings || { option: 'crnd_settings', keys: [], presets: {}, i18n: {} };
    const libraries = () => previewBox.querySelectorAll('.crnd');
    const fieldName = (key) => `${config.option}[${key}]`;
    const fields = (key) => form.querySelectorAll(`[name="${fieldName(key)}"]`);

    /* ---------- Live preview ---------- */

    const applyVar = (input) => {
        const unit = input.dataset.crndUnit || '';
        const value = input.value.trim();
        libraries().forEach((lib) => {
            if (value === '') {
                lib.style.removeProperty(input.dataset.crndVar);
            } else {
                lib.style.setProperty(input.dataset.crndVar, value + unit);
            }
        });
        const hex = input.closest('.crnd-color')?.querySelector('[data-crnd-hex]');
        if (hex) {
            hex.textContent = value;
        }
        const output = input.closest('.crnd-field')?.querySelector('[data-crnd-output]');
        if (output) {
            output.textContent = value + unit;
        }
    };

    form.querySelectorAll('[data-crnd-var]').forEach((input) => {
        input.addEventListener('input', () => applyVar(input));
        input.addEventListener('change', () => applyVar(input));
    });

    // Segmented choices that map to a class on the library (header style, density).
    form.querySelectorAll('[data-crnd-class-group]').forEach((group) => {
        const prefix = group.dataset.crndClassGroup;
        const radios = [...group.querySelectorAll('input[type="radio"]')];
        radios.forEach((radio) => {
            radio.addEventListener('change', () => {
                libraries().forEach((lib) => {
                    radios.forEach((r) => lib.classList.remove(prefix + r.value));
                    lib.classList.add(prefix + radio.value);
                });
            });
        });
    });

    // Layout: show the preview of the chosen one; the column count only applies to Grid.
    const columnsField = form.querySelector('[data-crnd-columns-field]');
    form.querySelectorAll('[data-crnd-layout]').forEach((radio) => {
        radio.addEventListener('change', () => {
            previewBox.querySelectorAll('[data-crnd-preview-layout]').forEach((box) => {
                box.hidden = box.dataset.crndPreviewLayout !== radio.value;
            });
            if (columnsField) {
                columnsField.hidden = radio.value !== 'grid';
            }
        });
    });

    // The sample documents link nowhere.
    previewBox.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            event.preventDefault();
        }
    });

    /* ---------- Presets, export and import ---------- */

    const status = form.querySelector('[data-crnd-preset-status]');
    const say = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const getValue = (key) => {
        const list = [...fields(key)];
        const radio = list.find((el) => el.type === 'radio');
        if (radio) {
            return list.find((el) => el.checked)?.value ?? '';
        }
        return list[0]?.value ?? '';
    };

    const setValue = (key, value) => {
        fields(key).forEach((el) => {
            if (el.type === 'radio') {
                if (el.value === String(value)) {
                    el.checked = true;
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return;
            }
            el.value = String(value);
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };

    const ALLOWED = {
        density: ['comfortable', 'compact'],
        header_style: ['soft', 'solid', 'minimal'],
        layout: ['tree', 'sections', 'grid', 'list'],
    };

    /** Keeps only known keys with valid values; returns null when nothing is usable. */
    const validate = (design) => {
        if (!design || typeof design !== 'object') {
            return null;
        }
        const clean = {};
        for (const key of config.keys) {
            if (!(key in design)) {
                continue;
            }
            const value = design[key];
            if (['primary', 'accent', 'text', 'muted', 'surface', 'surface_alt', 'border'].includes(key)) {
                if (/^#[0-9a-f]{6}$/i.test(String(value))) {
                    clean[key] = String(value).toLowerCase();
                }
            } else if (key === 'radius') {
                const n = parseInt(value, 10);
                if (n >= 0 && n <= 32) {
                    clean[key] = n;
                }
            } else if (key === 'columns') {
                const n = parseInt(value, 10);
                if (n >= 1 && n <= 4) {
                    clean[key] = n;
                }
            } else if (key === 'font') {
                clean[key] = String(value).replace(/[^\p{L}\p{N} ,'"-]/gu, '').slice(0, 120);
            } else if (ALLOWED[key]?.includes(value)) {
                clean[key] = value;
            }
        }
        return Object.keys(clean).length ? clean : null;
    };

    const apply = (design) => Object.entries(design).forEach(([key, value]) => setValue(key, value));

    form.querySelectorAll('[data-crnd-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const design = validate(config.presets[button.dataset.crndPreset]);
            if (design) {
                apply(design);
                form.querySelectorAll('[data-crnd-preset]').forEach((b) => b.classList.toggle('is-active', b === button));
                say(config.i18n.applied);
            }
        });
    });

    form.querySelector('[data-crnd-export]')?.addEventListener('click', () => {
        const design = {};
        config.keys.forEach((key) => {
            const value = getValue(key);
            design[key] = ['radius', 'columns'].includes(key) ? parseInt(value, 10) : value;
        });
        const file = new Blob(
            [JSON.stringify({ plugin: 'crnube-docs', version: 1, exported: new Date().toISOString(), design }, null, 2)],
            { type: 'application/json' }
        );
        const link = document.createElement('a');
        link.href = URL.createObjectURL(file);
        link.download = `crnube-docs-diseno-${config.site}.json`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    });

    form.querySelector('[data-crnd-import]')?.addEventListener('change', async (event) => {
        const file = event.target.files[0];
        event.target.value = '';
        if (!file || file.size > 50000) {
            say(config.i18n.invalid);
            return;
        }
        try {
            const data = JSON.parse(await file.text());
            const design = validate(data?.design ?? data);
            if (!design) {
                throw new Error('empty');
            }
            apply(design);
            form.querySelectorAll('[data-crnd-preset]').forEach((b) => b.classList.remove('is-active'));
            say(config.i18n.imported);
        } catch {
            say(config.i18n.invalid);
        }
    });
})();
