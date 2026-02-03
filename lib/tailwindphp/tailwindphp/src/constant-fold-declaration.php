<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP;

use BlockstudioVendor\TailwindPHP\Utils\Dimensions;
/**
 * Constant Fold Declaration
 *
 * Port of: packages/tailwindcss/src/constant-fold-declaration.ts
 *
 * @port-deviation:isLength TypeScript imports isLength from infer-data-type.ts.
 * PHP inlines a simplified version to avoid circular dependencies.
 *
 * @port-deviation:float PHP uses == for float comparisons (e.g., $value == 0.0)
 * instead of === since PHP floating point comparison needs loose equality.
 *
 * This module performs constant folding on CSS calc() expressions,
 * simplifying expressions with two operands and one operator.
 */
/**
 * Constant fold a CSS declaration value.
 *
 * Assumption: We already assume that we receive somewhat valid `calc()`
 * expressions. So we will see `calc(1 + 1)` and not `calc(1+1)`
 *
 * @param string $input The input value
 * @param int|null $rem The root font size in pixels (for rem conversion)
 * @return string The folded value
 */
function constantFoldDeclaration(string $input, ?int $rem = null): string
{
    $folded = \false;
    $valueAst = ValueParser\parse($input);
    ValueParser\walk($valueAst, ['exit' => function (&$valueNode) use (&$folded, $rem) {
        // Canonicalize dimensions to their simplest form
        if ($valueNode['kind'] === 'word' && $valueNode['value'] !== '0') {
            $canonical = canonicalizeDimension($valueNode['value'], $rem);
            if ($canonical === null) {
                return;
                // Couldn't be canonicalized
            }
            if ($canonical === $valueNode['value']) {
                return;
                // Already in canonical form
            }
            $folded = \true;
            return ValueParser\WalkAction::ReplaceSkip(ValueParser\word($canonical));
        }
        // Constant fold `calc()` expressions with two operands and one operator
        if ($valueNode['kind'] === 'function' && ($valueNode['value'] === 'calc' || $valueNode['value'] === '')) {
            // Expected structure:
            // [
            //   { kind: 'word', value: '0.25rem' },  0
            //   { kind: 'separator', value: ' ' },  1
            //   { kind: 'word', value: '*' },       2
            //   { kind: 'separator', value: ' ' },  3
            //   { kind: 'word', value: '256' }      4
            // ]
            if (count($valueNode['nodes']) !== 5) {
                return;
            }
            $lhs = Dimensions::get($valueNode['nodes'][0]['value']);
            $operator = $valueNode['nodes'][2]['value'];
            $rhs = Dimensions::get($valueNode['nodes'][4]['value']);
            // Nullify entire expression when multiplying by `0`
            if ($operator === '*' && ($lhs !== null && $lhs[0] === 0.0 && $lhs[1] === null || $rhs !== null && $rhs[0] === 0.0 && $rhs[1] === null)) {
                $folded = \true;
                return ValueParser\WalkAction::ReplaceSkip(ValueParser\word('0'));
            }
            // We're not dealing with dimensions, so we can't fold this
            if ($lhs === null || $rhs === null) {
                return;
            }
            switch ($operator) {
                case '*':
                    if ($lhs[1] === $rhs[1] || $lhs[1] === null && $rhs[1] !== null || $lhs[1] !== null && $rhs[1] === null) {
                        $folded = \true;
                        $result = $lhs[0] * $rhs[0];
                        $unit = $lhs[1] ?? '';
                        return ValueParser\WalkAction::ReplaceSkip(ValueParser\word("{$result}{$unit}"));
                    }
                    break;
                case '+':
                    if ($lhs[1] === $rhs[1]) {
                        // Same unit or unitless
                        $folded = \true;
                        $result = $lhs[0] + $rhs[0];
                        $unit = $lhs[1] ?? '';
                        return ValueParser\WalkAction::ReplaceSkip(ValueParser\word("{$result}{$unit}"));
                    }
                    break;
                case '-':
                    if ($lhs[1] === $rhs[1]) {
                        // Same unit or unitless
                        $folded = \true;
                        $result = $lhs[0] - $rhs[0];
                        $unit = $lhs[1] ?? '';
                        return ValueParser\WalkAction::ReplaceSkip(ValueParser\word("{$result}{$unit}"));
                    }
                    break;
                case '/':
                    if ($rhs[0] != 0 && ($lhs[1] === null && $rhs[1] === null || $lhs[1] !== null && $rhs[1] === null)) {
                        $folded = \true;
                        $result = $lhs[0] / $rhs[0];
                        $unit = $lhs[1] ?? '';
                        return ValueParser\WalkAction::ReplaceSkip(ValueParser\word("{$result}{$unit}"));
                    }
                    break;
            }
        }
    }]);
    return $folded ? ValueParser\toCss($valueAst) : $input;
}
/**
 * Canonicalize a dimension to its simplest form.
 *
 * @param string $input The input dimension
 * @param int|null $rem The root font size in pixels
 * @return string|null The canonicalized dimension or null
 */
