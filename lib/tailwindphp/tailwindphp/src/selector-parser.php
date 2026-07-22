<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\SelectorParser;

/**
 * Selector Parser - Parses CSS selectors into an AST.
 *
 * Port of: packages/tailwindcss/src/selector-parser.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 */
const SP_AMPERSAND = 0x26;
const SP_ASTERISK = 0x2a;
const SP_BACKSLASH = 0x5c;
const SP_CLOSE_BRACKET = 0x5d;
const SP_CLOSE_PAREN = 0x29;
const SP_COLON = 0x3a;
const SP_COMMA = 0x2c;
const SP_DOUBLE_QUOTE = 0x22;
const SP_FULL_STOP = 0x2e;
const SP_GREATER_THAN = 0x3e;
const SP_NEWLINE = 0xa;
const SP_NUMBER_SIGN = 0x23;
const SP_OPEN_BRACKET = 0x5b;
const SP_OPEN_PAREN = 0x28;
const SP_PLUS = 0x2b;
const SP_SINGLE_QUOTE = 0x27;
const SP_SPACE = 0x20;
const SP_TAB = 0x9;
const SP_TILDE = 0x7e;
/**
 * Create a combinator node.
 *
 * @param string $value
 * @return array{kind: 'combinator', value: string}
 */
function combinator(string $value): array
{
    return ['kind' => 'combinator', 'value' => $value];
}
/**
 * Create a function node.
 *
 * @param string $value
 * @param array $nodes
 * @return array{kind: 'function', value: string, nodes: array}
 */
function fun(string $value, array $nodes): array
{
    return ['kind' => 'function', 'value' => $value, 'nodes' => $nodes];
}
/**
 * Create a selector node.
 *
 * @param string $value
 * @return array{kind: 'selector', value: string}
 */
function selector(string $value): array
{
    return ['kind' => 'selector', 'value' => $value];
}
/**
 * Create a separator node.
 *
 * @param string $value
 * @return array{kind: 'separator', value: string}
 */
function separator(string $value): array
{
    return ['kind' => 'separator', 'value' => $value];
}
/**
 * Create a value node.
 *
 * @param string $value
 * @return array{kind: 'value', value: string}
 */
function value(string $value): array
{
    return ['kind' => 'value', 'value' => $value];
}
/**
 * Convert a selector AST to CSS string.
 *
 * @param array $ast
 * @return string
 */
function toCss(array $ast): string
{
    $css = '';
    foreach ($ast as $node) {
        switch ($node['kind']) {
            case 'combinator':
            case 'selector':
            case 'separator':
            case 'value':
                $css .= $node['value'];
                break;
            case 'function':
                $css .= $node['value'] . '(' . toCss($node['nodes']) . ')';
                break;
        }
    }
    return $css;
}
/**
 * Parse a CSS selector into an AST.
 *
 * @param string $input
 * @return array
 */
