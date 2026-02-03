<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP;

use function BlockstudioVendor\TailwindPHP\Ast\atRule;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Ast\styleRule;
use function BlockstudioVendor\TailwindPHP\Ast\toCss;
use function BlockstudioVendor\TailwindPHP\CssParser\parse;
use function BlockstudioVendor\TailwindPHP\DesignSystem\buildDesignSystem;
use BlockstudioVendor\TailwindPHP\DesignSystem\DesignSystem;
use BlockstudioVendor\TailwindPHP\LightningCss\LightningCss;
use BlockstudioVendor\TailwindPHP\Utilities\Utilities;
use BlockstudioVendor\TailwindPHP\Variants\Variants;
use function BlockstudioVendor\TailwindPHP\Walk\walk;
use BlockstudioVendor\TailwindPHP\Walk\WalkAction;
/**
 * TailwindPHP - CSS-first Tailwind CSS compiler for PHP.
 *
 * Port of: packages/tailwindcss/src/index.ts
 *
 * @port-deviation:async TypeScript uses async/await throughout (parseCss, compile).
 * PHP uses synchronous execution since PHP doesn't have native async support.
 *
 * @port-deviation:sourcemaps TypeScript creates source maps via createSourceMap().
 * PHP omits source map generation entirely.
 *
 * @port-deviation:modules TypeScript has loadModule/loadStylesheet for dynamic imports.
 * PHP uses inline file loading via file_get_contents() or requires.
 *
 * @port-deviation:plugins TypeScript supports JS plugins via @plugin/@config directives.
 * PHP does not support JS plugins - all utilities are implemented in PHP directly.
 *
 * @port-deviation:lightningcss TypeScript uses lightningcss (Rust) for CSS transforms.
 * PHP uses LightningCss.php class for equivalent transformations in pure PHP.
 */
// Polyfill flags
const POLYFILL_NONE = 0;
const POLYFILL_AT_PROPERTY = 1 << 0;
const POLYFILL_COLOR_MIX = 1 << 1;
const POLYFILL_ALL = POLYFILL_AT_PROPERTY | POLYFILL_COLOR_MIX;
// Feature flags
const FEATURE_NONE = 0;
const FEATURE_AT_APPLY = 1 << 0;
const FEATURE_AT_IMPORT = 1 << 1;
const FEATURE_JS_PLUGIN_COMPAT = 1 << 2;
const FEATURE_THEME_FUNCTION = 1 << 3;
const FEATURE_UTILITIES = 1 << 4;
const FEATURE_VARIANTS = 1 << 5;
const FEATURE_AT_THEME = 1 << 6;
/** @var Theme|null Cached default theme instance */
$_defaultThemeCache = null;
/**
 * Virtual modules that should not be resolved from the filesystem.
 */
const VIRTUAL_MODULES = ['tailwindcss', 'tailwindcss/theme', 'tailwindcss/theme.css', 'tailwindcss/preflight', 'tailwindcss/preflight.css', 'tailwindcss/utilities', 'tailwindcss/utilities.css', 'tw-animate-css'];
/**
 * Pre-compiled regex patterns for performance.
 *
 * @port-deviation:performance Regex patterns are compiled once at module load
 * rather than on every function call. This avoids repeated regex compilation
 * overhead in hot paths like extractCandidates() and theme value resolution.
 */
const REGEX_CLASS_ATTR = '/class\s*=\s*["\']([^"\']+)["\']/';
const REGEX_CLASSNAME_ATTR = '/className\s*=\s*["\']([^"\']+)["\']/';
const REGEX_WHITESPACE = '/\s+/';
const REGEX_UNESCAPE = '/\\\\(.)/';
const REGEX_THEME_CALL = '/--theme\(([^)]+)\)/';
const REGEX_THEME_CALL_FULL = '/^--theme\(([^)]+)\)$/';
const REGEX_VAR_EXTRACT = '/var\(\s*(--[^\s\)\'\",]+)/';
const REGEX_VAR_SIMPLE = '/var\(\s*(--[a-zA-Z0-9_-]+)/';
const REGEX_PREFIX_MOD = '/prefix\(([^)]+)\)/';
const REGEX_LAYER_MOD = '/layer\(([^)]+)\)/';
const REGEX_LAYER_BARE = '/\blayer\b(?!\()/';
const REGEX_SUPPORTS_MOD = '/supports\(([^)]+)\)/';
const REGEX_AT_RULE_PARAM = '/^(@[a-z-]+)\s*(.*)$/';
const REGEX_IMPORT_PARTS = '/^["\']([^"\']+)["\']\s*(.*)$/';
const REGEX_ANIMATE_KEY = '/^--(?:[a-z]+-)?animate/';
const REGEX_NUMERIC_START = '/^[\d.]+/';
const REGEX_TIME_VALUE = '/^-?[\d.]+(?:s|ms|%)$/';
const REGEX_CAMEL_TO_KEBAB = '/([A-Z])/';
const REGEX_COLOR_MIX_VAR = '/color-mix\s*\(\s*in\s+oklab\s*,\s*([^,]+)\s+var\s*\([^)]+\)\s*,\s*transparent\s*\)/i';
const REGEX_COLOR_MIX_OPACITY = '/color-mix\s*\(\s*in\s+oklab\s*,\s*var\s*\(\s*([^)]+)\s*\)\s+(\d+(?:\.\d+)?%?)\s*,\s*transparent\s*\)/i';
/**
 * Split a string by comma, respecting parenthesis depth.
 *
 * Uses array accumulation instead of string concatenation for performance.
 *
 * @port-deviation:performance Uses array + implode instead of string concat
 * in the loop, which is faster in PHP for building strings character by character.
 *
 * @param string $str The string to split
 * @return array<string> Array of trimmed parts
 */
function splitByCommaRespectingParens(string $str): array
{
    $parts = [];
    $chars = [];
    $depth = 0;
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $char = $str[$i];
        if ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
        }
        if ($char === ',' && $depth === 0) {
            $parts[] = trim(implode('', $chars));
            $chars = [];
        } else {
            $chars[] = $char;
        }
    }
    if (!empty($chars)) {
        $parts[] = trim(implode('', $chars));
    }
    return $parts;
}
/**
 * Resolve import paths and load CSS content.
 *
 * Handles:
 * - Single file path (ends with .css)
 * - Directory path (loads all .css files)
 * - Array of files/directories
 * - Callable resolver function
 *
 * @param string|array|callable|null $importPaths Import paths configuration
 * @return array{css: string, paths: array<string>} Loaded CSS and list of resolved paths for @import resolution
 */
function resolveImportPaths(string|array|callable|null $importPaths): array
{
    if ($importPaths === null) {
        return ['css' => '', 'paths' => []];
    }
    // Callable resolver - call with null to get root CSS
    if (is_callable($importPaths)) {
        $css = $importPaths(null, null) ?? '';
        return ['css' => $css, 'paths' => []];
    }
    $paths = is_array($importPaths) ? $importPaths : [$importPaths];
    $css = '';
    $resolvedPaths = [];
    foreach ($paths as $path) {
        $path = rtrim($path, '/\\');
        if (str_ends_with($path, '.css') && is_file($path)) {
            // Single CSS file
            $content = file_get_contents($path);
            if ($content !== \false) {
                $css .= $content . "\n";
                $resolvedPaths[] = dirname($path);
            }
        } elseif (is_dir($path)) {
            // Directory - load all .css files
            $files = glob($path . '/*.css');
            if ($files) {
                // Sort for consistent ordering
                sort($files);
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if ($content !== \false) {
                        $css .= $content . "\n";
                    }
                }
                $resolvedPaths[] = $path;
            }
        }
    }
    return ['css' => $css, 'paths' => $resolvedPaths];
}
/**
 * Check if a URI is a virtual module.
 *
 * @param string $uri Import URI
 * @return bool True if virtual module
 */
function isVirtualModule(string $uri): bool
{
    return in_array($uri, VIRTUAL_MODULES, \true);
}
/**
 * Resolve a file import URI to an absolute path.
 *
 * @param string $uri Import URI (e.g., "./components.css", "buttons.css")
 * @param string|null $fromFile Absolute path of the file containing the @import
 * @param array $searchPaths Additional paths to search
 * @return string|null Absolute path if found, null otherwise
 */
function resolveImportUri(string $uri, ?string $fromFile, array $searchPaths): ?string
{
    // Skip virtual modules
    if (isVirtualModule($uri)) {
        return null;
    }
    // Skip data URIs and remote URLs
    if (str_starts_with($uri, 'data:') || str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
        return null;
    }
    // Handle absolute paths (Unix style starting with /)
    if (str_starts_with($uri, '/')) {
        $resolved = realpath($uri);
        if ($resolved !== \false && is_file($resolved)) {
            return $resolved;
        }
        return null;
    }
    // Relative import - resolve from the importing file's directory
    if ((str_starts_with($uri, './') || str_starts_with($uri, '../')) && $fromFile !== null) {
        $fromDir = dirname($fromFile);
        $resolved = realpath($fromDir . '/' . $uri);
        if ($resolved !== \false && is_file($resolved)) {
            return $resolved;
        }
    }
    // Search in import paths
    foreach ($searchPaths as $searchPath) {
        $searchPath = rtrim($searchPath, '/\\');
        // Try direct path
        $resolved = realpath($searchPath . '/' . $uri);
        if ($resolved !== \false && is_file($resolved)) {
            return $resolved;
        }
        // Try without leading ./
        if (str_starts_with($uri, './')) {
            $resolved = realpath($searchPath . '/' . substr($uri, 2));
            if ($resolved !== \false && is_file($resolved)) {
                return $resolved;
            }
        }
    }
    return null;
}
/**
 * Parse @import modifiers into layer, supports, and media components.
 *
 * Handles the full @import syntax:
 * - @import "file.css" layer(name);
 * - @import "file.css" layer;  (anonymous layer)
 * - @import "file.css" supports(condition);
 * - @import "file.css" screen and (min-width: 768px);
 * - @import "file.css" layer(components) supports(display: grid) screen;
 *
 * @param string $modifiers The modifiers string after the import path
 * @return array{layer: string|null, supports: string|null, media: string|null}
 */
function parseImportModifiers(string $modifiers): array
{
    $layer = null;
    $supports = null;
    $media = null;
    $remaining = trim($modifiers);
    // Extract layer(name) - must come first per CSS spec
    if (preg_match(REGEX_LAYER_MOD, $remaining, $layerMatch)) {
        $layer = $layerMatch[1];
        $remaining = trim(preg_replace(REGEX_LAYER_MOD, '', $remaining, 1));
    } elseif (preg_match(REGEX_LAYER_BARE, $remaining)) {
        // Anonymous layer (just 'layer' without parentheses)
        $layer = '';
        $remaining = trim(preg_replace(REGEX_LAYER_BARE, '', $remaining, 1));
    }
    // Extract supports(condition)
    if (preg_match(REGEX_SUPPORTS_MOD, $remaining, $supportsMatch)) {
        $supports = $supportsMatch[1];
        $remaining = trim(preg_replace(REGEX_SUPPORTS_MOD, '', $remaining, 1));
    }
    // Everything remaining is the media query
    if (!empty($remaining)) {
        $media = $remaining;
    }
    return ['layer' => $layer, 'supports' => $supports, 'media' => $media];
}
/**
 * Wrap AST nodes with layer, media, and supports at-rules.
 *
 * This replicates the buildImportNodes logic from at-import.php for use
 * in the compile() import handling path.
 *
 * @param array $ast The AST nodes to wrap
 * @param string|null $layer Layer name (empty string for anonymous layer)
 * @param string|null $supports Supports condition
 * @param string|null $media Media query
 * @return array The wrapped AST nodes
 */
function wrapImportedAst(array $ast, ?string $layer, ?string $supports, ?string $media): array
{
    $root = $ast;
    // Layer wrapping (innermost)
    if ($layer !== null) {
        $root = [atRule('@layer', $layer, $root)];
    }
    // Media query wrapping
    if ($media !== null) {
        $root = [atRule('@media', $media, $root)];
    }
    // Supports wrapping (outermost)
    if ($supports !== null) {
        $supportsValue = $supports[0] === '(' ? $supports : "({$supports})";
        $root = [atRule('@supports', $supportsValue, $root)];
    }
    return $root;
}
/**
 * Create a file loader function for substituteAtImports.
 *
 * @param array $searchPaths Paths to search for imports
 * @param array &$seenFiles Tracks already-processed files to prevent duplicates
 * @param callable|null $customResolver Optional custom resolver function
 * @return callable Loader function compatible with substituteAtImports
 */
