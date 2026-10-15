<?php
declare(strict_types=1);

namespace CRNube\Docs\Migration;

use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Domain\DocumentPostType;
use CRNube\Docs\Domain\FileTypes;
use CRNube\Docs\Domain\YearTaxonomy;
use CRNube\Docs\Support\Cache;
use CRNube\Docs\Support\Filename;

/**
 * Imports FIVO Docs (and any plugin that files media in a category taxonomy) into
 * CRNube Docs.
 *
 * Nothing is copied or changed on the FIVO side: each new document points to the same
 * media file, so the old pages keep working until their shortcodes are replaced.
 * Running it again is safe: categories and documents already imported are reused
 * (they remember their origin in `_crnd_fivo_term` / `_crnd_fivo_attachment`).
 */
final class FivoImporter
{
    public const TERM_ORIGIN = '_crnd_fivo_term';
    public const POST_ORIGIN = '_crnd_fivo_attachment';

    /** Names of subcategories that only exist because FIVO needed a second level. */
    private const GENERIC_NAMES = ['ver aqui', 'ver', 'ver mas', 'documentos', 'archivos', 'descargar', 'descargas', 'aqui', 'documents', 'files', 'view', 'click aqui'];

    /**
     * Taxonomies that classify media files, best guess first.
     *
     * @return list<array{name: string, label: string, terms: int, attachments: int, fivo: bool}>
     */
    public static function sources(): array
    {
        $found = [];
        foreach (get_object_taxonomies('attachment', 'objects') as $taxonomy) {
            if (in_array($taxonomy->name, [CategoryTaxonomy::NAME, YearTaxonomy::NAME], true)) {
                continue;
            }
            $terms = wp_count_terms(['taxonomy' => $taxonomy->name, 'hide_empty' => false]);
            $found[] = [
                'name'        => $taxonomy->name,
                'label'       => (string) $taxonomy->label,
                'terms'       => is_numeric($terms) ? (int) $terms : 0,
                'attachments' => count(self::attachmentIds($taxonomy->name)),
                'fivo'        => str_contains($taxonomy->name, 'fivo') || str_contains(strtolower((string) $taxonomy->label), 'fivo'),
            ];
        }
        usort($found, static fn (array $a, array $b): int => [$b['fivo'], $b['attachments']] <=> [$a['fivo'], $a['attachments']]);
        return $found;
    }

    /**
     * The source categories as they will be created, with the generic levels already
     * skipped when asked, so the admin can review before importing.
     *
     * @return list<array{id: int, name: string, depth: int, attachments: int, skipped: bool, imported: bool}>
     */
    public static function preview(string $taxonomy, bool $skipGeneric): array
    {
        $terms = self::terms($taxonomy);
        $rows  = [];
        $walk  = function (int $parent, int $depth) use (&$walk, &$rows, $terms, $skipGeneric): void {
            foreach ($terms['children'][$parent] ?? [] as $term) {
                $skip = $skipGeneric && self::isGenericLevel($term, $terms);
                $rows[] = [
                    'id'          => $term->term_id,
                    'name'        => $term->name,
                    'depth'       => $depth,
                    'attachments' => (int) $term->count,
                    'skipped'     => $skip,
                    'imported'    => self::importedCategory($term->term_id) > 0,
                ];
                $walk($term->term_id, $skip ? $depth : $depth + 1);
            }
        };
        $walk(0, 0);
        return $rows;
    }

    /**
     * Creates (or finds) every category. Returns source term ID => CRNube Docs term ID.
     *
     * @return array<int, int>
     */
    public function importCategories(string $taxonomy, bool $skipGeneric): array
    {
        $terms = self::terms($taxonomy);
        $map   = [];
        $order = 0;
        $walk  = function (int $sourceParent, int $targetParent) use (&$walk, &$map, &$order, $terms, $skipGeneric): void {
            foreach ($terms['children'][$sourceParent] ?? [] as $term) {
                if ($skipGeneric && self::isGenericLevel($term, $terms)) {
                    // Its documents go to the parent category; its children hang from the parent too.
                    $map[$term->term_id] = $targetParent;
                    $walk($term->term_id, $targetParent);
                    continue;
                }
                $id = self::importedCategory($term->term_id);
                if ($id === 0) {
                    $created = wp_insert_term($term->name, CategoryTaxonomy::NAME, [
                        'parent'      => $targetParent,
                        'description' => $term->description,
                        'slug'        => $term->slug,
                    ]);
                    if (is_wp_error($created)) {
                        // Same name under the same parent: reuse it.
                        $existing = $created->get_error_data('term_exists');
                        $id       = is_numeric($existing) ? (int) $existing : 0;
                    } else {
                        $id = (int) $created['term_id'];
                    }
                    if ($id > 0) {
                        update_term_meta($id, self::TERM_ORIGIN, $term->term_id);
                        update_term_meta($id, CategoryTaxonomy::META_ORDER, ++$order);
                    }
                }
                $map[$term->term_id] = $id;
                if ($id > 0) {
                    $walk($term->term_id, $id);
                }
            }
        };
        $walk(0, 0);
        update_option('crnd_fivo_map', $map, false);
        Cache::flush();
        return $map;
    }

