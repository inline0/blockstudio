<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\SelectorParser;

/**
 * Selector Parser - Parses CSS selectors into an AST.
 *
 * Port of: packages/tailwindcss/src/selector-parser.ts (v4.3.3)
 *
 * The AST distinguishes selector lists, complex selectors (containing
 * combinators), compound selectors, simple selectors, functional pseudos
 * (:is/:not/:where/:has parse their arguments; every other function keeps an
 * opaque value node), and raw value nodes.
 *
 * @port-deviation:references The reference mutates a shared `target` array
 * that aliases either the root AST, a list's nodes, or a function's nodes.
 * PHP arrays are value types, so the port keeps an explicit context stack of
 * pending segments and finalizes function/list nodes when their scope closes;
 * the produced AST is identical.
 */
const SP_AMPERSAND = 0x26;
const SP_ASTERISK = 0x2a;
const SP_BACKSLASH = 0x5c;
const SP_CLOSE_BRACKET = 0x5d;
const SP_CLOSE_PAREN = 0x29;
const SP_COLON = 0x3a;
const SP_COMMA = 0x2c;
const SP_DOT = 0x2e;
const SP_DOUBLE_QUOTE = 0x22;
const SP_GREATER_THAN = 0x3e;
const SP_HASH = 0x23;
const SP_NEWLINE = 0xa;
const SP_OPEN_BRACKET = 0x5b;
const SP_OPEN_PAREN = 0x28;
const SP_PLUS = 0x2b;
const SP_SINGLE_QUOTE = 0x27;
const SP_SPACE = 0x20;
const SP_TAB = 0x9;
const SP_TILDE = 0x7e;
/**
 * @param string $value One of ' ', '>', '+', '~'
 */
function combinator(string $value): array
{
    return ['kind' => 'combinator', 'value' => $value];
}
function complex(array $nodes): array
{
    return ['kind' => 'complex', 'nodes' => $nodes];
}
function compound(array $nodes): array
{
    return ['kind' => 'compound', 'nodes' => $nodes];
}
function fun(string $value, array $nodes): array
{
    return ['kind' => 'function', 'value' => $value, 'nodes' => $nodes];
}
function selectorList(array $nodes): array
{
    return ['kind' => 'list', 'nodes' => $nodes];
}
function selector(string $value): array
{
    return ['kind' => 'selector', 'value' => $value];
}
function value(string $value): array
{
    return ['kind' => 'value', 'value' => $value];
}
function isUniversalSelector(array $node): bool
{
    return $node['kind'] === 'selector' && ($node['value'][0] ?? '') === '*';
}
function isNestingSelector(array $node): bool
{
    return $node['kind'] === 'selector' && ($node['value'][0] ?? '') === '&';
}
function isClassSelector(array $node): bool
{
    return $node['kind'] === 'selector' && ($node['value'][0] ?? '') === '.';
}
function isIdSelector(array $node): bool
{
    return $node['kind'] === 'selector' && ($node['value'][0] ?? '') === '#';
}
function isPseudoSelector(array $node): bool
{
    return $node['kind'] === 'selector' && ($node['value'][0] ?? '') === ':';
}
function isAttributeSelector(array $node): bool
{
    return $node['kind'] === 'selector' && ($node['value'][0] ?? '') === '[';
}
function isTypeSelector(array $node): bool
{
    if ($node['kind'] !== 'selector') {
        return \false;
    }
    switch ($node['value'][0] ?? '') {
        case '*':
        // Universal selector
        case '&':
        // Nesting selector
        case '.':
        // Class selector
        case '#':
        // ID selector
        case ':':
        // Pseudo selector
        case '[':
            // Attribute selector
            return \false;
        // We don't fully verify whether this is actually a proper type
        // selector, but we assume it is one if it's not any of the others.
        default:
            return \true;
    }
}
function cloneAstNode(array $node): array
{
    if (isset($node['nodes'])) {
        $node['nodes'] = array_map(__FUNCTION__, $node['nodes']);
    }
    return $node;
}
function toCss(array $ast, bool $minify = \false): string
{
    $css = '';
    foreach ($ast as $node) {
        switch ($node['kind']) {
            case 'selector':
            case 'value':
                $css .= $node['value'];
                break;
            case 'combinator':
                if ($minify || $node['value'] === ' ') {
                    $css .= $node['value'];
                } else {
                    $css .= " {$node['value']} ";
                }
                break;
            case 'function':
                $css .= $node['value'] . '(' . toCss($node['nodes'], $minify) . ')';
                break;
            case 'complex':
            case 'compound':
                $css .= toCss($node['nodes'], $minify);
                break;
            case 'list':
                $css .= implode($minify ? ',' : ', ', array_map(fn($child) => toCss([$child], $minify), $node['nodes']));
                break;
        }
    }
    return $css;
}
/**
 * Parse a selector into an AST.
 *
 * @param string $input
 * @return array
 */
