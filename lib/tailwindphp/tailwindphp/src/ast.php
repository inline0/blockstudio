<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Ast;

use BlockstudioVendor\TailwindPHP\LightningCss\LightningCss;
/**
 * AST node types and builder functions for TailwindPHP.
 *
 * Port of: packages/tailwindcss/src/ast.ts
 *
 * @port-deviation:structure The TypeScript version includes `optimizeAst()` in this file,
 * but in PHP it's implemented in `index.php` as part of the compilation pipeline.
 * This keeps the PHP version simpler and avoids circular dependencies.
 *
 * @port-deviation:sourcemaps The TypeScript AST nodes include `src` and `dst` properties
 * for source map tracking. PHP version omits these as source maps are not implemented.
 *
 * @port-deviation:types TypeScript uses explicit type definitions (StyleRule, AtRule, etc.).
 * PHP uses PHPDoc @typedef annotations and array shapes for IDE support.
 *
 * @port-deviation:performance toCss() uses array accumulation + implode instead of string
 * concatenation, pre-computed indent strings, and a standalone function instead of a
 * closure. These optimizations provide ~50% speedup while maintaining identical output.
 */
const AT_SIGN = 0x40;
/**
 * @typedef array{kind: 'rule', selector: string, nodes: array<AstNode>} StyleRule
 * @typedef array{kind: 'at-rule', name: string, params: string, nodes: array<AstNode>} AtRule
 * @typedef array{kind: 'declaration', property: string, value: string|null, important: bool} Declaration
 * @typedef array{kind: 'comment', value: string} Comment
 * @typedef array{kind: 'context', context: array<string, string|bool>, nodes: array<AstNode>} Context
 * @typedef array{kind: 'at-root', nodes: array<AstNode>} AtRoot
 * @typedef StyleRule|AtRule|Declaration|Comment|Context|AtRoot AstNode
 */
/**
 * Create a style rule node.
 *
 * @param string $selector
 * @param array<AstNode> $nodes
 * @return array{kind: 'rule', selector: string, nodes: array}
 */
function styleRule(string $selector, array $nodes = []): array
{
    return ['kind' => 'rule', 'selector' => $selector, 'nodes' => $nodes];
}
/**
 * Create an at-rule node.
 *
 * @param string $name
 * @param string $params
 * @param array<AstNode> $nodes
 * @return array{kind: 'at-rule', name: string, params: string, nodes: array}
 */
function atRule(string $name, string $params = '', array $nodes = []): array
{
    return ['kind' => 'at-rule', 'name' => $name, 'params' => $params, 'nodes' => $nodes];
}
/**
 * Create a rule node (either style rule or at-rule based on selector).
 *
 * @param string $selector
 * @param array<AstNode> $nodes
 * @return array
 */
function rule(string $selector, array $nodes = []): array
{
    if (strlen($selector) > 0 && ord($selector[0]) === AT_SIGN) {
        return parseAtRule($selector, $nodes);
    }
    return styleRule($selector, $nodes);
}
/**
 * Create a declaration node.
 *
 * @param string $property
 * @param string|null $value
 * @param bool $important
 * @return array{kind: 'declaration', property: string, value: string|null, important: bool}
 */
function decl(string $property, ?string $value, bool $important = \false): array
{
    // Note: LightningCSS optimizations are applied later in optimizeAst,
    // not during AST construction. This preserves the original values
    // for accurate testing and debugging.
    return ['kind' => 'declaration', 'property' => $property, 'value' => $value, 'important' => $important];
}
/**
 * Create a comment node.
 *
 * @param string $value
 * @return array{kind: 'comment', value: string}
 */
function comment(string $value): array
{
    return ['kind' => 'comment', 'value' => $value];
}
/**
 * Create a context node.
 *
 * @param array<string, string|bool> $context
 * @param array<AstNode> $nodes
 * @return array{kind: 'context', context: array, nodes: array}
 */
function context(array $context, array $nodes): array
{
    return ['kind' => 'context', 'context' => $context, 'nodes' => $nodes];
}
/**
 * Create an at-root node.
 *
 * @param array<AstNode> $nodes
 * @return array{kind: 'at-root', nodes: array}
 */
function atRoot(array $nodes): array
{
    return ['kind' => 'at-root', 'nodes' => $nodes];
}
/**
 * Deep clone an AST node.
 *
 * @port-deviation:sourcemaps TypeScript version copies src/dst properties for source map tracking.
 * PHP version omits these as source maps are not implemented.
 *
 * @param array $node
 * @return array
 */
