<?php
declare(strict_types=1);

namespace CRNube\Docs\Admin;

use CRNube\Docs\Access\DocumentAccess;

/**
 * When some documents or categories are marked as private but no add-on manages access
 * (CRNube Docs Pro was deactivated, for example), only staff can open them. This says
 * so on the plugin's screens, so nobody wonders why members lost access.
 */
final class RestrictedNotice
{
    private const CACHE = 'crnd_has_restricted';

    public function register(): void
    {
        add_action('admin_notices', [$this, 'render']);
        add_action('crnd_access_changed', static function (): void {
            delete_transient(self::CACHE);
        });
    }

    public function render(): void
    {
        // The access policy of an add-on is in place: nothing to say.
        if (has_filter('crnd_access_policy') || !current_user_can('edit_crnd_documents')) {
            return;
        }
        $screen = get_current_screen();
        if ($screen === null || (!str_contains($screen->id, LibraryPage::SLUG) && !str_contains($screen->id, 'crnd') && $screen->post_type !== 'crnd_document')) {
            return;
        }
        if (!self::hasRestricted()) {
            return;
        }
        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html__('Some documents are marked as private, but the protected area (CRNube Docs Pro) is not active. Until it is, only the staff who manage documents can open them.', 'crnube-docs')
        );
    }

    private static function hasRestricted(): bool
    {
        $cached = get_transient(self::CACHE);
        if ($cached !== false) {
            return $cached === 'yes';
        }
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery -- one cached existence check
        $found = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = 'private' LIMIT 1",
            DocumentAccess::META_ACCESS
        )) || (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value = 'private' LIMIT 1",
            DocumentAccess::TERM_ACCESS
        ));
        // phpcs:enable
        set_transient(self::CACHE, $found ? 'yes' : 'no', HOUR_IN_SECONDS);
        return $found;
    }
}
