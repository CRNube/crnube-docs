<?php
declare(strict_types=1);

namespace CRNube\Docs\Rest;

use CRNube\Docs\Capabilities;
use CRNube\Docs\Migration\FivoImporter;
use CRNube\Docs\Migration\FivoShortcodes;

/**
 * crnd/v1/migrate/fivo: the steps of the FIVO Docs migration screen. Administrators only.
 */
final class MigrationController
{
    private const BATCH = 50;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        $ns  = DocumentsController::NAMESPACE;
        $can = static fn (): bool => current_user_can(Capabilities::MANAGE_SETTINGS);
        $tax = ['type' => 'string', 'required' => true, 'validate_callback' => static fn ($value): bool => is_string($value) && taxonomy_exists($value) && is_object_in_taxonomy('attachment', $value)];

        register_rest_route($ns, '/migrate/fivo', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'status'],
            'permission_callback' => $can,
            'args'                => ['taxonomy' => ['type' => 'string', 'default' => ''], 'skip_generic' => ['type' => 'boolean', 'default' => true]],
        ]);
        register_rest_route($ns, '/migrate/fivo/categories', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => fn (\WP_REST_Request $r): array => ['map' => (new FivoImporter())->importCategories((string) $r['taxonomy'], (bool) $r['skip_generic'])],
            'permission_callback' => $can,
            'args'                => ['taxonomy' => $tax, 'skip_generic' => ['type' => 'boolean', 'default' => true]],
        ]);
        register_rest_route($ns, '/migrate/fivo/documents', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => fn (\WP_REST_Request $r): array => (new FivoImporter())->importDocuments((string) $r['taxonomy'], (int) $r['offset'], self::BATCH, (bool) $r['clean_titles']),
            'permission_callback' => $can,
            'args'                => ['taxonomy' => $tax, 'offset' => ['type' => 'integer', 'default' => 0, 'minimum' => 0], 'clean_titles' => ['type' => 'boolean', 'default' => true]],
        ]);
        register_rest_route($ns, '/migrate/fivo/replace', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'replace'],
            'permission_callback' => $can,
            'args'                => ['taxonomy' => $tax, 'post_id' => ['type' => 'integer', 'required' => true, 'minimum' => 1]],
        ]);
        register_rest_route($ns, '/migrate/fivo/undo', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'undo'],
            'permission_callback' => $can,
            'args'                => ['taxonomy' => ['type' => 'string', 'default' => ''], 'post_id' => ['type' => 'integer', 'required' => true, 'minimum' => 1]],
        ]);
    }

    /** @return array<string, mixed> */
    public function status(\WP_REST_Request $request): array
    {
        $sources  = FivoImporter::sources();
        $taxonomy = (string) $request['taxonomy'];
        if ($taxonomy === '' || !in_array($taxonomy, array_column($sources, 'name'), true)) {
            $taxonomy = $sources[0]['name'] ?? '';
        }
        return [
            'sources'  => $sources,
            'taxonomy' => $taxonomy,
            'preview'  => $taxonomy !== '' ? FivoImporter::preview($taxonomy, (bool) $request['skip_generic']) : [],
            'pages'    => (new FivoShortcodes())->scan($taxonomy),
            'fivoActive' => self::pluginActive('fivo-docs/fivo-docs.php'),
        ];
    }

    public function replace(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!current_user_can('edit_post', (int) $request['post_id'])) {
            return new \WP_Error('crnd_forbidden', __('You cannot edit this page.', 'crnube-docs'), ['status' => 403]);
        }
        $shortcodes = new FivoShortcodes();
        $shortcodes->replace((int) $request['post_id'], (string) $request['taxonomy']);
        return new \WP_REST_Response(['pages' => $shortcodes->scan((string) $request['taxonomy'])]);
    }

    public function undo(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (!current_user_can('edit_post', (int) $request['post_id'])) {
            return new \WP_Error('crnd_forbidden', __('You cannot edit this page.', 'crnube-docs'), ['status' => 403]);
        }
        $shortcodes = new FivoShortcodes();
        $shortcodes->undo((int) $request['post_id']);
        return new \WP_REST_Response(['pages' => $shortcodes->scan((string) $request['taxonomy'])]);
    }

    /** is_plugin_active() lives in an admin-only file that REST requests do not load. */
    private static function pluginActive(string $plugin): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($plugin);
    }
}