function cloneAstNode(array $node): array
{
    switch ($node['kind']) {
        case 'rule':
            return ['kind' => $node['kind'], 'selector' => $node['selector'], 'nodes' => array_map('BlockstudioVendor\TailwindPHP\Ast\cloneAstNode', $node['nodes'])];
        case 'at-rule':
            return ['kind' => $node['kind'], 'name' => $node['name'], 'params' => $node['params'], 'nodes' => array_map('BlockstudioVendor\TailwindPHP\Ast\cloneAstNode', $node['nodes'])];
        case 'at-root':
            return ['kind' => $node['kind'], 'nodes' => array_map('BlockstudioVendor\TailwindPHP\Ast\cloneAstNode', $node['nodes'])];
        case 'context':
            return ['kind' => $node['kind'], 'context' => $node['context'], 'nodes' => array_map('BlockstudioVendor\TailwindPHP\Ast\cloneAstNode', $node['nodes'])];
        case 'declaration':
            return ['kind' => $node['kind'], 'property' => $node['property'], 'value' => $node['value'], 'important' => $node['important']];
        case 'comment':
            return ['kind' => $node['kind'], 'value' => $node['value']];
        default:
            throw new \Exception("Unknown node kind: {$node['kind']}");
    }
}
// Pre-computed indent strings for toCss (up to depth 10)
const INDENTS = ['', '  ', '    ', '      ', '        ', '          ', '            ', '              ', '                ', '                  ', '                    '];
/**
 * Convert AST to CSS string.
 *
 * @port-deviation:sourcemaps TypeScript version accepts a `track` parameter for source map tracking.
 * PHP version omits this as source maps are not implemented.
 *
 * @port-deviation:minify The `$minify` mode has no TypeScript equivalent; upstream relies on
 * LightningCSS for minification. When enabled, the serializer emits compact CSS directly:
 * no indentation or newlines, semicolon-joined declarations, no comments, structurally
 * skipped empty rules, and value-level minification via LightningCss::minifyValue().
 *
 * @param array<AstNode> $ast
 * @param bool $minify Emit minified CSS instead of pretty-printed CSS
 * @return string
 */
function toCss(array $ast, bool $minify = \false): string
{
    $parts = [];
    if ($minify) {
        stringifyNodesMinified($ast, $parts);
    } else {
        stringifyNodes($ast, 0, $parts);
    }
    return implode('', $parts);
}
/**
 * Stringify AST nodes into parts array (avoids string concatenation).
 *
 * @param array $nodes
 * @param int $depth
 * @param array &$parts
 */
function stringifyNodes(array $nodes, int $depth, array &$parts): void
{
    $indent = $depth < 11 ? INDENTS[$depth] : str_repeat('  ', $depth);
    foreach ($nodes as $node) {
        switch ($node['kind']) {
            case 'declaration':
                if ($node['important']) {
                    $parts[] = $indent . $node['property'] . ': ' . $node['value'] . " !important;\n";
                } else {
                    $parts[] = $indent . $node['property'] . ': ' . $node['value'] . ";\n";
                }
                break;
            case 'rule':
                $parts[] = $indent . $node['selector'] . " {\n";
                stringifyNodes($node['nodes'], $depth + 1, $parts);
                $parts[] = $indent . "}\n";
                break;
            case 'at-rule':
                if (empty($node['nodes'])) {
                    $parts[] = $indent . $node['name'] . ' ' . $node['params'] . ";\n";
                } else {
                    $params = $node['params'] !== '' ? ' ' . $node['params'] . ' ' : ' ';
                    $parts[] = $indent . $node['name'] . $params . "{\n";
                    stringifyNodes($node['nodes'], $depth + 1, $parts);
                    $parts[] = $indent . "}\n";
                }
                break;
            case 'comment':
                $parts[] = $indent . '/*' . $node['value'] . "*/\n";
                break;
        }
    }
}
// Fragment kinds for minified whitespace compaction
const MINIFY_FRAGMENT_SELECTOR = 0;
const MINIFY_FRAGMENT_PARAMS = 1;
const MINIFY_FRAGMENT_VALUE = 2;
/**
 * Stringify AST nodes into minified parts (no indentation, no newlines,
 * no comments, trailing semicolons trimmed before closing braces, and
 * structurally empty rules skipped).
 *
 * @param array $nodes
 * @param array &$parts
 */