function createFileLoader(array $searchPaths, array &$seenFiles, ?callable $customResolver = null): callable
{
    return function (string $uri, string $base) use ($searchPaths, &$seenFiles, $customResolver): array {
        // Skip virtual modules - they're handled elsewhere
        if (isVirtualModule($uri)) {
            return ['path' => $uri, 'base' => $base, 'content' => ''];
        }
        // Custom resolver takes precedence
        if ($customResolver !== null) {
            $content = $customResolver($uri, $base);
            if ($content !== null) {
                // Generate a pseudo-path for tracking
                $path = $base . '/' . $uri;
                if (isset($seenFiles[$path])) {
                    return ['path' => $path, 'base' => $base, 'content' => ''];
                }
                $seenFiles[$path] = \true;
                return ['path' => $path, 'base' => dirname($path), 'content' => $content];
            }
        }
        // Try to resolve the file path
        $fromFile = $base !== '' ? $base . '/dummy.css' : null;
        $resolved = resolveImportUri($uri, $fromFile, $searchPaths);
        if ($resolved === null) {
            // File not found - return empty (silently skip like Tailwind)
            return ['path' => $uri, 'base' => $base, 'content' => ''];
        }
        // Check if already seen (deduplication)
        if (isset($seenFiles[$resolved])) {
            return ['path' => $resolved, 'base' => dirname($resolved), 'content' => ''];
        }
        $seenFiles[$resolved] = \true;
        // Read the file
        $content = file_get_contents($resolved);
        if ($content === \false) {
            return ['path' => $resolved, 'base' => dirname($resolved), 'content' => ''];
        }
        return ['path' => $resolved, 'base' => dirname($resolved), 'content' => $content];
    };
}
/**
 * Static cache for resource files.
 *
 * @port-deviation:performance Resource file contents are cached to avoid
 * repeated file I/O on multiple generate() calls. The original TypeScript
 * relies on Node.js module caching for similar behavior.
 */
$_resourceFileCache = [];
/**
 * Read a resource file with error checking.
 *
 * @param string $filename The filename relative to resources directory
 * @return string The file contents
 * @throws \RuntimeException If file cannot be read
 */
function readResourceFile(string $filename): string
{
    global $_resourceFileCache;
    if (isset($_resourceFileCache[$filename])) {
        return $_resourceFileCache[$filename];
    }
    $path = __DIR__ . '/../resources/' . $filename;
    if (!file_exists($path)) {
        throw new \RuntimeException("Resource file not found: {$path}");
    }
    $contents = file_get_contents($path);
    if ($contents === \false) {
        throw new \RuntimeException("Failed to read resource file: {$path}");
    }
    $_resourceFileCache[$filename] = $contents;
    return $contents;
}
/**
 * Load and parse the default Tailwind theme from theme.css.
 *
 * @return Theme
 */
function loadDefaultTheme(): Theme
{
    global $_defaultThemeCache;
    if ($_defaultThemeCache !== null) {
        // Return a clone so modifications don't affect the cached instance
        return clone $_defaultThemeCache;
    }
    $css = readResourceFile('theme.css');
    $ast = parse($css);
    $theme = new Theme();
    // Walk AST to extract @theme declarations
    walk($ast, function (&$node) use ($theme) {
        if ($node['kind'] !== 'at-rule' || $node['name'] !== '@theme') {
            return WalkAction::Continue;
        }
        // Parse theme options from params (e.g., "default", "default inline reference")
        [$themeOptions, $themePrefix] = parseThemeOptions($node['params'] ?? '');
        // Process declarations and keyframes
        foreach ($node['nodes'] ?? [] as $child) {
            if ($child['kind'] === 'declaration' && str_starts_with($child['property'], '--')) {
                $property = preg_replace(REGEX_UNESCAPE, '$1', $child['property']);
                $value = $child['value'] ?? '';
                $theme->add($property, $value, $themeOptions);
            } elseif ($child['kind'] === 'at-rule' && $child['name'] === '@keyframes') {
                $theme->addKeyframes($child, $themeOptions);
            }
        }
        return WalkAction::Continue;
    });
    $_defaultThemeCache = $theme;
    return clone $_defaultThemeCache;
}
/**
 * Compile CSS with Tailwind utilities.
 *
 * This is the advanced compilation API that returns a reusable compiler.
 * The returned `build` function can be called multiple times with different
 * candidate sets, useful for watch mode or incremental compilation.
 *
 * @param string $css Input CSS containing @import, @theme, @utility directives
 * @param array{
 *     base?: string,
 *     importSearchPaths?: array<string>,
 *     importResolver?: callable(string, string): ?string,
 *     onDependency?: callable(string): void
 * } $options Compilation options:
 *   - `base`: Base directory for resolving relative imports
 *   - `importSearchPaths`: Additional directories to search for imports
 *   - `importResolver`: Custom resolver for virtual file systems
 *   - `onDependency`: Callback invoked when a file dependency is detected
 *
 * @return array{
 *     build: callable(array<string>): string,
 *     sources: array<string>,
 *     root: array,
 *     features: int
 * } Compilation result:
 *   - `build`: Function that takes candidate class names and returns CSS
 *   - `sources`: Detected content source patterns
 *   - `root`: Compiled AST root
 *   - `features`: Bitmask of detected features
 *
 * @throws \Exception When CSS parsing fails
 * @throws \Exception When @utility or @custom-variant directives are invalid
 * @throws \RuntimeException When @import resolution fails or exceeds depth limit
 *
 * @example Basic compilation:
 *   $compiled = compile('@import "tailwindcss";');
 *   $css = $compiled['build'](['flex', 'p-4', 'text-red-500']);
 *
 * @example With custom theme:
 *   $compiled = compile('@import "tailwindcss"; @theme { --color-brand: #3b82f6; }');
 *   $css = $compiled['build'](['bg-brand', 'text-brand']);
 *
 * @example With file imports:
 *   $compiled = compile($css, ['importSearchPaths' => ['/path/to/styles']]);
 */
function compile(string $css, array $options = []): array
{
    $ast = parse($css);
    return compileAst($ast, $options);
}
/**
 * Compile AST with Tailwind utilities.
 *
 * @param array $ast CSS AST
 * @param array $options Compilation options
 * @return array{build: callable, sources: array, root: mixed, features: int}
 */
function compileAst(array $ast, array $options = []): array
{
    $result = parseCss($ast, $options);
    $designSystem = $result['designSystem'];
    $sources = $result['sources'];
    $root = $result['root'];
    $utilitiesNodePath = $result['utilitiesNodePath'];
    $features = $result['features'];
    $inlineCandidates = $result['inlineCandidates'];
    // Substitute CSS functions (theme(), --theme(), --spacing(), --alpha())
    $features |= substituteFunctions($ast, $designSystem);
    // Process @apply directives first (this also handles @utility with @apply inside)
    // substituteAtApply will:
    // 1. Process @apply inside @utility definitions (topological order)
    // 2. Register @utility definitions with the design system
    // 3. Process remaining @apply rules
    $features |= substituteAtApply($ast, $designSystem);
    // Remove @utility nodes from AST (after @apply has processed them)
    walk($ast, function (&$node) {
        if ($node['kind'] !== 'at-rule') {
            return WalkAction::Continue;
        }
        if ($node['name'] === '@utility') {
            return WalkAction::Replace([]);
        }
        // @utility has to be top-level, so we don't need to traverse into nested trees
        return WalkAction::Skip;
    });
    $allValidCandidates = [];
    $compiled = null;
    $previousAstNodeCount = 0;
    foreach ($inlineCandidates as $candidate) {
        if (!$designSystem->hasInvalidCandidate($candidate)) {
            $allValidCandidates[$candidate] = \true;
        }
    }
    // Helper to find and update the utilities context node
    $updateUtilitiesNode = function (array &$ast, array $newNodes) {
        walk($ast, function (&$node) use ($newNodes) {
            // Find the context node that was converted from @tailwind utilities
            if ($node['kind'] === 'context' && isset($node['context']) && is_array($node['context'])) {
                $node['nodes'] = $newNodes;
                return WalkAction::Stop;
            }
            return WalkAction::Continue;
        });
    };
    return ['sources' => $sources, 'root' => $root, 'features' => $features, 'build' => function (array $newRawCandidates) use (&$allValidCandidates, &$compiled, &$previousAstNodeCount, $designSystem, $utilitiesNodePath, &$ast, $features, $options, $updateUtilitiesNode) {
        if ($features === FEATURE_NONE) {
            return toCss($ast);
        }
        if ($utilitiesNodePath === null) {
            if ($compiled === null) {
                $compiled = optimizeAst($ast, $designSystem, $options['polyfills'] ?? POLYFILL_ALL);
            }
            return toCss($compiled);
        }
        // Track if this is the first build call with inline candidates
        $hasInlineCandidates = !empty($allValidCandidates) && $compiled === null;
        $didChange = $hasInlineCandidates;
        // Add all new candidates unless we know they are invalid
        $prevSize = count($allValidCandidates);
        foreach ($newRawCandidates as $candidate) {
            if (!$designSystem->hasInvalidCandidate($candidate)) {
                if (str_starts_with($candidate, '--')) {
                    $didMarkVariableAsUsed = $designSystem->getTheme()->markUsedVariable($candidate);
                    $didChange = $didChange || $didMarkVariableAsUsed;
                } else {
                    $allValidCandidates[$candidate] = \true;
                    $didChange = $didChange || count($allValidCandidates) !== $prevSize;
                }
            }
        }
        // If no new candidates were added and no inline candidates to process, return cached result
        if (!$didChange) {
            if ($compiled === null) {
                $compiled = optimizeAst($ast, $designSystem, $options['polyfills'] ?? POLYFILL_ALL);
            }
            return toCss($compiled);
        }
        $compileResult = \BlockstudioVendor\TailwindPHP\Compile\compileCandidates(array_keys($allValidCandidates), $designSystem, ['onInvalidCandidate' => function ($candidate) use ($designSystem) {
            $designSystem->addInvalidCandidate($candidate);
        }]);
        $newNodes = $compileResult['astNodes'];
        // Apply CSS function substitution to compiled utilities (resolves theme() etc.)
        substituteFunctions($newNodes, $designSystem);
        // If no new nodes were generated, return cached result
        if ($previousAstNodeCount === count($newNodes)) {
            if ($compiled === null) {
                $compiled = optimizeAst($ast, $designSystem, $options['polyfills'] ?? POLYFILL_ALL);
            }
            return toCss($compiled);
        }
        $previousAstNodeCount = count($newNodes);
        // Update the context node with the compiled utilities
        $updateUtilitiesNode($ast, $newNodes);
        $compiled = optimizeAst($ast, $designSystem, $options['polyfills'] ?? POLYFILL_ALL);
        return toCss($compiled);
    }];
}
/**
 * Parse CSS and extract theme, utilities, variants, etc.
 *
 * @param array $ast CSS AST
 * @param array $options Parse options
 * @return array
 */
