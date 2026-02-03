<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Compile;

use function BlockstudioVendor\TailwindPHP\Ast\atRule;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Ast\rule;
use const BlockstudioVendor\TailwindPHP\PropertyOrder\PROPERTY_ORDER;
use function BlockstudioVendor\TailwindPHP\Utils\compare;
use function BlockstudioVendor\TailwindPHP\Utils\escape;
use function BlockstudioVendor\TailwindPHP\Walk\walk;
/**
 * Compile - Candidate compilation to CSS AST.
 *
 * Port of: packages/tailwindcss/src/compile.ts
 *
 * @port-deviation:bigint TypeScript uses BigInt for variant order bitmask allowing >64 variants.
 * PHP uses regular integers (limited to 64 bits but sufficient for current variant count).
 *
 * @port-deviation:sorting TypeScript uses Map<AstNode, ...> for nodeSorting.
 * PHP embeds sorting info directly in nodes via '__sorting' key (removed after sorting)
 * since PHP arrays are value types and Map equivalents have reference limitations.
 *
 * @port-deviation:variant-result TypeScript returns null for failed variants.
 * PHP returns false to distinguish from void/no-return success cases.
 */
// CompileAstFlags
const COMPILE_FLAG_NONE = 0;
const COMPILE_FLAG_RESPECT_IMPORTANT = 1 << 0;
/**
 * Cache for variant order bitmasks.
 *
 * @port-deviation:performance Pre-computed bitmasks for variant ordering.
 * Avoids repeated `1 << $order` calculations for the same variant roots.
 *
 * @var array<string, int>
 */
$_variantMaskCache = [];
/**
 * Compile multiple candidates into AST nodes.
 *
 * @param iterable<string> $rawCandidates
 * @param object $designSystem
 * @param array $options
 * @return array{astNodes: array, nodeSorting: array}
 */
