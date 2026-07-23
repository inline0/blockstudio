<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/tw-merge.ts
 *
 * Main entry point for tailwind-merge.
 *
 * @port-deviation:singleton Uses static variable for singleton pattern
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

require_once __DIR__ . '/create-tailwind-merge.php';
require_once __DIR__ . '/default-config.php';
/**
 * Merge Tailwind CSS classes, removing conflicts.
 *
 * Later classes take precedence over earlier ones when there are conflicts.
 *
 * @param mixed ...$classLists Class values to merge
 * @return string Merged class string
 */
function twMerge(mixed ...$classLists): string
{
    static $merge = null;
    if ($merge === null) {
        $merge = createTailwindMerge(fn() => DefaultConfig::get());
    }
    return $merge(...$classLists);
}
