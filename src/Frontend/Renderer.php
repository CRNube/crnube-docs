<?php
declare(strict_types=1);

namespace CRNube\Docs\Frontend;

use CRNube\Docs\Domain\DocumentRepository;
use CRNube\Docs\Domain\DocumentTree;
use CRNube\Docs\Domain\TreeQuery;
use CRNube\Docs\Settings\Settings;

/**
 * Turns shortcode (and later block and Elementor widget) attributes into a rendered
 * library. Whatever calls it, the attributes are validated here.
 */
final class Renderer
{
    /** Attributes accepted from a shortcode, block or widget. */
    public const ATTRIBUTES = [
        'category', 'layout', 'title', 'search', 'year_filter', 'counts', 'date', 'size', 'type',
        'toggle_all', 'open', 'group_by_year', 'columns', 'orderby', 'order', 'depth', 'primary', 'accent',
        'header_style', 'density', 'class',
    ];


    private static int $instances = 0;

    public function __construct(
        private readonly Settings $settings,
        private readonly DocumentRepository $documents,
    ) {
    }

    /**
     * @param array<string, string> $atts
     */
    public function render(array $atts): string
    {
        $options = $this->options($atts);

        // A mistyped category must show nothing, never fall back to the whole library.
        if ($options['category_given'] && $options['categories'] === []) {
            return current_user_can('edit_crnd_documents')
                ? '<p class="crnd-notice">' . esc_html(sprintf(
                    /* translators: %s: the category value typed in the shortcode */
                    __('CRNube Docs: the category "%s" does not exist. Only editors see this message.', 'crnube-docs'),
                    (string) $atts['category']
                )) . '</p>'
                : '';
        }

        $tree = $this->documents->tree(new TreeQuery(
            rootIds: $options['categories'],
            orderBy: $options['orderby'],
            order: $options['order'],
            depth: $options['depth'],
            groupByYear: $options['group_by_year'],
        ));

        return $this->renderTree($tree, $options);
    }

    /**
     * Renders an already built tree; the settings page uses it for its sample preview.
     *
     * @param array<string, mixed> $options As returned by options().
     */
    public function renderTree(DocumentTree $tree, array $options): string
    {
        Assets::enqueue();

        $view = new View($tree, $options, $this->settings, 'crnd-' . ++self::$instances);

        ob_start();
        TemplateLoader::include($options['layout'] . '.php', ['view' => $view]);
        return (string) ob_get_clean();
    }

    /**
     * @param array<string, string> $atts
     * @return array<string, mixed>
     */
    public function options(array $atts): array
    {
        $s    = $this->settings;
        $bool = static fn (string $key, bool $default): bool => isset($atts[$key])
            ? in_array(strtolower(trim((string) $atts[$key])), ['1', 'yes', 'true', 'si', 'sí', 'on'], true)
            : $default;
        $pick = static fn (string $key, array $allowed, mixed $default): string => isset($atts[$key]) && in_array($atts[$key], $allowed, true)
            ? (string) $atts[$key]
            : (string) $default;

        $categories = isset($atts['category']) && $atts['category'] !== ''
            ? $this->documents->resolveCategoryIds(explode(',', (string) $atts['category']))
            : [];

        $overrides = [];
        foreach (['primary', 'accent'] as $color) {
            if (!empty($atts[$color]) && sanitize_hex_color((string) $atts[$color])) {
                $overrides[$color] = (string) $atts[$color];
            }
        }

        return [
            'categories'      => $categories,
            'category_given'  => isset($atts['category']) && $atts['category'] !== '',
            'layout'          => $pick('layout', Settings::LAYOUTS, $s->get('layout')),
            'columns'         => isset($atts['columns']) ? max(1, min(4, (int) $atts['columns'])) : (int) $s->get('columns'),
            'title'           => isset($atts['title']) ? sanitize_text_field((string) $atts['title']) : '',
            'search'          => $bool('search', (bool) $s->get('search')),
            'year_filter'     => $bool('year_filter', (bool) $s->get('year_filter')),
            'counts'          => $bool('counts', (bool) $s->get('counts')),
            'show_date'       => $bool('date', (bool) $s->get('show_date')),
            'show_size'       => $bool('size', (bool) $s->get('show_size')),
            'show_type'       => $bool('type', (bool) $s->get('show_type')),
            'toggle_all'      => $bool('toggle_all', (bool) $s->get('toggle_all')),
            'group_by_year'   => $bool('group_by_year', (bool) $s->get('group_by_year')),
            'open'            => $pick('open', Settings::OPEN_MODES, $s->get('open')),
            'orderby'         => $pick('orderby', TreeQuery::ORDER_BY, $s->get('orderby')),
            'order'           => strtoupper($pick('order', ['asc', 'desc', 'ASC', 'DESC'], $s->get('order'))),
            'depth'           => isset($atts['depth']) ? max(0, (int) $atts['depth']) : 0,
            'header_style'    => $pick('header_style', Settings::HEADER_STYLES, $s->get('header_style')),
            'density'         => $pick('density', Settings::DENSITIES, $s->get('density')),
            'class'           => isset($atts['class']) ? implode(' ', array_map('sanitize_html_class', preg_split('/\s+/', (string) $atts['class']) ?: [])) : '',
            'style_overrides' => $overrides,
        ];
    }
}
