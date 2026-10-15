<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

/**
 * Which part of the library to load and how to arrange it.
 */
final class TreeQuery
{
    public const ORDER_BY = ['date', 'title', 'menu_order'];

    /**
     * @param list<int> $rootIds Categories to start from. Empty: every top-level category.
     *                           With exactly one, its own documents go on top and its
     *                           subcategories become the groups.
     * @param int       $depth   Levels of groups to show; deeper documents move up to
     *                           the last level shown. 0 means no limit.
     */
    public function __construct(
        public readonly array $rootIds = [],
        public readonly string $orderBy = 'date',
        public readonly string $order = 'DESC',
        public readonly int $depth = 0,
        public readonly bool $groupByYear = false,
    ) {
    }

    public function cacheKey(): string
    {
        return md5((string) wp_json_encode([
            $this->rootIds,
            $this->orderBy,
            $this->order,
            $this->depth,
            $this->groupByYear,
        ]));
    }
}