function parseCss(array &$ast, array $options = []): array
{
    $features = FEATURE_NONE;
    // Use default theme unless 'loadDefaultTheme' option is explicitly false
    $loadDefaultTheme = $options['loadDefaultTheme'] ?? \true;
    $theme = $loadDefaultTheme ? loadDefaultTheme() : new Theme();
    $utilitiesNodePath = null;
    $sources = [];
    $inlineCandidates = [];
    $ignoredCandidates = [];
    $root = null;
    $firstThemeRule = null;
    $important = \false;
    $customVariants = [];
    // Collect @custom-variant definitions
    $plugins = [];
    // Collect @plugin directives
    // Import deduplication tracking (persists across walk iterations)
    $seenFiles = [];
    $seenVirtualModules = [];
    // Walk AST to find @tailwind utilities, @theme, @source, @utility, @custom-variant, @plugin, @media important
    walk($ast, function (&$node, $ctx) use (&$features, &$theme, &$utilitiesNodePath, &$sources, &$inlineCandidates, &$ignoredCandidates, &$firstThemeRule, &$important, &$customVariants, &$plugins, $options, &$seenFiles, &$seenVirtualModules) {
        if ($node['kind'] !== 'at-rule') {
            return WalkAction::Continue;
        }
        // Handle @tailwind utilities - can be nested (e.g., inside #app {})
        if ($node['name'] === '@tailwind' && ($node['params'] === 'utilities' || str_starts_with($node['params'], 'utilities'))) {
            // Any additional @tailwind utilities nodes can be removed
            if ($utilitiesNodePath !== null) {
                return WalkAction::Replace([]);
            }
            // Store the path to this node for later modification
            $utilitiesNodePath = $ctx->path();
            $utilitiesNodePath[] = $node;
            // Add current node to path
            $features |= FEATURE_UTILITIES;
            // Convert the @tailwind node to a context node in place
            // This is how the TypeScript does it - mutate in place
            $node['kind'] = 'context';
            $node['context'] = [];
            $node['nodes'] = [];
            unset($node['name']);
            unset($node['params']);
            return WalkAction::Skip;
        }
        // Handle @theme
        if ($node['name'] === '@theme') {
            $features |= FEATURE_AT_THEME;
            [$themeOptions, $themePrefix] = parseThemeOptions($node['params']);
            // Validate and apply prefix
            if ($themePrefix !== null) {
                if (!preg_match(IS_VALID_PREFIX, $themePrefix)) {
                    throw new \Exception("The prefix \"{$themePrefix}\" is invalid. Prefixes must be lowercase ASCII letters (a-z) only.");
                }
                $theme->prefix = $themePrefix;
            }
            // Process theme declarations and keyframes
            foreach ($node['nodes'] ?? [] as $child) {
                if ($child['kind'] === 'declaration' && str_starts_with($child['property'], '--')) {
                    // Unescape CSS escape sequences in property names (e.g., \* -> *)
                    $property = preg_replace(REGEX_UNESCAPE, '$1', $child['property']);
                    $value = $child['value'] ?? '';
                    // Store the raw value including --theme() calls
                    // They will be resolved later by substituteFunctions()
                    $theme->add($property, $value, $themeOptions);
                } elseif ($child['kind'] === 'at-rule' && $child['name'] === '@keyframes') {
                    $theme->addKeyframes($child, $themeOptions);
                }
            }
            // Keep a reference to the first @theme rule to update with the full
            // theme later, and delete any other @theme rules.
            if ($firstThemeRule === null) {
                $firstThemeRule = styleRule(':root, :host', []);
                return WalkAction::ReplaceSkip($firstThemeRule);
            } else {
                return WalkAction::ReplaceSkip([]);
            }
        }
        // Handle @source
        if ($node['name'] === '@source') {
            // Validate: @source cannot have a body
            if (!empty($node['nodes'])) {
                throw new \Exception('`@source` cannot have a body.');
            }
            // Validate: @source cannot be nested
            if ($ctx->parent !== null) {
                throw new \Exception('`@source` cannot be nested.');
            }
            $not = \false;
            $inline = \false;
            $path = $node['params'];
            // Check for 'not' prefix
            if (str_starts_with($path, 'not ')) {
                $not = \true;
                $path = substr($path, 4);
            }
            // Check for 'inline()' wrapper
            if (str_starts_with($path, 'inline(') && str_ends_with($path, ')')) {
                $inline = \true;
                $path = substr($path, 7, -1);
            }
            // Validate: paths must be quoted
            if ($path[0] === '"' && $path[strlen($path) - 1] !== '"' || $path[0] === "'" && $path[strlen($path) - 1] !== "'" || $path[0] !== "'" && $path[0] !== '"') {
                throw new \Exception('`@source` paths must be quoted.');
            }
            $source = substr($path, 1, -1);
            if ($inline) {
                // Inline candidates: expand brace patterns and add to appropriate list
                $destination = $not ? 'ignored' : 'inline';
                $parts = \BlockstudioVendor\TailwindPHP\Utils\segment($source, ' ');
                foreach ($parts as $part) {
                    foreach (\BlockstudioVendor\TailwindPHP\Utils\expand($part) as $candidate) {
                        if ($destination === 'ignored') {
                            $ignoredCandidates[] = $candidate;
                        } else {
                            $inlineCandidates[] = $candidate;
                        }
                    }
                }
            } else {
                // File/directory source pattern
                $sources[] = ['base' => $options['base'] ?? '', 'pattern' => $source, 'negated' => $not];
            }
            return WalkAction::ReplaceSkip([]);
        }
        // Handle @import - especially for 'tailwindcss' module
        if ($node['name'] === '@import') {
            $params = $node['params'];
            // Parse the import path and modifiers
            // e.g., "'tailwindcss' theme(inline)" or "'tailwindcss/utilities' important"
            preg_match(REGEX_IMPORT_PARTS, $params, $matches);
            if ($matches) {
                $importPath = $matches[1];
                $modifiers = trim($matches[2] ?? '');
                // Deduplicate virtual module imports (tailwindcss, tailwindcss/*)
                // This applies to all virtual module imports, not just file-based ones
                if (isVirtualModule($importPath)) {
                    if (isset($seenVirtualModules[$importPath])) {
                        // Already imported - remove duplicate
                        return WalkAction::Replace([]);
                    }
                    $seenVirtualModules[$importPath] = \true;
                }
                // Handle 'tailwindcss' virtual module - full Tailwind CSS (theme + preflight + utilities)
                if ($importPath === 'tailwindcss') {
                    // Load theme.css
                    $themeCss = readResourceFile('theme.css');
                    $themeAst = parse($themeCss);
                    // Load preflight.css
                    $preflightCss = readResourceFile('preflight.css');
                    $preflightAst = parse($preflightCss);
                    // Create utilities node
                    $utilitiesNode = atRule('@tailwind', 'utilities', []);
                    // Apply modifiers to theme if present
                    if (str_contains($modifiers, 'theme(')) {
                        $themeAst = [atRule('@media', $modifiers, $themeAst)];
                    }
                    // Combine: theme + preflight + utilities
                    $fullContent = array_merge($themeAst, $preflightAst, [$utilitiesNode]);
                    return WalkAction::Replace($fullContent);
                }
                // Handle 'tailwindcss/theme' or 'tailwindcss/theme.css' - theme variables
                if ($importPath === 'tailwindcss/theme' || $importPath === 'tailwindcss/theme.css') {
                    $themeCss = readResourceFile('theme.css');
                    $themeAst = parse($themeCss);
                    // Check for prefix() modifier
                    if (preg_match(REGEX_PREFIX_MOD, $modifiers, $prefixMatch)) {
                        $themeAst = [atRule('@media', 'prefix(' . $prefixMatch[1] . ')', $themeAst)];
                    }
                    // Check for theme(static) modifier - theme values always included
                    if (str_contains($modifiers, 'theme(static)')) {
                        $themeAst = [atRule('@media', 'theme(static)', $themeAst)];
                    }
                    // Check for theme(inline) modifier - theme values inlined, not as variables
                    if (str_contains($modifiers, 'theme(inline)')) {
                        $themeAst = [atRule('@media', 'theme(inline)', $themeAst)];
                    }
                    // source(none) is a no-op in TailwindPHP since we don't do file scanning
                    // It's accepted for compatibility with official Tailwind CSS syntax
                    // Check for layer() modifier
                    if (preg_match(REGEX_LAYER_MOD, $modifiers, $layerMatch)) {
                        return WalkAction::Replace([atRule('@layer', $layerMatch[1], $themeAst)]);
                    }
                    return WalkAction::Replace($themeAst);
                }
                // Handle 'tailwindcss/utilities' or 'tailwindcss/utilities.css'
                if ($importPath === 'tailwindcss/utilities' || $importPath === 'tailwindcss/utilities.css') {
                    $utilityNode = atRule('@tailwind', 'utilities', []);
                    // If there's an 'important' modifier
                    if (str_contains($modifiers, 'important')) {
                        $utilityNode = atRule('@media', 'important', [$utilityNode]);
                    }
                    // If there's a prefix() modifier, wrap in @media prefix()
                    if (preg_match(REGEX_PREFIX_MOD, $modifiers, $prefixMatch)) {
                        $utilityNode = atRule('@media', 'prefix(' . $prefixMatch[1] . ')', [$utilityNode]);
                    }
                    // If there's a layer() modifier, wrap in @layer
                    if (preg_match(REGEX_LAYER_MOD, $modifiers, $layerMatch)) {
                        return WalkAction::Replace([atRule('@layer', $layerMatch[1], [$utilityNode])]);
                    }
                    return WalkAction::Replace([$utilityNode]);
                }
                // Handle 'tailwindcss/preflight' - CSS reset/base styles
                if ($importPath === 'tailwindcss/preflight' || $importPath === 'tailwindcss/preflight.css') {
                    $preflightCss = readResourceFile('preflight.css');
                    $preflightAst = parse($preflightCss);
                    // Check for layer(base) modifier
                    if (str_contains($modifiers, 'layer(base)')) {
                        return WalkAction::Replace([atRule('@layer', 'base', $preflightAst)]);
                    }
                    return WalkAction::Replace($preflightAst);
                }
                // Handle 'tw-animate-css' - animation utilities for shadcn/ui
                if ($importPath === 'tw-animate-css') {
                    $animateCss = readResourceFile('tw-animate.css');
                    $animateAst = parse($animateCss);
                    return WalkAction::Replace($animateAst);
                }
                // Handle file-based imports if import resolution is enabled
                $searchPaths = $options['importSearchPaths'] ?? [];
                $importResolver = $options['importResolver'] ?? null;
                if (!empty($searchPaths) || $importResolver !== null) {
                    // Virtual modules are already deduplicated above, so skip them here
                    if (isVirtualModule($importPath)) {
                        return WalkAction::Continue;
                    }
                    // Try to resolve file-based import
                    // Note: $seenFiles is captured by reference from outer scope
                    $loader = createFileLoader($searchPaths, $seenFiles, $importResolver);
                    $loaded = $loader($importPath, $options['base'] ?? '');
                    if (!empty($loaded['content'])) {
                        // Parse the imported CSS
                        $importedAst = parse($loaded['content']);
                        // Recursively resolve imports in the loaded file
                        substituteAtImports($importedAst, $loaded['base'], $loader, 0, \false);
                        // Parse all import modifiers (layer, supports, media)
                        $parsedMods = parseImportModifiers($modifiers);
                        // Wrap with all applicable modifiers
                        $wrappedAst = wrapImportedAst($importedAst, $parsedMods['layer'], $parsedMods['supports'], $parsedMods['media']);
                        return WalkAction::Replace($wrappedAst);
                    }
                }
            }
            // For other imports, leave as-is (will be output in final CSS)
            return WalkAction::Continue;
        }
        // Handle @utility - validate name but don't register yet
        // Registration happens AFTER @apply processing in compileAst
        if ($node['name'] === '@utility') {
            if ($ctx->parent !== null) {
                throw new \Exception('`@utility` cannot be nested.');
            }
            if (empty($node['nodes'])) {
                throw new \Exception("`@utility {$node['params']}` is empty. Utilities should include at least one property.");
            }
            // Validate utility name
            $name = $node['params'];
            if (!preg_match(IS_VALID_FUNCTIONAL_UTILITY_NAME, $name) && !preg_match(IS_VALID_STATIC_UTILITY_NAME, $name)) {
                if (str_ends_with($name, '-*')) {
                    throw new \Exception("`@utility {$name}` defines an invalid utility name. Utilities should be alphanumeric and start with a lowercase letter.");
                } elseif (str_contains($name, '*')) {
                    throw new \Exception("`@utility {$name}` defines an invalid utility name. The dynamic portion marked by `-*` must appear once at the end.");
                }
                throw new \Exception("`@utility {$name}` defines an invalid utility name. Utilities should be alphanumeric and start with a lowercase letter.");
            }
            // Mark as having custom utilities feature
            $features |= FEATURE_AT_APPLY;
            // Don't remove or register yet - will be done after @apply processing
            return WalkAction::Skip;
        }
        // Handle @custom-variant
        if ($node['name'] === '@custom-variant') {
            if ($ctx->parent !== null) {
                throw new \Exception('`@custom-variant` cannot be nested.');
            }
            $params = $node['params'] ?? '';
            $parts = \BlockstudioVendor\TailwindPHP\Utils\segment($params, ' ');
            $name = $parts[0] ?? '';
            $selector = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : null;
            if (!preg_match(\BlockstudioVendor\TailwindPHP\Variants\IS_VALID_VARIANT_NAME, $name)) {
                throw new \Exception("`@custom-variant {$name}` defines an invalid variant name. Variants should only contain alphanumeric, dashes, or underscore characters and start with a lowercase letter or number.");
            }
            $nodes = $node['nodes'] ?? [];
            if (count($nodes) > 0 && $selector) {
                throw new \Exception("`@custom-variant {$name}` cannot have both a selector and a body.");
            }
            // Store for later registration
            $customVariants[] = ['name' => $name, 'selector' => $selector, 'nodes' => $nodes];
            $features |= FEATURE_VARIANTS;
            return WalkAction::ReplaceSkip([]);
        }
        // Handle @plugin
        if ($node['name'] === '@plugin') {
            if ($ctx->parent !== null) {
                throw new \Exception('`@plugin` cannot be nested.');
            }
            // Extract plugin name from params (remove quotes)
            $pluginName = trim($node['params'], "\"'");
            if (empty($pluginName)) {
                throw new \Exception('`@plugin` requires a plugin name.');
            }
            // Parse any options from nested declarations
            $pluginOptions = [];
            foreach ($node['nodes'] ?? [] as $child) {
                if ($child['kind'] === 'declaration') {
                    $pluginOptions[$child['property']] = parsePluginOptionValue($child['value'] ?? '');
                }
            }
            $plugins[] = ['name' => $pluginName, 'options' => $pluginOptions];
            $features |= FEATURE_JS_PLUGIN_COMPAT;
            return WalkAction::ReplaceSkip([]);
        }
        // Handle @media important, @media theme(...), @media prefix(...)
        if ($node['name'] === '@media') {
            $params = \BlockstudioVendor\TailwindPHP\Utils\segment($node['params'], ' ');
            $unknownParams = [];
            foreach ($params as $param) {
                if ($param === 'important') {
                    $important = \true;
                } elseif (str_starts_with($param, 'theme(')) {
                    $themeParams = substr($param, 6, -1);
                    // extract from theme(...)
                    $hasReference = str_contains($themeParams, 'reference');
                    // Walk children and append theme params to @theme blocks
                    if (isset($node['nodes'])) {
                        walk($node['nodes'], function (&$child) use ($themeParams, $hasReference) {
                            if ($child['kind'] === 'context') {
                                return WalkAction::Continue;
                            }
                            if ($child['kind'] !== 'at-rule') {
                                if ($hasReference) {
                                    throw new \Exception("Files imported with `@import \"…\" theme(reference)` must only contain `@theme` blocks.\nUse `@reference \"…\";` instead.");
                                }
                                return WalkAction::Continue;
                            }
                            if ($child['name'] === '@theme') {
                                $child['params'] = trim($child['params'] . ' ' . $themeParams);
                                return WalkAction::Skip;
                            }
                            return WalkAction::Continue;
                        });
                    }
                } elseif (str_starts_with($param, 'prefix(')) {
                    $prefixValue = substr($param, 7, -1);
                    // extract from prefix(...)
                    // Walk children and append prefix to @theme blocks
                    if (isset($node['nodes'])) {
                        walk($node['nodes'], function (&$child) use ($prefixValue, $theme) {
                            if ($child['kind'] === 'context') {
                                return WalkAction::Continue;
                            }
                            if ($child['kind'] !== 'at-rule') {
                                return WalkAction::Continue;
                            }
                            if ($child['name'] === '@theme') {
                                $child['params'] = trim($child['params'] . ' prefix(' . $prefixValue . ')');
                                return WalkAction::Skip;
                            }
                            return WalkAction::Continue;
                        });
                    }
                    // Also set the prefix on the theme directly for utility generation
                    if (!empty($prefixValue) && preg_match(IS_VALID_PREFIX, $prefixValue)) {
                        $theme->prefix = $prefixValue;
                    }
                } else {
                    $unknownParams[] = $param;
                }
            }
            if (count($unknownParams) > 0) {
                $node['params'] = implode(' ', $unknownParams);
            } elseif (count($params) > 0) {
                // All params were recognized, replace @media with its children
                return WalkAction::Replace($node['nodes'] ?? []);
            }
        }
        return WalkAction::Continue;
    });
    // Populate the first theme rule with theme values
    if ($firstThemeRule !== null) {
        walk($ast, function (&$node) use ($theme) {
            if ($node['kind'] === 'rule' && $node['selector'] === ':root, :host') {
                $nodes = [];
                foreach ($theme->entries() as [$key, $value]) {
                    // Skip REFERENCE and INLINE values - they don't get output as CSS variables
                    if ($value['options'] & (Theme::OPTIONS_REFERENCE | Theme::OPTIONS_INLINE)) {
                        continue;
                    }
                    // Skip values that are 'initial' - they act as markers for fallback injection
                    if ($value['value'] === 'initial') {
                        continue;
                    }
                    // Skip values that contain --theme() calls resolving to 'initial'
                    // These are markers for fallback injection, not actual CSS values
                    if (str_contains($value['value'], '--theme(') && themeValueResolvesToInitial($value['value'], $theme)) {
                        continue;
                    }
                    $nodes[] = decl(\BlockstudioVendor\TailwindPHP\Utils\escape($key), $value['value']);
                }
                $node['nodes'] = $nodes;
                return WalkAction::Stop;
            }
            return WalkAction::Continue;
        });
        // Add keyframes to the AST (they get hoisted to top level during output)
        foreach ($theme->getKeyframes() as $keyframes) {
            $ast[] = $keyframes;
        }
    }
    // Build the design system
    $designSystem = buildDesignSystem($theme);
    // Set important flag on design system
    if ($important) {
        $designSystem->setImportant(\true);
    }
    // Add ignored candidates (from @source not inline("...")) to design system
    if (!empty($ignoredCandidates)) {
        foreach ($ignoredCandidates as $candidate) {
            $designSystem->addInvalidCandidate($candidate);
        }
    }
    // Apply plugins
    if (!empty($plugins)) {
        $pluginManager = getPluginManager();
        // Pass theme config from options for plugins to access via theme('...')
        $themeConfig = $options['theme'] ?? [];
        $api = $pluginManager->createAPI($theme, $designSystem->getUtilities(), $designSystem->getVariants(), ['theme' => $themeConfig]);
        foreach ($plugins as $pluginRef) {
            $pluginName = $pluginRef['name'];
            $pluginOptions = $pluginRef['options'];
            if (!$pluginManager->has($pluginName)) {
                throw new \Exception("Plugin \"{$pluginName}\" is not registered. Make sure the plugin is installed and registered.");
            }
            // Apply theme extensions first
            // Note: Complex nested theme extensions (like typography's styles) are skipped
            // The actual plugin functionality comes from addComponents/addUtilities
            $themeExtensions = $pluginManager->getThemeExtensions($pluginName, $pluginOptions);
            foreach ($themeExtensions as $namespace => $values) {
                // Skip non-string namespaces (numeric array indices) and non-array values
                if (!is_string($namespace) || !is_array($values)) {
                    continue;
                }
                $themeNamespace = '--' . strtolower(preg_replace(REGEX_CAMEL_TO_KEBAB, '-$1', $namespace));
                foreach ($values as $key => $value) {
                    // Only add simple string values to theme
                    if (!is_string($value)) {
                        continue;
                    }
                    if ($key === 'DEFAULT') {
                        $theme->add($themeNamespace, $value);
                    } else {
                        $theme->add("{$themeNamespace}-{$key}", $value);
                    }
                }
            }
            // Execute the plugin
            $pluginManager->execute($pluginName, $api, $pluginOptions);
        }
    }
    // Register custom variants
    foreach ($customVariants as $customVariant) {
        registerCustomVariant($designSystem, $customVariant['name'], $customVariant['selector'], $customVariant['nodes']);
    }
    // Note: @utility registration and removal is deferred to compileAst
    // This is because @apply inside @utility needs to be processed first
    return ['designSystem' => $designSystem, 'ast' => $ast, 'sources' => $sources, 'root' => $root, 'utilitiesNodePath' => $utilitiesNodePath, 'features' => $features, 'inlineCandidates' => $inlineCandidates];
}
const IS_VALID_PREFIX = '/^[a-z]+$/';
/**
 * Parse @theme options from params string.
 *
 * @param string $params
 * @return array{0: int, 1: string|null} [options flags, prefix]
 */
