<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP;

use BlockstudioVendor\TailwindPHP\DesignSystem\DesignSystem;
/**
 * Sort
 *
 * Port of: packages/tailwindcss/src/sort.ts
 *
 * @port-deviation:bigint TypeScript uses BigInt for sort order calculations.
 * PHP uses regular integers which is sufficient for current use cases.
 *
 * Provides class ordering functionality for tools like Prettier plugin.
 */
/**
 * Get the sort order for a list of classes.
 *
 * @param DesignSystem $designSystem The design system
 * @param array $classes List of class names
 * @return array Array of [className, sortOrder] pairs
 */
function getClassOrder(DesignSystem $designSystem, array $classes): array
{
    // Generate a sorted AST
    $compiled = compileCandidates($classes, $designSystem);
    $astNodes = $compiled['astNodes'];
    $nodeSorting = $compiled['nodeSorting'];
    // Map class names to their order in the AST
    // `null` indicates a non-Tailwind class
    $sorted = [];
    foreach ($classes as $className) {
        $sorted[$className] = null;
    }
    // Assign each class a unique, sorted number
    $idx = 0;
    foreach ($astNodes as $node) {
        $candidate = $nodeSorting[$node]['candidate'] ?? null;
        if (!$candidate) {
            continue;
        }
        // When multiple rules match a candidate
        // always take the position of the first one
        if (!isset($sorted[$candidate]) || $sorted[$candidate] === null) {
            $sorted[$candidate] = $idx++;
        }
    }
    // Pair classes with their assigned sorting number
    return array_map(fn($className) => [$className, $sorted[$className] ?? null], $classes);
}
