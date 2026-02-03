<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Math operator utilities for CSS calc() expressions.
 *
 * Port of: packages/tailwindcss/src/utils/math-operators.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 */
const LOWER_A_CHAR = 0x61;
const LOWER_Z_CHAR = 0x7a;
const UPPER_A_CHAR = 0x41;
const UPPER_Z_CHAR = 0x5a;
const LOWER_E_CHAR = 0x65;
const UPPER_E_CHAR = 0x45;
const ZERO_MATH = 0x30;
const NINE_MATH = 0x39;
const ADD_CHAR = 0x2b;
const SUB_CHAR = 0x2d;
const MUL_CHAR = 0x2a;
const DIV_CHAR = 0x2f;
const OPEN_PAREN_CHAR = 0x28;
const CLOSE_PAREN_CHAR = 0x29;
const COMMA_CHAR = 0x2c;
const SPACE_CHAR = 0x20;
const PERCENT_CHAR = 0x25;
const MATH_FUNCTIONS = ['calc', 'min', 'max', 'clamp', 'mod', 'rem', 'sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'atan2', 'pow', 'sqrt', 'hypot', 'log', 'exp', 'round'];
/**
 * Check if a string contains a math function.
 *
 * @param string $input
 * @return bool
 */
function hasMathFn(string $input): bool
{
    if (strpos($input, '(') === \false) {
        return \false;
    }
    foreach (MATH_FUNCTIONS as $fn) {
        if (strpos($input, "{$fn}(") !== \false) {
            return \true;
        }
    }
    return \false;
}
/**
 * Add whitespace around math operators.
 *
 * @param string $input
 * @return string
 */
function addWhitespaceAroundMathOperators(string $input): string
{
    // Bail early if there are no math functions in the input
    $hasMath = \false;
    foreach (MATH_FUNCTIONS as $fn) {
        if (strpos($input, $fn) !== \false) {
            $hasMath = \true;
            break;
        }
    }
    if (!$hasMath) {
        return $input;
    }
    $result = '';
    $formattable = [];
    $valuePos = null;
    $lastValuePos = null;
    $len = strlen($input);
    for ($i = 0; $i < $len; $i++) {
        $char = ord($input[$i]);
        // Track if we see a number followed by a unit
        if ($char >= ZERO_MATH && $char <= NINE_MATH) {
            $valuePos = $i;
        } elseif ($valuePos !== null && ($char === PERCENT_CHAR || $char >= LOWER_A_CHAR && $char <= LOWER_Z_CHAR || $char >= UPPER_A_CHAR && $char <= UPPER_Z_CHAR)) {
            $valuePos = $i;
        } else {
            $lastValuePos = $valuePos;
            $valuePos = null;
        }
        // Determine if we're inside a math function
        if ($char === OPEN_PAREN_CHAR) {
            $result .= $input[$i];
            // Scan backwards to determine the function name
            $start = $i;
            for ($j = $i - 1; $j >= 0; $j--) {
                $inner = ord($input[$j]);
                if ($inner >= ZERO_MATH && $inner <= NINE_MATH || $inner >= LOWER_A_CHAR && $inner <= LOWER_Z_CHAR) {
                    $start = $j;
                } else {
                    break;
                }
            }
            $fn = substr($input, $start, $i - $start);
            if (in_array($fn, MATH_FUNCTIONS, \true)) {
                array_unshift($formattable, \true);
                continue;
            } elseif (!empty($formattable) && $formattable[0] && $fn === '') {
                array_unshift($formattable, \true);
                continue;
            }
            array_unshift($formattable, \false);
            continue;
        } elseif ($char === CLOSE_PAREN_CHAR) {
            $result .= $input[$i];
            array_shift($formattable);
        } elseif ($char === COMMA_CHAR && !empty($formattable) && $formattable[0]) {
            $result .= ', ';
            continue;
        } elseif ($char === SPACE_CHAR && !empty($formattable) && $formattable[0] && strlen($result) > 0 && ord($result[strlen($result) - 1]) === SPACE_CHAR) {
            continue;
        } elseif (($char === ADD_CHAR || $char === MUL_CHAR || $char === DIV_CHAR || $char === SUB_CHAR) && !empty($formattable) && $formattable[0]) {
            $trimmed = rtrim($result);
            $trimmedLen = strlen($trimmed);
            $prev = $trimmedLen > 0 ? ord($trimmed[$trimmedLen - 1]) : 0;
            $prevPrev = $trimmedLen > 1 ? ord($trimmed[$trimmedLen - 2]) : 0;
            $next = $i + 1 < $len ? ord($input[$i + 1]) : 0;
            // Do not add spaces for scientific notation
            if (($prev === LOWER_E_CHAR || $prev === UPPER_E_CHAR) && $prevPrev >= ZERO_MATH && $prevPrev <= NINE_MATH) {
                $result .= $input[$i];
                continue;
            } elseif ($prev === ADD_CHAR || $prev === MUL_CHAR || $prev === DIV_CHAR || $prev === SUB_CHAR) {
                $result .= $input[$i];
                continue;
            } elseif ($prev === OPEN_PAREN_CHAR || $prev === COMMA_CHAR) {
                $result .= $input[$i];
                continue;
            } elseif ($i > 0 && ord($input[$i - 1]) === SPACE_CHAR) {
                $result .= $input[$i] . ' ';
            } elseif ($prev >= ZERO_MATH && $prev <= NINE_MATH || $next >= ZERO_MATH && $next <= NINE_MATH || $prev === CLOSE_PAREN_CHAR || $next === OPEN_PAREN_CHAR || $next === ADD_CHAR || $next === MUL_CHAR || $next === DIV_CHAR || $next === SUB_CHAR || $lastValuePos !== null && $lastValuePos === $i - 1) {
                $result .= ' ' . $input[$i] . ' ';
            } else {
                $result .= $input[$i];
            }
        } else {
            $result .= $input[$i];
        }
    }
    return $result;
}
