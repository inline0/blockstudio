<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge
 *
 * Main entry point for tailwind-merge PHP port.
 * Exports twMerge, twJoin, and cn functions.
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

require_once __DIR__ . '/tw-merge.php';
require_once __DIR__ . '/tw-join.php';
require_once __DIR__ . '/../clsx/clsx.php';
use function BlockstudioVendor\TailwindPHP\Lib\Clsx\clsx;
/**
 * Combines clsx and twMerge for the ultimate class name utility.
 *
 * This is the recommended way to conditionally apply Tailwind classes.
 * It first processes conditional classes with clsx, then merges conflicts with twMerge.
 *
 * @param mixed ...$inputs Class values (strings, arrays, objects)
 * @return string Merged class string
 *
 * @example
 * cn('px-2 py-1', 'px-4') // => 'py-1 px-4'
 * cn('text-red-500', ['hover:text-blue-500' => true]) // => 'text-red-500 hover:text-blue-500'
 * cn(['hidden' => false, 'block' => true]) // => 'block'
 */
function cn(mixed ...$inputs): string
{
    return twMerge(clsx(...$inputs));
}
