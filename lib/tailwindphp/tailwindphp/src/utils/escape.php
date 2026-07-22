<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Escape a CSS identifier.
 * https://drafts.csswg.org/cssom/#serialize-an-identifier
 *
 * Port of: packages/tailwindcss/src/utils/escape.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 *
 * @param string $value
 * @return string
 */
function escape(string $value): string
{
    $length = strlen($value);
    if ($length === 0) {
        return $value;
    }
    $result = '';
    $firstCodeUnit = ord($value[0]);
    // If the character is the first character and is a `-` (U+002D), and
    // there is no second character, […]
    if ($length === 1 && $firstCodeUnit === 0x2d) {
        return '\\' . $value;
    }
    for ($index = 0; $index < $length; $index++) {
        $codeUnit = ord($value[$index]);
        // If the character is NULL (U+0000), then the REPLACEMENT CHARACTER (U+FFFD).
        if ($codeUnit === 0x0) {
            $result .= "�";
            continue;
        }
        if ($codeUnit >= 0x1 && $codeUnit <= 0x1f || $codeUnit === 0x7f || $index === 0 && $codeUnit >= 0x30 && $codeUnit <= 0x39 || $index === 1 && $codeUnit >= 0x30 && $codeUnit <= 0x39 && $firstCodeUnit === 0x2d) {
            // https://drafts.csswg.org/cssom/#escape-a-character-as-code-point
            $result .= '\\' . dechex($codeUnit) . ' ';
            continue;
        }
        // If the character is not handled by one of the above rules and is
        // greater than or equal to U+0080, is `-` (U+002D) or `_` (U+005F), or
        // is in one of the ranges [0-9] (U+0030 to U+0039), [A-Z] (U+0041 to
        // U+005A), or [a-z] (U+0061 to U+007A), […]
        if ($codeUnit >= 0x80 || $codeUnit === 0x2d || $codeUnit === 0x5f || $codeUnit >= 0x30 && $codeUnit <= 0x39 || $codeUnit >= 0x41 && $codeUnit <= 0x5a || $codeUnit >= 0x61 && $codeUnit <= 0x7a) {
            // the character itself
            $result .= $value[$index];
            continue;
        }
        // Otherwise, the escaped character.
        // https://drafts.csswg.org/cssom/#escape-a-character
        $result .= '\\' . $value[$index];
    }
    return $result;
}
/**
 * Unescape a CSS identifier.
 *
 * @param string $escaped
 * @return string
 */
function unescape(string $escaped): string
{
    return preg_replace_callback('/\\\\([\dA-Fa-f]{1,6}[\t\n\f\r ]?|[\S\s])/', function ($match) {
        if (strlen($match[0]) > 2) {
            // It's a hex escape sequence
            $hex = trim($match[1]);
            return mb_chr((int) hexdec($hex), 'UTF-8');
        }
        return $match[1];
    }, $escaped);
}
