<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/sort-modifiers.ts
 *
 * Sorts modifiers for consistent class comparison.
 *
 * @port-deviation:storage Uses PHP arrays
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

class SortModifiers
{
    /** @var array<string, int> */
    private array $modifierWeights = [];
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $orderSensitiveModifiers = $config['orderSensitiveModifiers'] ?? [];
        // Assign weights to sensitive modifiers
        foreach ($orderSensitiveModifiers as $index => $mod) {
            $this->modifierWeights[$mod] = 1000000 + $index;
        }
    }
    /**
     * Sort modifiers according to:
     * - Predefined modifiers are sorted alphabetically
     * - Arbitrary variants preserve their relative order
     *
     * @param array<string> $modifiers
     * @return array<string>
     */
    public function sort(array $modifiers): array
    {
        $result = [];
        $currentSegment = [];
        foreach ($modifiers as $modifier) {
            $isArbitrary = str_starts_with($modifier, '[');
            $isOrderSensitive = isset($this->modifierWeights[$modifier]);
            if ($isArbitrary || $isOrderSensitive) {
                // Sort and flush current segment alphabetically
                if (count($currentSegment) > 0) {
                    sort($currentSegment);
                    array_push($result, ...$currentSegment);
                    $currentSegment = [];
                }
                $result[] = $modifier;
            } else {
                // Regular modifier - add to current segment for batch sorting
                $currentSegment[] = $modifier;
            }
        }
        // Sort and add any remaining segment items
        if (count($currentSegment) > 0) {
            sort($currentSegment);
            array_push($result, ...$currentSegment);
        }
        return $result;
    }
}
