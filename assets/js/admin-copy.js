/* global crndCopy */
/**
 * Copy-to-clipboard buttons for shortcodes in the admin.
 */
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-crnd-copy]');
    if (!button) {
        return;
    }
    event.preventDefault();
    try {
        await navigator.clipboard.writeText(button.dataset.crndCopy);
    } catch {
        return;
    }
    const code = button.querySelector('code');
    const original = code.textContent;
    code.textContent = (window.crndCopy && crndCopy.copied) || '✓';
    button.classList.add('is-copied');
    setTimeout(() => {
        code.textContent = original;
        button.classList.remove('is-copied');
    }, 1400);
});
