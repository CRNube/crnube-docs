<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

use CRNube\Docs\Support\Cache;

/**
 * Loads documents and arranges them into the category tree; each category's documents
 * follow its own ordering mode (CategoryOrder). A whole library is read
 * with one posts query plus one terms query, and the result is cached until any
 * document or category changes.
 */
final class DocumentRepository
{
    /** @var array<int, \WP_Term> */
    private array $terms = [];

    /** @var array<int, list<int>> parent term id => child term ids, in display order */
    private array $childrenOf = [];

    public function tree(TreeQuery $query): DocumentTree
    {
        $key    = 'crnd_tree_' . $query->cacheKey() . '_' . Cache::version();
        $cached = get_transient($key);
        if ($cached instanceof DocumentTree) {
            return $cached;
        }

        $tree = $this->buildTree($query);
        set_transient($key, $tree, 12 * HOUR_IN_SECONDS);
        return $tree;
    }

    /**
     * Resolves category slugs or IDs, as typed in a shortcode, to term IDs.
     *
     * @param list<string> $values
     * @return list<int>
     */
    public function resolveCategoryIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $term = ctype_digit($value)
                ? get_term((int) $value, CategoryTaxonomy::NAME)
                : get_term_by('slug', sanitize_title($value), CategoryTaxonomy::NAME);
            if ($term instanceof \WP_Term) {
                $ids[] = $term->term_id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function buildTree(TreeQuery $query): DocumentTree
    {
        $this->loadTerms();

        $roots = $query->rootIds !== []
            ? array_values(array_filter($query->rootIds, fn (int $id): bool => isset($this->terms[$id])))
            : ($this->childrenOf[0] ?? []);

        if ($roots === []) {
            return new DocumentTree([], [], [], 0);
        }

        $scope = [];
        foreach ($roots as $rootId) {
            $scope = [...$scope, $rootId, ...$this->descendants($rootId)];
        }
        $scope = array_values(array_unique($scope));

        // Documents per category, each list keeping the order of the query.
        $byTerm = [];
        $years  = [];
        $all    = [];
        foreach ($this->queryDocuments($scope, $query) as $position => $document) {
            $all[$document->id] = $position;
            $years[$document->year] = true;
            foreach ($document->categoryIds as $termId) {
                if (in_array($termId, $scope, true)) {
                    $byTerm[$termId][] = [$position, $document];
                }
            }
        }

        $single = count($roots) === 1 && $query->rootIds !== [];
        if ($single) {
            $rootId    = $roots[0];
            $documents = CategoryOrder::sortDocuments($this->sorted($byTerm[$rootId] ?? []), $rootId, $query->orderBy, $query->order);
            $groups    = $this->buildChildren($rootId, 1, $byTerm, $query);
        } else {
            $documents = [];
            $groups    = array_values(array_filter(array_map(
                fn (int $id): ?CategoryNode => $this->buildNode($id, 1, $byTerm, $query),
                $roots
            )));
        }

        $years = array_map('strval', array_keys($years));
        rsort($years);

        return new DocumentTree($documents, $groups, $years, count($all));
    }

    /**
     * @param array<int, list<array{int, Document}>> $byTerm
     */
    private function buildNode(int $termId, int $level, array $byTerm, TreeQuery $query): ?CategoryNode
    {
        $term    = $this->terms[$termId];
        $entries = $byTerm[$termId] ?? [];
        $atLimit = $query->depth > 0 && $level >= $query->depth;

        if ($atLimit) {
            // Documents below the last level shown are listed in it instead.
            foreach ($this->descendants($termId) as $childId) {
                $entries = [...$entries, ...($byTerm[$childId] ?? [])];
            }
            $children = [];
        } else {
            $children = $this->buildChildren($termId, $level + 1, $byTerm, $query);
        }

        $documents = CategoryOrder::sortDocuments($this->sorted($entries), $termId, $query->orderBy, $query->order);
        $ids       = array_map(static fn (Document $d): int => $d->id, $documents);
        foreach ($children as $child) {
            $ids = [...$ids, ...$this->documentIds($child)];
        }
        $count = count(array_unique($ids));

        if ($count === 0) {
            return null;
        }

        if ($query->groupByYear && $documents !== []) {
            $children  = [...$this->yearGroups($termId, $documents), ...$children];
            $documents = [];
        }

        return new CategoryNode(
            termId: $termId,
            name: $term->name,
            slug: $term->slug,
            description: $term->description,
            isYearGroup: false,
            documents: $documents,
            children: $children,
            count: $count,
        );
    }

    /**
     * @param array<int, list<array{int, Document}>> $byTerm
     * @return list<CategoryNode>
     */
    private function buildChildren(int $termId, int $level, array $byTerm, TreeQuery $query): array
    {
        $children = [];
        foreach ($this->childrenOf[$termId] ?? [] as $childId) {
            $node = $this->buildNode($childId, $level, $byTerm, $query);
            if ($node !== null) {
                $children[] = $node;
            }
        }
        return $children;
    }

    /**
     * @param list<Document> $documents
     * @return list<CategoryNode>
     */
    private function yearGroups(int $termId, array $documents): array
    {
        $byYear = [];
        foreach ($documents as $document) {
            $byYear[$document->year][] = $document;
        }
        krsort($byYear);

        $groups = [];
        foreach ($byYear as $year => $yearDocuments) {
            $groups[] = new CategoryNode(
                termId: $termId,
                name: (string) $year,
                slug: 'year-' . $year,
                description: '',
                isYearGroup: true,
                documents: $yearDocuments,
                children: [],
                count: count($yearDocuments),
            );
        }
        return $groups;
    }

    /** @return list<int> */
    private function documentIds(CategoryNode $node): array
    {
        $ids = array_map(static fn (Document $d): int => $d->id, $node->documents);
        foreach ($node->children as $child) {
            $ids = [...$ids, ...$this->documentIds($child)];
        }
        return $ids;
    }

    /**
     * Restores the query order and drops repeats (a document can sit in a category and
     * in one of its subcategories at the same time).
     *
     * @param list<array{int, Document}> $entries
     * @return list<Document>
     */
    private function sorted(array $entries): array
    {
        $unique = [];
        foreach ($entries as [$position, $document]) {
            $unique[$position] = $document;
        }
        ksort($unique);
        return array_values($unique);
    }

    /**
     * @param list<int> $termIds
     * @return list<Document>
     */
    private function queryDocuments(array $termIds, TreeQuery $query): array
    {
        $order   = $query->order === 'ASC' ? 'ASC' : 'DESC';
        $orderBy = match ($query->orderBy) {
            'title'      => ['title' => $order, 'date' => 'DESC'],
            'menu_order' => ['menu_order' => $order, 'date' => 'DESC'],
            default      => ['date' => $order, 'ID' => $order],
        };

        $posts = (new \WP_Query([
            'post_type'           => DocumentPostType::NAME,
            'post_status'         => 'publish',
            /** Upper bound for one library; very large ones will page through the REST API. */
            'posts_per_page'      => (int) apply_filters('crnd_tree_limit', 2000),
            'orderby'             => $orderBy,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'tax_query'           => [[ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                'taxonomy'         => CategoryTaxonomy::NAME,
                'field'            => 'term_id',
                'terms'            => $termIds,
                'include_children' => false,
            ]],
        ]))->posts;

        // Read every attached file's post and metadata in two queries instead of two per document.
        $fileIds = [];
        foreach ($posts as $post) {
            $fileId = (int) get_post_meta($post->ID, DocumentPostType::META_FILE_ID, true);
            if ($fileId > 0) {
                $fileIds[] = $fileId;
            }
        }
        if ($fileIds !== []) {
            _prime_post_caches($fileIds, false, true);
        }

        return array_map([Document::class, 'fromPost'], $posts);
    }

    private function loadTerms(): void
    {
        $terms = get_terms([
            'taxonomy'   => CategoryTaxonomy::NAME,
            'hide_empty' => false,
        ]);
        $this->terms      = [];
        $this->childrenOf = [];
        if (!is_array($terms)) {
            return;
        }

        foreach ($terms as $term) {
            $this->terms[$term->term_id] = $term;
        }

        $order = static fn (\WP_Term $t): int => (int) get_term_meta($t->term_id, CategoryTaxonomy::META_ORDER, true);
        $sorted = $terms;
        usort($sorted, static fn (\WP_Term $a, \WP_Term $b): int => [$order($a), $a->name] <=> [$order($b), $b->name]);

        foreach ($sorted as $term) {
            $this->childrenOf[$term->parent][] = $term->term_id;
        }
    }

    /** @return list<int> */
    private function descendants(int $termId): array
    {
        $ids = [];
        foreach ($this->childrenOf[$termId] ?? [] as $childId) {
            $ids = [...$ids, $childId, ...$this->descendants($childId)];
        }
        return $ids;
    }
}