function parseThemeOptions(string $params): array
{
    $options = Theme::OPTIONS_NONE;
    $prefix = null;
    foreach (\BlockstudioVendor\TailwindPHP\Utils\segment($params, ' ') as $option) {
        if ($option === 'reference') {
            $options |= Theme::OPTIONS_REFERENCE;
        } elseif ($option === 'inline') {
            $options |= Theme::OPTIONS_INLINE;
        } elseif ($option === 'default') {
            $options |= Theme::OPTIONS_DEFAULT;
        } elseif ($option === 'static') {
            $options |= Theme::OPTIONS_STATIC;
        } elseif (str_starts_with($option, 'prefix(') && str_ends_with($option, ')')) {
            $prefix = substr($option, 7, -1);
        }
    }
    return [$options, $prefix];
}
/**
 * Register a custom variant with the design system.
 *
 * @param DesignSystem\DesignSystem $designSystem The design system
 * @param string $name The variant name
 * @param string|null $selector The selector (for simple variants like "&:hover")
 * @param array $nodes The AST nodes (for complex variants with @slot)
 */
function registerCustomVariant($designSystem, string $name, ?string $selector, array $nodes): void
{
    $variants = $designSystem->getVariants();
    // Simple selector-based variant: @custom-variant hocus (&:hover, &:focus);
    if ($selector !== null && empty($nodes)) {
        if (!str_starts_with($selector, '(') || !str_ends_with($selector, ')')) {
            throw new \Exception("`@custom-variant {$name}` selector must be wrapped in parentheses.");
        }
        // Parse selectors from "(sel1, sel2, ...)"
        $selectorContent = substr($selector, 1, -1);
        $selectors = array_map('trim', \BlockstudioVendor\TailwindPHP\Utils\segment($selectorContent, ','));
        if (empty($selectors) || in_array('', $selectors, \true)) {
            throw new \Exception("`@custom-variant {$name} {$selector}` selector is invalid.");
        }
        $atRuleParams = [];
        $styleRuleSelectors = [];
        foreach ($selectors as $sel) {
            if (str_starts_with($sel, '@')) {
                $atRuleParams[] = $sel;
            } else {
                $styleRuleSelectors[] = $sel;
            }
        }
        // Build the variant apply function
        $variants->static($name, function (&$r) use ($atRuleParams, $styleRuleSelectors) {
            // Wrap in style rule selectors first
            if (!empty($styleRuleSelectors)) {
                $r['nodes'] = [styleRule(implode(', ', $styleRuleSelectors), $r['nodes'])];
            }
            // Then wrap in at-rules
            foreach (array_reverse($atRuleParams) as $atRuleParam) {
                // Parse at-rule name and params
                if (preg_match(REGEX_AT_RULE_PARAM, $atRuleParam, $m)) {
                    $r['nodes'] = [atRule($m[1], $m[2], $r['nodes'])];
                }
            }
        }, ['compounds' => \BlockstudioVendor\TailwindPHP\Variants\compoundsForSelectors($selectors)]);
    } elseif (!empty($nodes)) {
        $variants->fromAst($name, $nodes, $designSystem);
    } else {
        throw new \Exception("`@custom-variant {$name}` has no selector or body.");
    }
}
// Regex patterns for utility name validation
const IS_VALID_STATIC_UTILITY_NAME = '/^-?[a-z][a-zA-Z0-9\/%._-]*$/';
const IS_VALID_FUNCTIONAL_UTILITY_NAME = '/^-?[a-z][a-zA-Z0-9\/%._-]*-\*$/';
/**
 * Create a CSS utility from an @utility at-rule.
 *
 * @param array $node The @utility at-rule node
 * @return callable|null Returns a callback to register the utility, or null if invalid
 */
function createCssUtility(array $node): ?callable
{
    $name = $node['params'];
    // Functional utilities. E.g.: `tab-size-*`
    if (preg_match(IS_VALID_FUNCTIONAL_UTILITY_NAME, $name)) {
        // For now, just support static functional utilities (no --value/--modifier)
        return function (DesignSystem $designSystem) use ($name, $node) {
            $utilityName = substr($name, 0, -2);
            // Remove trailing -*
            $designSystem->getUtilities()->functional($utilityName, function (array $candidate) use ($node) {
                // A value is required for functional utilities
                if (!isset($candidate['value'])) {
                    return null;
                }
                // Return all nodes (declarations, nested rules, etc.)
                // Deep clone to avoid mutation
                return array_map(fn($child) => cloneAstNode($child), $node['nodes'] ?? []);
            });
        };
    }
    // Static utilities. E.g.: `my-utility`
    if (preg_match(IS_VALID_STATIC_UTILITY_NAME, $name)) {
        return function (DesignSystem $designSystem) use ($name, $node) {
            // Return all nodes (declarations, nested rules, etc.)
            // Deep clone to avoid mutation
            $designSystem->getUtilities()->static($name, fn() => array_map(fn($child) => cloneAstNode($child), $node['nodes'] ?? []));
        };
    }
    return null;
}
/**
 * Deep clone an AST node.
 *
 * @param array $node The node to clone
 * @return array Cloned node
 */
function cloneAstNode(array $node): array
{
    $cloned = $node;
    if (isset($cloned['nodes'])) {
        $cloned['nodes'] = array_map(fn($child) => cloneAstNode($child), $cloned['nodes']);
    }
    return $cloned;
}
/**
 * Optimize AST by flattening context nodes and other optimizations.
 *
 * @param array $ast
 * @param DesignSystem $designSystem
 * @param int $polyfills
 * @return array
 */