function compileCandidates(iterable $rawCandidates, object $designSystem, array $options = []): array
{
    $onInvalidCandidate = $options['onInvalidCandidate'] ?? null;
    $respectImportant = $options['respectImportant'] ?? \true;
    $nodeSorting = [];
    $astNodes = [];
    $matches = [];
    // Parse candidates and variants
    foreach ($rawCandidates as $rawCandidate) {
        if (isset($designSystem->invalidCandidates[$rawCandidate])) {
            if ($onInvalidCandidate) {
                $onInvalidCandidate($rawCandidate);
            }
            continue;
        }
        $candidates = $designSystem->parseCandidate($rawCandidate);
        if (empty($candidates)) {
            if ($onInvalidCandidate) {
                $onInvalidCandidate($rawCandidate);
            }
            continue;
        }
        $matches[$rawCandidate] = $candidates;
    }
    $flags = COMPILE_FLAG_NONE;
    if ($respectImportant) {
        $flags |= COMPILE_FLAG_RESPECT_IMPORTANT;
    }
    $variantOrderMap = $designSystem->getVariantOrder();
    // Create the AST
    foreach ($matches as $rawCandidate => $candidates) {
        $found = \false;
        foreach ($candidates as $candidate) {
            $rules = $designSystem->compileAstNodes($candidate, $flags);
            if (empty($rules)) {
                continue;
            }
            $found = \true;
            foreach ($rules as $ruleInfo) {
                // Validate rule info structure
                if (!isset($ruleInfo['node']) || !isset($ruleInfo['propertySort'])) {
                    continue;
                }
                $node = $ruleInfo['node'];
                $propertySort = $ruleInfo['propertySort'];
                // Track the variant order (uses cached bitmasks)
                global $_variantMaskCache;
                $variantOrder = 0;
                foreach ($candidate['variants'] as $variant) {
                    // Arbitrary variants get order 0, named variants use their root
                    $root = $variant['root'] ?? null;
                    if ($root === null) {
                        $variantOrder |= 1;
                        // 1 << 0
                    } elseif (isset($_variantMaskCache[$root])) {
                        $variantOrder |= $_variantMaskCache[$root];
                    } else {
                        $order = $variantOrderMap[$root] ?? 0;
                        $mask = 1 << $order;
                        $_variantMaskCache[$root] = $mask;
                        $variantOrder |= $mask;
                    }
                }
                // Store sorting info with the node itself so it survives array operations
                $node['__sorting'] = ['properties' => $propertySort, 'variants' => $variantOrder, 'candidate' => $rawCandidate];
                $astNodes[] = $node;
            }
        }
        if (!$found && $onInvalidCandidate) {
            $onInvalidCandidate($rawCandidate);
        }
    }
    // Sort AST nodes using embedded sorting info
    usort($astNodes, function ($a, $z) {
        $aSorting = $a['__sorting'] ?? null;
        $zSorting = $z['__sorting'] ?? null;
        // If either sorting info is missing, keep original order
        if ($aSorting === null || $zSorting === null) {
            return 0;
        }
        // Sort by variant order first
        if ($aSorting['variants'] !== $zSorting['variants']) {
            return $aSorting['variants'] - $zSorting['variants'];
        }
        // Get property orders, defaulting to empty arrays
        $aPropsOrder = $aSorting['properties']['order'] ?? [];
        $zPropsOrder = $zSorting['properties']['order'] ?? [];
        // Find the first property that is different between the two rules
        $offset = 0;
        while ($offset < count($aPropsOrder) && $offset < count($zPropsOrder) && $aPropsOrder[$offset] === $zPropsOrder[$offset]) {
            $offset++;
        }
        // Sort by lowest property index first
        $aOrder = $aPropsOrder[$offset] ?? \PHP_INT_MAX;
        $zOrder = $zPropsOrder[$offset] ?? \PHP_INT_MAX;
        if ($aOrder !== $zOrder) {
            return $aOrder - $zOrder;
        }
        // Sort by most properties first, then by least properties
        $aCount = $aSorting['properties']['count'] ?? 0;
        $zCount = $zSorting['properties']['count'] ?? 0;
        if ($zCount !== $aCount) {
            return $zCount - $aCount;
        }
        // Sort alphabetically
        return compare($aSorting['candidate'] ?? '', $zSorting['candidate'] ?? '');
    });
    // Remove sorting info from nodes before returning
    $nodeSorting = [];
    foreach ($astNodes as &$node) {
        if (isset($node['__sorting'])) {
            $nodeSorting[] = $node['__sorting'];
            unset($node['__sorting']);
        }
    }
    return ['astNodes' => $astNodes, 'nodeSorting' => $nodeSorting];
}
/**
 * Compile AST nodes for a single candidate.
 *
 * @param array $candidate
 * @param object $designSystem
 * @param int $flags
 * @return array
 */
function compileAstNodes(array $candidate, object $designSystem, int $flags): array
{
    $asts = compileBaseUtility($candidate, $designSystem);
    if (empty($asts)) {
        return [];
    }
    $respectImportant = $designSystem->isImportant() && $flags & COMPILE_FLAG_RESPECT_IMPORTANT;
    $rules = [];
    $selector = '.' . escape($candidate['raw']);
    foreach ($asts as $nodes) {
        $propertySort = getPropertySort($nodes);
        // Apply important if needed
        if ($candidate['important'] || $respectImportant) {
            applyImportant($nodes);
        }
        $node = ['kind' => 'rule', 'selector' => $selector, 'nodes' => $nodes];
        // Apply variants
        foreach ($candidate['variants'] as $variant) {
            $result = applyVariant($node, $variant, $designSystem->getVariants());
            // When the variant results in false, the variant cannot be applied
            // (null/no return means success - node was modified in place)
            if ($result === \false) {
                return [];
            }
        }
        $rules[] = ['node' => $node, 'propertySort' => $propertySort];
    }
    return $rules;
}
/**
 * Apply a variant to a rule node.
 *
 * @param array &$node
 * @param array $variant
 * @param object $variants
 * @param int $depth
 * @return false|void Returns false on failure, nothing on success
 */
