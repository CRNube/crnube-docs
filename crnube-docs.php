<?php
/**
 * Plugin Name:       CRNube Docs
 * Plugin URI:        https://crnube.com
 * Description:       Document library with multi-level collapsible categories, search, year filters and per-site design.
 * Version:           0.4.0
 * Author:            CRNube
 * Author URI:        https://crnube.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Text Domain:       crnube-docs
 * Domain Path:       /languages
 *
 * This file must stay parseable by old PHP versions: the namespaced classes in src/
 * use PHP 8.1 syntax and are only loaded after the requirements check passes.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CRND_VERSION', '0.4.0');
define('CRND_FILE', __FILE__);
define('CRND_DIR', plugin_dir_path(__FILE__));
define('CRND_URL', plugin_dir_url(__FILE__));
define('CRND_MIN_PHP', '8.1');
define('CRND_MIN_WP', '6.5');

if (version_compare(PHP_VERSION, CRND_MIN_PHP, '<') || version_compare(get_bloginfo('version'), CRND_MIN_WP, '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo esc_html(sprintf(
            /* translators: 1: PHP version, 2: WordPress version */
            __('CRNube Docs requires PHP %1$s and WordPress %2$s or newer.', 'crnube-docs'),
            CRND_MIN_PHP,
            CRND_MIN_WP
        ));
        echo '</p></div>';
    });
    return;
}

spl_autoload_register(function ($class) {
    $prefix = 'CRNube\\Docs\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $file = CRND_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, [\CRNube\Docs\Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [\CRNube\Docs\Installer::class, 'deactivate']);

add_action('plugins_loaded', [\CRNube\Docs\Plugin::class, 'boot']);
