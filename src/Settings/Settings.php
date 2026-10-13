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
            'muted'         => '#64748b',
            'surface'       => '#ffffff',
            'surface_alt'   => '#f4f7fb',
            'border'        => '#e2e8f0',
            'radius'        => 12,
            'font'          => '',
            'density'       => 'comfortable',
            'header_style'  => 'soft',
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
                in_array($key, ['primary', 'accent', 'text', 'muted', 'surface', 'surface_alt', 'border'], true)
                    => sanitize_hex_color((string) $value) ?: $default,
                $key === 'density'      => self::oneOf($value, self::DENSITIES, $default),
                $key === 'header_style' => self::oneOf($value, self::HEADER_STYLES, $default),
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
