<?php
declare(strict_types=1);

namespace CRNube\Docs\Settings;

use CRNube\Docs\Domain\TreeQuery;

/**
 * The plugin settings, stored in one option. Every value is checked against its default's
 * type and allowed values when saved, so the rest of the code can trust what it reads.
 */
final class Settings
{
    public const OPTION = 'crnd_settings';

    public const HEADER_STYLES = ['soft', 'solid', 'minimal'];
    public const LAYOUTS       = ['tree', 'sections', 'grid', 'list'];
    public const PRIVATE_DISPLAY = ['lock', 'hide'];

    /** The settings a brand preset (and an exported design file) is made of. */
    public const DESIGN_KEYS = [
        'primary', 'accent', 'text', 'muted', 'surface', 'surface_alt', 'border',
        'radius', 'font', 'density', 'header_style', 'layout', 'columns',
    ];

    /**
     * Ready-made brand designs. Every primary color reaches WCAG AA (4.5:1) both with
     * white text on it and as text on the light backgrounds, and so do the text colors.
     *
     * @return array<string, array{name: string, values: array<string, string|int>}>
     */
    public static function presets(): array
    {
        $base = ['text' => '#1e293b', 'muted' => '#5b6779', 'surface' => '#ffffff', 'border' => '#e2e8f0', 'font' => '', 'density' => 'comfortable', 'layout' => 'tree', 'columns' => 3];
        $presets = [
            'institutional' => ['name' => __('Institutional blue', 'crnube-docs'), 'values' => ['primary' => '#0f4c97', 'accent' => '#16a34a', 'surface_alt' => '#f4f7fb', 'radius' => 12, 'header_style' => 'soft'] + $base],
            'green'         => ['name' => __('Cooperative green', 'crnube-docs'), 'values' => ['primary' => '#166534', 'accent' => '#b45309', 'text' => '#1c2a22', 'muted' => '#5b6b62', 'surface_alt' => '#f2f8f3', 'border' => '#dcebe0', 'radius' => 14, 'header_style' => 'solid'] + $base],
            'wine'          => ['name' => __('Wine', 'crnube-docs'), 'values' => ['primary' => '#8a1538', 'accent' => '#c2410c', 'text' => '#2a1a1f', 'muted' => '#6b5a60', 'surface_alt' => '#fbf4f6', 'border' => '#f0dfe4', 'radius' => 10, 'header_style' => 'soft'] + $base],
            'purple'        => ['name' => __('Purple', 'crnube-docs'), 'values' => ['primary' => '#5b21b6', 'accent' => '#db2777', 'text' => '#1f1b2e', 'muted' => '#6b6680', 'surface_alt' => '#f6f3fc', 'border' => '#e6e0f3', 'radius' => 16, 'header_style' => 'soft', 'layout' => 'sections'] + $base],
            'teal'          => ['name' => __('Teal', 'crnube-docs'), 'values' => ['primary' => '#0e7490', 'accent' => '#c2410c', 'text' => '#14282e', 'muted' => '#5a6e74', 'surface_alt' => '#f0f8fa', 'border' => '#d8eaef', 'radius' => 12, 'header_style' => 'solid', 'layout' => 'sections'] + $base],
            'orange'        => ['name' => __('Warm orange', 'crnube-docs'), 'values' => ['primary' => '#c2410c', 'accent' => '#0f766e', 'text' => '#2a1d15', 'muted' => '#6e5f55', 'surface_alt' => '#fdf6f1', 'border' => '#f3e3d8', 'radius' => 18, 'header_style' => 'soft', 'layout' => 'grid'] + $base],
            'graphite'      => ['name' => __('Sober graphite', 'crnube-docs'), 'values' => ['primary' => '#334155', 'accent' => '#0284c7', 'text' => '#0f172a', 'surface_alt' => '#f5f6f8', 'radius' => 6, 'header_style' => 'minimal', 'density' => 'compact', 'layout' => 'list'] + $base],
            'contrast'      => ['name' => __('High contrast', 'crnube-docs'), 'values' => ['primary' => '#000000', 'accent' => '#0047ab', 'text' => '#000000', 'muted' => '#333333', 'surface_alt' => '#ffffff', 'border' => '#000000', 'radius' => 4, 'header_style' => 'solid'] + $base],
        ];
        /** @var array<string, array{name: string, values: array<string, string|int>}> */
        return apply_filters('crnd_presets', $presets);
    }
    public const DENSITIES     = ['comfortable', 'compact'];
    public const OPEN_MODES    = ['none', 'first', 'all'];

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            // Design
            'primary'       => '#0f4c97',
            'accent'        => '#16a34a',
            'text'          => '#1e293b',
            'muted'         => '#5b6779',
            'surface'       => '#ffffff',
            'surface_alt'   => '#f4f7fb',
            'border'        => '#e2e8f0',
            'radius'        => 12,
            'font'          => '',
            'density'       => 'comfortable',
            'header_style'  => 'soft',
            'layout'        => 'tree',
            'columns'       => 3,
            // Display
            'search'        => true,
            'year_filter'   => true,
            'counts'        => true,
            'show_date'     => true,
            'show_size'     => true,
            'show_type'     => true,
            'toggle_all'    => true,
            'open'          => 'none',
            'group_by_year' => false,
            'orderby'       => 'date',
            'order'         => 'DESC',
            'date_format'   => '',
            // Labels (empty: the translated default)
            'label_download' => '',
            'label_view'     => '',
            'label_search'   => '',
            'label_empty'    => '',
            // Protected area
            'portal_page'      => 0,
            'private_display'  => 'lock',
            'session_hours'    => 8,
            // Statistics
            'track_enabled'    => true,
            'respect_gpc'      => true,
            'retention_months' => 24,
            // Advanced
            'delete_data'   => false,
        ];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->values === null) {
            $stored       = get_option(self::OPTION, []);
            $this->values = array_merge(self::defaults(), is_array($stored) ? $stored : []);
        }
        return $this->values;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function label(string $name): string
    {
        $custom = (string) $this->get('label_' . $name);
        return $custom !== '' ? $custom : self::defaultLabel($name);
    }

    public static function defaultLabel(string $name): string
    {
        return match ($name) {
            'download' => __('Download', 'crnube-docs'),
            'view'     => __('View', 'crnube-docs'),
            'search'   => __('Search documents', 'crnube-docs'),
            'empty'    => __('No documents match your search.', 'crnube-docs'),
            default    => '',
        };
    }

    /**
     * @param mixed $input Raw values from the settings form.
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $input): array
    {
        $input    = is_array($input) ? wp_unslash($input) : [];
        $defaults = self::defaults();
        $clean    = [];

        foreach ($defaults as $key => $default) {
            $value = $input[$key] ?? null;
            $clean[$key] = match (true) {
                is_bool($default) => !empty($value),
                $key === 'radius' => max(0, min(32, (int) ($value ?? $default))),
                $key === 'retention_months' => max(0, min(120, (int) ($value ?? $default))),
                $key === 'session_hours'    => max(1, min(720, (int) ($value ?? $default))),
                $key === 'portal_page'      => get_post_type(absint($value)) === 'page' ? absint($value) : 0,
                $key === 'private_display'  => self::oneOf($value, self::PRIVATE_DISPLAY, $default),
                in_array($key, ['primary', 'accent', 'text', 'muted', 'surface', 'surface_alt', 'border'], true)
                    => sanitize_hex_color((string) $value) ?: $default,
                $key === 'density'      => self::oneOf($value, self::DENSITIES, $default),
                $key === 'header_style' => self::oneOf($value, self::HEADER_STYLES, $default),
                $key === 'layout'       => self::oneOf($value, self::LAYOUTS, $default),
                $key === 'columns'      => max(1, min(4, (int) ($value ?? $default))),
                $key === 'open'         => self::oneOf($value, self::OPEN_MODES, $default),
                $key === 'orderby'      => self::oneOf($value, TreeQuery::ORDER_BY, $default),
                $key === 'order'        => self::oneOf(strtoupper((string) $value), ['ASC', 'DESC'], $default),
                $key === 'font'         => self::sanitizeFont((string) $value),
                default                 => sanitize_text_field((string) $value),
            };
        }
        return $clean;
    }

    /** A CSS font-family list: letters, digits, spaces, commas, quotes and hyphens only. */
    public static function sanitizeFont(string $value): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N} ,\'"-]/u', '', $value));
    }

    /**
     * @param list<string> $allowed
     */
    private static function oneOf(mixed $value, array $allowed, mixed $default): string
    {
        return in_array($value, $allowed, true) ? (string) $value : (string) $default;
    }
}
