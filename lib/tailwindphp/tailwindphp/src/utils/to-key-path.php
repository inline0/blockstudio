<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Parse a path string into an array of path segments
 *
 * Port of: packages/tailwindcss/src/utils/to-key-path.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 *
 * Square bracket notation `a[b]` may be used to "escape" dots that would
 * otherwise be interpreted as path separators.
 *
 * Example:
 * a -> ['a']
 * a.b.c -> ['a', 'b', 'c']
 * a[b].c -> ['a', 'b', 'c']
 * a[b.c].e.f -> ['a', 'b.c', 'e', 'f']
 * a[b][c][d] -> ['a', 'b', 'c', 'd']
 *
 * @param string $path
 * @return string[]
 */
function toKeyPath(string $path): array
{
    $keypath = [];
    foreach (segment($path, '.') as $part) {
        if (strpos($part, '[') === \false) {
            $keypath[] = $part;
            continue;
        }
        $currentIndex = 0;
        while (\true) {
            $bracketL = strpos($part, '[', $currentIndex);
            $bracketR = $bracketL !== \false ? strpos($part, ']', $bracketL) : \false;
            if ($bracketL === \false || $bracketR === \false) {
                break;
            }
            // Add the part before the bracket as a key
            if ($bracketL > $currentIndex) {
                $keypath[] = substr($part, $currentIndex, $bracketL - $currentIndex);
            }
            // Add the part inside the bracket as a key
            $keypath[] = substr($part, $bracketL + 1, $bracketR - $bracketL - 1);
            $currentIndex = $bracketR + 1;
        }
        // Add the part after the last bracket as a key
        if ($currentIndex <= strlen($part) - 1) {
            $keypath[] = substr($part, $currentIndex);
        }
    }
    return $keypath;
}
