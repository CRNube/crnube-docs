<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

/**
 * One level of the document tree: a category (or a year group inside one) with its
 * documents and the levels below it.
 */
final class CategoryNode
{
    /**
     * @param list<Document>     $documents
     * @param list<CategoryNode> $children
     */
    public function __construct(
        public readonly int $termId,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $description,
        public readonly bool $isYearGroup,
        public readonly array $documents,
        public readonly array $children,
        public readonly int $count,
    ) {
    }
}
