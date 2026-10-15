<?php
declare(strict_types=1);

namespace CRNube\Docs\Domain;

use CRNube\Docs\Access\DocumentAccess;

/**
 * A published document, read once from the database and then handed to the templates.
 * Plain values only, so it can be cached as is.
 */
final class Document
{
    /**
     * @param list<int> $categoryIds
     * @param list<int> $groupIds    Groups allowed when restricted; empty means any member.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $excerpt,
        public readonly int $timestamp,
        public readonly string $year,
        public readonly int $fileId,
        public readonly string $externalUrl,
        public readonly string $extension,
        public readonly ?int $size,
        public readonly array $categoryIds,
        public readonly bool $restricted = false,
        public readonly array $groupIds = [],
    ) {
    }

    public static function fromPost(\WP_Post $post): self
    {
        $fileId    = (int) get_post_meta($post->ID, DocumentPostType::META_FILE_ID, true);
        $external  = (string) get_post_meta($post->ID, DocumentPostType::META_EXTERNAL, true);
        $extension = '';
        $size      = null;

        if ($fileId > 0) {
            $file      = (string) get_attached_file($fileId);
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $meta      = wp_get_attachment_metadata($fileId);
            $size      = is_array($meta) && !empty($meta['filesize']) ? (int) $meta['filesize'] : null;
            if ($size === null && $file !== '' && is_readable($file)) {
                $size = (int) filesize($file);
            }
        } elseif ($external !== '') {
            $path      = (string) wp_parse_url($external, PHP_URL_PATH);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        }

        $terms       = get_the_terms($post, CategoryTaxonomy::NAME);
        $categoryIds = is_array($terms) ? array_map(static fn (\WP_Term $t): int => $t->term_id, $terms) : [];
        $access      = DocumentAccess::resolve($post->ID, $categoryIds);

        return new self(
            id: $post->ID,
            title: get_the_title($post),
            excerpt: $post->post_excerpt,
            timestamp: (int) get_post_timestamp($post),
            year: DocumentPostType::yearOf($post),
            fileId: $fileId,
            externalUrl: $external,
            extension: $extension,
            size: $size,
            categoryIds: $categoryIds,
            restricted: $access['restricted'],
            groupIds: $access['groups'],
        );
    }

    public function hasFile(): bool
    {
        return $this->fileId > 0 || $this->externalUrl !== '';
    }

    public function isExternal(): bool
    {
        return $this->fileId === 0 && $this->externalUrl !== '';
    }

    public function kind(): string
    {
        return FileTypes::kind($this->extension);
    }

    public function isViewable(): bool
    {
        return $this->isExternal() || FileTypes::isViewable($this->extension);
    }
}
