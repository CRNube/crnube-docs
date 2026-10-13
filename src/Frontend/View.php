<?php
declare(strict_types=1);

namespace CRNube\Docs\Frontend;

use CRNube\Docs\Domain\CategoryNode;
use CRNube\Docs\Domain\Document;
use CRNube\Docs\Domain\DocumentTree;
use CRNube\Docs\Domain\FileTypes;
use CRNube\Docs\Download\DownloadController;
use CRNube\Docs\Settings\Settings;

/**
 * What the templates get as `$view`: the tree plus small helpers, so the templates
 * hold markup only. Methods ending in Html return escaped markup; the rest return plain
 * values the template must escape.
 */
final class View
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly DocumentTree $tree,
        private readonly array $options,
        private readonly Settings $settings,
        public readonly string $id,
    ) {
    }

    public function option(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }

    public function label(string $name): string
    {
        return $this->settings->label($name);
    }

    public function hasToolbar(): bool
    {
        return !$this->tree->isEmpty()
            && ($this->options['search'] || ($this->options['year_filter'] && count($this->tree->years) > 1) || ($this->options['toggle_all'] && $this->tree->groups !== []));
    }

    public function wrapperClass(): string
    {
        return implode(' ', array_filter([
            'crnd',
            'crnd--' . $this->options['layout'],
            'crnd--header-' . $this->options['header_style'],
            'crnd--' . $this->options['density'],
            $this->options['class'],
        ]));
    }

    /** Per-library color overrides from the shortcode, as an inline style value. */
    public function wrapperStyle(): string
    {
        return Assets::cssVariables($this->options['style_overrides']);
    }

    public function isOpen(int $level, int $index): bool
    {
        return match ($this->options['open']) {
            'all'   => true,
            'first' => $level === 1 && $index === 0,
            default => false,
        };
    }

    public function groupId(CategoryNode $node): string
    {
        return $this->id . '-' . ($node->isYearGroup ? $node->termId . '-' . $node->slug : $node->slug);
    }

    public function downloadUrl(Document $document): string
    {
        return DownloadController::url($document->id, 'download');
    }

    public function viewUrl(Document $document): string
    {
        return DownloadController::url($document->id, 'view');
    }

    public function showViewButton(Document $document): bool
    {
        return $document->hasFile() && $document->isViewable();
    }

    public function showDownloadButton(Document $document): bool
    {
        return $document->hasFile() && !$document->isExternal();
    }

    public function date(Document $document): string
    {
        $format = (string) $this->settings->get('date_format');
        return (string) wp_date($format !== '' ? $format : (string) get_option('date_format'), $document->timestamp);
    }

    /** @return list<string> Plain text pieces such as "PDF", "1.2 MB", "12 March 2025". */
    public function meta(Document $document): array
    {
        $parts = [];
        if ($this->options['show_type'] && $document->extension !== '') {
            $parts[] = strtoupper($document->extension);
        }
        if ($this->options['show_size']) {
            $size = FileTypes::formatSize($document->size);
            if ($size !== '') {
                $parts[] = $size;
            }
        }
        if ($this->options['show_date']) {
            $parts[] = $this->date($document);
        }
        return $parts;
    }

    /** Text the search box matches against. */
    public function searchText(Document $document): string
    {
        return trim($document->title . ' ' . $document->excerpt . ' ' . $document->year);
    }

    public function fileIconHtml(Document $document): string
    {
        return Icons::file($document->kind(), $document->extension);
    }

    public function iconHtml(string $name): string
    {
        return Icons::get($name);
    }

    /** @param array<string, mixed> $vars */
    public function part(string $name, array $vars = []): void
    {
        TemplateLoader::include('parts/' . $name . '.php', ['view' => $this, ...$vars]);
    }
}
