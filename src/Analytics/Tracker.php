<?php
declare(strict_types=1);

namespace CRNube\Docs\Analytics;

use CRNube\Docs\Domain\Document;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Settings\Settings;

/**
 * Records views and downloads.
 *
 * Privacy by design (Costa Rica's Ley 8968): the IP address is never stored. Each visit
 * gets a visitor key, an HMAC of IP + browser with a salt that changes every day, so a
 * person can be counted once per day but cannot be followed from one day to the next
 * nor traced back. Visitors that send "Global Privacy Control" are still counted, but
 * nothing about their device or location is kept.
 */
final class Tracker
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

        $event   = $mode === 'view' ? EventsTable::EVENT_VIEW : EventsTable::EVENT_DOWNLOAD;
        $visitor = $this->visitorKey();

        if ($this->isRepeat($document->id, $event, $visitor)) {
            return;
        }

        $private = $this->settings->get('respect_gpc') && $this->sendsPrivacySignal();

        global $wpdb;
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            EventsTable::name(),
            [
                'document_id' => $document->id,
                'event'       => $event,
                'created_at'  => gmdate('Y-m-d H:i:s'),
                'visitor'     => $visitor,
                'user_id'     => $user !== null ? $user->ID : 0,
                'device'      => $private ? '' : $agent->device(),
                'browser'     => $private ? '' : $agent->browser(),
                'os'          => $private ? '' : $agent->os(),
                'country'     => $private ? '' : $this->country(),
                'source'      => $this->source(),
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        $this->increment($document->id, $event === EventsTable::EVENT_VIEW ? DocumentPostType::META_VIEWS : DocumentPostType::META_DOWNLOADS);
    }

    /** Browsers that load links ahead of a click say so; those loads are not visits. */
    private function isPrefetch(): bool
    {
        $purpose = strtolower((string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? $_SERVER['HTTP_PURPOSE'] ?? $_SERVER['HTTP_X_MOZ'] ?? '')); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
    }

    private function sendsPrivacySignal(): bool
    {
        return ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1' || ($_SERVER['HTTP_DNT'] ?? '') === '1'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
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

    /**
     * Two-letter country code, if the server or CDN provides one (Cloudflare and many
     * hosts do). No external lookup is made.
     */
    private function country(): string
    {
        $raw = '';
        foreach (['HTTP_CF_IPCOUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_GEO_COUNTRY'] as $key) {
            if (!empty($_SERVER[$key])) {
                $raw = sanitize_text_field(wp_unslash($_SERVER[$key]));
                break;
            }
        }
        $country = strtoupper((string) apply_filters('crnd_visitor_country', $raw));
        return preg_match('/^[A-Z]{2}$/', $country) && !in_array($country, ['XX', 'T1'], true) ? $country : '';
    }

    /**
     * Where the click came from: the path of a page on this site (without query string),
     * or the host of another site. Never the full external URL.
     */
    private function source(): string
    {
        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        if ($referer === '') {
            return '';
        }
        $host = (string) wp_parse_url($referer, PHP_URL_HOST);
        if ($host === (string) wp_parse_url(home_url(), PHP_URL_HOST)) {
            return substr((string) (wp_parse_url($referer, PHP_URL_PATH) ?: '/'), 0, 191);
        }
        return substr($host, 0, 191);
    }

    private function isRepeat(int $documentId, int $event, string $visitor): bool
    {
        global $wpdb;
        $table = EventsTable::name();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE document_id = %d AND event = %d AND visitor = %s AND created_at >= %s LIMIT 1",
            $documentId,
            $event,
            $visitor,
            gmdate('Y-m-d H:i:s', time() - self::DEDUPE_SECONDS)
        ));
        // phpcs:enable
    }

    /** Atomic counter, so the library list does not need to count events. */
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
