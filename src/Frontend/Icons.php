<?php
declare(strict_types=1);

namespace CRNube\Docs\Frontend;

/**
 * Inline SVG icons. They are fixed markup with no user input in them, except the file
 * extension label, which is escaped.
 */
final class Icons
{
    private const KIND_COLORS = [
        'pdf'        => '#e5484d',
        'word'       => '#2b6cd9',
        'excel'      => '#1f9d55',
        'powerpoint' => '#e86a1c',
        'archive'    => '#7c6f64',
        'image'      => '#8e4ec6',
        'text'       => '#5b6b7f',
        'other'      => '#5b6b7f',
    ];

    public static function file(string $kind, string $extension): string
    {
        $color = self::KIND_COLORS[$kind] ?? self::KIND_COLORS['other'];
        $label = strtoupper(substr($extension !== '' ? $extension : 'doc', 0, 4));
        $size  = strlen($label) > 3 ? '7' : '8.5';

        return '<svg class="crnd-file-icon" viewBox="0 0 32 40" width="32" height="40" aria-hidden="true" focusable="false">'
            . '<path d="M4 0h17l11 11v25a4 4 0 0 1-4 4H4a4 4 0 0 1-4-4V4a4 4 0 0 1 4-4z" fill="' . esc_attr($color) . '" opacity=".14"/>'
            . '<path d="M21 0v7a4 4 0 0 0 4 4h7z" fill="' . esc_attr($color) . '" opacity=".35"/>'
            . '<rect x="0" y="21" width="28" height="13" rx="3" fill="' . esc_attr($color) . '"/>'
            . '<text x="14" y="30.5" text-anchor="middle" font-family="system-ui,sans-serif" font-size="' . $size . '" font-weight="700" fill="#fff">' . esc_html($label) . '</text>'
            . '</svg>';
    }

    public static function get(string $name): string
    {
        $paths = [
            'chevron'  => '<path d="m6 9 6 6 6-6"/>',
            'folder'   => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
            'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'download' => '<path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 20h14"/>',
            'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
            'expand'   => '<path d="m7 15 5 5 5-5M7 9l5-5 5 5"/>',
            'collapse' => '<path d="m7 20 5-5 5 5M7 4l5 5 5-5"/>',
        ];
        if (!isset($paths[$name])) {
            return '';
        }
        return '<svg class="crnd-icon crnd-icon--' . $name . '" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $paths[$name]
            . '</svg>';
    }
}
