<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/tw-join.ts
 *
 * The code in this file is copied from https://github.com/lukeed/clsx
 * and modified to suit the needs of tailwind-merge better.
 *
 * Original code has MIT license: Copyright (c) Luke Edwards <luke.edwards05@gmail.com> (lukeed.com)
 *
 * @port-deviation:types Uses PHP mixed types
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

/**
 * Join class names together, filtering out falsy values.
 *
 * @param mixed ...$classLists Class values to join
 * @return string Space-separated class string
 */
function twJoin(mixed ...$classLists): string
{
    $result = '';
    foreach ($classLists as $argument) {
        if (!$argument) {
            continue;
        }
        $resolvedValue = toValue($argument);
        if ($resolvedValue !== '') {
            $result .= ($result !== '' ? ' ' : '') . $resolvedValue;
        }
    }
    return $result;
}
/**
 * Convert a mixed value to a class string.
 *
 * @param mixed $mix The value to convert
 * @return string The resulting class string
 */
function toValue(mixed $mix): string
{
    // Fast path for strings
    if (is_string($mix)) {
        return $mix;
    }
    if (!is_array($mix)) {
        return '';
    }
    $result = '';
    foreach ($mix as $item) {
        if ($item) {
            $resolvedValue = toValue($item);
            if ($resolvedValue !== '') {
                $result .= ($result !== '' ? ' ' : '') . $resolvedValue;
            }
        }
    }
    return $result;
}
