<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\StateCache;

use function BlockstudioVendor\TailwindPHP\compileAst;
use function BlockstudioVendor\TailwindPHP\compileParsed;
use function BlockstudioVendor\TailwindPHP\CssParser\parse;
use function BlockstudioVendor\TailwindPHP\finalizeCssState;
use function BlockstudioVendor\TailwindPHP\parseCssState;
use BlockstudioVendor\TailwindPHP\Theme;
/**
 * Persistent construction-state cache.
 *
 * Constructing a compiler is dominated by parseCssState(): resolving the
 * framework imports, parsing the bundled resources, and accumulating the
 * theme. That phase is deterministic per CSS input and engine build, and its
 * products are pure data, so they can be persisted as a PHP file (which
 * OPcache shares across requests) and rehydrated through finalizeCssState()
 * plus compileParsed() for a fraction of a cold construction.
 *
 * The cache file validates against a payload version, an engine fingerprint
 * derived from the engine sources and resources, the input key, and the
 * mtime/size of any filesystem imports the input resolved. Callers own the
 * cache file path and its lifecycle.
 */
const STATE_CACHE_VERSION = 1;
/**
 * Fingerprint the engine build that produced a cached state.
 *
 * Uses mtime and size of the sources and resources that shape parse output,
 * so a vendored engine update invalidates cached state without coordination.
 *
 * @return string Engine fingerprint
 */
function engineFingerprint(): string
{
    static $fingerprint = null;
    if ($fingerprint !== null) {
        return $fingerprint;
    }
    $parts = [];
    foreach ([__DIR__ . '/index.php', __DIR__ . '/state-cache.php', __DIR__ . '/theme.php', __DIR__ . '/css-parser.php', __DIR__ . '/design-system.php', __DIR__ . '/utilities.php', __DIR__ . '/variants.php', __DIR__ . '/compile.php', __DIR__ . '/../resources/theme.css', __DIR__ . '/../resources/preflight.css', __DIR__ . '/../resources/tw-animate.css'] as $file) {
        $parts[] = @filemtime($file) . ':' . @filesize($file);
    }
    $fingerprint = hash('sha256', implode('|', $parts));
    return $fingerprint;
}
/**
 * Build the cache key for a CSS input and the options that shape parse state.
 *
 * @param string $css CSS input
 * @param array $options Compilation options
 * @return string Cache key
 */
function stateKey(string $css, array $options = []): string
{
    return hash('sha256', implode('|', [(string) STATE_CACHE_VERSION, engineFingerprint(), $css, $options['loadDefaultTheme'] ?? \true ? '1' : '0', (string) ($options['base'] ?? ''), implode(',', array_map('strval', $options['importSearchPaths'] ?? []))]));
}
/**
 * Load a cached parse state.
 *
 * @param string $cacheFile Cache file path
 * @param string $key Expected cache key
 * @return array{ast: array, state: array}|null Rehydrated state, or null on miss
 */
function loadState(string $cacheFile, string $key): ?array
{
    if (!is_file($cacheFile)) {
        return null;
    }
    $payload = @include $cacheFile;
    if (!is_array($payload) || ($payload['version'] ?? null) !== STATE_CACHE_VERSION || ($payload['key'] ?? null) !== $key || !is_array($payload['ast'] ?? null) || !is_array($payload['state'] ?? null) || !is_string($payload['theme'] ?? null)) {
        return null;
    }
    foreach ($payload['filesMeta'] ?? [] as $file => $meta) {
        if (@filemtime($file) !== ($meta[0] ?? null) || @filesize($file) !== ($meta[1] ?? null)) {
            return null;
        }
    }
    $theme = @unserialize($payload['theme'], ['allowed_classes' => [Theme::class]]);
    if (!$theme instanceof Theme) {
        return null;
    }
    $state = $payload['state'];
    $state['theme'] = $theme;
    return ['ast' => $payload['ast'], 'state' => $state];
}
/**
 * Persist a parse state atomically.
 *
 * @param string $cacheFile Cache file path
 * @param string $key Cache key
 * @param array $ast Import-substituted AST captured after parseCssState()
 * @param array $state parseCssState() result
 * @return bool Whether the state was written
 */
function saveState(string $cacheFile, string $key, array $ast, array $state): bool
{
    $dir = dirname($cacheFile);
    if (!is_dir($dir) && !@mkdir($dir, 0755, \true) && !is_dir($dir)) {
        return \false;
    }
    $theme = $state['theme'];
    if (!$theme instanceof Theme) {
        return \false;
    }
    unset($state['theme']);
    $filesMeta = [];
    foreach ($state['files'] ?? [] as $file) {
        if (is_string($file) && $file !== '') {
            $filesMeta[$file] = [@filemtime($file), @filesize($file)];
        }
    }
    $payload = "<?php\n\nreturn [\n" . "    'version' => " . var_export(STATE_CACHE_VERSION, \true) . ",\n" . "    'key' => " . var_export($key, \true) . ",\n" . "    'filesMeta' => " . var_export($filesMeta, \true) . ",\n" . "    'theme' => " . var_export(serialize($theme), \true) . ",\n" . "    'ast' => " . var_export($ast, \true) . ",\n" . "    'state' => " . var_export($state, \true) . ",\n" . "];\n";
    $tmp = $cacheFile . '.tmp-' . bin2hex(random_bytes(8));
    if (@file_put_contents($tmp, $payload, \LOCK_EX) === \false) {
        return \false;
    }
    if (!@rename($tmp, $cacheFile)) {
        @unlink($tmp);
        return \false;
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($cacheFile, \true);
    }
    return \true;
}
/**
 * Compile CSS input with a persistent construction-state cache.
 *
 * Behaves exactly like parse() + compileAst() and returns the same compiled
 * array. Inputs using a custom importResolver bypass the cache because the
 * resolver's behavior cannot be fingerprinted.
 *
 * @param string $css CSS input
 * @param string $cacheFile Cache file path owned by the caller
 * @param array $options Compilation options
 * @return array Compiled result with build/buildExact closures
 */
function compileCssCached(string $css, string $cacheFile, array $options = []): array
{
    if (isset($options['importResolver'])) {
        $ast = parse($css);
        return compileAst($ast, $options);
    }
    $key = stateKey($css, $options);
    $cached = loadState($cacheFile, $key);
    if ($cached !== null) {
        $ast = $cached['ast'];
        $parsed = finalizeCssState($ast, $cached['state'], $options);
        return compileParsed($ast, $parsed, $options);
    }
    $ast = parse($css);
    $state = parseCssState($ast, $options);
    saveState($cacheFile, $key, $ast, $state);
    $parsed = finalizeCssState($ast, $state, $options);
    return compileParsed($ast, $parsed, $options);
}
