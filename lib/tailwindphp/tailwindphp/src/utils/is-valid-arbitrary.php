<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Arbitrary value validation utilities.
 *
 * Port of: packages/tailwindcss/src/utils/is-valid-arbitrary.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 */
const BACKSLASH_ARB = 0x5c;
const OPEN_CURLY_ARB = 0x7b;
const CLOSE_CURLY_ARB = 0x7d;
const OPEN_PAREN_ARB = 0x28;
const CLOSE_PAREN_ARB = 0x29;
const OPEN_BRACKET_ARB = 0x5b;
const CLOSE_BRACKET_ARB = 0x5d;
const DOUBLE_QUOTE_ARB = 0x22;
const SINGLE_QUOTE_ARB = 0x27;
const SEMICOLON_ARB = 0x3b;
/**
 * Determine if a given string might be a valid arbitrary value.
 *
 * Unbalanced parens, brackets, and braces are not allowed. Additionally, a
 * top-level `;` is not allowed.
 *
 * @param string $input
 * @return bool
 */
function isValidArbitrary(string $input): bool
{
    $closingBracketStack = [];
    $stackPos = 0;
    $len = strlen($input);
    for ($idx = 0; $idx < $len; $idx++) {
        $char = ord($input[$idx]);
        switch ($char) {
            case BACKSLASH_ARB:
                // The next character is escaped, so we skip it.
                $idx += 1;
                break;
            case SINGLE_QUOTE_ARB:
            case DOUBLE_QUOTE_ARB:
                // Ensure we don't go out of bounds.
                while (++$idx < $len) {
                    $nextChar = ord($input[$idx]);
                    // The next character is escaped, so we skip it.
                    if ($nextChar === BACKSLASH_ARB) {
                        $idx += 1;
                        continue;
                    }
                    if ($nextChar === $char) {
                        break;
                    }
                }
                break;
            case OPEN_PAREN_ARB:
                $closingBracketStack[$stackPos] = CLOSE_PAREN_ARB;
                $stackPos++;
                break;
            case OPEN_BRACKET_ARB:
                $closingBracketStack[$stackPos] = CLOSE_BRACKET_ARB;
                $stackPos++;
                break;
            case OPEN_CURLY_ARB:
                // NOTE: We intentionally do not consider `{` to move the stack pointer
                // because a candidate like `[&{color:red}]:flex` should not be valid.
                break;
            case CLOSE_BRACKET_ARB:
            case CLOSE_CURLY_ARB:
            case CLOSE_PAREN_ARB:
                if ($stackPos === 0) {
                    return \false;
                }
                if ($stackPos > 0 && $char === $closingBracketStack[$stackPos - 1]) {
                    $stackPos--;
                }
                break;
            case SEMICOLON_ARB:
                if ($stackPos === 0) {
                    return \false;
                }
                break;
        }
    }
    return \true;
}