// Cap for the bounded memo caches used during minified serialization
const MINIFY_CACHE_CAP = 20000;
function stringifyNodesMinified(array $nodes, array &$parts): void
{
    // Memo caches for the pure per-fragment transforms. Selectors and values
    // repeat heavily across nodes and across warm builds, so cache the
    // minified form keyed by the raw fragment (bounded; reset at the cap).
    static $valueCache = [];
    static $selectorCache = [];
    static $paramsCache = [];
    foreach ($nodes as $node) {
        switch ($node['kind']) {
            case 'declaration':
                $property = $node['property'];
                $value = $node['value'];
                if ($value === null || $value === '') {
                    $value = '';
                } elseif (($value === 'normal' || $value === 'bold') && ($property === 'font-weight' || str_ends_with($property, '-font-weight'))) {
                    $value = $value === 'normal' ? '400' : '700';
                } else {
                    $cached = $valueCache[$value] ?? null;
                    if ($cached === null) {
                        $cached = minifyValueFragment($value);
                        if (count($valueCache) >= MINIFY_CACHE_CAP) {
                            $valueCache = [];
                        }
                        $valueCache[$value] = $cached;
                    }
                    $value = $cached;
                }
                $parts[] = $node['important'] ? $property . ':' . $value . ' !important;' : $property . ':' . $value . ';';
                break;
            case 'rule':
                $selector = $node['selector'];
                $cached = $selectorCache[$selector] ?? null;
                if ($cached === null) {
                    $cached = strpbrk($selector, " \t\n\r") !== \false ? minifyFragmentScan($selector, MINIFY_FRAGMENT_SELECTOR) : $selector;
                    if (count($selectorCache) >= MINIFY_CACHE_CAP) {
                        $selectorCache = [];
                    }
                    $selectorCache[$selector] = $cached;
                }
                $headerIndex = count($parts);
                $parts[] = $cached . '{';
                stringifyNodesMinified($node['nodes'], $parts);
                closeMinifiedBlock($parts, $headerIndex);
                break;
            case 'at-rule':
                $params = $node['params'];
                if ($params !== '') {
                    $cached = $paramsCache[$params] ?? null;
                    if ($cached === null) {
                        $cached = strpbrk($params, " \t\n\r") !== \false ? minifyFragmentScan($params, MINIFY_FRAGMENT_PARAMS) : $params;
                        if (count($paramsCache) >= MINIFY_CACHE_CAP) {
                            $paramsCache = [];
                        }
                        $paramsCache[$params] = $cached;
                    }
                    $params = $cached;
                }
                if (empty($node['nodes'])) {
                    $parts[] = $params !== '' ? $node['name'] . ' ' . $params . ';' : $node['name'] . ';';
                } else {
                    $headerIndex = count($parts);
                    $parts[] = $params !== '' ? $node['name'] . ' ' . $params . '{' : $node['name'] . '{';
                    stringifyNodesMinified($node['nodes'], $parts);
                    closeMinifiedBlock($parts, $headerIndex);
                }
                break;
        }
    }
}
/**
 * Minify a declaration value fragment: value-level minifier wins (hex
 * shortening, zero units) plus whitespace compaction. The font-weight
 * keyword shortening is property-dependent and handled by the caller so
 * results stay cacheable per value.
 *
 * @param string $value
 * @return string
 */
function minifyValueFragment(string $value): string
{
    if (strpbrk($value, '#0') !== \false) {
        $value = LightningCss::minifyValue($value);
    }
    if (strpos($value, ', ') !== \false || strpos($value, ' ,') !== \false) {
        $value = stripTopLevelCommaSpaces($value);
    }
    if (strpos($value, '  ') !== \false || strpbrk($value, "\t\n\r") !== \false) {
        $value = minifyFragmentScan($value, MINIFY_FRAGMENT_VALUE);
    }
    return $value;
}
/**
 * Close a minified block opened at $headerIndex: roll the header back when
 * no content was emitted (structurally empty rule), otherwise trim the
 * trailing semicolon and emit the closing brace.
 *
 * @param array &$parts
 * @param int $headerIndex
 */
function closeMinifiedBlock(array &$parts, int $headerIndex): void
{
    $last = count($parts) - 1;
    if ($last === $headerIndex) {
        array_pop($parts);
        return;
    }
    if (str_ends_with($parts[$last], ';')) {
        $parts[$last] = substr($parts[$last], 0, -1);
    }
    $parts[] = '}';
}
/**
 * Remove spaces around commas that sit at the top nesting level of a
 * declaration value (outside parentheses and quoted strings). Values are
 * already whitespace-normalized by LightningCss::optimizeValue, so this
 * avoids a full character scan on the hot path.
 *
 * @param string $value
 * @return string
 */
