<?php
declare(strict_types=1);

namespace CRNube\Docs\Admin;

use CRNube\Docs\Capabilities;
use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Domain\FileTypes;
use CRNube\Docs\Frontend\Shortcode;

/**
 * The "Documents" admin menu and its first screen, the Library: a React app (built from
 * assets/src/library into build/) to browse, upload and organize documents.
 */
final class LibraryPage
{
    public const SLUG = 'crnd-library';

    private string $hook = '';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 9);
        add_action('admin_menu', [$this, 'sortSubmenu'], 999);
        add_filter('parent_file', [$this, 'parentFile']);
        add_filter('submenu_file', [$this, 'submenuFile']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public static function url(): string
    {
        return admin_url('admin.php?page=' . self::SLUG);
    }

    public function menu(): void
    {
        $this->hook = (string) add_menu_page(
            __('Document library', 'crnube-docs'),
            __('Documents', 'crnube-docs'),
            'edit_crnd_documents',
            self::SLUG,
            [$this, 'render'],
            'dashicons-media-document',
            25
        );
        add_submenu_page(self::SLUG, __('Document library', 'crnube-docs'), __('Library', 'crnube-docs'), 'edit_crnd_documents', self::SLUG, [$this, 'render']);
        add_submenu_page(self::SLUG, __('Add new document', 'crnube-docs'), __('Add document', 'crnube-docs'), 'edit_crnd_documents', 'post-new.php?post_type=' . DocumentPostType::NAME);
        add_submenu_page(self::SLUG, __('Document categories', 'crnube-docs'), __('Categories', 'crnube-docs'), Capabilities::MANAGE_CATEGORIES, $this->categoriesSlug());
    }

    /** Library first, then the list, add, categories and settings. */
    public function sortSubmenu(): void
    {
        global $submenu;
        if (empty($submenu[self::SLUG])) {
            return;
        }
        /**
         * Position of each screen in the "Documents" menu; add-on screens without one go
         * in the middle (5), before Import and Settings.
         *
         * @param array<string, int> $weight menu slug => position
         */
        $weight = (array) apply_filters('crnd_submenu_order', [
            self::SLUG                                      => 0,
            'edit.php?post_type=' . DocumentPostType::NAME  => 1,
            'post-new.php?post_type=' . DocumentPostType::NAME => 2,
            $this->categoriesSlug()                         => 3,
            MigrationPage::SLUG                             => 8,
            SettingsPage::SLUG                              => 9,
        ]);
        usort($submenu[self::SLUG], static fn (array $a, array $b): int => ($weight[$a[2]] ?? 5) <=> ($weight[$b[2]] ?? 5));
    }

    /** Keeps the "Documents" menu open on the post type and category screens. */
    public function parentFile(string $parentFile): string
    {
        $screen = get_current_screen();
        if ($screen !== null && ($screen->post_type === DocumentPostType::NAME || $screen->taxonomy === CategoryTaxonomy::NAME)) {
            return self::SLUG;
        }
        return $parentFile;
    }

    public function submenuFile(?string $submenuFile): ?string
    {
        $screen = get_current_screen();
        if ($screen === null) {
            return $submenuFile;
        }
        if ($screen->taxonomy === CategoryTaxonomy::NAME) {
            return $this->categoriesSlug();
        }
        if ($screen->post_type === DocumentPostType::NAME && $screen->base === 'post' && $screen->action === 'add') {
            return 'post-new.php?post_type=' . DocumentPostType::NAME;
        }
        return $submenuFile;
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== $this->hook) {
            return;
        }

        $asset = CRND_DIR . 'build/library.asset.php';
        if (!is_readable($asset)) {
            return;
        }
        /** @var array{dependencies: list<string>, version: string} $meta */
        $meta = require $asset;

        wp_enqueue_script('crnd-library', CRND_URL . 'build/library.js', $meta['dependencies'], $meta['version'], true);
        wp_enqueue_style('crnd-library', CRND_URL . 'build/library.css', ['wp-components'], $meta['version']);
        wp_style_add_data('crnd-library', 'rtl', 'replace');
        wp_set_script_translations('crnd-library', 'crnube-docs', CRND_DIR . 'languages');

        $extensions = array_keys(FileTypes::allowedMimes());

        /**
         * Settings handed to the library app as window.crndLibrary. Add-ons add their own
         * keys here, and their scripts extend the app through the `crnd.library.*` hooks
         * of @wordpress/hooks (see assets/src/library/extensions.js).
         *
         * @param array<string, mixed> $config
         */
        $config = (array) apply_filters('crnd_library_config', [
            'restUrl'          => esc_url_raw(rest_url('crnd/v1/')),
            'nonce'            => wp_create_nonce('wp_rest'),
            'maxUploadSize'    => wp_max_upload_size(),
            'extensions'       => $extensions,
            'accept'           => '.' . implode(',.', $extensions),
            'canManageCategories' => current_user_can(Capabilities::MANAGE_CATEGORIES),
            'canPublish'       => current_user_can('publish_crnd_documents'),
            'canUpload'        => current_user_can('upload_files'),
            'newDocumentUrl'   => admin_url('post-new.php?post_type=' . DocumentPostType::NAME),
            'shortcodeTag'     => Shortcode::TAG,
            'currentYear'      => (int) wp_date('Y'),
            'reportsUrl'       => '',
        ]);
        wp_add_inline_script('crnd-library', 'window.crndLibrary = ' . wp_json_encode($config) . ';', 'before');

        /** Add-on scripts for the library: enqueue them here, before the app starts. */
        do_action('crnd_library_enqueue');
    }

    public function render(): void
    {
        if (!is_readable(CRND_DIR . 'build/library.js')) {
            echo '<div class="wrap"><div class="notice notice-error"><p>'
                . esc_html__('The library app is not built. Run "npm install && npm run build" in the plugin folder.', 'crnube-docs')
                . '</p></div></div>';
            return;
        }
        echo '<div class="wrap crnd-library-wrap"><div id="crnd-library-root"></div></div>';
    }

    private function categoriesSlug(): string
    {
        return 'edit-tags.php?taxonomy=' . CategoryTaxonomy::NAME . '&post_type=' . DocumentPostType::NAME;
    }
}
