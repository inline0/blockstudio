<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Topologically sort a dependency graph.
 *
 * Port of: packages/tailwindcss/src/utils/topological-sort.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 *
 * @template TKey
 * @param array<TKey, array<TKey>> $graph Map of node to its dependencies
 * @param callable(array<TKey>, TKey): void $onCircularDependency Callback for circular dependencies
 * @return array<TKey>
 */
function topologicalSort(array $graph, callable $onCircularDependency): array
{
    $seen = [];
    $wip = [];
    $sorted = [];
    $visit = function ($node, array $path = []) use (&$visit, &$graph, &$seen, &$wip, &$sorted, $onCircularDependency): void {
        if (!array_key_exists($node, $graph)) {
            return;
        }
        if (isset($seen[$node])) {
            return;
        }
        // Circular dependency detected
        if (isset($wip[$node])) {
            $onCircularDependency($path, $node);
            return;
        }
        $wip[$node] = \true;
        foreach ($graph[$node] ?? [] as $dependency) {
            $path[] = $node;
            $visit($dependency, $path);
            array_pop($path);
        }
        $seen[$node] = \true;
        unset($wip[$node]);
        $sorted[] = $node;
    };
    foreach (array_keys($graph) as $node) {
        $visit($node);
    }
    return $sorted;
}
