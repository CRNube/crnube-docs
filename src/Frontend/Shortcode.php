<?php
declare(strict_types=1);

namespace CRNube\Docs\Frontend;

/**
 * [crnube_docs category="estados-financieros" open="first" group_by_year="yes"]
 *
 * Every attribute is optional; the ones left out take the value from the settings page.
 */
final class Shortcode
{
    public const TAG = 'crnube_docs';

    public function __construct(private readonly Renderer $renderer)
    {
    }

    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
    }

    /**
     * @param array<string, string>|string $atts
     */
    public function render(array|string $atts = []): string
    {
        $atts = shortcode_atts(array_fill_keys(Renderer::ATTRIBUTES, null), is_array($atts) ? $atts : [], self::TAG);
        return $this->renderer->render(array_filter($atts, static fn ($v): bool => $v !== null));
    }
}
