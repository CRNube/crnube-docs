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

    /** The members' portal of CRNube Docs Pro. */
    public const PORTAL_TAG = 'crnube_docs_portal';

    public function __construct(private readonly Renderer $renderer)
    {
    }

    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
        // Without the add-on, a portal page shows nothing to visitors instead of the raw shortcode.
        add_action('init', static function (): void {
            if (!shortcode_exists(self::PORTAL_TAG)) {
                add_shortcode(self::PORTAL_TAG, [self::class, 'portalPlaceholder']);
            }
        }, 20);
    }

    public static function portalPlaceholder(): string
    {
        if (!current_user_can('edit_crnd_documents')) {
            return '';
        }
        return '<p class="crnd-notice">' . esc_html__('CRNube Docs: the members\' portal needs CRNube Docs Pro, which is not active. Only editors see this message.', 'crnube-docs') . '</p>';
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
