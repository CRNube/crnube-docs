<?php
declare(strict_types=1);

namespace CRNube\Docs;

use CRNube\Docs\Access\AccessPolicy;
use CRNube\Docs\Rest\MigrationController;
use CRNube\Docs\Integrations\Elementor\Integration as ElementorIntegration;
use CRNube\Docs\Frontend\Block;
use CRNube\Docs\Admin\MigrationPage;
use CRNube\Docs\Admin\RestrictedNotice;
use CRNube\Docs\Access\StaffOnlyPolicy;
use CRNube\Docs\Analytics\Counter;
use CRNube\Docs\Admin\CategoryFields;
use CRNube\Docs\Admin\DocumentListTable;
use CRNube\Docs\Admin\DocumentMetaBox;
use CRNube\Docs\Admin\LibraryPage;
use CRNube\Docs\Admin\SettingsPage;
use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Domain\DocumentRepository;
use CRNube\Docs\Domain\YearTaxonomy;
use CRNube\Docs\Download\DownloadController;
use CRNube\Docs\Frontend\Assets;
use CRNube\Docs\Frontend\Renderer;
use CRNube\Docs\Frontend\Shortcode;
use CRNube\Docs\Rest\CategoriesController;
use CRNube\Docs\Rest\DocumentsController;
use CRNube\Docs\Settings\Settings;
use CRNube\Docs\Support\Cache;

/**
 * Wires the services together. There is no service container on purpose: the object
 * graph is small, and building it here keeps every dependency visible in one place.
 */
final class Plugin
{
    private static ?self $instance = null;

    public readonly Settings $settings;
    public readonly DocumentRepository $documents;
    public readonly AccessPolicy $access;
    public readonly Renderer $renderer;

    private function __construct()
    {
        $this->settings  = new Settings();
        $this->documents = new DocumentRepository();
        /**
         * Lets an add-on replace who may open which document: CRNube Docs Pro plugs in
         * its access groups here, a cooperative could check its own system instead.
         *
         * @param AccessPolicy $policy
         */
        $this->access   = apply_filters('crnd_access_policy', new StaffOnlyPolicy());
        $this->renderer = new Renderer($this->settings, $this->documents, $this->access);
    }

    public static function boot(): void
    {
        if (self::$instance !== null) {
            return;
        }
        self::$instance = new self();
        self::$instance->register();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::boot();
        }
        return self::$instance;
    }

    private function register(): void
    {
        // Translations: WordPress loads languages/*.l10n.php on demand for this text domain.
        load_plugin_textdomain('crnube-docs', false, dirname(plugin_basename(CRND_FILE)) . '/languages');

        // On init, not before: the upgrade names roles with translated strings.
        add_action('init', [Installer::class, 'maybeUpgrade'], 1);

        (new DocumentPostType())->register();
        (new CategoryTaxonomy())->register();
        (new YearTaxonomy())->register();
        (new Cache())->register();
        (new DownloadController($this->access, new Counter($this->settings)))->register();
        (new Assets($this->settings))->register();
        (new Shortcode($this->renderer))->register();
        (new DocumentsController())->register();
        (new CategoriesController())->register();
        (new MigrationController())->register();

        // Elementor loads after this plugin; its widgets hook in once it is ready.
        if (did_action('elementor/loaded')) {
            (new ElementorIntegration())->register();
        } else {
            add_action('elementor/loaded', static fn () => (new ElementorIntegration())->register());
        }
        (new Block($this->renderer))->register();

        if (is_admin()) {
            (new LibraryPage())->register();
            (new MigrationPage())->register();
            (new RestrictedNotice())->register();
            (new DocumentMetaBox())->register();
            (new DocumentListTable())->register();
            (new CategoryFields())->register();
            (new SettingsPage($this->settings, $this->renderer))->register();
        }

        do_action('crnd_loaded', $this);
    }
}