function parse(string $input): array
{
    $input = str_replace("\r\n", "\n", $input);
    $ast = [];
    // Stack stores paths to current parent function nodes
    $stack = [];
    $buffer = '';
    $len = strlen($input);
    // Helper to add a node to the current parent
    $addNode = function ($node) use (&$ast, &$stack) {
        if (count($stack) === 0) {
            $ast[] = $node;
            return count($ast) - 1;
        } else {
            // Navigate to current parent and add to its nodes
            $parentPath = $stack[count($stack) - 1];
            $target =& $ast;
            foreach ($parentPath as $key) {
                $target =& $target[$key];
            }
            $target['nodes'][] = $node;
            return count($target['nodes']) - 1;
        }
    };
    for ($i = 0; $i < $len; $i++) {
        $currentChar = ord($input[$i]);
        switch ($currentChar) {
            // E.g.:
            //
            // ```css
            // .foo .bar
            //     ^
            //
            // .foo > .bar
            //     ^^^
            // ```
            case SP_COMMA:
            case SP_GREATER_THAN:
            case SP_NEWLINE:
            case SP_SPACE:
            case SP_PLUS:
            case SP_TAB:
            case SP_TILDE:
                // 1. Handle everything before the combinator as a selector
                if (strlen($buffer) > 0) {
                    $addNode(selector($buffer));
                    $buffer = '';
                }
                // 2. Look ahead and find the end of the combinator
                $start = $i;
                $end = $i + 1;
                for (; $end < $len; $end++) {
                    $peekChar = ord($input[$end]);
                    if ($peekChar !== SP_COMMA && $peekChar !== SP_GREATER_THAN && $peekChar !== SP_NEWLINE && $peekChar !== SP_SPACE && $peekChar !== SP_PLUS && $peekChar !== SP_TAB && $peekChar !== SP_TILDE) {
                        break;
                    }
                }
                $i = $end - 1;
                $contents = substr($input, $start, $end - $start);
                $node = trim($contents) === ',' ? separator($contents) : combinator($contents);
                $addNode($node);
                break;
            // Start of a function call.
            //
            // E.g.:
            //
            // ```css
            // .foo:not(.bar)
            //         ^
            // ```
            case SP_OPEN_PAREN:
                $node = fun($buffer, []);
                $buffer = '';
                // If the function is not one of the following, we combine all it's
                // contents into a single value node
                if ($node['value'] !== ':not' && $node['value'] !== ':where' && $node['value'] !== ':has' && $node['value'] !== ':is') {
                    // Find the end of the function call
                    $start = $i + 1;
                    $nesting = 0;
                    // Find the closing bracket.
                    for ($j = $i + 1; $j < $len; $j++) {
                        $peekChar = ord($input[$j]);
                        if ($peekChar === SP_OPEN_PAREN) {
                            $nesting++;
                            continue;
                        }
                        if ($peekChar === SP_CLOSE_PAREN) {
                            if ($nesting === 0) {
                                $i = $j;
                                break;
                            }
                            $nesting--;
                        }
                    }
                    $end = $i;
                    $node['nodes'][] = value(substr($input, $start, $end - $start));
                    $buffer = '';
                    $i = $end;
                    $addNode($node);
                    break;
                }
                if (count($stack) === 0) {
                    $ast[] = $node;
                    $nodeIndex = count($ast) - 1;
                    $stack[] = [$nodeIndex];
                } else {
                    // Navigate to current parent and add to its nodes
                    $parentPath = $stack[count($stack) - 1];
                    $target =& $ast;
                    foreach ($parentPath as $key) {
                        $target =& $target[$key];
                    }
                    $target['nodes'][] = $node;
                    $nodeIndex = count($target['nodes']) - 1;
                    // New path extends parent path
                    $newPath = $parentPath;
                    $newPath[] = 'nodes';
                    $newPath[] = $nodeIndex;
                    $stack[] = $newPath;
                }
                break;
            // End of a function call.
            //
            // E.g.:
            //
            // ```css
            // foo(bar, baz)
            //             ^
            // ```
            case SP_CLOSE_PAREN:
                // Handle everything before the closing paren as a selector
                if (strlen($buffer) > 0) {
                    $addNode(selector($buffer));
                    $buffer = '';
                }
                // Pop the stack to return to parent
                if (count($stack) > 0) {
                    array_pop($stack);
                }
                break;
            // Split compound selectors.
            //
            // E.g.:
            //
            // ```css
            // .foo.bar
            //     ^
            // ```
            case SP_FULL_STOP:
            case SP_COLON:
            case SP_NUMBER_SIGN:
                // Handle everything before the combinator as a selector and
                // start a new selector
                if (strlen($buffer) > 0) {
                    $addNode(selector($buffer));
                }
                $buffer = $input[$i];
                break;
            // Start of an attribute selector.
            //
            // NOTE: Right now we don't care about the individual parts of the
            // attribute selector, we just want to find the matching closing bracket.
            //
            // If we need more information from inside the attribute selector in the
            // future, then we can use the `AttributeSelectorParser` here (and even
            // inline it if needed)
            case SP_OPEN_BRACKET:
                // Handle everything before the combinator as a selector
                if (strlen($buffer) > 0) {
                    $addNode(selector($buffer));
                }
                $buffer = '';
                $start = $i;
                $nesting = 0;
                // Find the closing bracket.
                for ($j = $i + 1; $j < $len; $j++) {
                    $peekChar = ord($input[$j]);
                    if ($peekChar === SP_OPEN_BRACKET) {
                        $nesting++;
                        continue;
                    }
                    if ($peekChar === SP_CLOSE_BRACKET) {
                        if ($nesting === 0) {
                            $i = $j;
                            break;
                        }
                        $nesting--;
                    }
                }
                // Adjust `buffer` to include the string.
                $buffer .= substr($input, $start, $i - $start + 1);
                break;
            // Start of a string.
            case SP_SINGLE_QUOTE:
            case SP_DOUBLE_QUOTE:
                $start = $i;
                // We need to ensure that the closing quote is the same as the opening
                // quote.
                //
                // E.g.:
                //
                // ```css
                // "This is a string with a 'quote' in it"
                //                          ^     ^         -> These are not the end of the string.
                // ```
                for ($j = $i + 1; $j < $len; $j++) {
                    $peekChar = ord($input[$j]);
                    // Current character is a `\` therefore the next character is escaped.
                    if ($peekChar === SP_BACKSLASH) {
                        $j += 1;
                    } elseif ($peekChar === $currentChar) {
                        $i = $j;
                        break;
                    }
                }
                // Adjust `buffer` to include the string.
                $buffer .= substr($input, $start, $i - $start + 1);
                break;
            // Nesting `&` is always a new selector.
            // Universal `*` is always a new selector.
            case SP_AMPERSAND:
            case SP_ASTERISK:
                // 1. Handle everything before the combinator as a selector
                if (strlen($buffer) > 0) {
                    $addNode(selector($buffer));
                    $buffer = '';
                }
                // 2. Handle the `&` or `*` as a selector on its own
                $addNode(selector($input[$i]));
                break;
            // Escaped characters.
            case SP_BACKSLASH:
                $buffer .= $input[$i];
                if ($i + 1 < $len) {
                    $buffer .= $input[$i + 1];
                    $i += 1;
                }
                break;
            // Everything else will be collected in the buffer
            default:
                $buffer .= $input[$i];
        }
    }
    // Collect the remainder as a word
    if (strlen($buffer) > 0) {
        $ast[] = selector($buffer);
    }
    return $ast;
}
