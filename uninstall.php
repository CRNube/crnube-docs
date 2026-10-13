<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 *
 * Always removes the settings, caches, capabilities and the "Document manager" role.
 * Documents, categories and their view/download history are removed only if "Delete all documents and categories"
 * was turned on in the settings; the files themselves stay in the media library.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/src/Capabilities.php';
require_once __DIR__ . '/src/Analytics/EventsTable.php';

global $wpdb;

$crnd_settings = get_option('crnd_settings', []);

if (is_array($crnd_settings) && !empty($crnd_settings['delete_data'])) {
    $crnd_ids = get_posts([
        'post_type'      => 'crnd_document',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    foreach ($crnd_ids as $crnd_id) {
        wp_delete_post((int) $crnd_id, true);
    }

    // The taxonomies are not registered while uninstalling, so their terms are removed directly.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery
    foreach (['crnd_category', 'crnd_year'] as $crnd_taxonomy) {
        $crnd_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT term_id, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
            $crnd_taxonomy
        ));
        foreach ($crnd_rows as $crnd_row) {
            $wpdb->delete($wpdb->term_relationships, ['term_taxonomy_id' => (int) $crnd_row->term_taxonomy_id]);
            $wpdb->delete($wpdb->term_taxonomy, ['term_taxonomy_id' => (int) $crnd_row->term_taxonomy_id]);
            $wpdb->delete($wpdb->termmeta, ['term_id' => (int) $crnd_row->term_id]);
            $wpdb->delete($wpdb->terms, ['term_id' => (int) $crnd_row->term_id]);
        }
    }
    // phpcs:enable

    // The view and download history goes with the documents.
    \CRNube\Docs\Analytics\EventsTable::uninstall();
}

\CRNube\Docs\Capabilities::uninstall();
wp_clear_scheduled_hook('crnd_purge_events');

delete_option('crnd_settings');
delete_option('crnd_version');
delete_option('crnd_cache_version');

$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_crnd\\_%' OR option_name LIKE '\\_transient\\_timeout\\_crnd\\_%'"
);
