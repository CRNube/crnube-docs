<?php
declare(strict_types=1);

namespace CRNube\Docs\Frontend;

use CRNube\Docs\Settings\Settings;

/**
 * The library's stylesheet and script. Both are only registered on every page and are
 * enqueued by the shortcode itself, so pages without a library load nothing.
 */
final class Assets
{
    public const HANDLE = 'crnd-frontend';

    public function __construct(private readonly Settings $settings)
    {
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
        add_action('admin_enqueue_scripts', [$this, 'registerAssets'], 5);
    }

    public function registerAssets(): void
    {
        wp_register_style(self::HANDLE, CRND_URL . 'assets/css/frontend.css', [], self::version('assets/css/frontend.css'));
        wp_add_inline_style(self::HANDLE, '.crnd{' . self::cssVariables($this->settings->all()) . '}');

        wp_register_script_module(self::HANDLE, CRND_URL . 'assets/js/frontend.js', [], self::version('assets/js/frontend.js'));
    }

    public static function enqueue(): void
    {
        wp_enqueue_style(self::HANDLE);
        wp_enqueue_script_module(self::HANDLE);
    }

    /**
     * The design settings as CSS custom properties. The values were validated when saved
     * (hex colors, integers, a filtered font list), so they are safe to print.
     *
     * @param array<string, mixed> $values
     */
    public static function cssVariables(array $values): string
    {
        $map = [
            'primary'     => '--crnd-primary',
            'accent'      => '--crnd-accent',
            'text'        => '--crnd-text',
            'muted'       => '--crnd-muted',
            'surface'     => '--crnd-surface',
            'surface_alt' => '--crnd-surface-alt',
            'border'      => '--crnd-border',
        ];
        $css = '';
        foreach ($map as $key => $property) {
            $color = isset($values[$key]) ? sanitize_hex_color((string) $values[$key]) : null;
            if ($color) {
                $css .= $property . ':' . $color . ';';
            }
        }
        if (isset($values['radius'])) {
            $css .= '--crnd-radius:' . (int) $values['radius'] . 'px;';
        }
        if (!empty($values['font'])) {
            $css .= '--crnd-font:' . Settings::sanitizeFont((string) $values['font']) . ';';
        }
        return $css;
    }

    /** Cache-busting version: the file's modification time while developing. */
    public static function version(string $relativePath): string
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $mtime = @filemtime(CRND_DIR . $relativePath);
            if ($mtime !== false) {
                return CRND_VERSION . '.' . $mtime;
            }
        }
        return CRND_VERSION;
    }
}
