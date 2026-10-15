<?php
declare(strict_types=1);

namespace CRNube\Docs\Frontend;

use CRNube\Docs\Domain\CategoryNode;
use CRNube\Docs\Domain\Document;
use CRNube\Docs\Domain\DocumentTree;

/**
 * Removes documents from a tree (for example the ones the visitor may not open) and
 * drops the groups left empty, recounting what remains. The cached tree is never
 * changed: this builds a new one for the current visitor.
 */
final class TreeFilter
{
    /**
     * @param callable(Document): bool $keep
     */
    public static function filter(DocumentTree $tree, callable $keep): DocumentTree
    {
        $documents = array_values(array_filter($tree->documents, $keep));
        $groups    = [];
        foreach ($tree->groups as $group) {
            $node = self::node($group, $keep);
            if ($node !== null) {
                $groups[] = $node;
            }
        }

        $all = [];
        foreach ($documents as $document) {
            $all[$document->id] = $document;
        }
        foreach ($groups as $group) {
            foreach (self::documents($group) as $document) {
                $all[$document->id] = $document;
            }
        }
        $years = array_values(array_unique(array_map(static fn (Document $d): string => $d->year, $all)));
        rsort($years);

        return new DocumentTree($documents, $groups, $years, count($all));
    }

    /** @param callable(Document): bool $keep */
    private static function node(CategoryNode $node, callable $keep): ?CategoryNode
    {
        $documents = array_values(array_filter($node->documents, $keep));
        $children  = [];
        foreach ($node->children as $child) {
            $kept = self::node($child, $keep);
            if ($kept !== null) {
                $children[] = $kept;
            }
        }
        if ($documents === [] && $children === []) {
            return null;
        }

        $ids = array_map(static fn (Document $d): int => $d->id, $documents);
        foreach ($children as $child) {
            $ids = [...$ids, ...array_map(static fn (Document $d): int => $d->id, self::documents($child))];
        }

        return new CategoryNode(
            termId: $node->termId,
            name: $node->name,
            slug: $node->slug,
            description: $node->description,
            isYearGroup: $node->isYearGroup,
            documents: $documents,
            children: $children,
            count: count(array_unique($ids)),
        );
    }

    /** @return list<Document> */
    private static function documents(CategoryNode $node): array
    {
        $documents = $node->documents;
        foreach ($node->children as $child) {
            $documents = [...$documents, ...self::documents($child)];
        }
        return $documents;
    }
}
