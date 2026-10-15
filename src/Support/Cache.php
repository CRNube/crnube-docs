<?php
declare(strict_types=1);

namespace CRNube\Docs\Support;

use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Domain\DocumentPostType;

/**
 * One version number for every cached library. Any change to a document, a category or
 * an attached file bumps it, so stale entries are never read again and simply expire.
 *
 * An option is used instead of wp_cache_get_last_changed() because most of the sites
 * have no persistent object cache, where that value would reset on every request.
 */
final class Cache
{
    private const OPTION = 'crnd_cache_version';

    public static function version(): string
    {
        return CRND_VERSION . '.' . (int) get_option(self::OPTION, 0);
    }

    public static function flush(): void
    {
        update_option(self::OPTION, (int) get_option(self::OPTION, 0) + 1, true);
    }

    public function register(): void
    {
        add_action('save_post_' . DocumentPostType::NAME, [self::class, 'flush']);
        add_action('deleted_post', [$this, 'onPostChange'], 10, 2);
        add_action('trashed_post', [$this, 'onPostChange']);
        add_action('untrashed_post', [$this, 'onPostChange']);
        add_action('edit_attachment', [self::class, 'flush']);
        add_action('delete_attachment', [self::class, 'flush']);
        add_action('set_object_terms', [$this, 'onTermsSet'], 10, 4);
        foreach (['created_', 'edited_', 'delete_'] as $hook) {
            add_action($hook . CategoryTaxonomy::NAME, [self::class, 'flush']);
        }
        add_action('updated_term_meta', [$this, 'onTermMeta'], 10, 2);
        add_action('added_term_meta', [$this, 'onTermMeta'], 10, 2);
    }

    public function onPostChange(int $postId, ?\WP_Post $post = null): void
    {
        if (get_post_type($post ?? $postId) === DocumentPostType::NAME) {
            self::flush();
        }
    }

    /**
     * @param array<int|string> $terms
     * @param array<int>        $ttIds
     */
    public function onTermsSet(int $objectId, array $terms, array $ttIds, string $taxonomy): void
    {
        if ($taxonomy === CategoryTaxonomy::NAME) {
            self::flush();
        }
    }

    public function onTermMeta(int $metaId, int $termId): void
    {
        $term = get_term($termId);
        if ($term instanceof \WP_Term && $term->taxonomy === CategoryTaxonomy::NAME) {
            self::flush();
        }
    }
}
