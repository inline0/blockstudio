<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Variables
 *
 * Port of: packages/tailwindcss/src/utils/variables.ts
 *
 * @port-deviation:none This is a direct 1:1 port with no significant deviations.
 *
 * Extracts CSS custom property (variable) names from values.
 */
/**
 * Extract used CSS variables from a raw value string.
 *
 * @param string $raw The raw CSS value
 * @return array List of variable names (e.g., ['--color-red-500', '--spacing'])
 */
function extractUsedVariables(string $raw): array
{
    $variables = [];
    $ast = \BlockstudioVendor\TailwindPHP\ValueParser\parse($raw);
    \BlockstudioVendor\TailwindPHP\ValueParser\walk($ast, function ($node) use (&$variables) {
        if ($node['kind'] !== 'function' || $node['value'] !== 'var') {
            return;
        }
        \BlockstudioVendor\TailwindPHP\ValueParser\walk($node['nodes'], function ($child) use (&$variables) {
            if ($child['kind'] !== 'word' || !isset($child['value'][0]) || !isset($child['value'][1]) || $child['value'][0] !== '-' || $child['value'][1] !== '-') {
                return;
            }
            $variables[] = $child['value'];
        });
        return \BlockstudioVendor\TailwindPHP\ValueParser\WalkAction::Skip;
    });
    return $variables;
}