function canonicalizeDimension(string $input, ?int $rem = null): ?string
{
    $dimension = Dimensions::get($input);
    if ($dimension === null) {
        return null;
    }
    [$value, $unit] = $dimension;
    // Normalize negative zero to zero
    if ($value == 0.0) {
        $value = 0.0;
        // This removes the -0 sign
    }
    if ($unit === null) {
        // For unitless values, return as integer if possible
        if ($value == 0.0) {
            return '0';
        }
        return (string) (int) $value === (string) $value ? (string) (int) $value : (string) $value;
    }
    // Replace `0<length>` units with just `0`
    if ($value == 0.0 && isLength($input)) {
        return '0';
    }
    // Convert to canonical units
    switch (strtolower($unit)) {
        // <length> to px
        case 'in':
            return $value * 96 . 'px';
        case 'cm':
            return $value * 96 / 2.54 . 'px';
        case 'mm':
            return $value * 96 / 2.54 / 10 . 'px';
        case 'q':
            return $value * 96 / 2.54 / 10 / 4 . 'px';
        case 'pc':
            return $value * 96 / 6 . 'px';
        case 'pt':
            return $value * 96 / 72 . 'px';
        case 'rem':
            return $rem !== null ? $value * $rem . 'px' : null;
        // <angle> to deg
        case 'deg':
            return ($value == 0.0 ? '0' : $value) . 'deg';
        case 'grad':
            $result = $value * 0.9;
            return ($result == 0.0 ? '0' : $result) . 'deg';
        case 'rad':
            $result = $value * 180 / \M_PI;
            return ($result == 0.0 ? '0' : $result) . 'deg';
        case 'turn':
            $result = $value * 360;
            return ($result == 0.0 ? '0' : $result) . 'deg';
        // <time> to s
        case 's':
            return ($value == 0.0 ? '0' : $value) . 's';
        case 'ms':
            $result = $value / 1000;
            return ($result == 0.0 ? '0' : $result) . 's';
        // <frequency> to hz
        case 'khz':
            return $value * 1000 . 'hz';
        // <percentage> and <flex>
        case '%':
            return ($value == 0.0 ? '0' : $value) . '%';
        case 'fr':
            return ($value == 0.0 ? '0' : $value) . 'fr';
        default:
            return "{$value}{$unit}";
    }
}
/**
 * Check if a value is a length unit.
 *
 * @param string $input The input value
 * @return bool
 */
function isLength(string $input): bool
{
    $dimension = Dimensions::get($input);
    if ($dimension === null) {
        return \false;
    }
    $unit = strtolower($dimension[1] ?? '');
    return in_array($unit, ['px', 'em', 'rem', 'vw', 'vh', 'vmin', 'vmax', 'ch', 'ex', 'cm', 'mm', 'in', 'pt', 'pc', 'q', 'lh', 'rlh', 'cqw', 'cqh', 'cqi', 'cqb', 'cqmin', 'cqmax', 'dvw', 'dvh', 'svw', 'svh', 'lvw', 'lvh'], \true);
}
