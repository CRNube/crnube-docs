<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

use CRNube\Docs\Capabilities;

/**
 * Hierarchical categories that work exactly like WordPress categories (same screen,
 * parents and children, unlimited levels) but stay separate from the blog's categories.
 */
final class CategoryTaxonomy
{
    public const NAME       = 'crnd_category';
    public const META_ORDER = 'crnd_order';

    public function register(): void
    {
        add_action('init', [$this, 'registerTaxonomy']);
    }

    public function registerTaxonomy(): void
    {
        register_taxonomy(self::NAME, DocumentPostType::NAME, [
            'labels' => [
                'name'              => __('Document categories', 'crnube-docs'),
                'singular_name'     => __('Document category', 'crnube-docs'),
                'menu_name'         => __('Categories', 'crnube-docs'),
                'all_items'         => __('All categories', 'crnube-docs'),
                'edit_item'         => __('Edit category', 'crnube-docs'),
                'view_item'         => __('View category', 'crnube-docs'),
                'update_item'       => __('Update category', 'crnube-docs'),
                'add_new_item'      => __('Add new category', 'crnube-docs'),
                'new_item_name'     => __('New category name', 'crnube-docs'),
                'parent_item'       => __('Parent category', 'crnube-docs'),
                'parent_item_colon' => __('Parent category:', 'crnube-docs'),
                'search_items'      => __('Search categories', 'crnube-docs'),
                'not_found'         => __('No categories found.', 'crnube-docs'),
                'back_to_items'     => __('&larr; Back to categories', 'crnube-docs'),
            ],
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'show_tagcloud'     => false,
            'rewrite'           => false,
            'query_var'         => false,
            'capabilities'      => [
                'manage_terms' => Capabilities::MANAGE_CATEGORIES,
                'edit_terms'   => Capabilities::MANAGE_CATEGORIES,
                'delete_terms' => Capabilities::MANAGE_CATEGORIES,
                'assign_terms' => 'edit_crnd_documents',
            ],
        ]);

        register_term_meta(self::NAME, self::META_ORDER, [
            'type'              => 'integer',
            'single'            => true,
            'default'           => 0,
            // A closure, not 'intval': meta sanitizers get four arguments and intval() accepts two.
            'sanitize_callback' => static fn (mixed $value): int => (int) $value,
            'show_in_rest'      => true,
        ]);

        register_term_meta(self::NAME, CategoryOrder::META_SORT, [
            'type'              => 'string',
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => static fn (mixed $value): string => in_array($value, CategoryOrder::MODES, true) ? (string) $value : '',
            'show_in_rest'      => true,
        ]);
    }
}
