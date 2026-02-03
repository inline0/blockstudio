<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\AttributeSelectorParser;

/**
 * Attribute Selector Parser
 *
 * Port of: packages/tailwindcss/src/attribute-selector-parser.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 * PHP adds an explicit minimum length check (strlen < 3) for safety.
 */
const TAB = 9;
const LINE_BREAK = 10;
const CARRIAGE_RETURN = 13;
const SPACE = 32;
const DOUBLE_QUOTE = 34;
const DOLLAR = 36;
const SINGLE_QUOTE = 39;
const ASTERISK = 42;
const EQUALS = 61;
const UPPER_I = 73;
const UPPER_S = 83;
const BACKSLASH = 92;
const CARET = 94;
const LOWER_I = 105;
const LOWER_S = 115;
const PIPE = 124;
const TILDE = 126;
const LOWER_A = 97;
const LOWER_Z = 122;
const UPPER_A = 65;
const UPPER_Z = 90;
const ZERO = 48;
const NINE = 57;
const DASH = 45;
const UNDERSCORE = 95;
/**
 * Parse an attribute selector string.
 *
 * @param string $input The attribute selector string (e.g., "[data-foo=value]")
 * @return array|null Parsed attribute selector or null if invalid
 */
function parse(string $input): ?array
{
    // Empty string or too short
    if (strlen($input) < 3) {
        return null;
    }
    // Must start with `[` and end with `]`
    if ($input[0] !== '[' || $input[strlen($input) - 1] !== ']') {
        return null;
    }
    $i = 1;
    $end = strlen($input) - 1;
    // Skip whitespace, e.g.: [   data-foo]
    while ($i < $end && isAsciiWhitespace(ord($input[$i]))) {
        $i++;
    }
    // Attribute name, e.g.: [data-foo]
    $start = $i;
    for (; $i < $end; $i++) {
        $currentChar = ord($input[$i]);
        // Skip escaped character
        if ($currentChar === BACKSLASH) {
            $i++;
            continue;
        }
        if ($currentChar >= UPPER_A && $currentChar <= UPPER_Z) {
            continue;
        }
        if ($currentChar >= LOWER_A && $currentChar <= LOWER_Z) {
            continue;
        }
        if ($currentChar >= ZERO && $currentChar <= NINE) {
            continue;
        }
        if ($currentChar === DASH || $currentChar === UNDERSCORE) {
            continue;
        }
        break;
    }
    // Must have at least one character in the attribute name
    if ($start === $i) {
        return null;
    }
    $attribute = substr($input, $start, $i - $start);
    // Skip whitespace, e.g.: [data-foo   =value]
    while ($i < $end && isAsciiWhitespace(ord($input[$i]))) {
        $i++;
    }
    // At the end, e.g.: `[data-foo]`
    if ($i === $end) {
        return ['attribute' => $attribute, 'operator' => null, 'quote' => null, 'value' => null, 'sensitivity' => null];
    }
    // Operator, e.g.: [data-foo*=value]
    $operator = null;
    $currentChar = ord($input[$i]);
    if ($currentChar === EQUALS) {
        $operator = '=';
        $i++;
    } elseif (($currentChar === TILDE || $currentChar === PIPE || $currentChar === CARET || $currentChar === DOLLAR || $currentChar === ASTERISK) && isset($input[$i + 1]) && ord($input[$i + 1]) === EQUALS) {
        $operator = $input[$i] . '=';
        $i += 2;
    } else {
        return null;
        // Invalid operator
    }
    // Skip whitespace, e.g.: [data-foo*=   value]
    while ($i < $end && isAsciiWhitespace(ord($input[$i]))) {
        $i++;
    }
    // At the end, that means we have an operator but no value, which is invalid
    if ($i === $end) {
        return null;
    }
    // Value, e.g.: [data-foo*=value]
    $value = '';
    // Quoted value, e.g.: [data-foo*="value"]
    $quote = null;
    $currentChar = ord($input[$i]);
    if ($currentChar === SINGLE_QUOTE || $currentChar === DOUBLE_QUOTE) {
        $quote = $input[$i];
        $i++;
        $start = $i;
        for ($j = $i; $j < $end; $j++) {
            $current = ord($input[$j]);
            // Found ending quote
            if ($current === $currentChar) {
                $i = $j + 1;
                break;
            }
            // Skip escaped character
            if ($current === BACKSLASH) {
                $j++;
            }
        }
        $value = substr($input, $start, $i - 1 - $start);
    } else {
        $start = $i;
        // Keep going until we find whitespace or the end
        while ($i < $end && !isAsciiWhitespace(ord($input[$i]))) {
            $i++;
        }
        $value = substr($input, $start, $i - $start);
    }
    // Skip whitespace, e.g.: [data-foo*=value   ]
    while ($i < $end && isAsciiWhitespace(ord($input[$i]))) {
        $i++;
    }
    // At the end, e.g.: `[data-foo=value]`
    if ($i === $end) {
        return ['attribute' => $attribute, 'operator' => $operator, 'quote' => $quote, 'value' => $value, 'sensitivity' => null];
    }
    // Sensitivity, e.g.: [data-foo=value i]
    $sensitivity = null;
    $charCode = ord($input[$i]);
    switch ($charCode) {
        case LOWER_I:
        case UPPER_I:
            $sensitivity = 'i';
            $i++;
            break;
        case LOWER_S:
        case UPPER_S:
            $sensitivity = 's';
            $i++;
            break;
        default:
            return null;
    }
    // Skip whitespace, e.g.: [data-foo=value i   ]
    while ($i < $end && isAsciiWhitespace(ord($input[$i]))) {
        $i++;
    }
    // We must be at the end now
    if ($i !== $end) {
        return null;
    }
    // Fully done
    return ['attribute' => $attribute, 'operator' => $operator, 'quote' => $quote, 'value' => $value, 'sensitivity' => $sensitivity];
}
/**
 * Check if a character code is ASCII whitespace.
 *
 * @param int $code Character code
 * @return bool
 */
function isAsciiWhitespace(int $code): bool
{
    return match ($code) {
        SPACE, TAB, LINE_BREAK, CARRIAGE_RETURN => \true,
        default => \false,
    };
}
