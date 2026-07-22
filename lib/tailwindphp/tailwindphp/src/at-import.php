<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP;

use function BlockstudioVendor\TailwindPHP\Ast\atRule;
use function BlockstudioVendor\TailwindPHP\Ast\context;
use function BlockstudioVendor\TailwindPHP\CssParser\parse;
use function BlockstudioVendor\TailwindPHP\Walk\walk;
use BlockstudioVendor\TailwindPHP\Walk\WalkAction;
/**
 * @import directive handling
 *
 * Port of: packages/tailwindcss/src/at-import.ts
 *
 * @port-deviation:async TypeScript uses async/await with Promise.all() for concurrent
 * stylesheet loading. PHP uses synchronous loading since PHP doesn't have native async.
 *
 * @port-deviation:sourcemaps TypeScript passes `track` parameter to CSS.parse() for
 * source map generation. PHP omits source map tracking.
 */
const FEATURES_NONE = 0;
const FEATURES_AT_IMPORT = 1 << 1;
/**
 * Callback type for loading stylesheets.
 *
 * @param string $id The import path/id
 * @param string $basedir The base directory
 * @return array{path: string, base: string, content: string}
 */
/**
 * Substitute @import at-rules with actual stylesheet contents.
 *
 * @param array &$ast The AST to process
 * @param string $base The base directory
 * @param callable $loadStylesheet Callback to load stylesheets
 * @param int $recurseCount Current recursion depth
 * @param bool $track Whether to track file paths
 * @return int Features flags
 */
function substituteAtImports(array &$ast, string $base, callable $loadStylesheet, int $recurseCount = 0, bool $track = \false): int
{
    $features = FEATURES_NONE;
    walk($ast, function (&$node) use (&$features, $base, $loadStylesheet, $recurseCount, $track) {
        if ($node['kind'] === 'at-rule' && ($node['name'] === '@import' || $node['name'] === '@reference')) {
            $parsed = parseImportParams(ValueParser\parse($node['params']));
            if ($parsed === null) {
                return;
            }
            if ($node['name'] === '@reference') {
                $parsed['media'] = 'reference';
            }
            $features |= FEATURES_AT_IMPORT;
            $uri = $parsed['uri'];
            $layer = $parsed['layer'];
            $media = $parsed['media'];
            $supports = $parsed['supports'];
            // Skip importing data or remote URIs
            if (str_starts_with($uri, 'data:')) {
                return;
            }
            if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
                return;
            }
            // Since we do not have fully resolved paths in core, we can't
            // reliably detect circular imports. Instead, we try to limit the
            // recursion depth.
            if ($recurseCount > 100) {
                throw new \RuntimeException("Exceeded maximum recursion depth while resolving `{$uri}` in `{$base}`)");
            }
            $loaded = $loadStylesheet($uri, $base);
            $importedAst = parse($loaded['content']);
            substituteAtImports($importedAst, $loaded['base'], $loadStylesheet, $recurseCount + 1, $track);
            $contextNode = context(['base' => $loaded['base']], $importedAst);
            $newNodes = buildImportNodes($node, [$contextNode], $layer, $media, $supports);
            return WalkAction::ReplaceSkip($newNodes);
        }
    });
    return $features;
}
/**
 * Parse @import parameters.
 *
 * Modified and inlined version of `parse-statements` from
 * `postcss-import` <https://github.com/postcss/postcss-import>
 * Copyright (c) 2014 Maxime Thirouin, Jason Campbell & Kevin Mårtensson
 * Released under the MIT License.
 *
 * @param array $params Parsed value AST nodes
 * @return array|null Parsed import params or null if invalid
 */
function parseImportParams(array $params): ?array
{
    $uri = null;
    $layer = null;
    $media = null;
    $supports = null;
    for ($i = 0; $i < count($params); $i++) {
        $node = $params[$i];
        if ($node['kind'] === 'separator') {
            continue;
        }
        if ($node['kind'] === 'word' && $uri === null) {
            if (empty($node['value'])) {
                return null;
            }
            if ($node['value'][0] !== '"' && $node['value'][0] !== "'") {
                return null;
            }
            $uri = substr($node['value'], 1, -1);
            continue;
        }
        if ($node['kind'] === 'function' && strtolower($node['value']) === 'url') {
            // `@import` with `url(…)` functions are not inlined but skipped
            return null;
        }
        if ($uri === null) {
            return null;
        }
        if (($node['kind'] === 'word' || $node['kind'] === 'function') && strtolower($node['value']) === 'layer') {
            if ($layer !== null) {
                return null;
            }
            if ($supports !== null) {
                throw new \RuntimeException('`layer(…)` in an `@import` should come before any other functions or conditions');
            }
            if (isset($node['nodes'])) {
                $layer = ValueParser\toCss($node['nodes']);
            } else {
                $layer = '';
            }
            continue;
        }
        if ($node['kind'] === 'function' && strtolower($node['value']) === 'supports') {
            if ($supports !== null) {
                return null;
            }
            $supports = ValueParser\toCss($node['nodes']);
            continue;
        }
        $media = ValueParser\toCss(array_slice($params, $i));
        break;
    }
    if ($uri === null) {
        return null;
    }
    return ['uri' => $uri, 'layer' => $layer, 'media' => $media, 'supports' => $supports];
}
/**
 * Build import nodes with layer, media, and supports wrappers.
 *
 * @param array $importNode The original import node
 * @param array $importedAst The imported AST
 * @param string|null $layer Layer name
 * @param string|null $media Media query
 * @param string|null $supports Supports condition
 * @return array The wrapped AST nodes
 */
function buildImportNodes(array $importNode, array $importedAst, ?string $layer, ?string $media, ?string $supports): array
{
    $root = $importedAst;
    if ($layer !== null) {
        $node = atRule('@layer', $layer, $root);
        $node['src'] = $importNode['src'] ?? null;
        $root = [$node];
    }
    if ($media !== null) {
        $node = atRule('@media', $media, $root);
        $node['src'] = $importNode['src'] ?? null;
        $root = [$node];
    }
    if ($supports !== null) {
        $supportsValue = $supports[0] === '(' ? $supports : "({$supports})";
        $node = atRule('@supports', $supportsValue, $root);
        $node['src'] = $importNode['src'] ?? null;
        $root = [$node];
    }
    return $root;
}
