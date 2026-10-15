<?php
declare(strict_types=1);

namespace CRNube\Docs\Rest;

use CRNube\Docs\Access\DocumentAccess;
use CRNube\Docs\Capabilities;
use CRNube\Docs\Domain\CategoryOrder;
use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Domain\DocumentPostType;

/**
 * crnd/v1/categories: the category tree of the admin library. Reading needs the
 * document capability; creating, renaming, moving and deleting need the category one.
 */
final class CategoriesController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        $ns = DocumentsController::NAMESPACE;

        register_rest_route($ns, '/categories', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'index'],
                'permission_callback' => static fn (): bool => current_user_can('edit_crnd_documents'),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'name'   => ['type' => 'string', 'required' => true, 'minLength' => 1, 'sanitize_callback' => 'sanitize_text_field'],
                    'parent' => ['type' => 'integer', 'default' => 0, 'minimum' => 0],
                ],
            ],
        ]);

        register_rest_route($ns, '/categories/reorder', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'reorder'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'ids' => ['type' => 'array', 'required' => true, 'items' => ['type' => 'integer', 'minimum' => 1], 'maxItems' => 500],
            ],
        ]);


        // Drag and drop in the tree: put a category inside `parent`, at position `index` among its siblings.
        register_rest_route($ns, '/categories/move', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'move'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id'     => ['type' => 'integer', 'required' => true, 'minimum' => 1],
                'parent' => ['type' => 'integer', 'required' => true, 'minimum' => 0],
                'index'  => ['type' => 'integer', 'required' => true, 'minimum' => 0],
            ],
        ]);

        // How the documents of a category are ordered. Arranging documents is document
        // work, so it needs the document capability, not the category one.
        register_rest_route($ns, '/categories/(?P<id>\d+)/order', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'order'],
            'permission_callback' => static fn (): bool => current_user_can('edit_crnd_documents'),
            'args'                => [
                'id'   => ['type' => 'integer', 'required' => true],
                'sort' => ['type' => 'string', 'enum' => CategoryOrder::MODES],
                'ids'  => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'maxItems' => 2000],
            ],
        ]);

        register_rest_route($ns, '/categories/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'id'     => ['type' => 'integer', 'required' => true],
                    'name'   => ['type' => 'string', 'minLength' => 1, 'sanitize_callback' => 'sanitize_text_field'],
                    'parent' => ['type' => 'integer', 'minimum' => 0],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => ['id' => ['type' => 'integer', 'required' => true]],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can(Capabilities::MANAGE_CATEGORIES);
    }

    /**
     * Every category, flat, in display order. Counts include drafts, not the trash.
     */
    public function index(): \WP_REST_Response
    {
        $terms = get_terms(['taxonomy' => CategoryTaxonomy::NAME, 'hide_empty' => false]);
        $terms = is_array($terms) ? $terms : [];
        $counts = $this->counts();

        /**
         * Each category as the library app gets it; add-ons add their own fields.
         *
         * @param array<string, mixed> $item
         * @param \WP_Term             $term
         */
        $items = array_map(static fn (\WP_Term $t): array => (array) apply_filters('crnd_rest_category', [
            'id'     => $t->term_id,
            'name'   => $t->name,
            'slug'   => $t->slug,
            'parent' => $t->parent,
            'order'  => (int) get_term_meta($t->term_id, CategoryTaxonomy::META_ORDER, true),
            'sort'   => CategoryOrder::mode($t->term_id),
            // Own access setting ('' inherits from the parent) and the effective one.
            'access'        => (string) get_term_meta($t->term_id, DocumentAccess::TERM_ACCESS, true),
            'access_groups' => DocumentAccess::documentGroupsSetting($t->term_id),
            'restricted'    => DocumentAccess::categorySetting($t->term_id)['access'] === 'private',
            'count'  => $counts[$t->term_id] ?? 0,
        ], $t), $terms);

        usort($items, static fn (array $a, array $b): int => [$a['order'], $a['name']] <=> [$b['order'], $b['name']]);

        return new \WP_REST_Response([
            'categories'    => $items,
            'total'         => $counts['total'],
            'uncategorized' => $counts['uncategorized'],
        ]);
    }

    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $parent = (int) $request['parent'];
        if ($parent > 0 && !term_exists($parent, CategoryTaxonomy::NAME)) {
            return new \WP_Error('crnd_bad_parent', __('The parent category does not exist.', 'crnube-docs'), ['status' => 400]);
        }
        $result = wp_insert_term((string) $request['name'], CategoryTaxonomy::NAME, ['parent' => $parent]);
        if ($result instanceof \WP_Error) {
            $result->add_data(['status' => 400]);
            return $result;
        }
        // New categories go last among their siblings.
        update_term_meta((int) $result['term_id'], CategoryTaxonomy::META_ORDER, $this->nextOrder($parent));
        return $this->index();
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id   = (int) $request['id'];
        $term = get_term($id, CategoryTaxonomy::NAME);
        if (!$term instanceof \WP_Term) {
            return new \WP_Error('crnd_not_found', __('The category does not exist.', 'crnube-docs'), ['status' => 404]);
        }

        $args = [];
        if ($request->has_param('name')) {
            $args['name'] = (string) $request['name'];
        }
        if ($request->has_param('parent')) {
            $parent = (int) $request['parent'];
            // A category cannot go inside itself or one of its own subcategories.
            if ($parent === $id || $this->isInBranch($parent, $id)) {
                return new \WP_Error('crnd_bad_parent', __('A category cannot be moved inside itself.', 'crnube-docs'), ['status' => 400]);
            }
            if ($parent > 0 && !term_exists($parent, CategoryTaxonomy::NAME)) {
                return new \WP_Error('crnd_bad_parent', __('The parent category does not exist.', 'crnube-docs'), ['status' => 400]);
            }
            $args['parent'] = $parent;
        }

        $result = wp_update_term($id, CategoryTaxonomy::NAME, $args);
        if ($result instanceof \WP_Error) {
            $result->add_data(['status' => 400]);
            return $result;
        }
        return $this->index();
    }


    public function move(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id     = (int) $request['id'];
        $parent = (int) $request['parent'];
        $term   = get_term($id, CategoryTaxonomy::NAME);
        if (!$term instanceof \WP_Term) {
            return new \WP_Error('crnd_not_found', __('The category does not exist.', 'crnube-docs'), ['status' => 404]);
        }
        if ($parent === $id || $this->isInBranch($parent, $id)) {
            return new \WP_Error('crnd_bad_parent', __('A category cannot be moved inside itself.', 'crnube-docs'), ['status' => 400]);
        }
        if ($parent > 0 && !term_exists($parent, CategoryTaxonomy::NAME)) {
            return new \WP_Error('crnd_bad_parent', __('The parent category does not exist.', 'crnube-docs'), ['status' => 400]);
        }

        if ($term->parent !== $parent) {
            $result = wp_update_term($id, CategoryTaxonomy::NAME, ['parent' => $parent]);
            if ($result instanceof \WP_Error) {
                $result->add_data(['status' => 400]);
                return $result;
            }
        }

        // The new siblings in their current order, with the moved category inserted at `index`.
        $siblings = get_terms(['taxonomy' => CategoryTaxonomy::NAME, 'hide_empty' => false, 'parent' => $parent]);
        $siblings = is_array($siblings) ? array_values(array_filter($siblings, static fn (\WP_Term $t): bool => $t->term_id !== $id)) : [];
        usort($siblings, static fn (\WP_Term $a, \WP_Term $b): int => [(int) get_term_meta($a->term_id, CategoryTaxonomy::META_ORDER, true), $a->name] <=> [(int) get_term_meta($b->term_id, CategoryTaxonomy::META_ORDER, true), $b->name]);
        $ids = array_map(static fn (\WP_Term $t): int => $t->term_id, $siblings);
        array_splice($ids, min((int) $request['index'], count($ids)), 0, [$id]);

        foreach ($ids as $position => $siblingId) {
            update_term_meta($siblingId, CategoryTaxonomy::META_ORDER, $position + 1);
        }
        return $this->index();
    }

    /**
     * Sets a category's ordering mode and/or its manual document order. Saving an order
     * switches the category to manual. Only documents that are in the category are kept.
     */
    public function order(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request['id'];
        if (!term_exists($id, CategoryTaxonomy::NAME)) {
            return new \WP_Error('crnd_not_found', __('The category does not exist.', 'crnube-docs'), ['status' => 404]);
        }

        if ($request->has_param('ids')) {
            $inCategory = get_objects_in_term($id, CategoryTaxonomy::NAME);
            $inCategory = is_array($inCategory) ? array_map('intval', $inCategory) : [];
            $ids        = array_values(array_intersect(array_map('intval', (array) $request['ids']), $inCategory));
            CategoryOrder::saveManualOrder($id, $ids);
        }
        if ($request->has_param('sort')) {
            update_term_meta($id, CategoryOrder::META_SORT, (string) $request['sort']);
        }
        return $this->index();
    }

    /** Sets the order of sibling categories to the order of the given IDs. */
    public function reorder(\WP_REST_Request $request): \WP_REST_Response
    {
        foreach (array_values(array_map('intval', (array) $request['ids'])) as $position => $id) {
            if (term_exists($id, CategoryTaxonomy::NAME)) {
                update_term_meta($id, CategoryTaxonomy::META_ORDER, $position + 1);
            }
        }
        return $this->index();
    }

    /** Documents in a deleted category stay; they move to its parent category, if it has one. */
    public function delete(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id   = (int) $request['id'];
        $term = get_term($id, CategoryTaxonomy::NAME);
        if (!$term instanceof \WP_Term) {
            return new \WP_Error('crnd_not_found', __('The category does not exist.', 'crnube-docs'), ['status' => 404]);
        }

        if ($term->parent > 0) {
            $documents = get_objects_in_term($id, CategoryTaxonomy::NAME);
            foreach (is_array($documents) ? $documents : [] as $documentId) {
                wp_set_object_terms((int) $documentId, [$term->parent], CategoryTaxonomy::NAME, true);
            }
        }

        $result = wp_delete_term($id, CategoryTaxonomy::NAME);
        if ($result instanceof \WP_Error) {
            $result->add_data(['status' => 400]);
            return $result;
        }
        return $this->index();
    }

    /** Whether $termId is one of the subcategories (at any depth) of $ancestorId. */
    private function isInBranch(int $termId, int $ancestorId): bool
    {
        $children = get_term_children($ancestorId, CategoryTaxonomy::NAME);
        return is_array($children) && in_array($termId, array_map('intval', $children), true);
    }

    private function nextOrder(int $parent): int
    {
        $siblings = get_terms(['taxonomy' => CategoryTaxonomy::NAME, 'hide_empty' => false, 'parent' => $parent, 'fields' => 'ids']);
        $max      = 0;
        foreach (is_array($siblings) ? $siblings : [] as $siblingId) {
            $max = max($max, (int) get_term_meta((int) $siblingId, CategoryTaxonomy::META_ORDER, true));
        }
        return $max + 1;
    }

    /**
     * Documents per category (direct, any status but trash), plus the total and the ones
     * without a category, in two queries.
     *
     * @return array<int|string, int>
     */
    private function counts(): array
    {
        global $wpdb;

        $statuses = "'publish','draft','future','private','pending'";

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed status list, values prepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT tt.term_id, COUNT(DISTINCT p.ID) AS n
             FROM {$wpdb->term_taxonomy} tt
             JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
             JOIN {$wpdb->posts} p ON p.ID = tr.object_id
             WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status IN ($statuses)
             GROUP BY tt.term_id",
            CategoryTaxonomy::NAME,
            DocumentPostType::NAME
        ));

        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(NOT EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr
                        JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                        WHERE tr.object_id = p.ID AND tt.taxonomy = %s
                    )) AS uncategorized
             FROM {$wpdb->posts} p
             WHERE p.post_type = %s AND p.post_status IN ($statuses)",
            CategoryTaxonomy::NAME,
            DocumentPostType::NAME
        ));
        // phpcs:enable

        $counts = [];
        foreach ((array) $rows as $row) {
            $counts[(int) $row->term_id] = (int) $row->n;
        }
        $counts['total']         = (int) ($totals->total ?? 0);
        $counts['uncategorized'] = (int) ($totals->uncategorized ?? 0);
        return $counts;
    }
}
