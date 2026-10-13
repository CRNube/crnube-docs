<?php
declare(strict_types=1);

namespace CRNube\Docs\Admin;

use CRNube\Docs\Capabilities;
use CRNube\Docs\Frontend\Assets;

/**
 * Documents > Import: brings categories, documents and pages over from FIVO Docs.
 * The page is a shell; assets/js/admin-migrate.js drives the steps through the REST API.
 */
final class MigrationPage
{
    public const SLUG = 'crnd-import';

    private string $hook = '';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 12);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            LibraryPage::SLUG,
            __('Import from FIVO Docs', 'crnube-docs'),
            __('Import', 'crnube-docs'),
            Capabilities::MANAGE_SETTINGS,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== $this->hook) {
            return;
        }
        wp_enqueue_style('crnd-admin', CRND_URL . 'assets/css/admin.css', [], Assets::version('assets/css/admin.css'));
        wp_enqueue_script('crnd-admin-migrate', CRND_URL . 'assets/js/admin-migrate.js', ['wp-api-fetch', 'wp-i18n'], Assets::version('assets/js/admin-migrate.js'), true);
        wp_set_script_translations('crnd-admin-migrate', 'crnube-docs', CRND_DIR . 'languages');
        wp_add_inline_script('crnd-admin-migrate', 'window.crndMigrate = ' . wp_json_encode([
            'libraryUrl' => LibraryPage::url(),
        ]) . ';', 'before');
    }

    public function render(): void
    {
        ?>
        <div class="wrap crnd-migrate">
            <h1><?php esc_html_e('Import from FIVO Docs', 'crnube-docs'); ?></h1>
            <p class="crnd-migrate__intro">
                <?php esc_html_e('Brings the categories and documents of FIVO Docs into CRNube Docs. The files are not copied or changed: each document uses the same file, so the current pages keep working until you replace their shortcodes in step 3. You can run it again at any time without creating duplicates.', 'crnube-docs'); ?>
            </p>
            <div id="crnd-migrate-root" class="crnd-migrate__body">
                <p><span class="spinner is-active" style="float:none"></span></p>
            </div>
            <noscript><p><?php esc_html_e('This screen needs JavaScript.', 'crnube-docs'); ?></p></noscript>
        </div>
        <?php
    }
}
