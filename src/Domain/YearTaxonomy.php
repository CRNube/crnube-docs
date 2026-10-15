<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

/**
 * The year of each document (its own year, or else its publication year), kept in sync
 * on every save. Being a taxonomy
 * (not a meta value) makes filtering by year an indexed query.
 */
final class YearTaxonomy
{
    public const NAME = 'crnd_year';

    public function register(): void
    {
        add_action('init', [$this, 'registerTaxonomy']);
        add_action('save_post_' . DocumentPostType::NAME, [$this, 'syncYear'], 20, 2);
    }

    public function registerTaxonomy(): void
    {
        register_taxonomy(self::NAME, DocumentPostType::NAME, [
            'labels' => [
                'name'          => __('Years', 'crnube-docs'),
                'singular_name' => __('Year', 'crnube-docs'),
            ],
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'meta_box_cb'       => false, // Assigned automatically, never by hand.
            'rewrite'           => false,
            'query_var'         => false,
            'capabilities'      => [
                'manage_terms' => 'do_not_allow',
                'edit_terms'   => 'do_not_allow',
                'delete_terms' => 'do_not_allow',
                'assign_terms' => 'edit_crnd_documents',
            ],
        ]);
    }

    public function syncYear(int $postId, \WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || $post->post_status === 'auto-draft') {
            return;
        }
        wp_set_object_terms($postId, DocumentPostType::yearOf($post), self::NAME, false);
    }
}