function stripTopLevelCommaSpaces(string $value): string
{
    if (strpbrk($value, '()\'"\\') === \false) {
        return str_replace([' ,', ', '], ',', $value);
    }
    $len = strlen($value);
    $result = '';
    $start = 0;
    $i = 0;
    $depth = 0;
    while ($i < $len) {
        $i += strcspn($value, ",()'\"\\", $i);
        if ($i >= $len) {
            break;
        }
        $ch = $value[$i];
        if ($ch === '\\') {
            $i += 2;
            continue;
        }
        if ($ch === '(') {
            $depth++;
            $i++;
            continue;
        }
        if ($ch === ')') {
            if ($depth > 0) {
                $depth--;
            }
            $i++;
            continue;
        }
        if ($ch === '"' || $ch === "'") {
            $i++;
            while ($i < $len) {
                $i += strcspn($value, $ch . '\\', $i);
                if ($i >= $len) {
                    break;
                }
                if ($value[$i] === '\\') {
                    $i += 2;
                    continue;
                }
                $i++;
                break;
            }
            continue;
        }
        // Top-level comma: trim spaces on both sides
        if ($depth === 0) {
            $left = $i;
            while ($left > $start && $value[$left - 1] === ' ') {
                $left--;
            }
            $result .= substr($value, $start, $left - $start) . ',';
            $i++;
            while ($i < $len && $value[$i] === ' ') {
                $i++;
            }
            $start = $i;
        } else {
            $i++;
        }
    }
    return $start === 0 ? $value : $result . substr($value, $start);
}
/**
 * Character scan for minified fragment compaction: collapses whitespace
 * runs to single spaces and drops spaces where CSS allows it (fragment
 * edges, around top-level commas, around top-level selector combinators,
 * and after colons in at-rule params). Never touches quoted strings or
 * escaped characters.
 *
 * @param string $fragment
 * @param int $mode One of the MINIFY_FRAGMENT_* constants
 * @return string
 */
function minifyFragmentScan(string $fragment, int $mode): string
{
    $out = '';
    $len = strlen($fragment);
    $i = 0;
    $depth = 0;
    while ($i < $len) {
        $chunk = strcspn($fragment, " \t\n\r\"'()\\", $i);
        if ($chunk > 0) {
            $out .= substr($fragment, $i, $chunk);
            $i += $chunk;
            if ($i >= $len) {
                break;
            }
        }
        $ch = $fragment[$i];
        if ($ch === '\\') {
            $out .= substr($fragment, $i, 2);
            $i += 2;
            continue;
        }
        if ($ch === '(') {
            $depth++;
            $out .= '(';
            $i++;
            continue;
        }
        if ($ch === ')') {
            if ($depth > 0) {
                $depth--;
            }
            $out .= ')';
            $i++;
            continue;
        }
        if ($ch === '"' || $ch === "'") {
            $end = $i + 1;
            while ($end < $len) {
                $end += strcspn($fragment, $ch . '\\', $end);
                if ($end >= $len) {
                    break;
                }
                if ($fragment[$end] === '\\') {
                    $end += 2;
                    continue;
                }
                $end++;
                break;
            }
            $out .= substr($fragment, $i, $end - $i);
            $i = $end;
            continue;
        }
        // Whitespace run: collapse to one space or drop entirely
        $i += strspn($fragment, " \t\n\r", $i);
        $prev = $out !== '' ? $out[strlen($out) - 1] : '';
        $next = $i < $len ? $fragment[$i] : '';
        if ($prev === '' || $next === '') {
            continue;
        }
        if ($depth === 0 && ($prev === ',' || $next === ',')) {
            continue;
        }
        if ($mode === MINIFY_FRAGMENT_PARAMS && $prev === ':') {
            continue;
        }
        if ($mode === MINIFY_FRAGMENT_SELECTOR && $depth === 0 && (str_contains('>~+', $prev) || str_contains('>~+', $next))) {
            continue;
        }
        $out .= ' ';
    }
    return $out;
}
/**
 * Parse an at-rule from a buffer string.
 *
 * @port-deviation:location TypeScript version imports parseAtRule from css-parser.ts.
 * PHP version defines it here to avoid circular dependencies.
 *
 * @param string $buffer
 * @param array<AstNode> $nodes
 * @return array{kind: 'at-rule', name: string, params: string, nodes: array}
 */
function parseAtRule(string $buffer, array $nodes = []): array
{
    $name = $buffer;
    $params = '';
    // Find where the name ends and params begin
    $len = strlen($buffer);
    for ($i = 5; $i < $len; $i++) {
        $char = ord($buffer[$i]);
        // SPACE = 0x20, TAB = 0x09, OPEN_PAREN = 0x28
        if ($char === 0x20 || $char === 0x9 || $char === 0x28) {
            $name = substr($buffer, 0, $i);
            $params = substr($buffer, $i);
            break;
        }
    }
    return atRule(trim($name), trim($params), $nodes);
}
