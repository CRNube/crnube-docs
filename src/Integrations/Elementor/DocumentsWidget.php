<?php
declare(strict_types=1);

namespace CRNube\Docs\Integrations\Elementor;

use CRNube\Docs\Domain\CategoryTaxonomy;
use CRNube\Docs\Plugin;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * "Document library" widget: every option of [crnube_docs] as Elementor controls.
 * Options left on "Settings" use the plugin's settings, like a shortcode without them.
 */
final class DocumentsWidget extends Widget_Base
{
    public function get_name(): string
    {
        return 'crnube-docs-library';
    }

    public function get_title(): string
    {
        return __('Document library', 'crnube-docs');
    }

    public function get_icon(): string
    {
        return 'eicon-folder-o';
    }

    /** @return list<string> */
    public function get_categories(): array
    {
        return [Integration::CATEGORY];
    }

    /** @return list<string> */
    public function get_keywords(): array
    {
        return ['documentos', 'documents', 'pdf', 'informes', 'crnube', 'descargas'];
    }

    /** @return list<string> */
    public function get_style_depends(): array
    {
        return ['crnd-frontend'];
    }

    protected function register_controls(): void
    {
        $inherit = ['' => __('As in the settings', 'crnube-docs')];
        $yesNo   = $inherit + ['yes' => __('Yes', 'crnube-docs'), 'no' => __('No', 'crnube-docs')];

        $this->start_controls_section('content', ['label' => __('Documents', 'crnube-docs')]);
        $this->add_control('category', [
            'label'       => __('Categories', 'crnube-docs'),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => self::categoryOptions(),
            'label_block' => true,
            'description' => __('Empty: every category. With one category, its own documents go on top and its subcategories become the groups.', 'crnube-docs'),
        ]);
        $this->add_control('title', [
            'label' => __('Title', 'crnube-docs'),
            'type'  => Controls_Manager::TEXT,
        ]);
        $this->add_control('layout', [
            'label'   => __('Layout', 'crnube-docs'),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $inherit + [
                'tree'     => __('Tree', 'crnube-docs'),
                'sections' => __('Sections', 'crnube-docs'),
                'grid'     => __('Grid', 'crnube-docs'),
                'list'     => __('List', 'crnube-docs'),
            ],
        ]);
        $this->add_control('columns', [
            'label'     => __('Grid columns', 'crnube-docs'),
            'type'      => Controls_Manager::SELECT,
            'default'   => '',
            'options'   => $inherit + ['2' => '2', '3' => '3', '4' => '4'],
            'condition' => ['layout' => 'grid'],
        ]);
        $this->add_control('open', [
            'label'   => __('Groups open on load', 'crnube-docs'),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $inherit + [
                'none'  => __('All closed', 'crnube-docs'),
                'first' => __('First one open', 'crnube-docs'),
                'all'   => __('All open', 'crnube-docs'),
            ],
        ]);
        $this->add_control('group_by_year', [
            'label'   => __('Group documents by year inside each category', 'crnube-docs'),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $yesNo,
        ]);
        $this->add_control('depth', [
            'label'       => __('Levels of groups', 'crnube-docs'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 0,
            'max'         => 10,
            'default'     => 0,
            'description' => __('0: all levels.', 'crnube-docs'),
        ]);
        $this->end_controls_section();

        $this->start_controls_section('display', ['label' => __('Display', 'crnube-docs')]);
        foreach ([
            'search'      => __('Search box', 'crnube-docs'),
            'year_filter' => __('Year filter', 'crnube-docs'),
            'toggle_all'  => __('Expand / collapse all buttons', 'crnube-docs'),
            'counts'      => __('Document count on each group', 'crnube-docs'),
            'date'        => __('Publication date', 'crnube-docs'),
            'size'        => __('File size', 'crnube-docs'),
            'type'        => __('File type', 'crnube-docs'),
        ] as $key => $label) {
            $this->add_control($key, ['label' => $label, 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => $yesNo]);
        }
        $this->add_control('orderby', [
            'label'   => __('Order documents by', 'crnube-docs'),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $inherit + [
                'date'       => __('Publication date', 'crnube-docs'),
                'title'      => __('Title', 'crnube-docs'),
                'menu_order' => __('Manual order', 'crnube-docs'),
            ],
        ]);
        $this->add_control('private', [
            'label'   => __('Restricted documents in public libraries', 'crnube-docs'),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $inherit + [
                'lock' => __('Show them with a lock and "Sign in"', 'crnube-docs'),
                'hide' => __('Hide them from people without access', 'crnube-docs'),
            ],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('look', ['label' => __('Design', 'crnube-docs'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('primary', ['label' => __('Primary', 'crnube-docs'), 'type' => Controls_Manager::COLOR, 'alpha' => false]);
        $this->add_control('accent', ['label' => __('Accent', 'crnube-docs'), 'type' => Controls_Manager::COLOR, 'alpha' => false]);
        $this->add_control('header_style', [
            'label'   => __('Group header style', 'crnube-docs'),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $inherit + ['soft' => __('Soft', 'crnube-docs'), 'solid' => __('Solid', 'crnube-docs'), 'minimal' => __('Minimal', 'crnube-docs')],
        ]);
        $this->add_control('density', [
            'label'   => __('Density', 'crnube-docs'),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => $inherit + ['comfortable' => __('Comfortable', 'crnube-docs'), 'compact' => __('Compact', 'crnube-docs')],
        ]);
        $this->add_control('style_hint', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => esc_html__('Colors, fonts and borders for every library are set in Documents › Settings. Here you only override this one.', 'crnube-docs'),
            'content_classes' => 'elementor-descriptor',
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        echo Plugin::instance()->renderer->render(self::attributes($this->get_settings_for_display())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the library templates
    }

    /**
     * Widget settings to shortcode attributes; empty values are left out so the plugin
     * settings apply.
     *
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    public static function attributes(array $settings): array
    {
        $atts = [];
        $categories = array_filter(array_map('absint', (array) ($settings['category'] ?? [])));
        if ($categories !== []) {
            $atts['category'] = implode(',', $categories);
        }
        foreach (['title', 'layout', 'columns', 'open', 'group_by_year', 'search', 'year_filter', 'toggle_all', 'counts', 'date', 'size', 'type', 'orderby', 'private', 'primary', 'accent', 'header_style', 'density'] as $key) {
            $value = (string) ($settings[$key] ?? '');
            if ($value !== '') {
                $atts[$key] = $value;
            }
        }
        if (!empty($settings['depth'])) {
            $atts['depth'] = (string) (int) $settings['depth'];
        }
        // A layout chosen in the widget keeps the grid columns of the settings unless set.
        if (($atts['layout'] ?? '') !== 'grid') {
            unset($atts['columns']);
        }
        return $atts;
    }

    /** @return array<int, string> term ID => name, indented by level */
    public static function categoryOptions(): array
    {
        $terms = get_terms(['taxonomy' => CategoryTaxonomy::NAME, 'hide_empty' => false]);
        if (!is_array($terms)) {
            return [];
        }
        $byParent = [];
        foreach ($terms as $term) {
            $byParent[$term->parent][] = $term;
        }
        $options = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$options, $byParent): void {
            $children = $byParent[$parent] ?? [];
            usort($children, static fn (\WP_Term $a, \WP_Term $b): int => [(int) get_term_meta($a->term_id, CategoryTaxonomy::META_ORDER, true), $a->name] <=> [(int) get_term_meta($b->term_id, CategoryTaxonomy::META_ORDER, true), $b->name]);
            foreach ($children as $term) {
                $options[(string) $term->term_id] = str_repeat('— ', $depth) . $term->name;
                $walk($term->term_id, $depth + 1);
            }
        };
        $walk(0, 0);
        return $options;
    }
}
