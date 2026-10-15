<?php
declare(strict_types=1);

namespace CRNube\Docs\Frontend;

use CRNube\Docs\Access\AccessPolicy;

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
        private readonly ?AccessPolicy $access = null,
    ) {
    }

    /** Whether the current visitor may open the document. */
    public function canAccess(Document $document): bool
    {
        if (!$document->restricted || $this->access === null) {
            return true;
        }
        return $this->access->canAccess($document, is_user_logged_in() ? wp_get_current_user() : null);
    }

    /** Restricted and closed to this visitor: shown with a lock instead of the file buttons. */
    public function isLocked(Document $document): bool
    {
        return !$this->canAccess($document);
    }

    /** Where a locked document sends the visitor: sign in, then back to the document. */
    public function loginUrl(Document $document): string
    {
        if (is_user_logged_in()) {
            return '';
        }
        $target = $this->viewUrl($document);
        /**
         * Where visitors sign in to open a restricted document (the members' portal of
         * CRNube Docs Pro, a single sign-on page…).
         *
         * @param string $url    wp-login.php, coming back to the document.
         * @param string $target The document URL to return to.
         */
        return (string) apply_filters('crnd_login_url', wp_login_url($target), $target);
    }

    public function option(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }

    public function label(string $name): string
    {
        return $this->settings->label($name);
    }

    /** @var array{groups: list<array{path: string, node: CategoryNode}>, documents: array<int, array{document: Document, categories: list<int>, labels: list<string>}>, categories: list<array{id: int, name: string, depth: int}>}|null */
    private ?array $flat = null;

    public function hasToolbar(): bool
    {
        return !$this->tree->isEmpty()
            && ($this->options['search']
                || ($this->options['year_filter'] && count($this->tree->years) > 1)
                || $this->showToggleAll()
                || $this->showCategoryFilter());
    }

    /** Expand/collapse buttons make sense only where groups fold (Tree and Sections). */
    public function showToggleAll(): bool
    {
        return $this->options['toggle_all']
            && in_array($this->options['layout'], ['tree', 'sections'], true)
            && $this->tree->groups !== [];
    }

    /** The List layout has no groups on screen, so it filters by category instead. */
    public function showCategoryFilter(): bool
    {
        return $this->options['layout'] === 'list' && count($this->categoryOptions()) > 1;
    }

    /**
     * Every group that holds documents, in tree order, with its full path
     * ("Estados financieros › Auditados"). Used by the Grid layout, which shows one
     * heading per group instead of nesting them.
     *
     * @return list<array{path: string, node: CategoryNode}>
     */
    public function flatGroups(): array
    {
        return $this->flatten()['groups'];
    }

    /**
     * Each document once, with the categories it sits in (and their ancestors, for the
     * category filter) and the names to show on it. Used by the List layout.
     *
     * @return list<array{document: Document, categories: list<int>, labels: list<string>}>
     */
    public function flatDocuments(): array
    {
        $items = array_values($this->flatten()['documents']);
        $order = $this->options['order'] === 'ASC' ? 1 : -1;
        if ($this->options['orderby'] === 'title') {
            usort($items, static fn (array $a, array $b): int => $order * strnatcasecmp(remove_accents($a['document']->title), remove_accents($b['document']->title)));
        } elseif ($this->options['orderby'] === 'date') {
            usort($items, static fn (array $a, array $b): int => $order * ($a['document']->timestamp <=> $b['document']->timestamp));
        }
        // Manual order: the order in which documents appear in the tree.
        return $items;
    }

    /** @return list<array{id: int, name: string, depth: int}> Categories for the filter, indented by depth. */
    public function categoryOptions(): array
    {
        return $this->flatten()['categories'];
    }

    /**
     * @return array{groups: list<array{path: string, node: CategoryNode}>, documents: array<int, array{document: Document, categories: list<int>, labels: list<string>}>, categories: list<array{id: int, name: string, depth: int}>}
     */
    private function flatten(): array
    {
        if ($this->flat !== null) {
            return $this->flat;
        }
        $flat = ['groups' => [], 'documents' => [], 'categories' => []];

        $add = static function (Document $document, array $ancestors, string $label) use (&$flat): void {
            $entry = $flat['documents'][$document->id] ?? ['document' => $document, 'categories' => [], 'labels' => []];

            $entry['categories'] = array_values(array_unique([...$entry['categories'], ...$ancestors]));
            if ($label !== '') {
                $entry['labels'] = array_values(array_unique([...$entry['labels'], $label]));
            }
            $flat['documents'][$document->id] = $entry;
        };

        if ($this->tree->documents !== []) {
            $flat['groups'][] = ['path' => '', 'node' => new CategoryNode(0, '', 'root', '', false, $this->tree->documents, [], count($this->tree->documents))];
            foreach ($this->tree->documents as $document) {
                $add($document, [], '');
            }
        }

        $walk = function (CategoryNode $node, array $names, array $ancestors, int $depth) use (&$walk, &$flat, $add): void {
            $names = [...$names, $node->name];
            if (!$node->isYearGroup) {
                $ancestors              = [...$ancestors, $node->termId];
                $flat['categories'][]   = ['id' => $node->termId, 'name' => $node->name, 'depth' => $depth];
            }
            if ($node->documents !== []) {
                $flat['groups'][] = ['path' => implode(' › ', $names), 'node' => $node];
                // The label is the category the document is filed in, not a year group.
                $label = $node->isYearGroup ? (string) ($names[count($names) - 2] ?? '') : $node->name;
                foreach ($node->documents as $document) {
                    $add($document, $ancestors, $label);
                }
            }
            foreach ($node->children as $child) {
                $walk($child, $names, $ancestors, $node->isYearGroup ? $depth : $depth + 1);
            }
        };
        foreach ($this->tree->groups as $group) {
            $walk($group, [], [], 0);
        }

        return $this->flat = $flat;
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
        $css = Assets::cssVariables($this->options['style_overrides']);
        if ($this->options['layout'] === 'grid') {
            $css .= '--crnd-columns:' . (int) $this->options['columns'] . ';';
        }
        return $css;
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
        return $document->hasFile() && $document->isViewable() && !$this->isLocked($document);
    }

    public function showDownloadButton(Document $document): bool
    {
        return $document->hasFile() && !$document->isExternal() && !$this->isLocked($document);
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
