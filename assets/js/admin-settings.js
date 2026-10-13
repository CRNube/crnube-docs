/**
 * Settings page: applies design changes to the live preview as they are made.
 */
(function () {
    'use strict';

    const form = document.querySelector('.crnd-settings__grid');
    const preview = document.querySelector('[data-crnd-preview] .crnd');
    if (!form || !preview) {
        return;
    }

    // CSS variables: colors, radius, font.
    form.querySelectorAll('[data-crnd-var]').forEach((input) => {
        const update = () => {
            const unit = input.dataset.crndUnit || '';
            const value = input.value.trim();
            if (value === '') {
                preview.style.removeProperty(input.dataset.crndVar);
            } else {
                preview.style.setProperty(input.dataset.crndVar, value + unit);
            }
            const hex = input.closest('.crnd-color')?.querySelector('[data-crnd-hex]');
            if (hex) {
                hex.textContent = value;
            }
            const output = input.closest('.crnd-field')?.querySelector('[data-crnd-output]');
            if (output) {
                output.textContent = value + unit;
            }
        };
        input.addEventListener('input', update);
    });

    // Segmented choices that map to a class on the library (header style, density).
    form.querySelectorAll('[data-crnd-class-group]').forEach((group) => {
        const prefix = group.dataset.crndClassGroup;
        const radios = [...group.querySelectorAll('input[type="radio"]')];
        radios.forEach((radio) => {
            radio.addEventListener('change', () => {
                radios.forEach((r) => preview.classList.remove(prefix + r.value));
                preview.classList.add(prefix + radio.value);
            });
        });
    });

    // The sample documents link nowhere.
    preview.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            event.preventDefault();
        }
    });
})();
