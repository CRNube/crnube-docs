<?php
declare(strict_types=1);

namespace CRNube\Docs;

use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Domain\YearTaxonomy;
use CRNube\Docs\Download\DownloadController;

final class Installer
{
    private const VERSION_OPTION = 'crnd_version';

    public static function activate(): void
    {
        Capabilities::install();
        update_option(self::VERSION_OPTION, CRND_VERSION, false);

        // The rewrite rules need the post type, taxonomies and endpoint registered first.
        (new DocumentPostType())->registerPostType();
        (new CategoryTaxonomy())->registerTaxonomy();
        (new YearTaxonomy())->registerTaxonomy();
        DownloadController::addRewriteRule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    /**
     * The activation hook does not run when the plugin is updated in place, so the
     * capabilities and rewrite rules are refreshed whenever the stored version changes.
     */
    public static function maybeUpgrade(): void
    {
        if (get_option(self::VERSION_OPTION) === CRND_VERSION) {
            return;
        }
        Capabilities::install();
        update_option(self::VERSION_OPTION, CRND_VERSION, false);
        add_action('init', static fn () => flush_rewrite_rules(), 99);
    }
}