function applyVariant(array &$node, array $variant, object $variants, int $depth = 0)
{
    if ($variant['kind'] === 'arbitrary') {
        // Relative selectors are not valid at the top level
        if ($variant['relative'] && $depth === 0) {
            return \false;
        }
        $node['nodes'] = [rule($variant['selector'], $node['nodes'])];
        return;
    }
    // Get the variant's apply function
    $variantData = $variants->get($variant['root']);
    if (!$variantData || !isset($variantData['applyFn'])) {
        return \false;
    }
    $applyFn = $variantData['applyFn'];
    if ($variant['kind'] === 'compound') {
        // Create an isolated placeholder node
        $isolatedNode = atRule('@slot');
        $result = applyVariant($isolatedNode, $variant['variant'], $variants, $depth + 1);
        if ($result === \false) {
            return \false;
        }
        if ($variant['root'] === 'not' && count($isolatedNode['nodes']) > 1) {
            return \false;
        }
        foreach ($isolatedNode['nodes'] as &$child) {
            if ($child['kind'] !== 'rule' && $child['kind'] !== 'at-rule') {
                return \false;
            }
            $result = $applyFn($child, $variant);
            if ($result === \false) {
                return \false;
            }
        }
        // Replace placeholder with actual node
        walk($isolatedNode['nodes'], function (&$child) use (&$node) {
            if (($child['kind'] === 'rule' || $child['kind'] === 'at-rule') && empty($child['nodes'])) {
                $child['nodes'] = $node['nodes'];
                return \BlockstudioVendor\TailwindPHP\Walk\WalkAction::Skip;
            }
        });
        $node['nodes'] = $isolatedNode['nodes'];
        return;
    }
    // All other variants
    // Note: applyFn modifies $node by reference and returns nothing on success.
    // It returns false explicitly to indicate failure.
    $result = $applyFn($node, $variant);
    if ($result === \false) {
        return \false;
    }
}
/**
 * Check if a utility is a fallback utility.
 *
 * @param array $utility
 * @return bool
 */
function isFallbackUtility(array $utility): bool
{
    $types = $utility['options']['types'] ?? [];
    return count($types) > 1 && in_array('any', $types);
}
/**
 * Compile the base utility for a candidate.
 *
 * @param array $candidate
 * @param object $designSystem
 * @return array
 */
function compileBaseUtility(array $candidate, object $designSystem): array
{
    if ($candidate['kind'] === 'arbitrary') {
        $value = $candidate['value'];
        // Handle opacity modifier for arbitrary properties
        if ($candidate['modifier']) {
            $value = asColor($value, $candidate['modifier'], $designSystem->getTheme());
        }
        if ($value === null) {
            return [];
        }
        return [[decl($candidate['property'], $value)]];
    }
    $utilities = $designSystem->getUtilities()->get($candidate['root']) ?? [];
    $asts = [];
    // Try normal utilities first
    $normalUtilities = array_filter($utilities, fn($u) => !isFallbackUtility($u));
    foreach ($normalUtilities as $utility) {
        if ($utility['kind'] !== $candidate['kind']) {
            continue;
        }
        $compiledNodes = $utility['compileFn']($candidate);
        if ($compiledNodes === null) {
            return $asts;
        }
        if ($compiledNodes === \false) {
            continue;
        }
        $asts[] = $compiledNodes;
    }
    if (!empty($asts)) {
        return $asts;
    }
    // Try fallback utilities
    $fallbackUtilities = array_filter($utilities, fn($u) => isFallbackUtility($u));
    foreach ($fallbackUtilities as $utility) {
        if ($utility['kind'] !== $candidate['kind']) {
            continue;
        }
        $compiledNodes = $utility['compileFn']($candidate);
        if ($compiledNodes === null) {
            return $asts;
        }
        if ($compiledNodes === \false) {
            continue;
        }
        $asts[] = $compiledNodes;
    }
    return $asts;
}
/**
 * Apply color modification with opacity.
 *
 * @param string $value
 * @param array $modifier
 * @param object $theme
 * @return string|null
 */