function optimizeAst(array $ast, DesignSystem $designSystem, int $polyfills = POLYFILL_ALL): array
{
    $result = [];
    $usedVariables = [];
    $usedKeyframeNames = [];
    $theme = $designSystem->getTheme();
    $atRoots = [];
    // Collect at-root nodes to hoist
    $seenAtProperties = [];
    // Track seen @property rules to dedupe
    // First pass: collect used variables and keyframe names
    $collectUsed = function (array $node) use (&$collectUsed, &$usedVariables, &$usedKeyframeNames) {
        if ($node['kind'] === 'declaration') {
            $value = $node['value'] ?? '';
            // Extract variables from var() functions
            // The regex matches CSS custom property names which can contain
            // any character except whitespace, quotes, closing parens, or commas
            if (preg_match_all(REGEX_VAR_EXTRACT, $value, $matches)) {
                foreach ($matches[1] as $var) {
                    // Unescape the variable name (e.g., --width-1\/2 -> --width-1/2)
                    $usedVariables[preg_replace(REGEX_UNESCAPE, '$1', $var)] = \true;
                }
            }
            // Extract keyframe names from animation property
            if ($node['property'] === 'animation' || $node['property'] === 'animation-name') {
                foreach (extractKeyframeNames($value) as $name) {
                    $usedKeyframeNames[$name] = \true;
                }
            }
        }
        foreach ($node['nodes'] ?? [] as $child) {
            $collectUsed($child);
        }
    };
    foreach ($ast as $node) {
        $collectUsed($node);
    }
    // Also mark theme variables that reference other variables
    // Iterate until no new variables are found
    do {
        $changed = \false;
        foreach ($theme->entries() as [$key, $value]) {
            if (isset($usedVariables[$key])) {
                // Extract variables this value depends on
                if (preg_match_all(REGEX_VAR_SIMPLE, $value['value'], $matches)) {
                    foreach ($matches[1] as $var) {
                        if (!isset($usedVariables[$var])) {
                            $usedVariables[$var] = \true;
                            $changed = \true;
                        }
                    }
                }
                // Extract keyframe names from animation values
                // Handle both prefixed (--tw-animate-foo) and non-prefixed (--animate-foo)
                if (preg_match(REGEX_ANIMATE_KEY, $key)) {
                    foreach (extractKeyframeNames($value['value']) as $name) {
                        if (!isset($usedKeyframeNames[$name])) {
                            $usedKeyframeNames[$name] = \true;
                            $changed = \true;
                        }
                    }
                }
            }
        }
    } while ($changed);
    $transform = function (array $node, array &$parent) use (&$transform, $usedVariables, $usedKeyframeNames, $theme, &$atRoots, &$seenAtProperties) {
        // Handle context nodes - lift their children to parent
        if ($node['kind'] === 'context') {
            // Skip reference context nodes
            if (!empty($node['context']['reference'])) {
                return;
            }
            // Recursively process children
            foreach ($node['nodes'] ?? [] as $child) {
                $transform($child, $parent);
            }
            return;
        }
        // Handle at-root nodes - hoist their children to top level
        if ($node['kind'] === 'at-root') {
            foreach ($node['nodes'] ?? [] as $child) {
                $newParent = [];
                $transform($child, $newParent);
                foreach ($newParent as $hoistedNode) {
                    // Collect @property rules separately
                    if ($hoistedNode['kind'] === 'at-rule' && $hoistedNode['name'] === '@property') {
                        $propName = trim($hoistedNode['params'] ?? '');
                        if (!isset($seenAtProperties[$propName])) {
                            $seenAtProperties[$propName] = \true;
                            $atRoots[] = $hoistedNode;
                        }
                    } else {
                        $atRoots[] = $hoistedNode;
                    }
                }
            }
            return;
        }
        // Skip --tw-sort declarations (internal sorting only)
        if ($node['kind'] === 'declaration' && ($node['property'] ?? '') === '--tw-sort') {
            return;
        }
        // Filter :root, :host declarations to only used variables
        if ($node['kind'] === 'rule' && $node['selector'] === ':root, :host') {
            $filteredNodes = [];
            foreach ($node['nodes'] ?? [] as $child) {
                if ($child['kind'] === 'declaration') {
                    $prop = $child['property'] ?? '';
                    // Unescape the property for comparison (AST has escaped names, usedVariables has unescaped)
                    $unescapedProp = preg_replace(REGEX_UNESCAPE, '$1', $prop);
                    // Check if this variable is used or has STATIC option
                    if (isset($usedVariables[$unescapedProp])) {
                        $filteredNodes[] = $child;
                    } elseif (str_starts_with($prop, '--')) {
                        // Check theme options for STATIC (use unescaped for theme lookup)
                        $options = $theme->getOptions($unescapedProp);
                        if ($options & Theme::OPTIONS_STATIC) {
                            $filteredNodes[] = $child;
                        }
                    }
                } else {
                    $filteredNodes[] = $child;
                }
            }
            if (empty($filteredNodes)) {
                return;
                // Skip empty :root, :host
            }
            $node['nodes'] = $filteredNodes;
            $parent[] = $node;
            return;
        }
        // Filter keyframes to only used ones (but only for theme keyframes)
        if ($node['kind'] === 'at-rule' && $node['name'] === '@keyframes') {
            $keyframeName = trim($node['params'] ?? '');
            // Only filter keyframes that came from @theme
            // Keyframes defined outside @theme are always preserved
            $isThemeKeyframe = $theme->hasKeyframe($keyframeName);
            if ($isThemeKeyframe && !isset($usedKeyframeNames[$keyframeName])) {
                // Check if theme has STATIC option for this keyframe
                $keyframeOptions = $theme->getKeyframeOptions($keyframeName);
                if ($keyframeOptions & Theme::OPTIONS_STATIC) {
                    // Keep it - static keyframes are always included
                } else {
                    return;
                    // Skip unused theme keyframes
                }
            }
        }
        // Handle rules with children
        if (($node['kind'] === 'rule' || $node['kind'] === 'at-rule') && isset($node['nodes'])) {
            $children = [];
            foreach ($node['nodes'] as $child) {
                $transform($child, $children);
            }
            $node['nodes'] = $children;
            // Skip empty rules (no declarations or nested rules)
            // But keep @layer, @charset, @custom-media, @namespace, @import (they can be empty)
            $name = $node['name'] ?? '';
            if (empty($children) && !in_array($name, ['@layer', '@charset', '@custom-media', '@namespace', '@import'])) {
                return;
            }
        }
        $parent[] = $node;
    };
    foreach ($ast as $node) {
        $transform($node, $result);
    }
    // Transform CSS nesting (flatten & selectors, hoist @media)
    $result = LightningCss::transformNesting($result);
    // Add vendor prefixes to declarations that need them
    $result = LightningCss::addVendorPrefixes($result);
    // Apply LightningCSS value optimizations to all declarations
    $optimizeValues = function (array &$node) use (&$optimizeValues): void {
        if ($node['kind'] === 'declaration' && isset($node['value'])) {
            $node['value'] = LightningCss::optimizeValue($node['value'], $node['property'] ?? '');
        }
        if (isset($node['nodes'])) {
            foreach ($node['nodes'] as &$child) {
                $optimizeValues($child);
            }
        }
    };
    foreach ($result as &$node) {
        $optimizeValues($node);
    }
    // Apply color-mix polyfill - convert color-mix with variables to @supports fallback
    if ($polyfills & POLYFILL_COLOR_MIX) {
        $result = applyColorMixPolyfill($result, $designSystem);
    }
    // Process atRoots - separate @property rules from others
    $atPropertyRules = [];
    $otherAtRoots = [];
    foreach ($atRoots as $atRoot) {
        if ($atRoot['kind'] === 'at-rule' && $atRoot['name'] === '@property') {
            $atPropertyRules[] = $atRoot;
        } else {
            $otherAtRoots[] = $atRoot;
        }
    }
    // If we have @property rules, wrap their fallbacks in @layer properties + @supports
    if (!empty($atPropertyRules) && $polyfills & POLYFILL_AT_PROPERTY) {
        // Extract initial values for fallback declarations
        $fallbackDeclarations = [];
        foreach ($atPropertyRules as $property) {
            $propName = trim($property['params'] ?? '');
            $initialValue = null;
            $syntax = null;
            foreach ($property['nodes'] ?? [] as $decl) {
                if ($decl['kind'] === 'declaration') {
                    if ($decl['property'] === 'initial-value') {
                        $initialValue = $decl['value'] ?? '';
                    } elseif ($decl['property'] === 'syntax') {
                        $syntax = $decl['value'] ?? '';
                    }
                }
            }
            // For <length> syntax with bare "0", add "px" unit in fallback
            // This is because @property strips units from zero but fallbacks need them
            $fallbackValue = $initialValue ?? 'initial';
            if ($fallbackValue === '0' && $syntax === '"<length>"') {
                $fallbackValue = '0px';
            }
            $fallbackDeclarations[] = decl($propName, $fallbackValue);
        }
        if (!empty($fallbackDeclarations)) {
            // Create @layer properties with @supports fallback
            // @supports (((-webkit-hyphens: none)) and (not (margin-trim: inline))) or ((-moz-orient: inline) and (not (color:rgb(from red r g b))))
            // Note: Extra parens around -webkit-hyphens test for specificity
            $supportsCondition = '(((-webkit-hyphens: none)) and (not (margin-trim: inline))) or ((-moz-orient: inline) and (not (color: rgb(from red r g b))))';
            $universalSelector = '*, :before, :after, ::backdrop';
            $fallbackRule = Ast\styleRule($universalSelector, $fallbackDeclarations);
            $supportsRule = Ast\atRule('@supports', $supportsCondition, [$fallbackRule]);
            $layerProperties = Ast\atRule('@layer', 'properties', [$supportsRule]);
            // Prepend to result
            array_unshift($result, $layerProperties);
        }
    }
    // Append other atRoots (non-@property) and @property rules
    $result = array_merge($result, $otherAtRoots, $atPropertyRules);
    // Merge adjacent rules with same declarations (selector merging)
    // Process @custom-media definitions and substitute them
    $result = LightningCss::processCustomMedia($result);
    // Transform media query range syntax (width >= X → min-width: X)
    $result = LightningCss::processQueryRangeSyntax($result);
    $result = LightningCss::mergeRulesWithSameDeclarations($result);
    return $result;
}
/**
 * Extract keyframe names from an animation CSS value.
 *
 * @param string $value Animation value like "spin 1s infinite, fade 2s"
 * @return array<string> List of keyframe names
 */
function extractKeyframeNames(string $value): array
{
    $names = [];
    // Animation value format: name duration timing-function delay iteration-count direction fill-mode play-state
    // Keyframe name is a custom identifier (not a keyword)
    $keywords = ['none', 'infinite', 'normal', 'reverse', 'alternate', 'alternate-reverse', 'forwards', 'backwards', 'both', 'running', 'paused', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear', 'step-start', 'step-end', 'initial', 'inherit'];
    // Split by comma for multiple animations
    $animations = preg_split('/\s*,\s*/', $value);
    foreach ($animations as $animation) {
        // Split by whitespace
        $parts = preg_split(REGEX_WHITESPACE, trim($animation));
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            // Skip timing values (numbers, percentages, seconds)
            if (preg_match(REGEX_NUMERIC_START, $part)) {
                continue;
            }
            if (preg_match(REGEX_TIME_VALUE, $part)) {
                continue;
            }
            // Skip keywords
            if (in_array(strtolower($part), $keywords)) {
                continue;
            }
            // Skip functions (like cubic-bezier, steps)
            if (str_contains($part, '(')) {
                continue;
            }
            // Skip var() references - the variable value will be resolved
            if (str_starts_with($part, 'var(')) {
                continue;
            }
            // This is likely a keyframe name
            $names[] = $part;
        }
    }
    return array_unique($names);
}
/**
 * Generate CSS from content containing Tailwind classes.
 *
 * This is the primary function for generating Tailwind CSS. It extracts class
 * candidates from HTML content and generates only the CSS needed for those classes.
 *
 * Accepts either:
 * 1. A string (HTML content to scan for classes)
 * 2. An array with 'content', optional 'css', optional 'importPaths', and optional 'minify' keys
 *
 * @param string|array{
 *     content: string,
 *     css?: string,
 *     importPaths?: string|array<string>|callable(string|null, string|null): ?string,
 *     minify?: bool,
 *     cache?: bool|string,
 *     cacheTtl?: int
 * } $input HTML string or array with options:
 *   - `content`: HTML string to extract class candidates from
 *   - `css`: Optional CSS with @import, @theme, @utility directives
 *   - `importPaths`: File path(s), directory, or callable resolver for imports
 *   - `minify`: Whether to minify the output (default: false)
 *   - `cache`: Enable caching. true for default directory, or path to cache directory
 *   - `cacheTtl`: Cache time-to-live in seconds (default: no expiration)
 * @param string $css Optional CSS with @import directives (only used if $input is string)
 * @return string Generated CSS containing only utilities used in the content
 *
 * @throws \Exception When CSS parsing fails or directives are invalid
 * @throws \RuntimeException When file imports fail or exceed recursion limit
 *
 * @example String input (includes theme + preflight + utilities):
 *   generate('<div class="flex p-4">Hello</div>');
 *
 * @example Array input with custom CSS:
 *   generate([
 *       'content' => '<div class="flex p-4">Hello</div>',
 *       'css' => '@import "tailwindcss"; @theme { --color-brand: #3b82f6; }'
 *   ]);
 *
 * @example Without preflight (granular imports):
 *   generate([
 *       'content' => '<div class="flex p-4">Hello</div>',
 *       'css' => '
 *           @import "tailwindcss/theme.css" layer(theme);
 *           @import "tailwindcss/utilities.css" layer(utilities);
 *       '
 *   ]);
 *
 * @example With file imports (single file):
 *   generate([
 *       'content' => '<div class="flex p-4 btn">Hello</div>',
 *       'importPaths' => '/var/www/theme/css/main.css',
 *   ]);
 *
 * @example With file imports (directory - loads all .css files):
 *   generate([
 *       'content' => '<div class="flex p-4">Hello</div>',
 *       'importPaths' => '/var/www/theme/css/',
 *   ]);
 *
 * @example With file imports (array of paths):
 *   generate([
 *       'content' => '<div class="flex p-4">Hello</div>',
 *       'importPaths' => [
 *           '/var/www/theme/css/main.css',
 *           '/var/www/shared/components/',
 *       ],
 *   ]);
 *
 * @example With inline CSS and file imports (inline CSS is processed first):
 *   generate([
 *       'content' => '<div class="flex p-4 brand-bg">Hello</div>',
 *       'css' => '@theme { --color-brand: #3b82f6; }',
 *       'importPaths' => '/var/www/theme/css/',
 *   ]);
 *
 * @example With custom resolver function:
 *   generate([
 *       'content' => '<div class="flex">Hello</div>',
 *       'importPaths' => function (string $uri, ?string $fromFile): ?string {
 *           // Return CSS content or null if not found
 *           return DB::table('css_files')->where('name', $uri)->value('content');
 *       },
 *   ]);
 */
