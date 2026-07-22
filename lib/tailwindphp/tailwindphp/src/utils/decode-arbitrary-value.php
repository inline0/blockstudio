<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\DecodeArbitraryValue;

use function BlockstudioVendor\TailwindPHP\Utils\addWhitespaceAroundMathOperators;
use function BlockstudioVendor\TailwindPHP\ValueParser\parse;
use function BlockstudioVendor\TailwindPHP\ValueParser\toCss;
/**
 * Decode Arbitrary Value - Convert Tailwind arbitrary value syntax to CSS.
 *
 * Port of: packages/tailwindcss/src/utils/decode-arbitrary-value.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 */
/**
 * Decode an arbitrary value from Tailwind class syntax to CSS.
 *
 * @param string $input
 * @return string
 */
function decodeArbitraryValue(string $input): string
{
    // There are definitely no functions in the input, so bail early
    if (strpos($input, '(') === \false) {
        return convertUnderscoresToWhitespace($input);
    }
    $ast = parse($input);
    recursivelyDecodeArbitraryValues($ast);
    $input = toCss($ast);
    $input = addWhitespaceAroundMathOperators($input);
    return $input;
}
/**
 * Convert `_` to ` `, except for escaped underscores `\_` they should be
 * converted to `_` instead.
 *
 * @param string $input
 * @param bool $skipUnderscoreToSpace
 * @return string
 */
function convertUnderscoresToWhitespace(string $input, bool $skipUnderscoreToSpace = \false): string
{
    $output = '';
    $len = strlen($input);
    for ($i = 0; $i < $len; $i++) {
        $char = $input[$i];
        // Escaped underscore
        if ($char === '\\' && $i + 1 < $len && $input[$i + 1] === '_') {
            $output .= '_';
            $i += 1;
        } elseif ($char === '_' && !$skipUnderscoreToSpace) {
            $output .= ' ';
        } else {
            $output .= $char;
        }
    }
    return $output;
}
/**
 * Recursively decode arbitrary values in the AST.
 *
 * @param array &$ast
 * @return void
 */
function recursivelyDecodeArbitraryValues(array &$ast): void
{
    for ($i = 0; $i < count($ast); $i++) {
        $node =& $ast[$i];
        switch ($node['kind']) {
            case 'function':
                if ($node['value'] === 'url' || str_ends_with($node['value'], '_url')) {
                    // Don't decode underscores in url() but do decode the function name
                    $node['value'] = convertUnderscoresToWhitespace($node['value']);
                    break;
                }
                if ($node['value'] === 'var' || str_ends_with($node['value'], '_var') || $node['value'] === 'theme' || str_ends_with($node['value'], '_theme')) {
                    $node['value'] = convertUnderscoresToWhitespace($node['value']);
                    for ($j = 0; $j < count($node['nodes']); $j++) {
                        // Don't decode underscores to spaces in the first argument of var()
                        if ($j === 0 && $node['nodes'][$j]['kind'] === 'word') {
                            $node['nodes'][$j]['value'] = convertUnderscoresToWhitespace($node['nodes'][$j]['value'], \true);
                            continue;
                        }
                        $subAst = [$node['nodes'][$j]];
                        recursivelyDecodeArbitraryValues($subAst);
                        $node['nodes'][$j] = $subAst[0];
                    }
                    break;
                }
                $node['value'] = convertUnderscoresToWhitespace($node['value']);
                recursivelyDecodeArbitraryValues($node['nodes']);
                break;
            case 'separator':
            case 'word':
                $node['value'] = convertUnderscoresToWhitespace($node['value']);
                break;
        }
    }
}
