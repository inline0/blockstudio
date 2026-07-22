<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

const ZERO_CHAR = 48;
const NINE_CHAR = 57;
/**
 * Compare two strings alphanumerically, where numbers are compared as numbers
 * instead of strings.
 *
 * Port of: packages/tailwindcss/src/utils/compare.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 *
 * @param string $a
 * @param string $z
 * @return int
 */
function compare(string $a, string $z): int
{
    $aLen = strlen($a);
    $zLen = strlen($z);
    $minLen = $aLen < $zLen ? $aLen : $zLen;
    for ($i = 0; $i < $minLen; $i++) {
        $aCode = ord($a[$i]);
        $zCode = ord($z[$i]);
        // If both are numbers, compare them as numbers instead of strings.
        if ($aCode >= ZERO_CHAR && $aCode <= NINE_CHAR && $zCode >= ZERO_CHAR && $zCode <= NINE_CHAR) {
            $aStart = $i;
            $aEnd = $i + 1;
            $zStart = $i;
            $zEnd = $i + 1;
            // Consume the number
            while ($aEnd < $aLen) {
                $code = ord($a[$aEnd]);
                if ($code >= ZERO_CHAR && $code <= NINE_CHAR) {
                    $aEnd++;
                } else {
                    break;
                }
            }
            // Consume the number
            while ($zEnd < $zLen) {
                $code = ord($z[$zEnd]);
                if ($code >= ZERO_CHAR && $code <= NINE_CHAR) {
                    $zEnd++;
                } else {
                    break;
                }
            }
            $aNumber = substr($a, $aStart, $aEnd - $aStart);
            $zNumber = substr($z, $zStart, $zEnd - $zStart);
            $diff = (int) $aNumber - (int) $zNumber;
            if ($diff !== 0) {
                return $diff;
            }
            // Fallback case if numbers are the same but the string representation
            // is not. Fallback to string sorting. E.g.: `0123` vs `123`
            if ($aNumber < $zNumber) {
                return -1;
            }
            if ($aNumber > $zNumber) {
                return 1;
            }
            // Adjust index to continue after the numbers
            $i = min($aEnd, $zEnd) - 1;
            continue;
        }
        // Continue if the characters are the same
        if ($aCode === $zCode) {
            continue;
        }
        // Otherwise, compare them as strings
        return $aCode - $zCode;
    }
    // If we got this far, the strings are equal up to the length of the shortest
    // string. The shortest string should come first.
    return $aLen - $zLen;
}
