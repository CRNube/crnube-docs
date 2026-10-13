<?php
declare(strict_types=1);

namespace CRNube\Docs\Analytics;

use CRNube\Docs\Domain\Document;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Settings\Settings;

/**
 * Counts views and downloads of each document (the numbers in the library).
 *
 * Privacy by design (Costa Rica's Ley 8968): nothing about the visitor is stored here.
 * Bots and link prefetches are not counted, and repeated hits of the same visitor on the
 * same document within 30 seconds count once. For that, each visit gets a visitor key:
 * an HMAC of IP + browser with a salt that changes every day, kept only for those 30
 * seconds. Add-ons that keep a history (CRNube Docs Pro's reports) receive every hit
 * that counted through the `crnd_hit` action.
 */
final class Counter
{
    /** Repeated hits of the same visitor on the same document within this window count once. */
    private const DEDUPE_SECONDS = 30;

    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * @param 'view'|'download' $mode
     */
    public function record(Document $document, string $mode, ?\WP_User $user): void
    {
        if (!$this->settings->get('track_enabled') || $this->isPrefetch()) {
            return;
        }

        $agent = UserAgent::fromRequest();
        if ($agent->isBot()) {
            return;
        }

        $visitor = $this->visitorKey();
        if ($this->isRepeat($document->id, $mode, $visitor)) {
            return;
        }

        $this->increment($document->id, $mode === 'view' ? DocumentPostType::META_VIEWS : DocumentPostType::META_DOWNLOADS);

        /**
         * A view or download that counted (not a bot, a prefetch or a repeat).
         *
         * @param Document      $document
         * @param string        $mode    'view' or 'download'.
         * @param \WP_User|null $user    Signed-in visitor, if any.
         * @param string        $visitor Daily-rotating anonymous visitor key.
         * @param UserAgent     $agent
         */
        do_action('crnd_hit', $document, $mode, $user, $visitor, $agent);
    }

    /** Browsers that load links ahead of a click say so; those loads are not visits. */
    private function isPrefetch(): bool
    {
        $purpose = strtolower((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? $_SERVER['HTTP_X_MOZ'] ?? '')); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
    }

    private function visitorKey(): string
    {
        /**
         * The visitor's IP. Behind a proxy or CDN, return the real client IP from the
         * header the proxy sets (only if that proxy can be trusted).
         */
        $ip = (string) apply_filters('crnd_visitor_ip', sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')));
        $ua = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return substr(hash_hmac('sha256', $ip . '|' . $ua, wp_salt('auth') . gmdate('Y-m-d')), 0, 16);
    }

    /** Remembers the hit for a few seconds (object cache if there is one, else a transient). */
    private function isRepeat(int $documentId, string $mode, string $visitor): bool
    {
        $key = 'crnd_hit_' . substr(md5($documentId . '|' . $mode . '|' . $visitor), 0, 20);
        if (wp_using_ext_object_cache()) {
            return !wp_cache_add($key, 1, 'crnd', self::DEDUPE_SECONDS);
        }
        if (get_transient($key) !== false) {
            return true;
        }
        set_transient($key, 1, self::DEDUPE_SECONDS);
        return false;
    }

    /** Atomic counter, so the library list does not need to count anything. */
    private function increment(int $documentId, string $key): void
    {
        global $wpdb;
        if (add_post_meta($documentId, $key, 1, true)) {
            return;
        }
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
            $documentId,
            $key
        ));
        wp_cache_delete($documentId, 'post_meta');
    }
}
