<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

/**
 * What one library on the page shows: the documents placed directly at its top level,
 * the category groups, and the years available for the filter.
 */
final class DocumentTree
{
    /**
     * @param list<Document>     $documents
     * @param list<CategoryNode> $groups
     * @param list<string>       $years
     */
    public function __construct(
        public readonly array $documents,
        public readonly array $groups,
        public readonly array $years,
        public readonly int $total,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }
}