function generate(string|array $input, string $css = '@import "tailwindcss";'): string
{
    $minify = \false;
    $cache = null;
    $cacheTtl = null;
    // Handle array input
    if (is_array($input)) {
        $content = $input['content'] ?? '';
        $inlineCss = $input['css'] ?? '';
        $importPaths = $input['importPaths'] ?? null;
        $minify = $input['minify'] ?? \false;
        $cache = $input['cache'] ?? null;
        $cacheTtl = $input['cacheTtl'] ?? null;
        // Resolve import paths to CSS content
        $resolved = resolveImportPaths($importPaths);
        $importedCss = $resolved['css'];
        $searchPaths = $resolved['paths'];
        // Combine: inline CSS first, then imported CSS
        // If neither is provided, default to @import "tailwindcss"
        if ($inlineCss === '' && $importedCss === '') {
            $css = '@import "tailwindcss";';
        } else {
            $css = $inlineCss . "\n" . $importedCss;
        }
        // Store search paths for @import resolution within files
        $compileOptions = !empty($searchPaths) ? ['importSearchPaths' => $searchPaths] : [];
        if (is_callable($importPaths)) {
            $compileOptions['importResolver'] = $importPaths;
        }
    } else {
        $content = $input;
        $compileOptions = [];
    }
    // Handle caching
    if ($cache !== null) {
        $cacheDir = $cache === \true ? sys_get_temp_dir() . '/tailwindphp' : $cache;
        // Create cache directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, \true);
        }
        // Create hash from inputs (content + css + minify flag)
        $cacheKey = md5($content . $css . ($minify ? '1' : '0'));
        $cachePath = $cacheDir . '/tailwind_' . $cacheKey . '.css';
        // Check for cache hit
        if (file_exists($cachePath)) {
            $isValid = \true;
            // Check TTL if specified
            if ($cacheTtl !== null) {
                $fileAge = time() - (int) filemtime($cachePath);
                $isValid = $fileAge < $cacheTtl;
            }
            if ($isValid) {
                return file_get_contents($cachePath);
            }
        }
        // Cache miss - compile, cache, and return
        $result = generateWithoutCache($content, $css, $compileOptions, $minify);
        file_put_contents($cachePath, $result);
        return $result;
    }
    return generateWithoutCache($content, $css, $compileOptions, $minify);
}
/**
 * Internal helper to generate CSS without caching.
 *
 * @param string $content HTML content
 * @param string $css CSS input
 * @param array $compileOptions Compile options
 * @param bool $minify Whether to minify output
 * @return string Generated CSS
 */
function generateWithoutCache(string $content, string $css, array $compileOptions, bool $minify): string
{
    // Extract class names from content
    $candidates = extractCandidates($content);
    // Compile
    $compiled = compile($css, $compileOptions);
    $result = $compiled['build']($candidates);
    // Optionally minify output
    if ($minify) {
        $result = \BlockstudioVendor\TailwindPHP\Minifier\CssMinifier::minify($result);
    }
    return $result;
}
/**
 * Clear the TailwindPHP CSS cache.
 *
 * @param string|bool|null $cache Cache directory path, true for default location, or null to skip
 * @return int Number of cache files deleted
 *
 * @example
 * // Clear cache in default location
 * clearCache();
 * clearCache(true);
 *
 * // Clear cache in custom directory
 * clearCache('/path/to/cache');
 */
function clearCache(string|bool|null $cache = \true): int
{
    if ($cache === null || $cache === \false) {
        return 0;
    }
    $cacheDir = $cache === \true ? sys_get_temp_dir() . '/tailwindphp' : $cache;
    if (!is_dir($cacheDir)) {
        return 0;
    }
    $deleted = 0;
    $files = glob($cacheDir . '/tailwind_*.css');
    if ($files === \false) {
        return 0;
    }
    foreach ($files as $file) {
        if (is_file($file) && unlink($file)) {
            $deleted++;
        }
    }
    return $deleted;
}
/**
 * Extract class name candidates from HTML content.
 *
 * @param string $html
 * @return array<string>
 */
function extractCandidates(string $html): array
{
    $candidates = [];
    // Extract from class and className attributes
    foreach ([REGEX_CLASS_ATTR, REGEX_CLASSNAME_ATTR] as $pattern) {
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[1] as $classAttr) {
                foreach (preg_split(REGEX_WHITESPACE, $classAttr) as $class) {
                    $class = trim($class);
                    if ($class !== '') {
                        $candidates[] = $class;
                    }
                }
            }
        }
    }
    return array_unique($candidates);
}
/**
 * Check if a theme value containing --theme() calls would resolve to 'initial'.
 *
 * This is used to determine if a theme value should be output as a CSS variable.
 * When the value would resolve to 'initial', it acts as a marker for fallback injection
 * and should not be output to the CSS.
 *
 * @param string $value The value string containing --theme() calls
 * @param Theme $theme The theme instance for lookups
 * @return bool True if the value resolves to 'initial'
 */
function themeValueResolvesToInitial(string $value, Theme $theme): bool
{
    // Simple regex to extract --theme() arguments
    if (!preg_match(REGEX_THEME_CALL_FULL, trim($value), $match)) {
        return \false;
    }
    $args = $match[1];
    // Parse the arguments (uses optimized helper with array accumulation)
    $parts = splitByCommaRespectingParens($args);
    $path = $parts[0];
    $fallback = count($parts) > 1 ? trim(implode(', ', array_slice($parts, 1))) : null;
    // Handle 'inline' modifier
    if (str_ends_with($path, ' inline')) {
        $path = substr($path, 0, -7);
    }
    // The path should start with --
    if (!str_starts_with($path, '--')) {
        return \false;
    }
    // Look up the value in the theme (without prefix - theme stores unprefixed)
    $themeValue = $theme->get([$path]);
    // If the referenced variable doesn't exist and the fallback is 'initial', then resolves to initial
    if ($themeValue === null && $fallback === 'initial') {
        return \true;
    }
    return \false;
}
/**
 * Resolve --theme() calls within a value string during @theme processing.
 *
 * This allows patterns like `--theme(--font-family, initial)` to resolve
 * to 'initial' when --font-family doesn't exist in the theme.
 *
 * @param string $value The value string containing --theme() calls
 * @param Theme $theme The theme instance for lookups
 * @return string The resolved value
 */
function resolveThemeCallsInValue(string $value, Theme $theme): string
{
    // Match --theme(path[, fallback]) patterns
    // This is a simplified regex that handles basic cases
    if (!preg_match_all(REGEX_THEME_CALL, $value, $matches, \PREG_SET_ORDER)) {
        return $value;
    }
    foreach ($matches as $match) {
        $fullMatch = $match[0];
        $args = $match[1];
        // Parse the arguments (uses optimized helper with array accumulation)
        $parts = splitByCommaRespectingParens($args);
        $path = $parts[0];
        $fallback = count($parts) > 1 ? implode(', ', array_slice($parts, 1)) : null;
        // Handle 'inline' modifier
        $inline = \false;
        if (str_ends_with($path, ' inline')) {
            $inline = \true;
            $path = substr($path, 0, -7);
        }
        // The path should start with --
        if (!str_starts_with($path, '--')) {
            continue;
        }
        // Try to get the value from the theme
        $prefix = $theme->getPrefix();
        $prefixedPath = $path;
        if ($prefix !== null && str_starts_with($path, '--')) {
            $prefixedPath = '--' . $prefix . '-' . substr($path, 2);
        }
        $themeValue = $theme->get([$prefixedPath]) ?? $theme->get([$path]);
        if ($themeValue === null) {
            // Value doesn't exist - use fallback
            if ($fallback !== null) {
                $value = str_replace($fullMatch, $fallback, $value);
            }
            // If no fallback, leave as-is (will cause error or be handled later)
        } else if ($inline) {
            // Return the actual value
            $value = str_replace($fullMatch, $themeValue, $value);
        } else {
            // Return var() reference
            $value = str_replace($fullMatch, "var({$prefixedPath})", $value);
        }
    }
    return $value;
}
/**
 * Apply color-mix polyfill to AST.
 *
 * When color-mix() contains CSS variables (like var(--opacity)), browsers that don't
 * support color-mix need a fallback. This creates:
 * 1. A fallback declaration with just the base color
 * 2. An @supports block with the color-mix version
 *
 * @param array $ast The AST to process
 * @param DesignSystem $designSystem The design system for theme lookups
 * @return array Modified AST with polyfill applied
 */
function applyColorMixPolyfill(array $ast, DesignSystem $designSystem): array
{
    $result = [];
    foreach ($ast as $node) {
        if ($node['kind'] === 'rule') {
            // Process each declaration in the rule
            $newNodes = [];
            $supportsDeclarations = [];
            foreach ($node['nodes'] ?? [] as $decl) {
                if ($decl['kind'] === 'declaration' && isset($decl['value'])) {
                    $value = $decl['value'];
                    // Check if this declaration has color-mix that needs polyfill
                    // Pattern 1: color-mix with var() in opacity position
                    // Pattern 2: color-mix with var() in color position (from --theme)
                    $needsPolyfill = \false;
                    $fallbackColor = null;
                    // Pattern: color-mix(in oklab, COLOR VAR_OPACITY, transparent)
                    if (preg_match(REGEX_COLOR_MIX_VAR, $value, $match)) {
                        $needsPolyfill = \true;
                        $fallbackColor = trim($match[1]);
                        $fallbackColor = LightningCss::optimizeValue($fallbackColor, $decl['property']);
                    } elseif (preg_match(REGEX_COLOR_MIX_OPACITY, $value, $match)) {
                        $needsPolyfill = \true;
                        $varName = '--' . ltrim(trim($match[1]), '-');
                        $opacityStr = $match[2];
                        // Get the color value from theme
                        $theme = $designSystem->getTheme();
                        $colorValue = $theme->get([$varName]);
                        if ($colorValue !== null) {
                            // Calculate hex with alpha
                            $opacity = floatval(rtrim($opacityStr, '%'));
                            if ($opacity > 1) {
                                $opacity = $opacity / 100;
                            }
                            $fallbackColor = LightningCss::colorWithAlpha($colorValue, $opacity);
                        } else {
                            // Unknown variable (arbitrary property) - fallback to just the variable
                            $fallbackColor = "var({$varName})";
                        }
                    }
                    if ($needsPolyfill && $fallbackColor !== null) {
                        // Create fallback with the computed color
                        $fallbackDecl = ['kind' => 'declaration', 'property' => $decl['property'], 'value' => $fallbackColor, 'important' => $decl['important'] ?? \false];
                        $newNodes[] = $fallbackDecl;
                        // Keep original for @supports
                        $supportsDeclarations[] = $decl;
                    } else {
                        $newNodes[] = $decl;
                    }
                } else {
                    $newNodes[] = $decl;
                }
            }
            // Add the rule with fallback declarations
            if (!empty($newNodes)) {
                $fallbackRule = $node;
                $fallbackRule['nodes'] = $newNodes;
                $result[] = $fallbackRule;
            }
            // Add @supports block if we have color-mix declarations
            if (!empty($supportsDeclarations)) {
                $supportsRule = ['kind' => 'rule', 'selector' => $node['selector'], 'nodes' => $supportsDeclarations];
                $supports = Ast\atRule('@supports', '(color: color-mix(in lab, red, red))', [$supportsRule]);
                $result[] = $supports;
            }
        } elseif (isset($node['nodes'])) {
            // Recursively process nested nodes
            $node['nodes'] = applyColorMixPolyfill($node['nodes'], $designSystem);
            $result[] = $node;
        } else {
            $result[] = $node;
        }
    }
    return $result;
}
// ==================================================
// Plugin System
// ==================================================
use BlockstudioVendor\TailwindPHP\Plugin\PluginManager;
/** @var \TailwindPHP\Plugin\PluginManager|null Global plugin manager instance */
$_pluginManager = null;
/**
 * Get the global plugin manager instance.
 *
 * @return PluginManager
 */
