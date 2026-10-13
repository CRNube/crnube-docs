<?php
declare(strict_types=1);

namespace CRNube\Docs\Migration;

use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Frontend\Shortcode;

/**
 * Finds the pages that show FIVO Docs (shortcodes in the content or inside Elementor
 * widgets) and replaces them with the CRNube Docs shortcode.
 *
 * Every replacement keeps a backup of the original content and Elementor data, and can
 * be undone. The new shortcode is worked out from the IDs in the old one: category IDs
 * become the imported categories; a hand-picked list of files becomes a category of its
 * own ("Selección: <page>") so the page keeps showing exactly the same documents.
 */
final class FivoShortcodes
{
    /** Any shortcode whose tag starts with "fivo" ([fivo_docs], [fivo-docs], [fivodocs]...). */
    private const PATTERN = '/\[(fivo[a-z_-]*)(\s[^\]]*)?\]/i';

    private const BACKUP_META = '_crnd_fivo_backup';

    /**
     * @return list<array{id: int, title: string, edit: string, view: string, items: list<array{original: string, replacement: string}>, replaced: bool}>
     */
    public function scan(string $taxonomy): array
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like('[fivo') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off scan of the migration screen
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
             WHERE p.post_status IN ('publish','draft','private','future','pending')
               AND p.post_type NOT IN ('revision','attachment','nav_menu_item')
               AND (p.post_content LIKE %s OR m.meta_value LIKE %s OR p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s))
             ORDER BY p.post_title",
            $like,
            $like,
            self::BACKUP_META
        ));

        $pages = [];
        foreach (array_map('intval', (array) $ids) as $postId) {
            $post = get_post($postId);
            if (!$post instanceof \WP_Post) {
                continue;
            }
            $items = [];
            foreach ($this->shortcodesIn($postId) as $original) {
                $items[] = ['original' => $original, 'replacement' => $this->replacementFor($original, $taxonomy, $post, false)];
            }
            $pages[] = [
                'id'       => $postId,
                'title'    => get_the_title($post) ?: __('(no title)', 'crnube-docs'),
                'edit'     => (string) get_edit_post_link($postId, 'raw'),
                'view'     => (string) get_permalink($postId),
                'items'    => $items,
                'replaced' => (bool) get_post_meta($postId, self::BACKUP_META, true),
            ];
        }
        return $pages;
    }

    /** Swaps every FIVO shortcode of a page, keeping a backup. */
    public function replace(int $postId, string $taxonomy): bool
    {
        $post = get_post($postId);
        if (!$post instanceof \WP_Post) {
            return false;
        }
        $elementor = (string) get_post_meta($postId, '_elementor_data', true);
        if (!get_post_meta($postId, self::BACKUP_META, true)) {
            add_post_meta($postId, self::BACKUP_META, wp_slash([
                'content'   => $post->post_content,
                'elementor' => $elementor,
                'date'      => current_time('mysql'),
            ]), true);
        }

        $swap = fn (string $text): string => (string) preg_replace_callback(
            self::PATTERN,
            fn (array $m): string => $this->replacementFor($m[0], $taxonomy, $post, true),
            $text
        );

        if (str_contains($post->post_content, '[fivo')) {
            wp_update_post(['ID' => $postId, 'post_content' => wp_slash($swap($post->post_content))]);
        }
        if ($elementor !== '' && str_contains($elementor, '[fivo')) {
            $data = json_decode($elementor, true);
            if (is_array($data)) {
                array_walk_recursive($data, static function (&$value) use ($swap): void {
                    if (is_string($value) && str_contains($value, '[fivo')) {
                        $value = $swap($value);
                    }
                });
                update_post_meta($postId, '_elementor_data', wp_slash((string) wp_json_encode($data)));
                $this->clearElementorCache($postId);
            }
        }
        return true;
    }

    /** Puts the page back exactly as it was before the replacement. */
    public function undo(int $postId): bool
    {
        $backup = get_post_meta($postId, self::BACKUP_META, true);
        if (!is_array($backup)) {
            return false;
        }
        wp_update_post(['ID' => $postId, 'post_content' => wp_slash((string) $backup['content'])]);
        if ((string) $backup['elementor'] !== '') {
            update_post_meta($postId, '_elementor_data', wp_slash((string) $backup['elementor']));
            $this->clearElementorCache($postId);
        }
        delete_post_meta($postId, self::BACKUP_META);
        return true;
    }

    /**
     * The CRNube Docs shortcode equivalent to a FIVO one.
     */
    /**
     * @param bool $create False while previewing: a hand-picked list then shows the name
     *                     its category will get, without creating anything.
     */
    public function replacementFor(string $shortcode, string $taxonomy, \WP_Post $page, bool $create = false): string
    {
        $atts = shortcode_parse_atts(trim((string) preg_replace(self::PATTERN, '$2', $shortcode)));
        $atts = is_array($atts) ? $atts : [];

        // Every number in the attributes, then split into categories and files.
        $numbers = [];
        foreach ($atts as $key => $value) {
            // Layout options (columns="2", xs="1"...) are numbers too, but never IDs.
            if (is_int($key) || preg_match('/col|^(xs|sm|md|lg|xl)$|align|scroll|masonry|date|box|open|title|style|layout|size|limit|order/i', (string) $key)) {
                continue;
            }
            if (preg_match_all('/\d+/', (string) $value, $m)) {
                $numbers = [...$numbers, ...array_map('intval', $m[0])];
            }
        }
        $categories = [];
        $files      = [];
        foreach (array_unique($numbers) as $id) {
            if ($id <= 0) {
                continue;
            }
            $term = get_term($id, $taxonomy);
            if ($term instanceof \WP_Term) {
                $target = FivoImporter::importedCategory($id) ?: $this->mappedTarget($id);
                if ($target > 0) {
                    $categories[] = $target;
                }
            } elseif (get_post_type($id) === 'attachment') {
                $files[] = $id;
            }
        }

        $slugs = [];
        if ($files !== []) {
            $selection = $this->selectionCategory($files, $page, $create);
            if ($selection > 0) {
                $categories[] = $selection;
            } elseif ($selection === -1) {
                // The slug the category will get once created.
                /* translators: %s: page title */
                $slugs[] = sanitize_title(sprintf(__('Selection: %s', 'crnube-docs'), get_the_title($page)));
            }
        }
        if ($categories === [] && $slugs === []) {
            // No IDs: FIVO showed every category. Show every imported top category.
            $categories = $this->importedTopCategories();
        }

        foreach (array_unique($categories) as $termId) {
            $term = get_term($termId, CategoryTaxonomy::NAME);
            if ($term instanceof \WP_Term) {
                $slugs[] = $term->slug;
            }
        }

        $extra = '';
        foreach ($atts as $key => $value) {
            $key = strtolower((string) $key);
            if (str_contains($key, 'title') && !is_numeric($value) && trim((string) $value) !== '') {
                $extra .= ' title="' . esc_attr((string) $value) . '"';
            }
            if (str_contains($key, 'open') && in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true)) {
                $extra .= ' open="first"';
            }
        }

        return sprintf('[%s%s%s]', Shortcode::TAG, $slugs !== [] ? ' category="' . implode(',', $slugs) . '"' : '', $extra);
    }

    /** @return list<string> */
    private function shortcodesIn(int $postId): array
    {
        $post  = get_post($postId);
        $texts = [$post instanceof \WP_Post ? $post->post_content : '', (string) get_post_meta($postId, '_elementor_data', true)];
        $found = [];
        foreach ($texts as $text) {
            // Elementor stores its data as JSON: quotes inside shortcodes come escaped.
            $text = str_replace(['\\"', '\\/'], ['"', '/'], $text);
            if (preg_match_all(self::PATTERN, $text, $m)) {
                $found = [...$found, ...$m[0]];
            }
        }
        return array_values(array_unique($found));
    }

    /** A generic level skipped on import maps to its parent's category. */
    private function mappedTarget(int $sourceTermId): int
    {
        $map = get_option('crnd_fivo_map', []);
        return is_array($map) ? (int) ($map[$sourceTermId] ?? 0) : 0;
    }

    /**
     * A category holding exactly the files a FIVO "custom selection" showed, under
     * "FIVO selections". Reused if the same page and files come up again.
     *
     * @param list<int> $files
     */
    private function selectionCategory(array $files, \WP_Post $page, bool $create): int
    {
        $sorted = $files;
        sort($sorted);
        $key = md5($page->ID . ':' . implode(',', $sorted));

        $existing = get_terms([
            'taxonomy'   => CategoryTaxonomy::NAME,
            'hide_empty' => false,
            'fields'     => 'ids',
            'number'     => 1,
            'meta_key'   => '_crnd_fivo_selection', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);
        if (is_array($existing) && $existing !== []) {
            return (int) $existing[0];
        }
        if (!$create) {
            return -1; // Previewing: it will be created on replace.
        }

        $parent = term_exists('selecciones-fivo', CategoryTaxonomy::NAME);
        if (!$parent) {
            $parent = wp_insert_term(__('FIVO selections', 'crnube-docs'), CategoryTaxonomy::NAME, ['slug' => 'selecciones-fivo']);
        }
        $parentId = is_array($parent) ? (int) $parent['term_id'] : 0;

        /* translators: %s: page title */
        $term = wp_insert_term(sprintf(__('Selection: %s', 'crnube-docs'), get_the_title($page)), CategoryTaxonomy::NAME, ['parent' => $parentId]);
        if (is_wp_error($term)) {
            $existingId = $term->get_error_data('term_exists');
            return is_numeric($existingId) ? (int) $existingId : 0;
        }
        $termId = (int) $term['term_id'];
        update_term_meta($termId, '_crnd_fivo_selection', $key);

        // Put each selected file's document in it, in the order FIVO listed them.
        foreach (array_values($files) as $position => $fileId) {
            $docs = get_posts([
                'post_type'      => \CRNube\Docs\Domain\DocumentPostType::NAME,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'meta_key'       => FivoImporter::POST_ORIGIN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_value'     => (string) $fileId, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            ]);
            if ($docs !== []) {
                wp_set_object_terms((int) $docs[0], [$termId], CategoryTaxonomy::NAME, true);
            }
        }
        \CRNube\Docs\Domain\CategoryOrder::saveManualOrder($termId, array_map(function (int $fileId): int {
            $docs = get_posts(['post_type' => \CRNube\Docs\Domain\DocumentPostType::NAME, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => FivoImporter::POST_ORIGIN, 'meta_value' => (string) $fileId]); // phpcs:ignore WordPress.DB.SlowDBQuery
            return $docs !== [] ? (int) $docs[0] : 0;
        }, $files));
        return $termId;
    }

    /** @return list<int> */
    private function importedTopCategories(): array
    {
        $map = get_option('crnd_fivo_map', []);
        $ids = [];
        foreach (is_array($map) ? array_unique(array_map('intval', $map)) : [] as $termId) {
            $term = get_term($termId, CategoryTaxonomy::NAME);
            if ($term instanceof \WP_Term && $term->parent === 0) {
                $ids[] = $termId;
            }
        }
        return $ids;
    }

    private function clearElementorCache(int $postId): void
    {
        delete_post_meta($postId, '_elementor_css');
        delete_post_meta($postId, '_elementor_element_cache');
        if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }
}
