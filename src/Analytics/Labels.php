<?php
declare(strict_types=1);

namespace CRNube\Docs\Analytics;

/**
 * Human names for the values stored in the events table (used in the CSV export; the
 * reports screen has the same names in JavaScript).
 */
final class Labels
{
    public static function event(string $event): string
    {
        return $event === 'view' ? __('Viewed', 'crnube-docs') : __('Downloaded', 'crnube-docs');
    }

    public static function get(string $dimension, string $value): string
    {
        if ($value === '') {
            return __('Unknown', 'crnube-docs');
        }
        if ($dimension === 'country') {
            // Country names in the site language when PHP has intl; the code otherwise.
            if (class_exists(\Locale::class)) {
                $name = \Locale::getDisplayRegion('-' . $value, get_locale());
                return $name !== '' && $name !== $value ? $name : $value;
            }
            return $value;
        }

        $names = [
            'device'  => [
                'desktop' => __('Computer', 'crnube-docs'),
                'mobile'  => __('Phone', 'crnube-docs'),
                'tablet'  => __('Tablet', 'crnube-docs'),
            ],
            'browser' => [
                'chrome'  => 'Chrome',
                'safari'  => 'Safari',
                'edge'    => 'Edge',
                'firefox' => 'Firefox',
                'opera'   => 'Opera',
                'samsung' => 'Samsung Internet',
                'other'   => __('Other', 'crnube-docs'),
            ],
            'os'      => [
                'windows'  => 'Windows',
                'macos'    => 'macOS',
                'ios'      => 'iOS',
                'android'  => 'Android',
                'linux'    => 'Linux',
                'chromeos' => 'ChromeOS',
                'other'    => __('Other', 'crnube-docs'),
            ],
        ];
        return $names[$dimension][$value] ?? $value;
    }
}
