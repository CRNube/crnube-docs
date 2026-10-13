<?php
declare(strict_types=1);

namespace CRNube\Docs;

use CRNube\Docs\Access\AccessPolicy;
use CRNube\Docs\Access\GroupAccessPolicy;
use CRNube\Docs\Access\Otp;
use CRNube\Docs\Access\Portal;
use CRNube\Docs\Access\PrivateStorage;
use CRNube\Docs\Admin\AccessPage;
use CRNube\Docs\Rest\AccessController;
use CRNube\Docs\Analytics\Retention;
use CRNube\Docs\Analytics\Tracker;
use CRNube\Docs\Admin\CategoryFields;
use CRNube\Docs\Admin\DocumentListTable;
use CRNube\Docs\Admin\DocumentMetaBox;
use CRNube\Docs\Admin\LibraryPage;
use CRNube\Docs\Admin\ReportsExport;
use CRNube\Docs\Admin\ReportsPage;
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
use CRNube\Docs\Rest\StatsController;
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
    public readonly Portal $portal;

    private function __construct()
    {
        $this->settings  = new Settings();
        $this->documents = new DocumentRepository();
        $this->portal    = new Portal($this->settings, new Otp());
        /**
         * Lets an add-on replace who may open which document (for example to check a
         * cooperative's own system instead of the access groups).
         *
         * @param AccessPolicy $policy
         */
        $this->access   = apply_filters('crnd_access_policy', new GroupAccessPolicy($this->portal));
        $this->renderer = new Renderer($this->settings, $this->documents, $this->access, $this->portal);
        $this->portal->setRenderer($this->renderer);
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
        (new DownloadController($this->access, new Tracker($this->settings)))->register();
        (new Retention($this->settings))->register();
        (new Assets($this->settings))->register();
        (new Shortcode($this->renderer))->register();
        (new DocumentsController())->register();
        (new CategoriesController())->register();
        (new StatsController())->register();
        (new AccessController())->register();
        (new PrivateStorage())->register();
        $this->portal->register();

        if (is_admin()) {
            (new LibraryPage())->register();
            (new AccessPage())->register();
            (new ReportsPage())->register();
            (new ReportsExport())->register();
            (new DocumentMetaBox())->register();
            (new DocumentListTable())->register();
            (new CategoryFields())->register();
            (new SettingsPage($this->settings, $this->renderer))->register();
        }

        do_action('crnd_loaded', $this);
    }
}
