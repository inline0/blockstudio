<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

const BACKSLASH = 0x5c;
const OPEN_CURLY = 0x7b;
const CLOSE_CURLY = 0x7d;
const OPEN_PAREN = 0x28;
const CLOSE_PAREN = 0x29;
const OPEN_BRACKET = 0x5b;
const CLOSE_BRACKET = 0x5d;
const DOUBLE_QUOTE = 0x22;
const SINGLE_QUOTE = 0x27;
/**
 * This splits a string on a top-level character.
 *
 * Port of: packages/tailwindcss/src/utils/segment.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 *
 * Regex doesn't support recursion (at least not the JS-flavored version),
 * so we have to use a tiny state machine to keep track of paren placement.
 *
 * Expected behavior using commas:
 * var(--a, 0 0 1px rgb(0, 0, 0)), 0 0 1px rgb(0, 0, 0)
 *        ┬              ┬  ┬    ┬
 *        x              x  x    ╰──────── Split because top-level
 *        ╰──────────────┴──┴───────────── Ignored b/c inside >= 1 levels of parens
 *
 * @param string $input
 * @param string $separator
 * @return string[]
 */
function segment(string $input, string $separator): array
{
    $closingBracketStack = [];
    $stackPos = 0;
    $parts = [];
    $lastPos = 0;
    $len = strlen($input);
    $separatorCode = ord($separator[0]);
    for ($idx = 0; $idx < $len; $idx++) {
        $char = ord($input[$idx]);
        if ($stackPos === 0 && $char === $separatorCode) {
            $parts[] = substr($input, $lastPos, $idx - $lastPos);
            $lastPos = $idx + 1;
            continue;
        }
        switch ($char) {
            case BACKSLASH:
                // The next character is escaped, so we skip it.
                $idx += 1;
                break;
            // Strings should be handled as-is until the end of the string. No need to
            // worry about balancing parens, brackets, or curlies inside a string.
            case SINGLE_QUOTE:
            case DOUBLE_QUOTE:
                // Ensure we don't go out of bounds.
                while (++$idx < $len) {
                    $nextChar = ord($input[$idx]);
                    // The next character is escaped, so we skip it.
                    if ($nextChar === BACKSLASH) {
                        $idx += 1;
                        continue;
                    }
                    if ($nextChar === $char) {
                        break;
                    }
                }
                break;
            case OPEN_PAREN:
                $closingBracketStack[$stackPos] = CLOSE_PAREN;
                $stackPos++;
                break;
            case OPEN_BRACKET:
                $closingBracketStack[$stackPos] = CLOSE_BRACKET;
                $stackPos++;
                break;
            case OPEN_CURLY:
                $closingBracketStack[$stackPos] = CLOSE_CURLY;
                $stackPos++;
                break;
            case CLOSE_BRACKET:
            case CLOSE_CURLY:
            case CLOSE_PAREN:
                if ($stackPos > 0 && $char === $closingBracketStack[$stackPos - 1]) {
                    $stackPos--;
                }
                break;
        }
    }
    $parts[] = substr($input, $lastPos);
    return $parts;
}
