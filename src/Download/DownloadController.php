<?php
declare(strict_types=1);

namespace CRNube\Docs\Download;

use CRNube\Docs\Access\AccessPolicy;
use CRNube\Docs\Analytics\Counter;
use CRNube\Docs\Domain\Document;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Domain\FileTypes;

/**
 * Every link to a file goes through /doc-download/{id}/{view|download}/ instead of the
 * file's real URL. That keeps links stable when a file is replaced, counts downloads,
 * and is the single place where access is checked once the protected area exists.
 */
final class DownloadController
{
    public const QUERY_ID   = 'crnd_doc';
    public const QUERY_MODE = 'crnd_mode';

    public function __construct(
        private readonly AccessPolicy $access,
        private readonly Counter $counter,
    ) {
    }

    public function register(): void
    {
        add_action('init', [self::class, 'addRewriteRule']);
        add_filter('query_vars', [$this, 'queryVars']);
        add_action('parse_request', [$this, 'handle']);
    }

    public static function base(): string
    {
        return trim((string) apply_filters('crnd_download_base', 'doc-download'), '/');
    }

    public static function addRewriteRule(): void
    {
        add_rewrite_rule(
            '^' . preg_quote(self::base(), '#') . '/([0-9]+)/(view|download)/?$',
            'index.php?' . self::QUERY_ID . '=$matches[1]&' . self::QUERY_MODE . '=$matches[2]',
            'top'
        );
    }

    public static function url(int $documentId, string $mode = 'download'): string
    {
        $mode = $mode === 'view' ? 'view' : 'download';
        if (get_option('permalink_structure')) {
            return home_url(user_trailingslashit(self::base() . '/' . $documentId . '/' . $mode));
        }
        return add_query_arg([self::QUERY_ID => $documentId, self::QUERY_MODE => $mode], home_url('/'));
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function queryVars(array $vars): array
    {
        $vars[] = self::QUERY_ID;
        $vars[] = self::QUERY_MODE;
        return $vars;
    }

    public function handle(\WP $wp): void
    {
        if (empty($wp->query_vars[self::QUERY_ID])) {
            return;
        }

        $id   = absint($wp->query_vars[self::QUERY_ID]);
        $mode = ($wp->query_vars[self::QUERY_MODE] ?? '') === 'view' ? 'view' : 'download';
        $post = get_post($id);

        $visible = $post instanceof \WP_Post
            && $post->post_type === DocumentPostType::NAME
            && ($post->post_status === 'publish' || current_user_can('edit_post', $post->ID));

        if (!$visible) {
            $this->fail(404, __('The document does not exist or is no longer available.', 'crnube-docs'));
        }

        $document = Document::fromPost($post);
        $user     = is_user_logged_in() ? wp_get_current_user() : null;

        if (!$this->access->canAccess($document, $user)) {
            $redirect = $this->access->deniedRedirect($document);
            if ($redirect !== null) {
                wp_safe_redirect($redirect);
                exit;
            }
            $this->fail(403, __('You do not have access to this document.', 'crnube-docs'));
        }

        if (!$document->hasFile()) {
            $this->fail(404, __('This document has no file attached yet.', 'crnube-docs'));
        }

        if (sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'get')) !== 'head') {
            // A "view" of a file the browser cannot show is served as a download.
            $this->counter->record($document, $mode === 'view' && $document->isViewable() ? 'view' : 'download', $user);
        }

        do_action('crnd_before_download', $document, $mode, $user);

        if ($document->isExternal()) {
            // The URL was validated as http(s) when it was saved; it may be on another host.
            wp_redirect($document->externalUrl, 302, 'CRNube Docs'); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
            exit;
        }

        $inline = $mode === 'view' && $document->isViewable();

        // Public files are sent to their URL; restricted ones never leave this controller.
        if ($inline && !$document->restricted && !self::inPrivateFolder($document->fileId)) {
            $url = wp_get_attachment_url($document->fileId);
            if ($url) {
                wp_redirect($url, 302, 'CRNube Docs'); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
                exit;
            }
        }

        $this->stream($document, $inline);
    }

    /**
     * Files in a private folder (uploads/crnd-private-…, where the protected area keeps
     * restricted files) are never sent to their URL, even if the document became public
     * and the file has not been moved back yet.
     */
    public static function inPrivateFolder(int $fileId): bool
    {
        return str_starts_with((string) get_post_meta($fileId, '_wp_attached_file', true), 'crnd-private-');
    }

    private function stream(Document $document, bool $inline = false): never
    {
        $path    = realpath((string) get_attached_file($document->fileId));
        $uploads = realpath(wp_get_upload_dir()['basedir']);

        // Only files inside the uploads folder are ever served.
        if ($path === false || $uploads === false || !str_starts_with($path, $uploads . DIRECTORY_SEPARATOR) || !is_readable($path)) {
            $this->fail(404, __('The file of this document could not be found.', 'crnube-docs'));
        }

        $mime = (string) get_post_mime_type($document->fileId);
        if (!FileTypes::isAllowedMime($mime)) {
            $mime = 'application/octet-stream';
        }

        $name     = sanitize_file_name($document->title) ?: 'document';
        $filename = $name . ($document->extension !== '' ? '.' . $document->extension : '');
        $ascii    = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?? 'document';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        status_header(200);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header(sprintf('Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s', $inline ? 'inline' : 'attachment', $ascii, rawurlencode($filename)));
        if ($document->restricted) {
            // Never kept by shared caches or proxies.
            header('Cache-Control: private, no-store, max-age=0');
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');

        /**
         * Lets the web server send the file (X-Sendfile / X-Accel-Redirect) instead of PHP.
         * Return the header line to send, e.g. "X-Sendfile: /path/file.pdf".
         */
        $sendfile = apply_filters('crnd_sendfile_header', '', $path, $document);
        if (is_string($sendfile) && $sendfile !== '') {
            header($sendfile);
            exit;
        }

        $handle = fopen($path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ($handle !== false) {
            fpassthru($handle);
            fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }
        exit;
    }

    private function fail(int $status, string $message): never
    {
        wp_die(esc_html($message), esc_html(get_bloginfo('name')), ['response' => $status, 'back_link' => true]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $status is an int HTTP code
    }
}
