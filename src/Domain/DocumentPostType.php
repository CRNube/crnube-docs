<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

/**
 * A document is a post of its own, not a bare attachment: it keeps a readable title, a
 * publication date and its order, and the file behind it can be replaced without
 * breaking the links already shared.
 */
final class DocumentPostType
{
    public const NAME = 'crnd_document';

    public const META_FILE_ID   = '_crnd_file_id';
    public const META_EXTERNAL  = '_crnd_external_url';
    public const META_DOWNLOADS = '_crnd_downloads';
    /** The year the document is about (a 2025 report published in 2026). Empty: the publication year. */
    public const META_YEAR = '_crnd_year';

    /** The year a document is filed under: its own year if set, else the publication year. */
    public static function yearOf(\WP_Post $post): string
    {
        $year = (string) get_post_meta($post->ID, self::META_YEAR, true);
        return preg_match('/^\d{4}$/', $year) ? $year : (string) get_post_time('Y', false, $post);
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_filter('use_block_editor_for_post_type', [$this, 'disableBlockEditor'], 10, 2);
        add_filter('enter_title_here', [$this, 'titlePlaceholder'], 10, 2);
        add_filter('post_updated_messages', [$this, 'updatedMessages']);
    }

    public function registerPostType(): void
    {
        register_post_type(self::NAME, [
            'labels' => [
                'name'               => __('Documents', 'crnube-docs'),
                'singular_name'      => __('Document', 'crnube-docs'),
                'menu_name'          => __('Documents', 'crnube-docs'),
                'all_items'          => __('All documents', 'crnube-docs'),
                'add_new'            => __('Add document', 'crnube-docs'),
                'add_new_item'       => __('Add new document', 'crnube-docs'),
                'edit_item'          => __('Edit document', 'crnube-docs'),
                'new_item'           => __('New document', 'crnube-docs'),
                'search_items'       => __('Search documents', 'crnube-docs'),
                'not_found'          => __('No documents found.', 'crnube-docs'),
                'not_found_in_trash' => __('No documents found in the trash.', 'crnube-docs'),
                'item_published'     => __('Document published.', 'crnube-docs'),
                'item_updated'       => __('Document updated.', 'crnube-docs'),
            ],
            // Documents are listed through the shortcode; they have no page of their own.
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            // Listed under the "Documents" menu that LibraryPage creates.
            'show_in_menu'        => 'crnd-library',
            'show_in_rest'        => true,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-media-document',
            'supports'            => ['title', 'excerpt', 'page-attributes'],
            'capability_type'     => ['crnd_document', 'crnd_documents'],
            'map_meta_cap'        => true,
            'rewrite'             => false,
            'query_var'           => false,
            'has_archive'         => false,
            'delete_with_user'    => false,
        ]);

        register_post_meta(self::NAME, self::META_FILE_ID, [
            'type'              => 'integer',
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => static fn (mixed $value): int => absint($value),
            'auth_callback'     => static fn (bool $allowed, string $key, int $postId): bool => current_user_can('edit_post', $postId),
            'show_in_rest'      => true,
        ]);
        register_post_meta(self::NAME, self::META_YEAR, [
            'type'              => 'string',
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => static fn (mixed $value): string => preg_match('/^\d{4}$/', (string) $value) ? (string) $value : '',
            'auth_callback'     => static fn (bool $allowed, string $key, int $postId): bool => current_user_can('edit_post', $postId),
            'show_in_rest'      => true,
        ]);
        register_post_meta(self::NAME, self::META_EXTERNAL, [
            'type'              => 'string',
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => [self::class, 'sanitizeExternalUrl'],
            'auth_callback'     => static fn (bool $allowed, string $key, int $postId): bool => current_user_can('edit_post', $postId),
            'show_in_rest'      => true,
        ]);
    }

    /** Only http(s) links are accepted, so a stored URL can never become a javascript: link. */
    public static function sanitizeExternalUrl(mixed $value): string
    {
        $url = esc_url_raw(trim((string) $value), ['http', 'https']);
        return wp_http_validate_url($url) ? $url : '';
    }

    public function disableBlockEditor(bool $useBlockEditor, string $postType): bool
    {
        return $postType === self::NAME ? false : $useBlockEditor;
    }

    public function titlePlaceholder(string $text, \WP_Post $post): string
    {
        return $post->post_type === self::NAME
            ? __('Document name, e.g. Annual Risk Report 2025', 'crnube-docs')
            : $text;
    }

    /**
     * @param array<string, array<int, string>> $messages
     * @return array<string, array<int, string>>
     */
    public function updatedMessages(array $messages): array
    {
        $updated = __('Document updated.', 'crnube-docs');
        $messages[self::NAME] = [
            1  => $updated,
            4  => $updated,
            6  => __('Document published.', 'crnube-docs'),
            7  => __('Document saved.', 'crnube-docs'),
            8  => __('Document submitted.', 'crnube-docs'),
            9  => __('Document scheduled.', 'crnube-docs'),
            10 => __('Document draft updated.', 'crnube-docs'),
        ];
        return $messages;
    }
}
