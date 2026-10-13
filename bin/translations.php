<?php
/**
 * Builds the Spanish translations from one source, languages/crnube-docs-es_CR.l10n.php:
 *
 * - copies it to the other Spanish locales (es_ES, es_MX, ...);
 * - writes the JSON files WordPress loads for each built script (wp_set_script_translations),
 *   with only the strings that script uses;
 * - reports strings used in the PHP or JS code that are missing from the source.
 *
 * Usage: php bin/translations.php   (exit code 1 if a string is missing)
 */

$root    = dirname(__DIR__);
$source  = $root . '/languages/crnube-docs-es_CR.l10n.php';
$locales = ['es_CR', 'es_ES', 'es_MX', 'es_CO', 'es_AR', 'es_CL', 'es_PE', 'es_VE', 'es_GT', 'es_UY', 'es_EC', 'es_PR', 'es_DO'];

/** Built script (relative path, as WordPress hashes it) => folder with its sources. */
$scripts = [
    'build/library.js' => 'assets/src/library',
    'build/reports.js' => 'assets/src/reports',
];

$data     = require $source;
$messages = $data['messages'];

/** @return array<string, true> */
function crnd_php_strings(string $root): array
{
    $funcs = ['__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', '_n', '_x'];
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        if (!str_ends_with($path, '.php') || preg_match('#/(vendor|node_modules|languages|bin|tests|build)/#', $path)) {
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
    return $found;
}

/** @return array<string, true> Strings of __( '…' ) and _n( '…', '…' ) calls in a JS folder. */
function crnd_js_strings(string $dir): array
{
    $string = "'((?:\\\\.|[^'\\\\])*)'";
    $found  = [];
    $files  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!preg_match('/\.(js|jsx)$/', $file->getFilename())) {
            continue;
        }
        $code = (string) file_get_contents($file->getPathname());
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $code); // translator comments sit inside calls
        $unescape = static fn (string $s): string => stripcslashes($s);

        preg_match_all('/\b_n\(\s*' . $string . '\s*,\s*' . $string . '/s', $code, $plurals, PREG_SET_ORDER);
        foreach ($plurals as $m) {
            $found[$unescape($m[1]) . "\0" . $unescape($m[2])] = true;
        }
        preg_match_all('/\b__\(\s*' . $string . '\s*,\s*\'crnube-docs\'/s', $code, $singles, PREG_SET_ORDER);
        foreach ($singles as $m) {
            $found[$unescape($m[1])] = true;
        }
    }
    return $found;
}

$found   = crnd_php_strings($root);
$byScript = [];
foreach ($scripts as $script => $dir) {
    $byScript[$script] = crnd_js_strings($root . '/' . $dir);
    $found += $byScript[$script];
}

$missing = array_diff(array_keys($found), array_keys($messages));
foreach ($missing as $string) {
    fwrite(STDERR, 'Missing translation: ' . str_replace("\0", ' | ', $string) . PHP_EOL);
}

foreach ($locales as $locale) {
    if ($locale !== 'es_CR') {
        $copy             = $data;
        $copy['language'] = $locale;
        file_put_contents(
            $root . "/languages/crnube-docs-{$locale}.l10n.php",
            "<?php\n// Generated from crnube-docs-es_CR.l10n.php by bin/translations.php. Do not edit.\nreturn " . var_export($copy, true) . ";\n"
        );
    }

    foreach ($byScript as $script => $strings) {
        $jed = ['' => ['domain' => 'messages', 'lang' => $locale, 'plural-forms' => $data['plural-forms']]];
        foreach (array_keys($strings) as $key) {
            if (!isset($messages[$key])) {
                continue;
            }
            $singular       = explode("\0", $key)[0];
            $jed[$singular] = explode("\0", $messages[$key]);
        }
        file_put_contents(
            $root . "/languages/crnube-docs-{$locale}-" . md5($script) . '.json',
            json_encode([
                'translation-revision-date' => gmdate('Y-m-d H:i:sO'),
                'generator'                 => 'crnube-docs/bin/translations.php',
                'source'                    => $script,
                'domain'                    => 'messages',
                'locale_data'               => ['messages' => $jed],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}

echo count($found) . ' strings (' . array_sum(array_map('count', $byScript)) . ' in JS), ' . count($missing) . ' missing, ' . count($locales) . " locales written.\n";
exit($missing === [] ? 0 : 1);