function parse(string $input): array
{
    $input = str_replace("\r\n", "\n", $input);
    $len = strlen($input);
    // Per-scope parse state. The root scope produces the returned AST; each
    // parseable functional pseudo (:is/:not/:where/:has) opens a new scope
    // whose finalized nodes become the function's arguments.
    $pending = [];
    $listItems = null;
    $containsCombinator = \false;
    $buffer = '';
    // Stack of paused scopes: [pending, listItems, containsCombinator, funValue]
    $contextStack = [];
    $current = function (array $nodes) use (&$containsCombinator): array {
        if (count($nodes) === 1) {
            return $nodes[0];
        }
        return $containsCombinator ? complex($nodes) : compound($nodes);
    };
    $append = function (array $node) use (&$pending): void {
        $lastIndex = count($pending) - 1;
        $existing = $lastIndex >= 0 ? $pending[$lastIndex] : null;
        if ($existing !== null && $existing['kind'] === 'compound') {
            $pending[$lastIndex]['nodes'][] = $node;
        } elseif ($existing !== null && $existing['kind'] !== 'list' && $existing['kind'] !== 'combinator') {
            $pending[$lastIndex] = compound([$existing, $node]);
        } else {
            $pending[] = $node;
        }
    };
    // Finalize the current scope's collected nodes into its node list
    $finalize = function () use (&$pending, &$listItems, &$containsCombinator, $current): array {
        if ($listItems !== null) {
            $listItems[] = $current($pending);
            return [selectorList($listItems)];
        }
        if ($containsCombinator) {
            return [complex($pending)];
        }
        return $pending;
    };
    for ($i = 0; $i < $len; $i++) {
        $currentChar = ord($input[$i]);
        switch ($currentChar) {
            // Handle selector lists
            //
            // ```css
            // .foo, .bar {}
            //     ^
            // ```
            case SP_COMMA:
                // Flush remaining buffer as a selector
                if ($buffer !== '') {
                    $append(selector($buffer));
                    $buffer = '';
                }
                // Skip whitespace
                for (; $i + 1 < $len; $i++) {
                    $peekChar = ord($input[$i + 1]);
                    if ($peekChar !== SP_NEWLINE && $peekChar !== SP_SPACE && $peekChar !== SP_TAB) {
                        break;
                    }
                }
                // Add the segment to the current list (started on demand)
                $listItems ??= [];
                $listItems[] = $current($pending);
                $pending = [];
                $containsCombinator = \false;
                break;
            // Handle combinators
            //
            // E.g.:
            //
            // ```css
            // .foo .bar
            //     ^
            //
            // .foo > .bar
            //     ^^^
            // ```
            case SP_GREATER_THAN:
            case SP_NEWLINE:
            case SP_SPACE:
            case SP_PLUS:
            case SP_TAB:
            case SP_TILDE:
                // Flush remaining buffer as a selector
                if ($buffer !== '') {
                    $append(selector($buffer));
                    $buffer = '';
                }
                // Look ahead and find the end of the combinator
                $start = $i;
                $end = $i + 1;
                for (; $end < $len; $end++) {
                    $peekChar = ord($input[$end]);
                    if ($peekChar !== SP_GREATER_THAN && $peekChar !== SP_NEWLINE && $peekChar !== SP_SPACE && $peekChar !== SP_PLUS && $peekChar !== SP_TAB && $peekChar !== SP_TILDE) {
                        break;
                    }
                }
                $i = $end - 1;
                $combinatorValue = trim(substr($input, $start, $end - $start));
                if ($combinatorValue === '' && (count($pending) === 0 || $end >= $len || ord($input[$end]) === SP_COMMA)) {
                    break;
                }
                $pending[] = combinator($combinatorValue === '' ? ' ' : $combinatorValue);
                $containsCombinator = \true;
                break;
            // Start of a function call
            //
            // E.g.:
            //
            // ```css
            // .foo:not(.bar)
            //         ^
            // ```
            case SP_OPEN_PAREN:
                $funValue = $buffer;
                $buffer = '';
                // If the function is not one of the following, we combine all
                // its contents into a single value node
                if ($funValue !== ':not' && $funValue !== ':where' && $funValue !== ':has' && $funValue !== ':is') {
                    // Find the end of the function call
                    $start = $i + 1;
                    $nesting = 0;
                    // Find the closing bracket
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
                    $contents = substr($input, $start, $end - $start);
                    // `:nth-child(…)` and `:nth-last-child(…)` can contain an
                    // `of <complex-selector-list>` clause. The selector list
                    // must be parsed (e.g. to be able to substitute `&`), but
                    // the `An+B` part is not a selector so it stays an opaque
                    // value node. E.g.:
                    //
                    // ```css
                    // :nth-child(2n + 1 of .foo, .bar)
                    //            ^^^^^^^^^^ value
                    //                       ^^^^^^^^^^ selector list
                    // ```
                    if ($funValue === ':nth-child' || $funValue === ':nth-last-child') {
                        $idx = strpos($contents, 'of ');
                        if ($idx !== \false) {
                            $node = fun($funValue, array_merge(
                                [value(substr($contents, 0, $idx + 3))],
                                // value `2n + 1 of `
                                parse(substr($contents, $idx + 3))
                            ));
                            $append($node);
                            break;
                        }
                    }
                    $append(fun($funValue, [value($contents)]));
                    break;
                }
                // Pause this scope and start collecting the function arguments
                $contextStack[] = [$pending, $listItems, $containsCombinator, $funValue];
                $pending = [];
                $listItems = null;
                $containsCombinator = \false;
                break;
            // End of a function call
            //
            // E.g.:
            //
            // ```css
            // foo(bar, baz)
            //             ^
            // ```
            case SP_CLOSE_PAREN:
                // Flush remaining buffer as a selector
                if ($buffer !== '') {
                    $append(selector($buffer));
                    $buffer = '';
                }
                $funNodes = $finalize();
                [$pending, $listItems, $containsCombinator, $funValue] = array_pop($contextStack);
                $append(fun($funValue, $funNodes));
                break;
            // Split compound selectors
            //
            // E.g.:
            //
            // ```css
            // .foo.bar
            //     ^
            // ```
            case SP_DOT:
            case SP_COLON:
            case SP_HASH:
                if ($currentChar === SP_COLON && $buffer === ':') {
                    $buffer .= $input[$i];
                    break;
                }
                // Handle everything before as a selector and start a new one
                if ($buffer !== '') {
                    $append(selector($buffer));
                }
                $buffer = $input[$i];
                break;
            // Start of an attribute selector
            //
            // NOTE: Right now we don't care about the individual parts of
            // the attribute selector, we just want to find the matching
            // closing bracket.
            case SP_OPEN_BRACKET:
                // Flush remaining buffer as a selector
                if ($buffer !== '') {
                    $append(selector($buffer));
                    $buffer = '';
                }
                $start = $i;
                $nesting = 0;
                // Find the closing bracket
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
                $append(selector(substr($input, $start, $i - $start + 1)));
                break;
            // Start of a string
            case SP_SINGLE_QUOTE:
            case SP_DOUBLE_QUOTE:
                $start = $i;
                // We need to ensure that the closing quote is the same as the
                // opening quote.
                //
                // E.g.:
                //
                // ```css
                // "This is a string with a 'quote' in it"
                //                          ^     ^         -> Not the end
                // ```
                for ($j = $i + 1; $j < $len; $j++) {
                    $peekChar = ord($input[$j]);
                    // Current character is a `\` so the next one is escaped
                    if ($peekChar === SP_BACKSLASH) {
                        $j += 1;
                    } elseif ($peekChar === $currentChar) {
                        $i = $j;
                        break;
                    }
                }
                // Adjust `buffer` to include the string
                $buffer .= substr($input, $start, $i - $start + 1);
                break;
            // Nesting `&` is always a new selector
            // Universal `*` is always a new selector
            case SP_AMPERSAND:
            case SP_ASTERISK:
                // Flush remaining buffer as a selector
                if ($buffer !== '') {
                    $append(selector($buffer));
                    $buffer = '';
                }
                // Handle the `&` or `*` as a selector on its own
                $append(selector($input[$i]));
                break;
            // Escaped characters
            case SP_BACKSLASH:
                $buffer .= $input[$i] . ($input[$i + 1] ?? '');
                $i += 1;
                break;
            // Everything else will be collected in the buffer
            default:
                $buffer .= $input[$i];
        }
    }
    // Collect the remainder as a selector
    if ($buffer !== '') {
        $append(selector($buffer));
    }
    // Unwind any unbalanced function scopes (missing closing parens)
    while (!empty($contextStack)) {
        $funNodes = $finalize();
        [$pending, $listItems, $containsCombinator, $funValue] = array_pop($contextStack);
        $append(fun($funValue, $funNodes));
    }
    return $finalize();
}
