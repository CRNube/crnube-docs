<?php
/**
 * Copies the Spanish translation (es_CR, the source) to the other Spanish locales, and
 * reports strings in the code that are missing from it.
 *
 * Usage: php bin/translations.php
 */

$root    = dirname(__DIR__);
$source  = $root . '/languages/crnube-docs-es_CR.l10n.php';
$locales = ['es_ES', 'es_MX', 'es_CO', 'es_AR', 'es_CL', 'es_PE', 'es_VE', 'es_GT', 'es_UY', 'es_EC', 'es_PR', 'es_DO'];

$data = require $source;

// Strings used in the code.
$funcs = ['__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', '_n', '_x'];
$found = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if (!str_ends_with($path, '.php') || preg_match('#/(vendor|languages|bin|tests)/#', $path)) {
        continue;
    }
    $tokens = token_get_all((string) file_get_contents($path));
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_STRING || !in_array($token[1], $funcs, true)) {
            continue;
        }
        $args = [];
        for ($j = $i + 1, $n = count($tokens); $j < $n && count($args) < 2; $j++) {
            $t = $tokens[$j];
            if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
                $args[] = eval('return ' . $t[1] . ';');
            } elseif ($t === ')' || $t === ';') {
                break;
            }
        }
        if ($args !== []) {
            $found[$token[1] === '_n' && count($args) === 2 ? $args[0] . "\0" . $args[1] : $args[0]] = true;
        }
    }
}

$missing = array_diff(array_keys($found), array_keys($data['messages']));
foreach ($missing as $string) {
    fwrite(STDERR, 'Missing translation: ' . str_replace("\0", ' | ', $string) . PHP_EOL);
}

foreach ($locales as $locale) {
    $copy             = $data;
    $copy['language'] = $locale;
    file_put_contents(
        $root . "/languages/crnube-docs-{$locale}.l10n.php",
        "<?php\n// Generated from crnube-docs-es_CR.l10n.php by bin/translations.php. Do not edit.\nreturn " . var_export($copy, true) . ";\n"
    );
}

echo count($found) . ' strings, ' . count($missing) . ' missing, ' . count($locales) . " locales written.\n";
exit($missing === [] ? 0 : 1);
