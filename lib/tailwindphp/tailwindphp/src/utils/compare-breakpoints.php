<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Compare two breakpoint values.
 *
 * Port of: packages/tailwindcss/src/utils/compare-breakpoints.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 *
 * @param string $a
 * @param string $z
 * @param string $direction 'asc' or 'desc'
 * @return int
 */
function compareBreakpoints(string $a, string $z, string $direction): int
{
    if ($a === $z) {
        return 0;
    }
    // Assumption: when a `(` exists, we are dealing with a CSS function.
    // E.g.: `calc(100% - 1rem)`
    $aIsCssFunction = strpos($a, '(');
    $zIsCssFunction = strpos($z, '(');
    $aBucket = $aIsCssFunction === \false ? preg_replace('/[\d.]+/', '', $a) : substr($a, 0, $aIsCssFunction);
    $zBucket = $zIsCssFunction === \false ? preg_replace('/[\d.]+/', '', $z) : substr($z, 0, $zIsCssFunction);
    // Compare by bucket name
    if ($aBucket !== $zBucket) {
        $order = $aBucket < $zBucket ? -1 : 1;
    } else {
        // If bucket names are the same, compare by value
        $aInt = (int) $a;
        $zInt = (int) $z;
        $order = $direction === 'asc' ? $aInt - $zInt : $zInt - $aInt;
    }
    // If the groups are the same, and the contents are not numbers, the
    // `order` will result in `0`. In this case, we want to make sorting
    // stable by falling back to a string comparison.
    if ($order === 0 && $a !== $z) {
        return $a < $z ? -1 : 1;
    }
    return $order;
}
