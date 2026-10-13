<?php
declare(strict_types=1);

namespace CRNube\Docs\Rest;

use CRNube\Docs\Domain\CategoryOrder;
use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Domain\Document;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Domain\FileTypes;
use CRNube\Docs\Domain\YearTaxonomy;
use CRNube\Docs\Download\DownloadController;
use CRNube\Docs\Support\Cache;
use CRNube\Docs\Support\Filename;

/**
 * crnd/v1/documents: what the admin library uses to list, upload, edit and organize
 * documents. Every route checks the document capabilities, and each item the WordPress
 * per-post capability, so a role without access to a document cannot touch it here.
 */
final class DocumentsController
{
    public const NAMESPACE = 'crnd/v1';

    private const STATUSES      = ['publish', 'draft', 'future', 'private', 'pending'];
    private const BULK_ACTIONS  = ['move', 'add_category', 'remove_category', 'set_year', 'publish', 'draft', 'trash'];
    private const BULK_MAX      = 200;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        $id = ['id' => ['type' => 'integer', 'required' => true, 'minimum' => 1]];

        register_rest_route(self::NAMESPACE, '/documents', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canEditDocuments'],
                'args'                => [
                    'page'     => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                    'per_page' => ['type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 200],
                    'search'   => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                    // 0: every category, -1: documents without a category.
                    'category' => ['type' => 'integer', 'default' => 0, 'minimum' => -1],
                    // Only the documents filed directly in `category`, not in its subcategories.
                    'direct'   => ['type' => 'boolean', 'default' => false],
                    'year'     => ['type' => 'string', 'pattern' => '^(\d{4})?$', 'default' => ''],
                    'status'   => ['type' => 'string', 'default' => 'any', 'enum' => ['any', ...self::STATUSES]],
                    'orderby'  => ['type' => 'string', 'default' => 'date', 'enum' => ['date', 'title', 'menu_order', 'modified', 'site']],
                    // 'site': the order the category has on the public site (its own mode).
                    'order'    => ['type' => 'string', 'default' => 'desc', 'enum' => ['asc', 'desc']],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/documents/upload', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'upload'],
            'permission_callback' => [$this, 'canUpload'],
            'args'                => [
                'category' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
                'status'   => ['type' => 'string', 'default' => 'publish', 'enum' => ['publish', 'draft']],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/documents/years', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'years'],
            'permission_callback' => [$this, 'canEditDocuments'],
        ]);

        register_rest_route(self::NAMESPACE, '/documents/bulk', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'bulk'],
            'permission_callback' => [$this, 'canEditDocuments'],
            'args'                => [
                'ids'    => ['type' => 'array', 'required' => true, 'items' => ['type' => 'integer', 'minimum' => 1], 'minItems' => 1, 'maxItems' => self::BULK_MAX],
                'action' => ['type' => 'string', 'required' => true, 'enum' => self::BULK_ACTIONS],
                'value'  => ['type' => ['integer', 'string'], 'default' => ''],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/documents/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canEditItem'],
                'args'                => $id + [
                    'title'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'status'     => ['type' => 'string', 'enum' => self::STATUSES],
                    'year'       => ['type' => 'string', 'pattern' => '^(\d{4})?$'],
                    'categories' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1]],
                    'menu_order' => ['type' => 'integer'],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete'],
                'permission_callback' => [$this, 'canDeleteItem'],
                'args'                => $id,
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/documents/(?P<id>\d+)/file', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'replaceFile'],
            'permission_callback' => fn (\WP_REST_Request $r): bool => $this->canUpload() && $this->canEditItem($r),
            'args'                => $id,
        ]);
    }

    public function canEditDocuments(): bool
    {
        return current_user_can('edit_crnd_documents');
    }

    public function canUpload(): bool
    {
        return current_user_can('edit_crnd_documents') && current_user_can('upload_files');
    }

    public function canEditItem(\WP_REST_Request $request): bool
    {
        return $this->isDocument((int) $request['id']) && current_user_can('edit_post', (int) $request['id']);
    }

    public function canDeleteItem(\WP_REST_Request $request): bool
    {
        return $this->isDocument((int) $request['id']) && current_user_can('delete_post', (int) $request['id']);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $args = [
            'post_type'      => DocumentPostType::NAME,
            'post_status'    => $request['status'] === 'any' ? self::STATUSES : $request['status'],
            'posts_per_page' => (int) $request['per_page'],
            'paged'          => (int) $request['page'],
            'orderby'        => [$request['orderby'] => strtoupper((string) $request['order']), 'ID' => 'DESC'],
        ];
        if ($request['search'] !== '') {
            $args['s'] = $request['search'];
        }

        $taxQuery = [];
        $category = (int) $request['category'];
        if ($category > 0) {
            $taxQuery[] = ['taxonomy' => CategoryTaxonomy::NAME, 'field' => 'term_id', 'terms' => $category, 'include_children' => !$request['direct']];
        } elseif ($category === -1) {
            $taxQuery[] = ['taxonomy' => CategoryTaxonomy::NAME, 'operator' => 'NOT EXISTS'];
        }
        if ($request['year'] !== '') {
            $taxQuery[] = ['taxonomy' => YearTaxonomy::NAME, 'field' => 'slug', 'terms' => (string) $request['year']];
        }
        if ($taxQuery !== []) {
            $args['tax_query'] = $taxQuery; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        }

        if ($request['orderby'] === 'site' && $category > 0) {
            [$posts, $total] = $this->siteOrdered($args, $category, (int) $request['page'], (int) $request['per_page']);
            $pages           = (int) ceil($total / max(1, (int) $request['per_page']));
        } else {
            if ($request['orderby'] === 'site') {
                $args['orderby'] = ['date' => 'DESC', 'ID' => 'DESC'];
            }
            $query = new \WP_Query($args);
            $posts = $query->posts;
            $total = (int) $query->found_posts;
            $pages = (int) $query->max_num_pages;
        }
        $this->primeFiles($posts);

        $response = new \WP_REST_Response(array_map([$this, 'item'], $posts));
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) max(1, $pages));
        return $response;
    }

    /**
     * The documents of a category in the order the public site shows them. That order
     * (manual or by the category's mode) is applied in PHP, so the whole category is read
     * and then paged; categories hold tens or hundreds of documents, not thousands.
     *
     * @param array<string, mixed> $args
     * @return array{0: list<\WP_Post>, 1: int}
     */
    private function siteOrdered(array $args, int $category, int $page, int $perPage): array
    {
        $args['posts_per_page'] = -1;
        $args['no_found_rows']  = true;
        unset($args['paged']);

        $settings = \CRNube\Docs\Plugin::instance()->settings;
        $sorted   = CategoryOrder::sort(
            (new \WP_Query($args))->posts,
            $category,
            static fn (\WP_Post $p): int => $p->ID,
            static fn (\WP_Post $p): string => $p->post_title,
            static fn (\WP_Post $p): int => (int) get_post_timestamp($p),
            (string) $settings->get('orderby'),
            (string) $settings->get('order')
        );

        return [array_slice($sorted, ($page - 1) * $perPage, $perPage), count($sorted)];
    }

    /** @return list<string> */
    public function years(): array
    {
        $terms = get_terms(['taxonomy' => YearTaxonomy::NAME, 'hide_empty' => false, 'fields' => 'names']);
        $years = is_array($terms) ? array_values(array_filter(array_map('strval', $terms), static fn (string $y): bool => (bool) preg_match('/^\d{4}$/', $y))) : [];
        rsort($years);
        return $years;
    }

    public function upload(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $category = (int) $request['category'];
        if ($category > 0 && !term_exists($category, CategoryTaxonomy::NAME)) {
            return new \WP_Error('crnd_bad_category', __('The category does not exist.', 'crnube-docs'), ['status' => 400]);
        }

        $fileId = $this->storeUpload($request);
        if ($fileId instanceof \WP_Error) {
            return $fileId;
        }

        $original = (string) ($request->get_file_params()['file']['name'] ?? '');
        $status   = $request['status'] === 'draft' ? 'draft' : (current_user_can('publish_crnd_documents') ? 'publish' : 'pending');

        $postId = wp_insert_post([
            'post_type'   => DocumentPostType::NAME,
            'post_status' => $status,
            'post_title'  => Filename::title($original),
            'meta_input'  => [
                DocumentPostType::META_FILE_ID => $fileId,
                DocumentPostType::META_YEAR    => Filename::year($original),
            ],
        ], true);

        if ($postId instanceof \WP_Error) {
            wp_delete_attachment($fileId, true);
            return $postId;
        }

        if ($category > 0) {
            wp_set_object_terms($postId, [$category], CategoryTaxonomy::NAME);
        }
        wp_update_post(['ID' => $fileId, 'post_parent' => $postId]);

        return new \WP_REST_Response($this->item((int) $postId), 201);
    }

    public function replaceFile(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $fileId = $this->storeUpload($request);
        if ($fileId instanceof \WP_Error) {
            return $fileId;
        }
        $postId = (int) $request['id'];
        wp_update_post(['ID' => $fileId, 'post_parent' => $postId]);
        // The old file stays in the media library; the document's links keep working.
        update_post_meta($postId, DocumentPostType::META_FILE_ID, $fileId);
        update_post_meta($postId, DocumentPostType::META_EXTERNAL, '');
        Cache::flush();
        return new \WP_REST_Response($this->item($postId));
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $postId = (int) $request['id'];
        $data   = ['ID' => $postId];

        if ($request->has_param('title')) {
            $title = trim((string) $request['title']);
            if ($title === '') {
                return new \WP_Error('crnd_empty_title', __('The title cannot be empty.', 'crnube-docs'), ['status' => 400]);
            }
            $data['post_title'] = $title;
        }
        if ($request->has_param('status')) {
            $status = (string) $request['status'];
            if (in_array($status, ['publish', 'future', 'private'], true) && !current_user_can('publish_crnd_documents')) {
                return new \WP_Error('crnd_cannot_publish', __('You cannot publish documents.', 'crnube-docs'), ['status' => 403]);
            }
            $data['post_status'] = $status;
        }
        if ($request->has_param('menu_order')) {
            $data['menu_order'] = (int) $request['menu_order'];
        }
        if ($request->has_param('year')) {
            // Saved before wp_update_post(), whose save_post hook files the document under this year.
            update_post_meta($postId, DocumentPostType::META_YEAR, (string) $request['year']);
        }
        if ($request->has_param('categories')) {
            $ids = $this->existingCategories((array) $request['categories']);
            wp_set_object_terms($postId, $ids, CategoryTaxonomy::NAME);
        }

        $result = wp_update_post($data, true);
        if ($result instanceof \WP_Error) {
            return $result;
        }
        return new \WP_REST_Response($this->item($postId));
    }

    public function delete(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $post = wp_trash_post((int) $request['id']);
        if (!$post) {
            return new \WP_Error('crnd_trash_failed', __('The document could not be moved to the trash.', 'crnube-docs'), ['status' => 500]);
        }
        return new \WP_REST_Response(['trashed' => true, 'id' => (int) $request['id']]);
    }

    public function bulk(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $action = (string) $request['action'];
        $value  = $request['value'];
        $done   = [];
        $failed = [];

        if (in_array($action, ['move', 'add_category', 'remove_category'], true)) {
            $value = (int) $value;
            if ($value <= 0 || !term_exists($value, CategoryTaxonomy::NAME)) {
                return new \WP_Error('crnd_bad_category', __('The category does not exist.', 'crnube-docs'), ['status' => 400]);
            }
        }
        if ($action === 'set_year' && !preg_match('/^(\d{4})?$/', (string) $value)) {
            return new \WP_Error('crnd_bad_year', __('The year must have four digits.', 'crnube-docs'), ['status' => 400]);
        }
        if ($action === 'publish' && !current_user_can('publish_crnd_documents')) {
            return new \WP_Error('crnd_cannot_publish', __('You cannot publish documents.', 'crnube-docs'), ['status' => 403]);
        }

        foreach (array_unique(array_map('intval', (array) $request['ids'])) as $postId) {
            $cap = $action === 'trash' ? 'delete_post' : 'edit_post';
            if (!$this->isDocument($postId) || !current_user_can($cap, $postId)) {
                $failed[] = $postId;
                continue;
            }

            $ok = match ($action) {
                'move'            => !is_wp_error(wp_set_object_terms($postId, [$value], CategoryTaxonomy::NAME)),
                'add_category'    => !is_wp_error(wp_set_object_terms($postId, [$value], CategoryTaxonomy::NAME, true)),
                'remove_category' => !is_wp_error(wp_remove_object_terms($postId, [$value], CategoryTaxonomy::NAME)),
                'set_year'        => $this->setYear($postId, (string) $value),
                'publish', 'draft' => !is_wp_error(wp_update_post(['ID' => $postId, 'post_status' => $action], true)),
                'trash'           => (bool) wp_trash_post($postId),
                default           => false,
            };
            if ($ok) {
                $done[] = $postId;
            } else {
                $failed[] = $postId;
            }
        }

        Cache::flush();
        return new \WP_REST_Response(['done' => $done, 'failed' => $failed]);
    }

    /**
     * The library's view of a document.
     *
     * @return array<string, mixed>
     */
    public function item(\WP_Post|int $post): array
    {
        $post     = get_post($post);
        $document = Document::fromPost($post);
        $file     = null;

        if ($document->fileId > 0) {
            $path = (string) get_attached_file($document->fileId);
            $file = [
                'id'   => $document->fileId,
                'name' => wp_basename($path),
                'ext'  => $document->extension,
                'kind' => $document->kind(),
                'size' => $document->size,
                'url'  => (string) wp_get_attachment_url($document->fileId),
            ];
        }

        return [
            'id'           => $post->ID,
            'title'        => $post->post_title,
            'status'       => $post->post_status,
            'date'         => (string) get_post_time('c', false, $post),
            // Short ("28 sep 2025") so the library's date column stays narrow.
            'date_label'   => (string) get_post_time('j M Y', false, $post, true),
            'year'         => $document->year,
            'year_own'     => (string) get_post_meta($post->ID, DocumentPostType::META_YEAR, true),
            'file'         => $file,
            'external_url' => $document->externalUrl,
            'kind'         => $document->kind(),
            'ext'          => $document->extension,
            'categories'   => $document->categoryIds,
            'menu_order'   => $post->menu_order,
            'downloads'    => (int) get_post_meta($post->ID, DocumentPostType::META_DOWNLOADS, true),
            'edit_url'     => (string) get_edit_post_link($post->ID, 'raw'),
            'view_url'     => DownloadController::url($post->ID, 'view'),
            'can_delete'   => current_user_can('delete_post', $post->ID),
        ];
    }

    /**
     * Saves the uploaded "file" field to the media library, accepting document types only.
     */
    private function storeUpload(\WP_REST_Request $request): int|\WP_Error
    {
        $file = $request->get_file_params()['file'] ?? null;
        if (!is_array($file) || empty($file['tmp_name'])) {
            return new \WP_Error('crnd_no_file', __('No file was received.', 'crnube-docs'), ['status' => 400]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $mimes = [];
        foreach (FileTypes::allowedMimes() as $ext => $mime) {
            $mimes[$ext] = $mime;
        }

        // Checks the real content type (not only the extension) against the allowed list.
        $check = wp_check_filetype_and_ext($file['tmp_name'], (string) $file['name'], $mimes);
        if (empty($check['ext']) || empty($check['type'])) {
            return new \WP_Error('crnd_bad_type', __('This file type is not allowed for documents.', 'crnube-docs'), ['status' => 415]);
        }
        if (!FileTypes::contentMatches((string) $file['tmp_name'], (string) $check['ext'])) {
            return new \WP_Error('crnd_bad_content', __('The content of the file does not match its extension.', 'crnube-docs'), ['status' => 415]);
        }

        $upload = wp_handle_upload($file, ['test_form' => false, 'mimes' => $mimes]);
        if (isset($upload['error'])) {
            return new \WP_Error('crnd_upload_failed', (string) $upload['error'], ['status' => 400]);
        }

        $fileId = wp_insert_attachment([
            'post_title'     => Filename::title((string) $file['name']),
            'post_mime_type' => $upload['type'],
            'post_status'    => 'inherit',
        ], $upload['file'], 0, true);

        if ($fileId instanceof \WP_Error) {
            wp_delete_file($upload['file']);
            return $fileId;
        }

        $meta = wp_generate_attachment_metadata($fileId, $upload['file']);
        if (!isset($meta['filesize'])) {
            $meta['filesize'] = (int) filesize($upload['file']);
        }
        wp_update_attachment_metadata($fileId, $meta);

        return (int) $fileId;
    }

    private function setYear(int $postId, string $year): bool
    {
        update_post_meta($postId, DocumentPostType::META_YEAR, $year);
        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            return false;
        }
        return !is_wp_error(wp_set_object_terms($postId, DocumentPostType::yearOf($post), YearTaxonomy::NAME));
    }

    /**
     * @param array<mixed> $ids
     * @return list<int>
     */
    private function existingCategories(array $ids): array
    {
        return array_values(array_filter(
            array_unique(array_map('intval', $ids)),
            static fn (int $id): bool => $id > 0 && (bool) term_exists($id, CategoryTaxonomy::NAME)
        ));
    }

    private function isDocument(int $postId): bool
    {
        return get_post_type($postId) === DocumentPostType::NAME;
    }

    /** @param array<\WP_Post|int> $posts */
    private function primeFiles(array $posts): void
    {
        $ids = [];
        foreach ($posts as $post) {
            $fileId = (int) get_post_meta(is_int($post) ? $post : $post->ID, DocumentPostType::META_FILE_ID, true);
            if ($fileId > 0) {
                $ids[] = $fileId;
            }
        }
        if ($ids !== []) {
            _prime_post_caches($ids, false, true);
        }
    }
}