    /**
     * Imports one batch of media files as documents.
     *
     * @return array{done: int, created: int, updated: int, skipped: int, total: int, next: int}
     */
    public function importDocuments(string $taxonomy, int $offset, int $limit, bool $cleanTitles): array
    {
        $map = get_option('crnd_fivo_map', []);
        $map = is_array($map) ? $map : [];
        $ids = self::attachmentIds($taxonomy);
        $batch = array_slice($ids, $offset, $limit);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        foreach ($batch as $attachmentId) {
            $file = get_post($attachmentId);
            if (!$file instanceof \WP_Post || !FileTypes::isAllowedMime((string) $file->post_mime_type)) {
                $skipped++;
                continue;
            }

            $sourceTerms = wp_get_object_terms($attachmentId, $taxonomy, ['fields' => 'ids']);
            $categories  = array_values(array_unique(array_filter(array_map(
                static fn ($id): int => (int) ($map[(int) $id] ?? 0),
                is_array($sourceTerms) ? $sourceTerms : []
            ))));

            $existing = get_posts([
                'post_type'      => DocumentPostType::NAME,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'meta_key'       => self::POST_ORIGIN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'meta_value'     => (string) $attachmentId, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            ]);

            if ($existing !== []) {
                $postId = (int) $existing[0];
                // Keep categories added by hand; add the imported ones.
                wp_set_object_terms($postId, $categories, CategoryTaxonomy::NAME, true);
                $updated++;
                continue;
            }

            $name  = (string) $file->post_title !== '' ? (string) $file->post_title : wp_basename((string) get_attached_file($attachmentId));
            $title = $cleanTitles ? Filename::title($name) : $name;
            $year  = Filename::year($name) ?: Filename::year(wp_basename((string) get_attached_file($attachmentId)));

            $postId = wp_insert_post([
                'post_type'     => DocumentPostType::NAME,
                'post_status'   => 'publish',
                'post_title'    => $title,
                'post_excerpt'  => (string) $file->post_excerpt,
                'post_date'     => $file->post_date,
                'post_date_gmt' => $file->post_date_gmt,
                'menu_order'    => (int) $file->menu_order,
                'meta_input'    => [
                    DocumentPostType::META_FILE_ID => $attachmentId,
                    DocumentPostType::META_YEAR    => $year,
                    self::POST_ORIGIN              => $attachmentId,
                ],
            ], true);
            if (is_wp_error($postId)) {
                $skipped++;
                continue;
            }
            if ($categories !== []) {
                wp_set_object_terms((int) $postId, $categories, CategoryTaxonomy::NAME);
            }
            $created++;
        }

        Cache::flush();
        $next = $offset + count($batch);
        return [
            'done'    => $next,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total'   => count($ids),
            'next'    => $next < count($ids) ? $next : -1,
        ];
    }

    /** The CRNube Docs category created from a source term, or 0. */
    public static function importedCategory(int $sourceTermId): int
    {
        $found = get_terms([
            'taxonomy'   => CategoryTaxonomy::NAME,
            'hide_empty' => false,
            'fields'     => 'ids',
            'number'     => 1,
            'meta_key'   => self::TERM_ORIGIN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value' => (string) $sourceTermId, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);
        return is_array($found) && $found !== [] ? (int) $found[0] : 0;
    }

    /** @return list<int> Media files filed in any category of the taxonomy, oldest first. */
    public static function attachmentIds(string $taxonomy): array
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids']);
        if (!is_array($terms) || $terms === []) {
            return [];
        }
        return array_map('intval', get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => ['date' => 'ASC', 'ID' => 'ASC'],
            'tax_query'      => [[ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                'taxonomy' => $taxonomy,
                'terms'    => array_map('intval', $terms),
            ]],
        ]));
    }

    /**
     * @return array{byId: array<int, \WP_Term>, children: array<int, list<\WP_Term>>}
     */
    private static function terms(string $taxonomy): array
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name']);
        $byId  = [];
        $children = [];
        foreach (is_array($terms) ? $terms : [] as $term) {
            $byId[$term->term_id]       = $term;
            $children[$term->parent][]  = $term;
        }
        return ['byId' => $byId, 'children' => $children];
    }

    /**
     * A level worth skipping: a category's only subcategory, with a generic name like
     * "Ver Aquí" (FIVO's layout needs a second level, the content does not).
     *
     * @param array{byId: array<int, \WP_Term>, children: array<int, list<\WP_Term>>} $terms
     */
    private static function isGenericLevel(\WP_Term $term, array $terms): bool
    {
        if ($term->parent === 0) {
            return false;
        }
        $name = strtolower(remove_accents(trim($term->name)));
        $name = trim((string) preg_replace('/[^a-z ]/', '', $name));
        return in_array($name, self::GENERIC_NAMES, true);
    }
}
