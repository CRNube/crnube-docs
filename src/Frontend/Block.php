<?php
declare(strict_types=1);

namespace CRNube\Docs\Frontend;

/**
 * "Document library" block for the block editor. Rendered on the server by the same
 * renderer as the shortcode, so the editor preview is the real library.
 */
final class Block
{
    public const NAME = 'crnube/documents';

    /** Block attribute => shortcode attribute. All strings; '' means "as in the settings". */
    private const ATTRIBUTES = [
        'title'       => 'title',
        'layout'      => 'layout',
        'columns'     => 'columns',
        'open'        => 'open',
        'groupByYear' => 'group_by_year',
        'search'      => 'search',
        'yearFilter'  => 'year_filter',
        'toggleAll'   => 'toggle_all',
        'counts'      => 'counts',
        'date'        => 'date',
        'size'        => 'size',
        'type'        => 'type',
        'orderby'     => 'orderby',
        'private'     => 'private',
        'headerStyle' => 'header_style',
        'density'     => 'density',
        'primary'     => 'primary',
        'accent'      => 'accent',
    ];

    public function __construct(private readonly Renderer $renderer)
    {
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerBlock']);
    }

    public function registerBlock(): void
    {
        $asset = CRND_DIR . 'build/block.asset.php';
        if (!is_readable($asset)) {
            return;
        }
        /** @var array{dependencies: list<string>, version: string} $meta */
        $meta = require $asset;
        wp_register_script('crnd-block', CRND_URL . 'build/block.js', $meta['dependencies'], $meta['version'], true);
        wp_set_script_translations('crnd-block', 'crnube-docs', CRND_DIR . 'languages');

        $attributes = ['categories' => ['type' => 'array', 'items' => ['type' => 'integer'], 'default' => []]];
        foreach (array_keys(self::ATTRIBUTES) as $name) {
            $attributes[$name] = ['type' => 'string', 'default' => ''];
        }

        register_block_type(self::NAME, [
            'api_version'     => '3',
            'editor_script'   => 'crnd-block',
            'style'           => Assets::HANDLE,
            'attributes'      => $attributes,
            'render_callback' => [$this, 'render'],
            'supports'        => ['align' => ['wide', 'full'], 'html' => false],
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes): string
    {
        $atts       = [];
        $categories = array_filter(array_map('absint', (array) ($attributes['categories'] ?? [])));
        if ($categories !== []) {
            $atts['category'] = implode(',', $categories);
        }
        foreach (self::ATTRIBUTES as $block => $shortcode) {
            $value = trim((string) ($attributes[$block] ?? ''));
            if ($value !== '') {
                $atts[$shortcode] = $value;
            }
        }
        $html = $this->renderer->render($atts);
        return sprintf('<div %s>%s</div>', get_block_wrapper_attributes(), $html);
    }
}
