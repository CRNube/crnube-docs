<?php
declare(strict_types=1);

namespace CRNube\Docs\Admin;

use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Domain\FileTypes;
use CRNube\Docs\Download\DownloadController;
use CRNube\Docs\Frontend\Assets;
use CRNube\Docs\Frontend\Icons;

/**
 * The "File" box on the document screen: pick or upload the file from the media
 * library, or point to an external link instead.
 */
final class DocumentMetaBox
{
    private const NONCE        = 'crnd_document_nonce';
    private const NONCE_ACTION = 'crnd_save_document';
    private const NOTICE_KEY   = 'crnd_file_rejected_';

    public function register(): void
    {
        add_action('add_meta_boxes_' . DocumentPostType::NAME, [$this, 'addBox']);
        add_action('save_post_' . DocumentPostType::NAME, [$this, 'save'], 10, 2);
        add_filter('wp_insert_post_data', [$this, 'defaultTitle'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_notices', [$this, 'rejectedFileNotice']);
    }

    public function addBox(): void
    {
        add_meta_box('crnd-file', __('File', 'crnube-docs'), [$this, 'render'], DocumentPostType::NAME, 'normal', 'high');
        add_meta_box('crnd-year', __('Document year', 'crnube-docs'), [$this, 'renderYear'], DocumentPostType::NAME, 'side', 'default');
    }

    public function renderYear(\WP_Post $post): void
    {
        $year = (string) get_post_meta($post->ID, DocumentPostType::META_YEAR, true);
        ?>
        <label for="crnd-year" class="screen-reader-text"><?php esc_html_e('Document year', 'crnube-docs'); ?></label>
        <input type="number" id="crnd-year" name="crnd_year" min="1900" max="2100" step="1" class="widefat"
            value="<?php echo esc_attr($year); ?>" placeholder="<?php echo esc_attr((string) get_post_time('Y', false, $post)); ?>">
        <p class="description"><?php esc_html_e('The year the document covers, used by the year filter and year groups. Empty: the publication year.', 'crnube-docs'); ?></p>
        <?php
    }

    public function enqueue(string $hook): void
    {
        $screen = get_current_screen();
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || $screen?->post_type !== DocumentPostType::NAME) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('crnd-admin', CRND_URL . 'assets/css/admin.css', [], Assets::version('assets/css/admin.css'));
        wp_enqueue_script('crnd-admin-document', CRND_URL . 'assets/js/admin-document.js', ['jquery', 'media-editor'], Assets::version('assets/js/admin-document.js'), true);
        wp_localize_script('crnd-admin-document', 'crndDocument', [
            'mimes' => array_values(array_unique(FileTypes::allowedMimes())),
            'i18n'  => [
                'frameTitle'  => __('Choose the document file', 'crnube-docs'),
                'frameButton' => __('Use this file', 'crnube-docs'),
                'notAllowed'  => __('This file type is not allowed for documents.', 'crnube-docs'),
            ],
        ]);
    }

    public function render(\WP_Post $post): void
    {
        $fileId   = (int) get_post_meta($post->ID, DocumentPostType::META_FILE_ID, true);
        $external = (string) get_post_meta($post->ID, DocumentPostType::META_EXTERNAL, true);
        $file     = $fileId > 0 ? get_post($fileId) : null;
        $hasFile  = $file instanceof \WP_Post && $file->post_type === 'attachment';
        $useLink  = !$hasFile && $external !== '';
        $path     = $hasFile ? (string) get_attached_file($fileId) : '';
        $ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $size     = $path !== '' && is_readable($path) ? FileTypes::formatSize((int) filesize($path)) : '';
        $count    = (int) get_post_meta($post->ID, DocumentPostType::META_DOWNLOADS, true);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE);
        ?>
        <div class="crnd-filebox" data-crnd-filebox>
            <div class="crnd-filebox__tabs" role="radiogroup" aria-label="<?php esc_attr_e('Source', 'crnube-docs'); ?>">
                <label><input type="radio" name="crnd_source" value="file" <?php checked(!$useLink); ?>> <?php esc_html_e('File', 'crnube-docs'); ?></label>
                <label><input type="radio" name="crnd_source" value="link" <?php checked($useLink); ?>> <?php esc_html_e('External link', 'crnube-docs'); ?></label>
            </div>

            <div class="crnd-filebox__panel" data-crnd-panel="file" <?php echo $useLink ? 'hidden' : ''; ?>>
                <input type="hidden" name="crnd_file_id" value="<?php echo (int) ($hasFile ? $fileId : 0); ?>" data-crnd-file-id>

                <div class="crnd-filebox__empty" data-crnd-empty <?php echo $hasFile ? 'hidden' : ''; ?>>
                    <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                    <p><?php esc_html_e('Upload a PDF, Word, Excel or PowerPoint file, or choose one already in the media library.', 'crnube-docs'); ?></p>
                    <button type="button" class="button button-primary button-hero" data-crnd-select><?php esc_html_e('Select or upload file', 'crnube-docs'); ?></button>
                </div>

                <div class="crnd-filebox__file" data-crnd-file <?php echo $hasFile ? '' : 'hidden'; ?>>
                    <span class="crnd-filebox__icon" data-crnd-icon><?php echo $hasFile ? Icons::file(FileTypes::kind($ext), $ext) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Icons::file() ?></span>
                    <div class="crnd-filebox__info">
                        <strong data-crnd-name><?php echo esc_html($hasFile ? wp_basename($path) : ''); ?></strong>
                        <span data-crnd-meta><?php echo esc_html(trim(strtoupper($ext) . ($size !== '' ? ' · ' . $size : ''), ' ·')); ?></span>
                    </div>
                    <div class="crnd-filebox__actions">
                        <button type="button" class="button" data-crnd-select><?php esc_html_e('Replace', 'crnube-docs'); ?></button>
                        <button type="button" class="button-link crnd-link-danger" data-crnd-remove><?php esc_html_e('Remove', 'crnube-docs'); ?></button>
                    </div>
                </div>
            </div>

            <div class="crnd-filebox__panel" data-crnd-panel="link" <?php echo $useLink ? '' : 'hidden'; ?>>
                <label for="crnd-external-url"><?php esc_html_e('Document URL', 'crnube-docs'); ?></label>
                <input type="url" id="crnd-external-url" name="crnd_external_url" class="widefat" value="<?php echo esc_attr($external); ?>" placeholder="https://">
                <p class="description"><?php esc_html_e('For documents hosted elsewhere (Google Drive, SUGEF, another site). It opens in a new tab.', 'crnube-docs'); ?></p>
            </div>

            <?php if ($post->post_status === 'publish' && ($hasFile || $external !== '')) : ?>
                <div class="crnd-filebox__footer">
                    <span>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: number of downloads */
                            _n('%s download', '%s downloads', $count, 'crnube-docs'),
                            number_format_i18n($count)
                        ));
                        ?>
                    </span>
                    <span class="crnd-filebox__link">
                        <?php esc_html_e('Permanent link:', 'crnube-docs'); ?>
                        <code><?php echo esc_html(DownloadController::url($post->ID)); ?></code>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), self::NONCE_ACTION)) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || !current_user_can('edit_post', $postId)) {
            return;
        }

        $source   = sanitize_key(wp_unslash($_POST['crnd_source'] ?? 'file'));
        $fileId   = $source === 'file' ? $this->validFileId(absint($_POST['crnd_file_id'] ?? 0)) : 0;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizeExternalUrl() runs esc_url_raw() and URL validation.
        $external = $source === 'link' ? DocumentPostType::sanitizeExternalUrl(wp_unslash($_POST['crnd_external_url'] ?? '')) : '';

        if ($source === 'file' && absint($_POST['crnd_file_id'] ?? 0) > 0 && $fileId === 0) {
            set_transient(self::NOTICE_KEY . get_current_user_id(), 1, 60);
        }

        update_post_meta($postId, DocumentPostType::META_FILE_ID, $fileId);
        update_post_meta($postId, DocumentPostType::META_EXTERNAL, $external);

        $year = sanitize_text_field(wp_unslash($_POST['crnd_year'] ?? ''));
        update_post_meta($postId, DocumentPostType::META_YEAR, preg_match('/^\d{4}$/', $year) ? $year : '');

        // Attach an unattached upload to this document, so the media library shows where it is used.
        if ($fileId > 0 && (int) wp_get_post_parent_id($fileId) === 0) {
            wp_update_post(['ID' => $fileId, 'post_parent' => $postId]);
        }
    }

    /**
     * A document saved without a title takes the file's title.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    public function defaultTitle(array $data, array $postarr): array
    {
        if ($data['post_type'] !== DocumentPostType::NAME || trim((string) $data['post_title']) !== '') {
            return $data;
        }
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), self::NONCE_ACTION)) {
            return $data;
        }
        $fileId = $this->validFileId(absint($_POST['crnd_file_id'] ?? 0));
        if ($fileId > 0) {
            $data['post_title'] = wp_slash(get_the_title($fileId));
        }
        return $data;
    }

    public function rejectedFileNotice(): void
    {
        $key = self::NOTICE_KEY . get_current_user_id();
        if (!get_transient($key)) {
            return;
        }
        delete_transient($key);
        echo '<div class="notice notice-error is-dismissible"><p>'
            . esc_html__('The selected file was not saved: its type is not allowed for documents.', 'crnube-docs')
            . '</p></div>';
    }

    private function validFileId(int $fileId): int
    {
        if ($fileId <= 0 || get_post_type($fileId) !== 'attachment') {
            return 0;
        }
        if (!FileTypes::isAllowedMime((string) get_post_mime_type($fileId))) {
            return 0;
        }
        $path = (string) get_attached_file($fileId);
        return FileTypes::contentMatches($path, strtolower(pathinfo($path, PATHINFO_EXTENSION))) ? $fileId : 0;
    }
}
