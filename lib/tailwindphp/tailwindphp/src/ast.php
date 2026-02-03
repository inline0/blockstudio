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
 * @param array<AstNode> $ast
 * @return string
 */
function toCss(array $ast): string
{
    $parts = [];
    stringifyNodes($ast, 0, $parts);
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
