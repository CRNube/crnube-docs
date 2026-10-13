<?php
declare(strict_types=1);

namespace CRNube\Docs\Frontend;

/**
 * Templates can be overridden by copying them to `{theme}/crnube-docs/`, keeping the
 * same file name (for example `crnube-docs/parts/document.php`).
 */
final class TemplateLoader
{
    public static function locate(string $name): string
    {
        $name  = ltrim(str_replace(['..', '\\'], ['', '/'], $name), '/');
        $theme = locate_template('crnube-docs/' . $name);
        $path  = $theme !== '' ? $theme : CRND_DIR . 'templates/' . $name;

        return (string) apply_filters('crnd_template_path', $path, $name);
    }

    /**
     * @param array<string, mixed> $vars Variables the template can use.
     */
    public static function include(string $name, array $vars = []): void
    {
        $path = self::locate($name);
        if (!is_readable($path)) {
            return;
        }
        (static function (string $__path, array $__vars): void {
            extract($__vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            include $__path;
        })($path, $vars);
    }
}