function getPluginManager(): PluginManager
{
    global $_pluginManager;
    if ($_pluginManager === null) {
        $_pluginManager = new PluginManager();
    }
    return $_pluginManager;
}
/**
 * Register a plugin with the global plugin manager.
 *
 * @param \TailwindPHP\Plugin\PluginInterface $plugin The plugin to register
 */
function registerPlugin(\BlockstudioVendor\TailwindPHP\Plugin\PluginInterface $plugin): void
{
    getPluginManager()->register($plugin);
}
/**
 * Parse a plugin option value from CSS.
 *
 * Handles:
 * - Quoted strings: "value" or 'value'
 * - Numbers: 123, 1.5
 * - Booleans: true, false
 * - Lists: value1, value2 (space or comma separated)
 *
 * @param string $value The raw value string
 * @return mixed Parsed value
 */
function parsePluginOptionValue(string $value): mixed
{
    $value = trim($value);
    // Empty value
    if ($value === '') {
        return '';
    }
    // Quoted string
    if (str_starts_with($value, '"') && str_ends_with($value, '"') || str_starts_with($value, "'") && str_ends_with($value, "'")) {
        return substr($value, 1, -1);
    }
    // Boolean
    if ($value === 'true') {
        return \true;
    }
    if ($value === 'false') {
        return \false;
    }
    // Number
    if (is_numeric($value)) {
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }
    // Check for comma-separated list
    if (str_contains($value, ',')) {
        return array_map('trim', explode(',', $value));
    }
    // Plain string
    return $value;
}
/**
 * TailwindCompiler - Compiled Tailwind instance for reuse.
 *
 * This class provides a compiled Tailwind instance that can be reused
 * for multiple operations without re-parsing the CSS configuration.
 *
 * Usage:
 * ```php
 * use TailwindPHP\tw;
 *
 * // Compile once
 * $tw = tw::compile('@import "tailwindcss"; @theme { --color-brand: #3b82f6; }');
 *
 * // Reuse for multiple operations
 * $css = $tw->generate('<div class="flex p-4">');
 * $props = $tw->properties('p-4');
 * $value = $tw->value('p-4');
 * ```
 */
class TailwindCompiler
{
    private array $compiled;
    private object $designSystem;
    private Theme $theme;
    /**
     * Create a new TailwindCompiler instance.
     *
     * @param string $css CSS input with @import, @theme, @utility directives
     * @param array $options Compilation options
     */
    public function __construct(string $css = '@import "tailwindcss";', array $options = [])
    {
        $ast = parse($css);
        // compileAst internally calls parseCss and returns the compiled result with build()
        $this->compiled = compileAst($ast, $options);
        // Get design system from a fresh parse (compileAst already processed it)
        // We need to re-parse to get the design system for properties() etc.
        $ast2 = parse($css);
        $result = parseCss($ast2, $options);
        $this->designSystem = $result['designSystem'];
        $this->theme = $this->designSystem->getTheme();
    }
    /**
     * Generate CSS from content containing Tailwind classes.
     *
     * @param string $content HTML string to extract classes from
     * @return string Generated CSS
     */
    public function generate(string $content): string
    {
        $candidates = extractCandidates($content);
        return $this->compiled['build']($candidates);
    }
    /**
     * Generate CSS from an array of class candidates.
     *
     * @param array<string> $candidates Array of class names
     * @return string Generated CSS
     */
    public function css(array $candidates): string
    {
        return $this->compiled['build']($candidates);
    }
    /**
     * Get raw CSS properties for a utility class.
     *
     * Returns the CSS properties as they would be output, including CSS variables.
     *
     * @param string|array<string> $utilities Single utility or array of utilities
     * @return array<string, string> Map of property => value
     */
    public function properties(string|array $utilities): array
    {
        if (is_string($utilities)) {
            $utilities = [$utilities];
        }
        $result = [];
        foreach ($utilities as $utility) {
            $declarations = $this->getDeclarations($utility);
            foreach ($declarations as $decl) {
                $result[$decl['property']] = $decl['value'];
            }
        }
        return $result;
    }
    /**
     * Get computed CSS properties for a utility class.
     *
     * Returns the CSS properties with CSS variables resolved to their actual values.
     *
     * @param string|array<string> $utilities Single utility or array of utilities
     * @return array<string, string> Map of property => resolved value
     */
    public function computedProperties(string|array $utilities): array
    {
        if (is_string($utilities)) {
            $utilities = [$utilities];
        }
        $result = [];
        foreach ($utilities as $utility) {
            $declarations = $this->getDeclarations($utility);
            foreach ($declarations as $decl) {
                $result[$decl['property']] = $this->resolveValue($decl['value']);
            }
        }
        return $result;
    }
    /**
     * Get raw value for a utility class.
     *
     * Returns a CSS value as it would be output, including CSS variables.
     * If the first property is a CSS variable (--), returns the first non-variable property
     * value to give more useful results for utilities like colors.
     *
     * @param string $utility Single utility class
     * @return string|null The raw value or null if not found
     */
    public function value(string $utility): ?string
    {
        $declarations = $this->getDeclarations($utility);
        if (empty($declarations)) {
            return null;
        }
        // If the first property is a CSS variable, find the first non-variable property
        $first = $declarations[0];
        if (str_starts_with($first['property'], '--')) {
            foreach ($declarations as $decl) {
                if (!str_starts_with($decl['property'], '--')) {
                    return $decl['value'];
                }
            }
        }
        return $first['value'];
    }
    /**
     * Get computed value for a utility class.
     *
     * Returns the first CSS value with CSS variables resolved.
     *
     * @param string $utility Single utility class
     * @return string|null The resolved value or null if not found
     */
    public function computedValue(string $utility): ?string
    {
        $value = $this->value($utility);
        if ($value === null) {
            return null;
        }
        return $this->resolveValue($value);
    }
    /**
     * Extract class name candidates from HTML content.
     *
     * @param string $html HTML content to extract classes from
     * @return array<string> Unique class name candidates
     */
    public function extractCandidates(string $html): array
    {
        return extractCandidates($html);
    }
    /**
     * Minify CSS output.
     *
     * @param string $css The CSS to minify
     * @return string Minified CSS
     */
    public function minify(string $css): string
    {
        return \BlockstudioVendor\TailwindPHP\Minifier\CssMinifier::minify($css);
    }
    /**
     * Get the compiled build function result.
     *
     * @return array{build: callable, sources: array, root: array, features: int}
     */
    public function getCompiled(): array
    {
        return $this->compiled;
    }
    /**
     * Get the design system instance.
     *
     * @return object
     */
    public function getDesignSystem(): object
    {
        return $this->designSystem;
    }
    /**
     * Get the theme instance.
     *
     * @return Theme
     */
    public function getTheme(): Theme
    {
        return $this->theme;
    }
    /**
     * Get all color values from the theme.
     *
     * Returns a flat array of color name => computed value pairs.
     *
     * @return array<string, string> Map of color name to computed value
     */
    public function colors(): array
    {
        return $this->getThemeNamespace('color');
    }
    /**
     * Get all breakpoint values from the theme.
     *
     * @return array<string, string> Map of breakpoint name to value
     */
    public function breakpoints(): array
    {
        return $this->getThemeNamespace('breakpoint');
    }
    /**
     * Get all spacing values from the theme.
     *
     * Note: TailwindCSS 4 uses a single --spacing base value, not --spacing-* namespace.
     * This returns any custom --spacing-* values defined in the theme.
     *
     * @return array<string, string> Map of spacing name to computed value
     */
    public function spacing(): array
    {
        return $this->getThemeNamespace('spacing');
    }
    /**
     * Get all values from a theme namespace.
     *
     * @param string $namespace The namespace (e.g., 'color', 'breakpoint', 'spacing')
     * @return array<string, string> Map of name to computed value
     */
    private function getThemeNamespace(string $namespace): array
    {
        $prefix = "--{$namespace}-";
        $result = [];
        foreach ($this->theme->entries() as [$key, $entry]) {
            if (str_starts_with($key, $prefix)) {
                $name = substr($key, strlen($prefix));
                $value = $this->resolveValue($entry['value']);
                $result[$name] = $value;
            }
        }
        return $result;
    }
    /**
     * Get CSS declarations for a utility class.
     *
     * @param string $utility
     * @return array<array{property: string, value: string}>
     */
    private function getDeclarations(string $utility): array
    {
        $candidates = $this->designSystem->parseCandidate($utility);
        if (empty($candidates)) {
            return [];
        }
        $declarations = [];
        foreach ($candidates as $candidate) {
            $rules = $this->designSystem->compileAstNodes($candidate, \BlockstudioVendor\TailwindPHP\Compile\COMPILE_FLAG_NONE);
            if (empty($rules)) {
                continue;
            }
            foreach ($rules as $ruleInfo) {
                if (!isset($ruleInfo['node'])) {
                    continue;
                }
                $node = $ruleInfo['node'];
                $this->extractDeclarations($node, $declarations);
            }
            // Only use first valid candidate
            if (!empty($declarations)) {
                break;
            }
        }
        return $declarations;
    }
    /**
     * Recursively extract declarations from AST node.
     *
     * Filters out @property declarations (syntax, inherits, initial-value) as these
     * are internal implementation details, not the actual CSS properties.
     *
     * @param array $node
     * @param array &$declarations
     */
    private function extractDeclarations(array $node, array &$declarations): void
    {
        // Skip @property at-rules entirely
        if ($node['kind'] === 'at-rule' && ($node['name'] ?? '') === '@property') {
            return;
        }
        if ($node['kind'] === 'declaration' && isset($node['property'], $node['value'])) {
            // Skip @property internal properties
            $prop = $node['property'];
            if ($prop === 'syntax' || $prop === 'inherits' || $prop === 'initial-value') {
                return;
            }
            $declarations[] = ['property' => $prop, 'value' => $node['value']];
            return;
        }
        if (isset($node['nodes']) && is_array($node['nodes'])) {
            foreach ($node['nodes'] as $child) {
                $this->extractDeclarations($child, $declarations);
            }
        }
    }
    /**
     * Resolve CSS variables in a value.
     *
     * @param string $value
     * @return string Resolved value
     */
    private function resolveValue(string $value): string
    {
        // Handle calc(var(--spacing) * N) pattern
        if (preg_match('/^calc\(var\(--spacing\)\s*\*\s*(\d+(?:\.\d+)?)\)$/', $value, $matches)) {
            $multiplier = (float) $matches[1];
            $spacing = $this->theme->get(['--spacing']);
            if ($spacing !== null) {
                // Parse the spacing value (e.g., "0.25rem")
                if (preg_match('/^([\d.]+)(.*)$/', $spacing, $spacingMatches)) {
                    $baseValue = (float) $spacingMatches[1];
                    $unit = $spacingMatches[2];
                    $computed = $baseValue * $multiplier;
                    // Format nicely - remove trailing zeros
                    $formatted = rtrim(rtrim(number_format($computed, 4, '.', ''), '0'), '.');
                    return $formatted . $unit;
                }
            }
        }
        // Handle simple var(--name) pattern
        if (preg_match('/^var\(--([^)]+)\)$/', $value, $matches)) {
            $varName = '--' . $matches[1];
            $resolved = $this->theme->get([$varName]);
            if ($resolved !== null) {
                return $this->resolveValue($resolved);
                // Recursively resolve
            }
        }
        // Handle var(--name, fallback) pattern
        if (preg_match('/^var\(--([^,)]+),\s*([^)]+)\)$/', $value, $matches)) {
            $varName = '--' . $matches[1];
            $fallback = trim($matches[2]);
            $resolved = $this->theme->get([$varName]);
            if ($resolved !== null) {
                return $this->resolveValue($resolved);
            }
            return $this->resolveValue($fallback);
        }
        // Handle calc() with var() inside
        if (str_contains($value, 'var(')) {
            $value = preg_replace_callback('/var\(--([^,)]+)(?:,\s*([^)]+))?\)/', function ($m) {
                $varName = '--' . $m[1];
                $fallback = $m[2] ?? null;
                $resolved = $this->theme->get([$varName]);
                if ($resolved !== null) {
                    return $this->resolveValue($resolved);
                }
                if ($fallback !== null) {
                    return $this->resolveValue(trim($fallback));
                }
                return $m[0];
                // Keep original if can't resolve
            }, $value) ?? $value;
        }
        // Run through LightningCSS optimizations for consistent output with compiled CSS
        return \BlockstudioVendor\TailwindPHP\LightningCss\LightningCss::optimizeValue($value);
    }
}
/**
 * TailwindPHP - Main facade class for CSS generation.
 *
 * This is the primary entry point for using TailwindPHP. It provides static methods
 * for generating CSS from HTML content containing Tailwind utility classes.
 *
 * Basic usage:
 * ```php
 * use TailwindPHP\tw;
 *
 * // Simple generation from HTML
 * $css = tw::generate('<div class="flex p-4">Hello</div>');
 *
 * // With custom CSS/theme
 * $css = tw::generate($html, '@import "tailwindcss"; @theme { --color-brand: #3b82f6; }');
 *
 * // Get properties
 * $props = tw::properties('p-4'); // ['padding' => 'calc(var(--spacing) * 4)']
 * $props = tw::computedProperties('p-4'); // ['padding' => '1rem']
 *
 * // Get single value
 * $value = tw::value('p-4'); // 'calc(var(--spacing) * 4)'
 * $value = tw::computedValue('p-4'); // '1rem'
 *
 * // Compiled instance (reuse for efficiency)
 * $tw = tw::compile('@import "tailwindcss";');
 * $css = $tw->generate('<div class="flex">');
 * $props = $tw->properties('p-4');
 * ```
 */
