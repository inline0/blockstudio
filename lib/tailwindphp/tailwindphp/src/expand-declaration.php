<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\ExpandDeclaration;

use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Utils\segment;
/**
 * Expand Declaration
 *
 * Port of: packages/tailwindcss/src/expand-declaration.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 *
 * Expands shorthand CSS declarations into their longhand equivalents.
 * Used for canonicalization of utility classes.
 */
// Signature features flags (from canonicalize-candidates.ts)
const SIGNATURE_EXPAND_PROPERTIES = 1 << 0;
const SIGNATURE_LOGICAL_TO_PHYSICAL = 1 << 1;
/**
 * Create a prefixed quad mapping for properties like margin, padding.
 *
 * @param string $prefix
 * @param string $t Top
 * @param string $r Right
 * @param string $b Bottom
 * @param string $l Left
 * @return array<int, array<array{0: string, 1: int}>>
 */
function createPrefixedQuad(string $prefix, string $t = 'top', string $r = 'right', string $b = 'bottom', string $l = 'left'): array
{
    return createBareQuad("{$prefix}-{$t}", "{$prefix}-{$r}", "{$prefix}-{$b}", "{$prefix}-{$l}");
}
/**
 * Create a bare quad mapping for properties like inset.
 *
 * @param string $t Top
 * @param string $r Right
 * @param string $b Bottom
 * @param string $l Left
 * @return array<int, array<array{0: string, 1: int}>>
 */
function createBareQuad(string $t = 'top', string $r = 'right', string $b = 'bottom', string $l = 'left'): array
{
    return [1 => [[$t, 0], [$r, 0], [$b, 0], [$l, 0]], 2 => [[$t, 0], [$r, 1], [$b, 0], [$l, 1]], 3 => [[$t, 0], [$r, 1], [$b, 2], [$l, 1]], 4 => [[$t, 0], [$r, 1], [$b, 2], [$l, 3]]];
}
/**
 * Create a pair mapping for properties like gap.
 *
 * @param string $lhs Left-hand side property
 * @param string $rhs Right-hand side property
 * @return array<int, array<array{0: string, 1: int}>>
 */
function createPair(string $lhs, string $rhs): array
{
    return [1 => [[$lhs, 0], [$rhs, 0]], 2 => [[$lhs, 0], [$rhs, 1]]];
}
/**
 * Get the variadic expansion map.
 * Depending on the length of the value, map to different properties.
 *
 * @return array<string, array<int, array<array{0: string, 1: int}>>>
 */
function getVariadicExpansionMap(): array
{
    static $map = null;
    if ($map === null) {
        $map = ['inset' => createBareQuad(), 'margin' => createPrefixedQuad('margin'), 'padding' => createPrefixedQuad('padding'), 'gap' => createPair('row-gap', 'column-gap')];
    }
    return $map;
}
/**
 * Get the variadic logical expansion map.
 * Depending on the length of the value, map to different properties.
 *
 * @return array<string, array<int, array<array{0: string, 1: int}>>>
 */
function getVariadicLogicalExpansionMap(): array
{
    static $map = null;
    if ($map === null) {
        $map = ['inset-block' => createPair('top', 'bottom'), 'inset-inline' => createPair('left', 'right'), 'margin-block' => createPair('margin-top', 'margin-bottom'), 'margin-inline' => createPair('margin-left', 'margin-right'), 'padding-block' => createPair('padding-top', 'padding-bottom'), 'padding-inline' => createPair('padding-left', 'padding-right')];
    }
    return $map;
}
/**
 * Get the logical expansion map.
 * The entire value is mapped to each property.
 *
 * @return array<string, string[]>
 */
function getLogicalExpansionMap(): array
{
    return ['border-block' => ['border-bottom', 'border-top'], 'border-block-color' => ['border-bottom-color', 'border-top-color'], 'border-block-style' => ['border-bottom-style', 'border-top-style'], 'border-block-width' => ['border-bottom-width', 'border-top-width'], 'border-inline' => ['border-left', 'border-right'], 'border-inline-color' => ['border-left-color', 'border-right-color'], 'border-inline-style' => ['border-left-style', 'border-right-style'], 'border-inline-width' => ['border-left-width', 'border-right-width']];
}
/**
 * Expand a declaration node into its longhand equivalents.
 *
 * @param array $node Declaration node with 'property', 'value', 'important' keys
 * @param int $options Signature features flags
 * @return array|null Array of declaration nodes, or null if no expansion
 */
function expandDeclaration(array $node, int $options): ?array
{
    $property = $node['property'];
    $value = $node['value'] ?? '';
    $important = $node['important'] ?? \false;
    if ($options & SIGNATURE_LOGICAL_TO_PHYSICAL) {
        $variadicLogicalMap = getVariadicLogicalExpansionMap();
        if (isset($variadicLogicalMap[$property])) {
            $args = segment($value, ' ');
            $mapping = $variadicLogicalMap[$property][count($args)] ?? null;
            if ($mapping === null) {
                return null;
            }
            return array_map(fn($item) => decl($item[0], $args[$item[1]], $important), $mapping);
        }
        $logicalMap = getLogicalExpansionMap();
        if (isset($logicalMap[$property])) {
            return array_map(fn($prop) => decl($prop, $value, $important), $logicalMap[$property]);
        }
    }
    $variadicMap = getVariadicExpansionMap();
    if (isset($variadicMap[$property])) {
        $args = segment($value, ' ');
        $mapping = $variadicMap[$property][count($args)] ?? null;
        if ($mapping === null) {
            return null;
        }
        return array_map(fn($item) => decl($item[0], $args[$item[1]], $important), $mapping);
    }
    return null;
}
