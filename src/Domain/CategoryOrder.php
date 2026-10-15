<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

/**
 * How the documents of each category are ordered. Every category has its own mode:
 * inherited from the library settings (empty), by date, by title, or manual — the
 * order set by dragging documents in the admin library, stored as a list of IDs.
 *
 * The same rules order the admin list and the public site, so what is arranged in the
 * library is what visitors see.
 */
final class CategoryOrder
{
    public const META_SORT      = 'crnd_sort';
    public const META_DOC_ORDER = 'crnd_doc_order';

    public const MODES = ['', 'date_desc', 'date_asc', 'title_asc', 'title_desc', 'manual'];

    public static function mode(int $termId): string
    {
        $mode = (string) get_term_meta($termId, self::META_SORT, true);
        return in_array($mode, self::MODES, true) ? $mode : '';
    }

    /** @return list<int> Document IDs in the order they were arranged by hand. */
    public static function manualOrder(int $termId): array
    {
        $ids = get_term_meta($termId, self::META_DOC_ORDER, true);
        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    /**
     * @param list<int> $ids
     */
    public static function saveManualOrder(int $termId, array $ids): void
    {
        update_term_meta($termId, self::META_DOC_ORDER, array_values(array_unique(array_map('intval', $ids))));
        update_term_meta($termId, self::META_SORT, 'manual');
    }

    /**
     * Orders items by a category's mode. Items are anything with an id, a title and a
     * timestamp, read through the three callbacks, so documents and posts share the rules.
     * Documents missing from a manual order (added after it was set) go first, newest first,
     * so a new upload is visible right away.
     *
     * @template T
     * @param list<T>            $items
     * @param callable(T): int    $id
     * @param callable(T): string $title
     * @param callable(T): int    $time
     * @param string              $fallbackBy    'date' | 'title' | 'menu_order', used when the category inherits.
     * @param string              $fallbackOrder 'ASC' | 'DESC'
     * @return list<T>
     */
    public static function sort(array $items, int $termId, callable $id, callable $title, callable $time, string $fallbackBy = 'date', string $fallbackOrder = 'DESC'): array
    {
        $mode = $termId > 0 ? self::mode($termId) : '';
        if ($mode === '') {
            $mode = match ($fallbackBy) {
                'title'      => $fallbackOrder === 'ASC' ? 'title_asc' : 'title_desc',
                'menu_order' => 'manual',
                default      => $fallbackOrder === 'ASC' ? 'date_asc' : 'date_desc',
            };
        }

        $byDate  = static fn ($a, $b): int => [$time($b), $id($b)] <=> [$time($a), $id($a)];
        $byTitle = static fn ($a, $b): int => strnatcasecmp(remove_accents($title($a)), remove_accents($title($b)));

        $compare = match ($mode) {
            'date_asc'   => static fn ($a, $b): int => -$byDate($a, $b),
            'title_asc'  => $byTitle,
            'title_desc' => static fn ($a, $b): int => -$byTitle($a, $b),
            'manual'     => (static function () use ($termId, $id, $byDate): \Closure {
                $position = array_flip(self::manualOrder($termId));
                return static function ($a, $b) use ($position, $id, $byDate): int {
                    $pa = $position[$id($a)] ?? -1;
                    $pb = $position[$id($b)] ?? -1;
                    // Unplaced (new) documents first, newest first; then the manual order.
                    if ($pa === -1 || $pb === -1) {
                        return $pa === $pb ? $byDate($a, $b) : ($pa === -1 ? -1 : 1);
                    }
                    return $pa <=> $pb;
                };
            })(),
            default => $byDate,
        };

        usort($items, $compare);
        return $items;
    }

    /**
     * @param list<Document> $documents
     * @return list<Document>
     */
    public static function sortDocuments(array $documents, int $termId, string $fallbackBy, string $fallbackOrder): array
    {
        return self::sort(
            $documents,
            $termId,
            static fn (Document $d): int => $d->id,
            static fn (Document $d): string => $d->title,
            static fn (Document $d): int => $d->timestamp,
            $fallbackBy,
            $fallbackOrder
        );
    }
}