class Tailwind
{
    /**
     * Generate CSS from content containing Tailwind classes.
     *
     * @param string|array{
     *     content: string,
     *     css?: string,
     *     importPaths?: string|array<string>|callable(string|null, string|null): ?string,
     *     minify?: bool
     * } $input HTML string, or array with configuration options
     * @param string $css Optional CSS input (only used when $input is a string)
     * @return string Generated CSS
     */
    public static function generate(string|array $input, string $css = '@import "tailwindcss";'): string
    {
        return generate($input, $css);
    }
    /**
     * Compile CSS and return a TailwindCompiler instance for reuse.
     *
     * @param string $css CSS input with @import, @theme, @utility directives
     * @param array $options Compilation options
     * @return TailwindCompiler Compiled instance with generate(), properties(), value() methods
     */
    public static function compile(string $css = '@import "tailwindcss";', array $options = []): TailwindCompiler
    {
        return new TailwindCompiler($css, $options);
    }
    /**
     * Get raw CSS properties for utility class(es).
     *
     * @param string|array{content: string|array<string>, css?: string}|array<string> $input
     *   - String: single utility class
     *   - Array with 'content': utility class(es) with optional 'css' config
     *   - Array of strings: multiple utility classes
     * @param string $css Optional CSS configuration
     * @return array<string, string> Map of property => raw value (with CSS variables)
     */
    public static function properties(string|array $input, string $css = '@import "tailwindcss";'): array
    {
        [$utilities, $cssConfig] = self::parseInput($input, $css);
        $compiler = new TailwindCompiler($cssConfig);
        return $compiler->properties($utilities);
    }
    /**
     * Get computed CSS properties for utility class(es).
     *
     * @param string|array{content: string|array<string>, css?: string}|array<string> $input
     * @param string $css Optional CSS configuration
     * @return array<string, string> Map of property => resolved value (CSS variables resolved)
     */
    public static function computedProperties(string|array $input, string $css = '@import "tailwindcss";'): array
    {
        [$utilities, $cssConfig] = self::parseInput($input, $css);
        $compiler = new TailwindCompiler($cssConfig);
        return $compiler->computedProperties($utilities);
    }
    /**
     * Get raw value for a single utility class.
     *
     * @param string|array{content: string, css?: string} $input
     * @param string $css Optional CSS configuration
     * @return string|null Raw value (with CSS variables) or null if not found
     */
    public static function value(string|array $input, string $css = '@import "tailwindcss";'): ?string
    {
        [$utilities, $cssConfig] = self::parseInput($input, $css);
        $compiler = new TailwindCompiler($cssConfig);
        $utility = is_array($utilities) ? $utilities[0] : $utilities;
        return $compiler->value($utility);
    }
    /**
     * Get computed value for a single utility class.
     *
     * @param string|array{content: string, css?: string} $input
     * @param string $css Optional CSS configuration
     * @return string|null Resolved value (CSS variables resolved) or null if not found
     */
    public static function computedValue(string|array $input, string $css = '@import "tailwindcss";'): ?string
    {
        [$utilities, $cssConfig] = self::parseInput($input, $css);
        $compiler = new TailwindCompiler($cssConfig);
        $utility = is_array($utilities) ? $utilities[0] : $utilities;
        return $compiler->computedValue($utility);
    }
    /**
     * Extract class name candidates from HTML content.
     *
     * @param string $html HTML content to extract classes from
     * @return array<string> Unique class name candidates
     */
    public static function extractCandidates(string $html): array
    {
        return extractCandidates($html);
    }
    /**
     * Minify CSS output.
     *
     * @param string $css The CSS to minify
     * @return string Minified CSS
     */
    public static function minify(string $css): string
    {
        return \BlockstudioVendor\TailwindPHP\Minifier\CssMinifier::minify($css);
    }
    /**
     * Clear the CSS cache.
     *
     * @param string|bool|null $cache Cache directory path, true for default, or null
     * @return int Number of cache files deleted
     */
    public static function clearCache(string|bool|null $cache = \true): int
    {
        return clearCache($cache);
    }
    /**
     * Get all color values from the theme.
     *
     * @param string $css Optional CSS configuration
     * @return array<string, string> Map of color name to computed value
     */
    public static function colors(string $css = '@import "tailwindcss";'): array
    {
        $compiler = new TailwindCompiler($css);
        return $compiler->colors();
    }
    /**
     * Get all breakpoint values from the theme.
     *
     * @param string $css Optional CSS configuration
     * @return array<string, string> Map of breakpoint name to value
     */
    public static function breakpoints(string $css = '@import "tailwindcss";'): array
    {
        $compiler = new TailwindCompiler($css);
        return $compiler->breakpoints();
    }
    /**
     * Get all spacing values from the theme.
     *
     * @param string $css Optional CSS configuration
     * @return array<string, string> Map of spacing name to computed value
     */
    public static function spacing(string $css = '@import "tailwindcss";'): array
    {
        $compiler = new TailwindCompiler($css);
        return $compiler->spacing();
    }
    /**
     * Parse input into utilities and CSS config.
     *
     * @param string|array $input
     * @param string $css
     * @return array{0: string|array<string>, 1: string}
     */
    private static function parseInput(string|array $input, string $css): array
    {
        if (is_string($input)) {
            return [$input, $css];
        }
        // Array with 'content' key
        if (isset($input['content'])) {
            $utilities = $input['content'];
            $cssConfig = $input['css'] ?? $css;
            return [$utilities, $cssConfig];
        }
        // Plain array of utilities
        return [$input, $css];
    }
}
/**
 * Short alias for the Tailwind class.
 *
 * Provides a more concise API: `tw::generate()` instead of `Tailwind::generate()`.
 * All methods are identical to the Tailwind class.
 *
 * @see Tailwind
 */
class_alias(Tailwind::class, 'BlockstudioVendor\TailwindPHP\tw');
// ==================================================
// Class Name Utilities
// ==================================================
// PHP ports of popular Tailwind companion libraries (clsx, tailwind-merge).
require_once __DIR__ . '/_tailwindphp/lib/clsx/clsx.php';
require_once __DIR__ . '/_tailwindphp/lib/tailwind-merge/index.php';
require_once __DIR__ . '/_tailwindphp/lib/cva/cva.php';
/**
 * The ultimate class name utility: conditional classes + conflict resolution.
 *
 * This is the recommended way to work with Tailwind classes in PHP.
 * Combines conditional class construction with intelligent conflict resolution.
 *
 * @param mixed ...$inputs Class values (strings, arrays, conditionals)
 * @return string Merged class string with conflicts resolved
 *
 * @example
 * cn('px-2 py-1', 'px-4');                       // => 'py-1 px-4'
 * cn('text-red-500', ['text-blue-500' => true]); // => 'text-blue-500'
 * cn('hidden', ['block' => $isVisible]);         // => 'block' (if $isVisible)
 * cn('btn', 'btn-primary', ['btn-lg' => $large]);
 */
function cn(mixed ...$inputs): string
{
    return \BlockstudioVendor\TailwindPHP\Lib\TailwindMerge\cn(...$inputs);
}
/**
 * Merge Tailwind CSS classes, resolving conflicts.
 *
 * Later classes override earlier ones when they conflict.
 *
 * @param mixed ...$args Class values to merge
 * @return string Merged class string with conflicts resolved
 *
 * @example
 * merge('px-2 py-1', 'px-4');                      // => 'py-1 px-4'
 * merge('text-red-500', 'text-blue-500');          // => 'text-blue-500'
 * merge('hover:bg-red-500', 'hover:bg-blue-500');  // => 'hover:bg-blue-500'
 */
function merge(mixed ...$args): string
{
    return \BlockstudioVendor\TailwindPHP\Lib\TailwindMerge\twMerge(...$args);
}
/**
 * Join class names without conflict resolution.
 *
 * Use this when you know there are no conflicts for better performance.
 *
 * @param mixed ...$args Class values to join
 * @return string Joined class string
 *
 * @example
 * join('foo', 'bar');       // => 'foo bar'
 * join('foo', null, 'bar'); // => 'foo bar'
 */
function join(mixed ...$args): string
{
    return \BlockstudioVendor\TailwindPHP\Lib\TailwindMerge\twJoin(...$args);
}
// ==================================================
// Variants (CVA Port)
// ==================================================
// PHP port of CVA (Class Variance Authority) for creating component variants.
// https://github.com/joe-bell/cva
/**
 * Create component style variants.
 *
 * PHP port of CVA (Class Variance Authority). Provides a declarative API
 * for managing component class variations with base classes, variants,
 * compound variants, and default variants.
 *
 * @param array|null $config Configuration with base, variants, compoundVariants, defaultVariants
 * @return callable A function that accepts a single props array and returns a class string
 *
 * @example
 * // Define component styles
 * $button = variants([
 *     'base' => 'btn font-semibold',
 *     'variants' => [
 *         'intent' => [
 *             'primary' => 'bg-blue-500 text-white',
 *             'secondary' => 'bg-gray-200 text-gray-800',
 *         ],
 *         'size' => [
 *             'sm' => 'text-sm px-2 py-1',
 *             'md' => 'text-base px-4 py-2',
 *         ],
 *     ],
 *     'defaultVariants' => [
 *         'intent' => 'primary',
 *         'size' => 'md',
 *     ],
 * ]);
 *
 * // React-style usage with single props object
 * $button();                                        // defaults applied
 * $button(['intent' => 'secondary']);               // override intent
 * $button(['size' => 'sm', 'class' => 'mt-4']);     // override + custom class
 *
 * // Use in a component function with cn() for class extension
 * function Button(array $props = []): string {
 *     static $styles = null;
 *     $styles ??= variants([...config...]);
 *     $class = cn($styles($props), $props['class'] ?? null);
 *     return '<button class="' . $class . '">' . ($props['children'] ?? '') . '</button>';
 * }
 */
function variants(?array $config = null): callable
{
    return \BlockstudioVendor\TailwindPHP\Lib\Cva\cva($config);
}
/**
 * Compose multiple variant components into one.
 *
 * Merges variants from multiple components, allowing you to combine
 * reusable variant definitions. (CVA compose() port)
 *
 * @param callable ...$components Variant component functions
 * @return callable A function that accepts merged props and returns a class string
 *
 * @example
 * $box = variants(['variants' => ['shadow' => ['sm' => 'shadow-sm', 'md' => 'shadow-md']]]);
 * $stack = variants(['variants' => ['gap' => ['1' => 'gap-1', '2' => 'gap-2']]]);
 * $card = compose($box, $stack);
 *
 * $card(['shadow' => 'md', 'gap' => '2']); // => 'shadow-md gap-2'
 */
function compose(callable ...$components): callable
{
    return \BlockstudioVendor\TailwindPHP\Lib\Cva\compose(...$components);
}
