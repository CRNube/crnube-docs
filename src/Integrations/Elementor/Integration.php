<?php
declare(strict_types=1);

namespace CRNube\Docs\Integrations\Elementor;

/**
 * Elementor widget for the library, in a "CRNube Docs" panel category that add-ons can
 * also use. It calls the same renderer as the shortcode, so both always look and behave
 * the same. Loaded only when Elementor is active.
 */
final class Integration
{
    public const CATEGORY = 'crnube-docs';

    public function register(): void
    {
        add_action('elementor/elements/categories_registered', [$this, 'registerCategory']);
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
        // Elementor checks a widget's style handles before rendering it (also in the editor).
        add_action('elementor/frontend/after_register_styles', [$this, 'registerAssets']);
    }

    /** @param \Elementor\Elements_Manager $elements */
    public function registerCategory($elements): void
    {
        $elements->add_category(self::CATEGORY, [
            'title' => __('CRNube Docs', 'crnube-docs'),
            'icon'  => 'eicon-document-file',
        ]);
    }

    /** @param \Elementor\Widgets_Manager $widgets */
    public function registerWidgets($widgets): void
    {
        $widgets->register(new DocumentsWidget());
    }

    public function registerAssets(): void
    {
        (new \CRNube\Docs\Frontend\Assets(\CRNube\Docs\Plugin::instance()->settings))->registerAssets();
    }
}
