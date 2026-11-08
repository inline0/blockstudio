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
                    $keepZeroUnits = LightningCss::keepsZeroUnits($property);
                    $key = $keepZeroUnits ? "\x00" . $value : $value;
                    $cached = $valueCache[$key] ?? null;
                    if ($cached === null) {
                        $cached = minifyValueFragment($value, $keepZeroUnits);
                        if (count($valueCache) >= MINIFY_CACHE_CAP) {
                            $valueCache = [];
                        }
                        $valueCache[$key] = $cached;
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
function minifyValueFragment(string $value, bool $keepZeroUnits = \false): string
{
    if (strpbrk($value, '#0') !== \false) {
        $value = LightningCss::minifyValueTokens($value, !$keepZeroUnits);
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
const PIPE = 0x7c;
// A set of at-rules that can be hoisted to the top without any repercussions.
// Typically at-rules that rely on the environment, not parent information and
// contain other rules/declarations.
const HOISTABLE_AT_RULES = ['@container' => \true, '@layer' => \true, '@media' => \true, '@page' => \true, '@starting-style' => \true, '@supports' => \true, '@view-transition' => \true];
// A set of at-rules that can be dropped if they don't contain any nodes. We
// don't have the distinction between an at-rule with no body, or an at-rule
// with a body that is empty right now.
const DROPPABLE_IF_EMPTY_AT_RULES = ['@container' => \true, '@media' => \true, '@page' => \true, '@starting-style' => \true, '@supports' => \true, '@view-transition' => \true];
/**
 * Flatten nested rules until a declaration-carrying level is reached,
 * substituting `&` with the parent selector (using `:is(…)` semantics where
 * required), hoisting conditional at-rules around the emitted rules, and
 * merging adjacent same-selector/same-at-rule siblings as it goes. Levels
 * below the first declaration keep their native nesting syntax; downleveling
 * those is lightningcss's job (LightningCss::transformNesting here).
 *
 * Port of: handleNesting() in packages/tailwindcss/src/ast.ts
 *
 * @port-deviation:references The TypeScript version threads live array
 * references around (`nodes` points into the result tree, dedupe tracking is
 * a Set of array references, `skipExit` is a Set keyed on node identity). PHP
 * arrays have value semantics and no identity, so the emission target is an
 * index path into `$result`, dedupe tracking stores those paths, and each
 * rule/at-rule enter pushes a frame flag that its exit pops in place of
 * `skipExit`. Two behavioral corners fall out of this: emissions that
 * upstream silently drops into a detached array (declarations directly inside
 * conditional at-rules with no rule on the stack) reset the path instead, and
 * the grandparent check in the `&`-substitution compares nodes by value
 * rather than identity (only distinguishable for a complex selector holding
 * two identical `&`-carrying compounds).
 *
 * @port-deviation:whitespace Upstream joins parent and child selector
 * segments without trimming and relies on lightningcss reserializing every
 * selector downstream. Our lightningcss layer does not reparse selectors, so
 * segments are trimmed here to match the reference output.
 *
 * @param array<AstNode> $ast
 * @return array<AstNode>
 */
function handleNesting(array $ast): array
{
    // Cache of parsed parent selectors (upstream: DefaultMap over SelectorParser.parse)
    $parseSelectorCache = [];
    $parseSelector = function (string $selector) use (&$parseSelectorCache): array {
        return $parseSelectorCache[$selector] ??= \BlockstudioVendor\TailwindPHP\SelectorParser\parse($selector);
    };
    // Track `rule` selectors as we go
    $selectorStack = [];
    // Track `at-rule` information as we go. Tracking this separately from the
    // selector stack for rules such that we can hoist this above all the rules.
    $atRuleStack = [];
    // The current "nodes" we can push to, addressed as an index path into
    // $result where each hop descends into that element's `nodes` array.
    $nodesPath = null;
    // Optimization: Track the declaration properties we've seen in the
    // current nodes.
    $seenDeclarationProperties = [];
    // Track node paths where we want to dedupe declarations
    $dedupeDeclarationPaths = [];
    // The final, new AST
    $result = [];
    // Frame flags replacing upstream's `skipExit` set: whether each entered
    // rule/at-rule pushed onto its stack (true) or must skip its exit (false).
    $ruleFrames = [];
    $atRuleFrames = [];
    $resolveTarget = function &(array $path) use (&$result): array {
        $target =& $result;
        foreach ($path as $i) {
            $target =& $target[$i]['nodes'];
        }
        return $target;
    };
    $emit = function (array $node) use (&$result, &$nodesPath, &$seenDeclarationProperties, &$dedupeDeclarationPaths, &$selectorStack, &$atRuleStack, &$resolveTarget): void {
        // Existing nodes are available, emit into those nodes
        if ($nodesPath !== null) {
            // Optimization: track used declarations in the current node.
            if ($node['kind'] === 'declaration') {
                if (isset($seenDeclarationProperties[$node['property']])) {
                    $dedupeDeclarationPaths[implode('.', $nodesPath)] = $nodesPath;
                } else {
                    $seenDeclarationProperties[$node['property']] = \true;
                }
            }
            $target =& $resolveTarget($nodesPath);
            $target[] = $node;
            return;
        }
        // Nothing available, setup a fresh node.
        //
        // There are no parent rules or at-rules available, which means that
        // we can emit the node as-is.
        if (count($selectorStack) === 0 && count($atRuleStack) === 0) {
            $lastNode = $result[count($result) - 1] ?? null;
            // Optimization: when the current and last node are the same,
            // ignore the new node entirely otherwise we will get unnecessary
            // duplicate results.
            //
            // We only care about at-rules with no body because some of them
            // (such as `@charset` or `@layer`) need to be emitted. A normal
            // rule that's empty doesn't need to be emitted. At-rules _with_ a
            // body (such as `@font-face` or `@keyframes`) can share the same
            // prelude while containing different bodies, so they must all be
            // emitted.
            if ($lastNode !== null && $lastNode['kind'] === 'at-rule' && $node['kind'] === 'at-rule' && count($lastNode['nodes'] ?? []) === 0 && count($node['nodes'] ?? []) === 0 && $lastNode['name'] === $node['name'] && ($lastNode['params'] ?? '') === ($node['params'] ?? '')) {
                return;
            }
            $result[] = $node;
            return;
        }
        // Track the new "parent" nodes
        $nodes = [$node];
        // Clear out seen declarations from the previous work in progress nodes
        $seenDeclarationProperties = [];
        // Track new declaration
        if ($node['kind'] === 'declaration') {
            $seenDeclarationProperties[$node['property']] = \true;
        }
        $targetPath = [];
        $atRuleOffset = 0;
        // Optimization: merge adjacent at-rules
        //
        // Figure out whether we can push our new node into a previous node
        // that was already emitted. We have to make sure that the order stays
        // the same, so we only ever look at the last node that was emitted.
        {
            $target =& $resolveTarget($targetPath);
            $lastIndex = count($target) - 1;
            $lastNode = $lastIndex >= 0 ? $target[$lastIndex] : null;
            if ($lastNode !== null && $lastNode['kind'] === 'at-rule') {
                $stackSize = count($atRuleStack);
                for ($i = 0; $i < $stackSize; $i++) {
                    $atRuleInfo = $atRuleStack[$i];
                    if ($lastNode === null) {
                        break;
                    }
                    if ($lastNode['kind'] !== 'at-rule') {
                        break;
                    }
                    if ($lastNode['name'] !== $atRuleInfo[0]) {
                        break;
                    }
                    if (($lastNode['params'] ?? '') !== $atRuleInfo[1]) {
                        break;
                    }
                    $atRuleOffset++;
                    $targetPath[] = $lastIndex;
                    $target =& $resolveTarget($targetPath);
                    $lastIndex = count($target) - 1;
                    $lastNode = $lastIndex >= 0 ? $target[$lastIndex] : null;
                }
            }
            unset($target);
        }
        // Build up the rule
        $root = null;
        $hasRule = \false;
        if (count($selectorStack) > 0) {
            $selector = $selectorStack[count($selectorStack) - 1];
            // Optimization: merge adjacent rules with the same selector
            //
            // Figure out whether we can push into an existing rule. If we
            // have some at-rules that we have to keep into account, then we
            // definitely can't.
            if (count($atRuleStack) - $atRuleOffset <= 0) {
                $target =& $resolveTarget($targetPath);
                $lastIndex = count($target) - 1;
                $lastNode = $lastIndex >= 0 ? $target[$lastIndex] : null;
                if ($lastNode !== null && $lastNode['kind'] === 'rule' && $lastNode['selector'] === $selector) {
                    foreach ($nodes as $mergedNode) {
                        $target[$lastIndex]['nodes'][] = $mergedNode;
                    }
                    unset($target);
                    // Ensure that our current nodes path points at the nodes
                    // of the last node, otherwise we will lose information.
                    $nodesPath = [...$targetPath, $lastIndex];
                    // We appended a group that could contain declarations
                    // already in the existing rule, so let the final dedupe
                    // pass handle it once.
                    $dedupeDeclarationPaths[implode('.', $nodesPath)] = $nodesPath;
                    // We know that we don't have to handle any more at-rules,
                    // so we can bail early since we just merged the nodes
                    // with the same selector.
                    return;
                }
                unset($target);
            }
            // Can't push into existing node, create a new node
            $root = rule($selector, $nodes);
            $hasRule = \true;
        }
        // Wrap in at-rules, if we can push into an existing node then we can
        // ignore `offset` amount of nodes since the `root`/`nodes` will
        // already point to a nested node.
        $wrapperCount = 0;
        for ($i = count($atRuleStack) - 1; $i >= $atRuleOffset; $i--) {
            [$atRuleName, $atRuleParams] = $atRuleStack[$i];
            $root = atRule($atRuleName, $atRuleParams, $root !== null ? [$root] : $nodes);
            $wrapperCount++;
        }
        // Track the root node in the AST
        $target =& $resolveTarget($targetPath);
        if ($root !== null) {
            $rootIndex = count($target);
            $target[] = $root;
            unset($target);
            // Point the nodes path at the innermost `nodes` array that
            // received the emitted node (the rule body, or the innermost
            // at-rule body when there is no rule).
            $layers = $wrapperCount + ($hasRule ? 1 : 0);
            $nodesPath = [...$targetPath, $rootIndex, ...array_fill(0, max(0, $layers - 1), 0)];
            return;
        }
        // We didn't build up any new root, so we can move our node directly
        // into the target. This can happen when we emit a node that is not a
        // declaration or a comment. (Upstream keeps `nodes` pointing at a
        // detached array here; we reset the path so later emissions re-enter
        // this setup instead of being dropped.)
        foreach ($nodes as $movedNode) {
            $target[] = $movedNode;
        }
        unset($target);
        $nodesPath = null;
    };
    \BlockstudioVendor\TailwindPHP\Walk\walk($ast, ['enter' => function (array &$node) use (&$selectorStack, &$atRuleStack, &$nodesPath, &$ruleFrames, &$atRuleFrames, $emit, $parseSelector) {
        switch ($node['kind']) {
            case 'rule':
                $nodesPath = null;
                // Start a new level
                // First time we see a rule
                if (count($selectorStack) === 0) {
                    // A rule with a selector containing `&` should
                    // replace the `&` with `:scope` if there is no parent
                    // rule.
                    //
                    // Note: there could be false positives when the `&`
                    // is escaped or part of a string inside an attribute
                    // selector. But the SelectorParser will take care of
                    // that.
                    if (str_contains($node['selector'], '&')) {
                        $selectorAst = \BlockstudioVendor\TailwindPHP\SelectorParser\parse($node['selector']);
                        $changed = \false;
                        \BlockstudioVendor\TailwindPHP\Walk\walk($selectorAst, function (array &$selectorNode) use (&$changed) {
                            if ($selectorNode['kind'] === 'selector' && $selectorNode['value'] === '&') {
                                $changed = \true;
                                $selectorNode['value'] = ':scope';
                            }
                        });
                        $selectorStack[] = $changed ? \BlockstudioVendor\TailwindPHP\SelectorParser\toCss($selectorAst) : $node['selector'];
                    } else {
                        $selectorStack[] = $node['selector'];
                    }
                } else {
                    // A rule with just `&` can be replaced by its
                    // children. Let's ignore this node and keep walking
                    // its children.
                    if ($node['selector'] === '&') {
                        $ruleFrames[] = \false;
                        return null;
                    }
                    $lastSelector = $selectorStack[count($selectorStack) - 1];
                    $mapped = [];
                    foreach (\BlockstudioVendor\TailwindPHP\Utils\segment($node['selector'], ',') as $segmentSelector) {
                        // @port-deviation:whitespace (see function docblock)
                        $segmentSelector = trim($segmentSelector);
                        // Fast path: we know there isn't an `&` so we can
                        // prepend the parent selector immediately.
                        if (!str_contains($segmentSelector, '&')) {
                            $lastAst = $parseSelector($lastSelector);
                            $mapped[] = (count($lastAst) === 1 && $lastAst[0]['kind'] === 'list' ? ':is(' . $lastSelector . ')' : $lastSelector) . ' ' . $segmentSelector;
                            continue;
                        }
                        // Slow path: we need to replace the `&` with the
                        // parent selector. A simple string replace won't
                        // work because a `&` could be escaped, or could
                        // be part of an attribute selector. Much safer to
                        // parse the selector and replace the `&` that way.
                        $selectorAst = \BlockstudioVendor\TailwindPHP\SelectorParser\parse($segmentSelector);
                        $changed = \false;
                        \BlockstudioVendor\TailwindPHP\Walk\walk($selectorAst, ['enter' => function (array &$selectorNode, $ctx) use (&$changed, $lastSelector, $parseSelector) {
                            if ($selectorNode['kind'] !== 'selector' || $selectorNode['value'] !== '&') {
                                return null;
                            }
                            $changed = \true;
                            // Safest option: use `:is(…)` semantics
                            // when substituting `&` for the parent
                            // selector.
                            $selectorNode['value'] = ':is(' . $lastSelector . ')';
                            // We should always have a parent, so this
                            // shouldn't happen
                            if ($ctx->parent === null) {
                                return null;
                            }
                            // Optimizations:
                            $parentAst = $parseSelector($lastSelector);
                            // 1. If we're dealing with multiple
                            //    selectors, then we know that the
                            //    `:is(…)` needs to stay.
                            if (count($parentAst) === 1 && $parentAst[0]['kind'] === 'list') {
                                return null;
                                // Keep `:is(…)` semantics
                            }
                            // 2. We know that `&` is standalone when
                            //    it's inside of a complex selector.
                            //    E.g. `[before] & [after]`
                            if ($ctx->parent['kind'] === 'complex') {
                                // `& [after]` — `:is(…)` semantics
                                // are not required.
                                if ($ctx->index === 0) {
                                    $selectorNode['value'] = $lastSelector;
                                    return null;
                                }
                                // `[before] &` — `:is(…)` semantics
                                // are required for a complex parent
                                // selector.
                                if ($ctx->index === count($ctx->siblings) - 1) {
                                    if ($parentAst[0]['kind'] === 'complex') {
                                        return null;
                                        // Keep `:is(…)` semantics
                                    }
                                    $selectorNode['value'] = $lastSelector;
                                    return null;
                                }
                                // `[before] & [after]` — `:is(…)`
                                // semantics are required for a
                                // complex parent selector.
                                if ($parentAst[0]['kind'] === 'complex') {
                                    return null;
                                    // Keep `:is(…)` semantics
                                }
                                $selectorNode['value'] = $lastSelector;
                                return null;
                            }
                            // 3. We know that `&` is attached to some
                            //    other selector when it's inside of a
                            //    compound selector. E.g. `[before]&[after]`
                            if ($ctx->parent['kind'] === 'compound') {
                                if ($parentAst[0]['kind'] === 'complex') {
                                    $path = $ctx->path();
                                    $grandParent = $path[count($path) - 2] ?? null;
                                    // When our compound parent is
                                    // part of a complex selector, and
                                    // it's not the very first node,
                                    // then we can't safely get rid of
                                    // the `:is(…)` if the last
                                    // selector is a complex selector
                                    // as well.
                                    if ($grandParent !== null && $grandParent['kind'] === 'complex' && ($grandParent['nodes'][0] ?? null) !== $ctx->parent) {
                                        return null;
                                        // Keep `:is(…)` semantics
                                    }
                                }
                                // `&*` and `&div` are invalid CSS so
                                // these should stay invalid. They
                                // should be written as `*&` and
                                // `div&` instead.
                                foreach (array_slice($ctx->siblings, $ctx->index + 1) as $sibling) {
                                    if (\BlockstudioVendor\TailwindPHP\SelectorParser\isUniversalSelector($sibling) || \BlockstudioVendor\TailwindPHP\SelectorParser\isTypeSelector($sibling)) {
                                        return null;
                                        // Keep `:is(…)` semantics
                                    }
                                }
                                // `&[after]` — `:is(…)` semantics are
                                // not required.
                                if ($ctx->index === 0) {
                                    $selectorNode['value'] = $lastSelector;
                                    return null;
                                }
                                // `[before]&` — `:is(…)` semantics
                                // are required for a complex parent,
                                // a universal selector, or a type
                                // selector.
                                if ($ctx->index === count($ctx->siblings) - 1) {
                                    if ($parentAst[0]['kind'] === 'complex' || \BlockstudioVendor\TailwindPHP\SelectorParser\isUniversalSelector($parentAst[0]) || \BlockstudioVendor\TailwindPHP\SelectorParser\isTypeSelector($parentAst[0])) {
                                        return null;
                                        // Keep `:is(…)` semantics
                                    }
                                    $selectorNode['value'] = $lastSelector;
                                    return null;
                                }
                                // `[before]&[after]` — same
                                // constraints as `[before]&`.
                                if ($parentAst[0]['kind'] === 'complex' || \BlockstudioVendor\TailwindPHP\SelectorParser\isUniversalSelector($parentAst[0]) || \BlockstudioVendor\TailwindPHP\SelectorParser\isTypeSelector($parentAst[0])) {
                                    return null;
                                    // Keep `:is(…)` semantics
                                }
                                $selectorNode['value'] = $lastSelector;
                                return null;
                            }
                            // 4. When the current node is a function
                            //    argument (e.g. `:not(&)`), then we
                            //    can drop the `:is(…)` entirely.
                            if ($ctx->parent['kind'] === 'function') {
                                $selectorNode['value'] = $lastSelector;
                                return null;
                            }
                            return null;
                        }, 'exit' => function (array &$selectorNode, $ctx) {
                            // Optimization: We can remove the
                            // universal selector `*` if it is part of
                            // a compound selector. E.g.:
                            //
                            // - `*:hover` → `:hover`
                            // - `*[attribute]` → `[attribute]`
                            //
                            // Except when the `*` is a namespace,
                            // e.g. `*|div`, because `*|div` (any
                            // namespace) and `|div` (no namespace)
                            // have different meanings.
                            if ($ctx->index === 0 && count($ctx->siblings) > 1 && $ctx->parent !== null && $ctx->parent['kind'] === 'compound' && \BlockstudioVendor\TailwindPHP\SelectorParser\isUniversalSelector($selectorNode)) {
                                $next = $ctx->siblings[1];
                                if ($next['kind'] === 'selector' && ord($next['value'][0] ?? "\x00") === PIPE) {
                                    return null;
                                }
                                return \BlockstudioVendor\TailwindPHP\Walk\WalkAction::ReplaceSkip([]);
                            }
                            return null;
                        }]);
                        if ($changed) {
                            $mapped[] = \BlockstudioVendor\TailwindPHP\SelectorParser\toCss($selectorAst);
                            continue;
                        }
                        // It could be that `&` was not found as an actual
                        // selector, in that case we still have to prepend
                        // the parent selector.
                        $lastAst = $parseSelector($lastSelector);
                        $mapped[] = (count($lastAst) === 1 && $lastAst[0]['kind'] === 'list' ? ':is(' . $lastSelector . ')' : $lastSelector) . ' ' . $segmentSelector;
                    }
                    $selectorStack[] = implode(', ', $mapped);
                }
                $ruleFrames[] = \true;
                // Once we hit a rule that has at least one declaration,
                // then we can stop handling the nested selectors. This
                // ensures that browser devtools can at least show
                // _something_ for a given rule, and we can leverage CSS
                // nesting which is better for gzip due to repetition.
                foreach ($node['nodes'] ?? [] as $child) {
                    if ($child['kind'] === 'declaration') {
                        // Emitting each child instead of the node itself
                        // because we do want to flatten the current node
                        // and its selector that is already pushed to the
                        // stack.
                        foreach ($node['nodes'] as $emitChild) {
                            $emit($emitChild);
                        }
                        return \BlockstudioVendor\TailwindPHP\Walk\WalkAction::Skip;
                    }
                }
                break;
            case 'at-rule':
                $nodesPath = null;
                // Start a new level
                // `@layer` is hoistable, but when it's empty then we have
                // to make sure that we still emit it because this might
                // influence the layer order. We can't just get rid of it.
                if (count($node['nodes'] ?? []) === 0 && !isset(DROPPABLE_IF_EMPTY_AT_RULES[$node['name']])) {
                    $emit($node);
                    $atRuleFrames[] = \false;
                    return \BlockstudioVendor\TailwindPHP\Walk\WalkAction::Skip;
                }
                // Hoist at-rules
                if (isset(HOISTABLE_AT_RULES[$node['name']])) {
                    $atRuleStack[] = [$node['name'], $node['params'] ?? ''];
                    $atRuleFrames[] = \true;
                    break;
                }
                // If we can't hoist them, emit them immediately as-is
                $emit($node);
                $atRuleFrames[] = \false;
                return \BlockstudioVendor\TailwindPHP\Walk\WalkAction::Skip;
            case 'declaration':
            case 'comment':
                $emit($node);
                break;
            case 'context':
            case 'at-root':
                break;
        }
        return null;
    }, 'exit' => function (array &$node) use (&$selectorStack, &$atRuleStack, &$nodesPath, &$ruleFrames, &$atRuleFrames) {
        switch ($node['kind']) {
            case 'rule':
                // Upstream `skipExit` equivalent: this rule never pushed
                // its selector, so leave the emission target untouched.
                if (!array_pop($ruleFrames)) {
                    return null;
                }
                $nodesPath = null;
                array_pop($selectorStack);
                break;
            case 'at-rule':
                if (!array_pop($atRuleFrames)) {
                    return null;
                }
                $nodesPath = null;
                array_pop($atRuleStack);
                break;
        }
        return null;
    }]);
    // Dedupe declarations that we've already seen before if they match the
    // `property`, `value` and `important` information. Deeper paths first so
    // splices cannot shift the indices of shallower stored paths.
    $paths = array_values($dedupeDeclarationPaths);
    usort($paths, fn(array $a, array $b) => count($b) <=> count($a));
    foreach ($paths as $path) {
        $target =& $resolveTarget($path);
        $seen = [];
        for ($i = count($target) - 1; $i >= 0; $i--) {
            $declaration = $target[$i];
            if ($declaration['kind'] !== 'declaration') {
                continue;
            }
            $id = $declaration['property'] . "\x00" . ($declaration['value'] ?? "\x01") . "\x00" . ($declaration['important'] ?? \false ? '1' : '0');
            if (isset($seen[$id])) {
                array_splice($target, $i, 1);
            } else {
                $seen[$id] = \true;
            }
        }
        unset($target);
    }
    return $result;
}
