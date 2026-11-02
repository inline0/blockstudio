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
    ValueParser\walk($valueAst, ['exit' => function (&$valueNode, $ctx) use (&$folded, $rem) {
        // Canonicalize dimensions to their simplest form. This includes:
        // - Convert `-0`, `+0`, `0.0`, … to `0`
        // - Convert `-0px`, `+0em`, `0.0rem`, … to `0<unit>`
        // - Convert units to an equivalent unit
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
            // We need to be careful with `0` because `0<unit>` can only be
            // converted to `0` if we're dealing with a `<length>` type.
            if ($canonical === '0') {
                // When used inside of a function such as `calc(…)`, then
                // this isn't always safe to convert to `0`.
                //
                // E.g.:
                // - `calc(0px + 1rem)` -> `calc(0 + 1rem)` goes from valid to invalid
                // - `calc(0px * 1rem)` -> `calc(0 * 1rem)` goes from invalid to valid
                if (($ctx['parent']['kind'] ?? null) === 'function') {
                    $withUnit = canonicalizeDimension($valueNode['value'], $rem, \false);
                    if ($withUnit === null) {
                        return;
                    }
                    $folded = \true;
                    return ValueParser\WalkAction::ReplaceSkip(ValueParser\word($withUnit));
                }
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
            // Fold `0<unit> * something-without-unit` to just `0<unit>`,
            // inside of a function such as `calc(…)`
            if ($operator === '*' && $lhs !== null && $lhs[0] == 0.0 && $lhs[1] !== null && $rhs !== null && $rhs[1] === null) {
                $folded = \true;
                if (($ctx['parent']['kind'] ?? null) === 'function') {
                    return ValueParser\WalkAction::ReplaceSkip(ValueParser\word("0{$lhs[1]}"));
                }
                return ValueParser\WalkAction::ReplaceSkip(ValueParser\word('0'));
            }
            // Fold `something-without-unit * 0<unit>` to just `0<unit>`,
            // inside of a function such as `calc(…)`
            if ($operator === '*' && $rhs !== null && $rhs[0] == 0.0 && $rhs[1] !== null && $lhs !== null && $lhs[1] === null) {
                $folded = \true;
                if (($ctx['parent']['kind'] ?? null) === 'function') {
                    return ValueParser\WalkAction::ReplaceSkip(ValueParser\word("0{$rhs[1]}"));
                }
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
                        $computed = $lhs[0] / $rhs[0];
                        // Only fold with .xx precision, anything beyond
                        // that might be a bit too much.
                        //
                        // E.g. 100% / 3.5 = 28.571428571428573%, which is
                        //      correct but not as user friendly.
                        if (round($computed * 100) / 100 != $computed) {
                            break;
                        }
                        $folded = \true;
                        $unit = $lhs[1] ?? '';
                        return ValueParser\WalkAction::ReplaceSkip(ValueParser\word("{$computed}{$unit}"));
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
function canonicalizeDimension(string $input, ?int $rem = null, bool $normalizeUnit = \true): ?string
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
        if ($normalizeUnit) {
            return '0';
        }
        return "0{$unit}";
        // Keep unit
    }
    // Only normalize into base units when necessary
    if (!$normalizeUnit) {
        return $input;
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
