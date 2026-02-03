<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/create-tailwind-merge.ts
 *
 * Factory for creating custom tailwind-merge instances.
 *
 * @port-deviation:types Uses Closure instead of TypeScript function types
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

require_once __DIR__ . '/lru-cache.php';
require_once __DIR__ . '/parse-class-name.php';
require_once __DIR__ . '/sort-modifiers.php';
require_once __DIR__ . '/class-group-utils.php';
require_once __DIR__ . '/merge-classlist.php';
require_once __DIR__ . '/tw-join.php';
/**
 * Create a tailwind-merge function with custom configuration.
 *
 * @param callable $createConfigFirst Function that returns the base config
 * @param callable ...$createConfigRest Functions that modify the config
 * @return \Closure Function that merges class lists
 */
function createTailwindMerge(callable $createConfigFirst, callable ...$createConfigRest): \Closure
{
    // Pre-declare all shared variables before creating closures
    $configUtils = null;
    $cache = null;
    $tailwindMerge = null;
    $functionToCall = null;
    $initTailwindMerge = function (string $classList) use (&$configUtils, &$cache, &$tailwindMerge, &$functionToCall, $createConfigFirst, $createConfigRest): string {
        // Build config by applying all config creators
        $config = $createConfigFirst();
        foreach ($createConfigRest as $createConfigCurrent) {
            $config = $createConfigCurrent($config);
        }
        // Create config utilities
        $cache = new LruCache($config['cacheSize'] ?? 500);
        $parseClassName = new ParseClassName($config);
        $sortModifiers = new SortModifiers($config);
        $classGroupUtils = new ClassGroupUtils($config);
        $configUtils = ['cache' => $cache, 'parseClassName' => fn(string $cn) => $parseClassName->parse($cn), 'sortModifiers' => fn(array $mods) => $sortModifiers->sort($mods), 'getClassGroupId' => fn(string $cn) => $classGroupUtils->getClassGroupId($cn), 'getConflictingClassGroupIds' => fn(string $id, bool $hasPostfix) => $classGroupUtils->getConflictingClassGroupIds($id, $hasPostfix)];
        // Switch to the real merge function
        $functionToCall = $tailwindMerge;
        return $tailwindMerge($classList);
    };
    $tailwindMerge = function (string $classList) use (&$cache, &$configUtils): string {
        $cachedResult = $cache->get($classList);
        if ($cachedResult !== null) {
            return $cachedResult;
        }
        $result = mergeClassList($classList, $configUtils);
        $cache->set($classList, $result);
        return $result;
    };
    $functionToCall = $initTailwindMerge;
    return function (mixed ...$classLists) use (&$functionToCall): string {
        return $functionToCall(twJoin(...$classLists));
    };
}
