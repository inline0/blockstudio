<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/merge-classlist.ts
 *
 * Merges a class list, removing conflicting classes.
 *
 * @port-deviation:storage Uses PHP arrays instead of JS objects
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

/**
 * Merge a class list, removing conflicting Tailwind classes.
 *
 * @param string $classList Space-separated class string
 * @param array<string, mixed> $configUtils Configuration utilities
 * @return string Merged class string
 */
function mergeClassList(string $classList, array $configUtils): string
{
    $parseClassName = $configUtils['parseClassName'];
    $getClassGroupId = $configUtils['getClassGroupId'];
    $getConflictingClassGroupIds = $configUtils['getConflictingClassGroupIds'];
    $sortModifiers = $configUtils['sortModifiers'];
    // Set of classGroupIds in following format:
    // `{importantModifier}{variantModifiers}{classGroupId}`
    $classGroupsInConflict = [];
    $classNames = preg_split('/\s+/', trim($classList));
    $result = '';
    // Process in reverse order (later classes take precedence)
    for ($index = count($classNames) - 1; $index >= 0; $index--) {
        $originalClassName = $classNames[$index];
        $parsed = $parseClassName($originalClassName);
        $isExternal = $parsed['isExternal'] ?? \false;
        $modifiers = $parsed['modifiers'];
        $hasImportantModifier = $parsed['hasImportantModifier'];
        $baseClassName = $parsed['baseClassName'];
        $maybePostfixModifierPosition = $parsed['maybePostfixModifierPosition'];
        if ($isExternal) {
            $result = $originalClassName . ($result !== '' ? ' ' . $result : '');
            continue;
        }
        $hasPostfixModifier = $maybePostfixModifierPosition !== null;
        $classGroupId = $getClassGroupId($hasPostfixModifier ? substr($baseClassName, 0, $maybePostfixModifierPosition) : $baseClassName);
        if ($classGroupId === null) {
            if (!$hasPostfixModifier) {
                // Not a Tailwind class
                $result = $originalClassName . ($result !== '' ? ' ' . $result : '');
                continue;
            }
            $classGroupId = $getClassGroupId($baseClassName);
            if ($classGroupId === null) {
                // Not a Tailwind class
                $result = $originalClassName . ($result !== '' ? ' ' . $result : '');
                continue;
            }
            $hasPostfixModifier = \false;
        }
        // Fast path: skip sorting for empty or single modifier
        if (count($modifiers) === 0) {
            $variantModifier = '';
        } elseif (count($modifiers) === 1) {
            $variantModifier = $modifiers[0];
        } else {
            $variantModifier = implode(':', $sortModifiers($modifiers));
        }
        $modifierId = $hasImportantModifier ? $variantModifier . '!' : $variantModifier;
        $classId = $modifierId . $classGroupId;
        if (in_array($classId, $classGroupsInConflict, \true)) {
            // Tailwind class omitted due to conflict
            continue;
        }
        $classGroupsInConflict[] = $classId;
        $conflictGroups = $getConflictingClassGroupIds($classGroupId, $hasPostfixModifier);
        foreach ($conflictGroups as $group) {
            $classGroupsInConflict[] = $modifierId . $group;
        }
        // Tailwind class not in conflict
        $result = $originalClassName . ($result !== '' ? ' ' . $result : '');
    }
    return $result;
}
