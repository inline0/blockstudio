<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\ValueParser;

/**
 * Value Parser - Parses CSS values into an AST.
 *
 * Port of: packages/tailwindcss/src/value-parser.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 */
const VP_BACKSLASH = 0x5c;
const VP_CLOSE_PAREN = 0x29;
const VP_COLON = 0x3a;
const VP_COMMA = 0x2c;
const VP_DOUBLE_QUOTE = 0x22;
const VP_EQUALS = 0x3d;
const VP_GREATER_THAN = 0x3e;
const VP_LESS_THAN = 0x3c;
const VP_NEWLINE = 0xa;
const VP_OPEN_PAREN = 0x28;
const VP_SINGLE_QUOTE = 0x27;
const VP_SLASH = 0x2f;
const VP_SPACE = 0x20;
const VP_TAB = 0x9;
/**
 * Create a word node.
 *
 * @param string $value
 * @return array{kind: 'word', value: string}
 */
function word(string $value): array
{
    return ['kind' => 'word', 'value' => $value];
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
 * Convert a value AST to CSS string.
 *
 * @param array $ast
 * @return string
 */
function toCss(array $ast): string
{
    $css = '';
    foreach ($ast as $node) {
        switch ($node['kind']) {
            case 'word':
            case 'separator':
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
 * Parse a CSS value into an AST.
 *
 * @param string $input
 * @return array
 */
function parse(string $input): array
{
    $input = str_replace("\r\n", "\n", $input);
    $ast = [];
    // Stack stores path to current parent: each entry is [containerRef, index]
    // where containerRef is 'ast' or a path like [0, 'nodes', 1, 'nodes']
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
            // Current character is a `\` therefore the next character is escaped,
            // consume it together with the next character and continue.
            case VP_BACKSLASH:
                $buffer .= $input[$i];
                if ($i + 1 < $len) {
                    $buffer .= $input[$i + 1];
                    $i++;
                }
                break;
            // Typically for math operators, they have to have spaces around them. But
            // there are situations in `theme(colors.red.500/10)` where we use `/`
            // without spaces. Let's make sure this is a separate word as well.
            case VP_SLASH:
                // 1. Handle everything before the separator as a word
                if (strlen($buffer) > 0) {
                    $addNode(word($buffer));
                    $buffer = '';
                }
                // 2. Track the `/` as a word on its own
                $addNode(word($input[$i]));
                break;
            // Space and commas are bundled into separators
            case VP_COLON:
            case VP_COMMA:
            case VP_EQUALS:
            case VP_GREATER_THAN:
            case VP_LESS_THAN:
            case VP_NEWLINE:
            case VP_SPACE:
            case VP_TAB:
                // 1. Handle everything before the separator as a word
                if (strlen($buffer) > 0) {
                    $addNode(word($buffer));
                    $buffer = '';
                }
                // 2. Look ahead and find the end of the separator
                $start = $i;
                $end = $i + 1;
                for (; $end < $len; $end++) {
                    $peekChar = ord($input[$end]);
                    if ($peekChar !== VP_COLON && $peekChar !== VP_COMMA && $peekChar !== VP_EQUALS && $peekChar !== VP_GREATER_THAN && $peekChar !== VP_LESS_THAN && $peekChar !== VP_NEWLINE && $peekChar !== VP_SPACE && $peekChar !== VP_TAB) {
                        break;
                    }
                }
                $i = $end - 1;
                $addNode(separator(substr($input, $start, $end - $start)));
                break;
            // Start of a string.
            case VP_SINGLE_QUOTE:
            case VP_DOUBLE_QUOTE:
                $start = $i;
                // We need to ensure that the closing quote is the same as the opening quote.
                for ($j = $i + 1; $j < $len; $j++) {
                    $peekChar = ord($input[$j]);
                    // Current character is a `\` therefore the next character is escaped.
                    if ($peekChar === VP_BACKSLASH) {
                        $j += 1;
                    } elseif ($peekChar === $currentChar) {
                        $i = $j;
                        break;
                    }
                }
                // Adjust `buffer` to include the string.
                $buffer .= substr($input, $start, $i - $start + 1);
                break;
            // Start of a function call.
            case VP_OPEN_PAREN:
                $node = fun($buffer, []);
                $buffer = '';
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
            case VP_CLOSE_PAREN:
                // Handle everything before the closing paren as a word
                if (strlen($buffer) > 0) {
                    $addNode(word($buffer));
                    $buffer = '';
                }
                // Pop the stack to return to parent
                if (count($stack) > 0) {
                    array_pop($stack);
                }
                break;
            // Everything else will be collected in the buffer
            default:
                $buffer .= chr($currentChar);
        }
    }
    // Collect the remainder as a word
    if (strlen($buffer) > 0) {
        $addNode(word($buffer));
    }
    return $ast;
}
// Walk action kinds (matching TailwindPHP\walk.php)
const VP_WALK_CONTINUE = 0;
const VP_WALK_SKIP = 1;
const VP_WALK_STOP = 2;
const VP_WALK_REPLACE = 3;
const VP_WALK_REPLACE_SKIP = 4;
const VP_WALK_REPLACE_STOP = 5;
/**
 * WalkAction helper class for ValueParser.
 */
class WalkAction
{
    public const Continue = ['kind' => VP_WALK_CONTINUE];
    public const Skip = ['kind' => VP_WALK_SKIP];
    public const Stop = ['kind' => VP_WALK_STOP];
    public static function Replace(array $nodes): array
    {
        if (!empty($nodes) && !isset($nodes[0])) {
            $nodes = [$nodes];
        }
        return ['kind' => VP_WALK_REPLACE, 'nodes' => $nodes];
    }
    public static function ReplaceSkip(array $nodes): array
    {
        if (!empty($nodes) && !isset($nodes[0])) {
            $nodes = [$nodes];
        }
        return ['kind' => VP_WALK_REPLACE_SKIP, 'nodes' => $nodes];
    }
    public static function ReplaceStop(array $nodes): array
    {
        if (!empty($nodes) && !isset($nodes[0])) {
            $nodes = [$nodes];
        }
        return ['kind' => VP_WALK_REPLACE_STOP, 'nodes' => $nodes];
    }
}
/**
 * Walk through a value AST, visiting each node.
 *
 * @param array &$ast The AST to walk (modified in-place)
 * @param callable|array $hooks Either a function (enter callback) or array with 'enter' and/or 'exit' callbacks
 * @return void
 */
function walk(array &$ast, callable|array $hooks): void
{
    if (is_callable($hooks)) {
        walkImplementation($ast, $hooks);
    } else {
        walkImplementation($ast, $hooks['enter'] ?? null, $hooks['exit'] ?? null);
    }
}
/**
 * Internal walk implementation for ValueParser.
 */
function walkImplementation(array &$ast, ?callable $enter = null, ?callable $exit = null): void
{
    $stack = [[&$ast, 0, null]];
    while (count($stack) > 0) {
        $depth = count($stack) - 1;
        $frame =& $stack[$depth];
        $nodes =& $frame[0];
        $offset = $frame[1];
        $parent = $frame[2];
        // Done with this level
        if ($offset >= 0 && $offset >= count($nodes)) {
            array_pop($stack);
            continue;
        }
        if ($offset < 0 && ~$offset >= count($nodes)) {
            array_pop($stack);
            continue;
        }
        // Enter phase (offsets are non-negative)
        if ($offset >= 0) {
            $node =& $nodes[$offset];
            $result = $enter !== null ? $enter($node) : WalkAction::Continue;
            if ($result === null) {
                $result = WalkAction::Continue;
            }
            switch ($result['kind']) {
                case VP_WALK_CONTINUE:
                    if (isset($nodes[$offset]['nodes']) && count($nodes[$offset]['nodes']) > 0) {
                        $stack[] = [&$nodes[$offset]['nodes'], 0, $nodes[$offset]];
                    }
                    $frame[1] = ~$offset;
                    unset($node);
                    continue 2;
                case VP_WALK_STOP:
                    return;
                case VP_WALK_SKIP:
                    $frame[1] = ~$offset;
                    unset($node);
                    continue 2;
                case VP_WALK_REPLACE:
                    unset($node);
                    array_splice($nodes, $offset, 1, $result['nodes']);
                    continue 2;
                case VP_WALK_REPLACE_STOP:
                    unset($node);
                    array_splice($nodes, $offset, 1, $result['nodes']);
                    return;
                case VP_WALK_REPLACE_SKIP:
                    unset($node);
                    array_splice($nodes, $offset, 1, $result['nodes']);
                    $frame[1] = $offset + count($result['nodes']);
                    continue 2;
                default:
                    throw new \Exception("Invalid WalkAction kind in enter: {$result['kind']}");
            }
        }
        // Exit phase
        $index = ~$offset;
        $node =& $nodes[$index];
        $result = $exit !== null ? $exit($node) : WalkAction::Continue;
        if ($result === null) {
            $result = WalkAction::Continue;
        }
        switch ($result['kind']) {
            case VP_WALK_CONTINUE:
                $frame[1] = $index + 1;
                unset($node);
                continue 2;
            case VP_WALK_STOP:
                return;
            case VP_WALK_REPLACE:
                unset($node);
                array_splice($nodes, $index, 1, $result['nodes']);
                $frame[1] = $index + count($result['nodes']);
                continue 2;
            case VP_WALK_REPLACE_STOP:
                unset($node);
                array_splice($nodes, $index, 1, $result['nodes']);
                return;
            case VP_WALK_REPLACE_SKIP:
                unset($node);
                array_splice($nodes, $index, 1, $result['nodes']);
                $frame[1] = $index + count($result['nodes']);
                continue 2;
            default:
                throw new \Exception("Invalid WalkAction kind in exit: {$result['kind']}");
        }
    }
}