function asColor(string $value, array $modifier, object $theme): ?string
{
    $modifierValue = $modifier['value'] ?? null;
    if ($modifierValue === null) {
        return $value;
    }
    $alpha = null;
    // Arbitrary modifier - use the value directly
    if (($modifier['kind'] ?? null) === 'arbitrary') {
        $alpha = $modifierValue;
    } elseif (($modifier['kind'] ?? null) === 'named') {
        // Check if the modifier exists in the `--opacity` theme configuration
        $themeAlpha = $theme->resolve($modifierValue, ['--opacity']);
        if ($themeAlpha !== null) {
            $alpha = $themeAlpha;
        } elseif (is_numeric($modifierValue)) {
            // Numeric values are treated as percentages (e.g., /50 = 50%)
            $alpha = $modifierValue . '%';
        } elseif (str_ends_with($modifierValue, '%')) {
            // Already a percentage
            $alpha = $modifierValue;
        } elseif (preg_match('/^0?\.\d+$/', $modifierValue)) {
            // Decimal like .5 or 0.5
            $alpha = floatval($modifierValue) * 100 . '%';
        } else {
            // Invalid modifier (e.g., /not-a-percentage)
            return null;
        }
    } elseif (is_numeric($modifierValue)) {
        $alpha = $modifierValue . '%';
    } elseif (str_ends_with($modifierValue, '%')) {
        $alpha = $modifierValue;
    } elseif (preg_match('/^0?\.\d+$/', $modifierValue)) {
        $alpha = floatval($modifierValue) * 100 . '%';
    } else {
        return null;
    }
    if ($alpha === null) {
        return $value;
    }
    // Check if value is a color that can have opacity applied
    // For CSS variables, we use color-mix and let the polyfill handle @supports fallback
    // For concrete colors (like 'red'), we can compute the oklab value directly
    if (str_contains($value, 'var(')) {
        // CSS variable - use color-mix (not inline), polyfill will add @supports fallback
        return \BlockstudioVendor\TailwindPHP\Utilities\withAlpha($value, $alpha, \false);
    }
    // Concrete color - compute inline oklab value
    return \BlockstudioVendor\TailwindPHP\Utilities\withAlpha($value, $alpha, \true);
}
/**
 * Apply !important to all declarations in an AST.
 *
 * @param array &$ast
 * @return void
 */
function applyImportant(array &$ast): void
{
    for ($i = 0; $i < count($ast); $i++) {
        $node =& $ast[$i];
        // Skip AtRoot nodes
        if ($node['kind'] === 'at-root') {
            continue;
        }
        if ($node['kind'] === 'declaration') {
            $node['important'] = \true;
        } elseif ($node['kind'] === 'rule' || $node['kind'] === 'at-rule') {
            applyImportant($node['nodes']);
        }
    }
}
/**
 * Get property sort order for AST nodes.
 *
 * @param array $nodes
 * @return array{order: array<int>, count: int}
 */
function getPropertySort(array $nodes): array
{
    $order = [];
    $count = 0;
    $queue = $nodes;
    $seenTwSort = \false;
    while (!empty($queue)) {
        $node = array_shift($queue);
        if ($node['kind'] === 'declaration') {
            if (!isset($node['value'])) {
                continue;
            }
            $count++;
            if ($seenTwSort) {
                continue;
            }
            // Check for --tw-sort property
            if ($node['property'] === '--tw-sort') {
                $idx = array_search($node['value'] ?? '', PROPERTY_ORDER);
                if ($idx !== \false) {
                    $order[$idx] = \true;
                    $seenTwSort = \true;
                    continue;
                }
            }
            $idx = array_search($node['property'], PROPERTY_ORDER);
            if ($idx !== \false) {
                $order[$idx] = \true;
            }
        } elseif ($node['kind'] === 'rule' || $node['kind'] === 'at-rule') {
            foreach ($node['nodes'] as $child) {
                $queue[] = $child;
            }
        }
    }
    // Sort the order array numerically (like TypeScript's Array.from(set).sort())
    $sortedOrder = array_keys($order);
    sort($sortedOrder, \SORT_NUMERIC);
    return ['order' => $sortedOrder, 'count' => $count];
}
