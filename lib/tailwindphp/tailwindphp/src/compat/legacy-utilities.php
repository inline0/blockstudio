<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Compat;

use function BlockstudioVendor\TailwindPHP\Ast\decl;
use BlockstudioVendor\TailwindPHP\DesignSystem\DesignSystem;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
use function BlockstudioVendor\TailwindPHP\Utils\isValidSpacingMultiplier;
use function BlockstudioVendor\TailwindPHP\Utils\segment;
/**
 * Legacy Utilities
 *
 * Port of: packages/tailwindcss/src/compat/legacy-utilities.ts
 *
 * @port-deviation:omitted `utilities.suggest()` calls are omitted (IDE tooling only).
 *
 * @port-deviation:helper Upstream registers these from `applyCompatibilityHooks()`.
 * PHP has no compat hooks, so `buildDesignSystem()` registers them directly.
 */
function registerLegacyUtilities(DesignSystem $designSystem): void
{
    $utilities = $designSystem->getUtilities();
    $theme = $designSystem->getTheme();
    foreach ([['t', 'top'], ['tr', 'top right'], ['r', 'right'], ['br', 'bottom right'], ['b', 'bottom'], ['bl', 'bottom left'], ['l', 'left'], ['tl', 'top left']] as [$value, $direction]) {
        $utilities->static("bg-gradient-to-{$value}", fn() => [decl('--tw-gradient-position', "to {$direction} in oklab"), decl('background-image', 'linear-gradient(var(--tw-gradient-stops))')]);
    }
    // Legacy `background-position` utilities for compatibility with v4.0 and earlier
    $utilities->static('bg-left-top', fn() => [decl('background-position', '0 0')]);
    $utilities->static('bg-right-top', fn() => [decl('background-position', '100% 0')]);
    $utilities->static('bg-left-bottom', fn() => [decl('background-position', '0 100%')]);
    $utilities->static('bg-right-bottom', fn() => [decl('background-position', '100% 100%')]);
    // Legacy `object-position` utilities for compatibility with v4.0 and earlier
    $utilities->static('object-left-top', fn() => [decl('object-position', 'left top')]);
    $utilities->static('object-right-top', fn() => [decl('object-position', 'right top')]);
    $utilities->static('object-left-bottom', fn() => [decl('object-position', 'left bottom')]);
    $utilities->static('object-right-bottom', fn() => [decl('object-position', 'right bottom')]);
    $utilities->functional('max-w-screen', function ($candidate) use ($theme) {
        if (!isset($candidate['value'])) {
            return null;
        }
        if ($candidate['value']['kind'] === 'arbitrary') {
            return null;
        }
        $value = $theme->resolve($candidate['value']['value'], ['--breakpoint']);
        if ($value === null) {
            return null;
        }
        return [decl('max-width', $value)];
    });
    $utilities->static('overflow-ellipsis', fn() => [decl('text-overflow', 'ellipsis')]);
    $utilities->static('decoration-slice', fn() => [decl('-webkit-box-decoration-break', 'slice'), decl('box-decoration-break', 'slice')]);
    $utilities->static('decoration-clone', fn() => [decl('-webkit-box-decoration-break', 'clone'), decl('box-decoration-break', 'clone')]);
    foreach (['flex-shrink', 'flex-grow'] as $name) {
        $utilities->functional($name, function ($candidate) use ($name) {
            if (isset($candidate['modifier'])) {
                return null;
            }
            if (!isset($candidate['value'])) {
                return [decl($name, '1')];
            }
            if ($candidate['value']['kind'] === 'arbitrary') {
                return [decl($name, $candidate['value']['value'])];
            }
            if (isPositiveInteger($candidate['value']['value'])) {
                return [decl($name, $candidate['value']['value'])];
            }
            return null;
        });
    }
    $utilities->static('order-none', fn() => [decl('order', '0')]);
    $utilities->static('break-words', fn() => [decl('overflow-wrap', 'break-word')]);
    // Legacy `start` and `end` inset utilities, replaced by `inset-s` and `inset-e`
    foreach ([['start', 'inset-inline-start'], ['end', 'inset-inline-end']] as [$name, $property]) {
        $utilities->static("{$name}-auto", fn() => [decl($property, 'auto')]);
        $utilities->static("{$name}-full", fn() => [decl($property, '100%')]);
        $utilities->static("-{$name}-full", fn() => [decl($property, '-100%')]);
        $utilities->static("{$name}-px", fn() => [decl($property, '1px')]);
        $utilities->static("-{$name}-px", fn() => [decl($property, '-1px')]);
        $handleInset = fn(bool $negative) => function ($candidate) use ($theme, $property, $negative) {
            if (!isset($candidate['value'])) {
                return null;
            }
            if ($candidate['value']['kind'] === 'arbitrary') {
                if (isset($candidate['modifier'])) {
                    return null;
                }
                $value = $candidate['value']['value'];
                return [decl($property, $negative ? "calc({$value} * -1)" : $value)];
            }
            $fraction = $candidate['value']['fraction'] ?? null;
            $value = $theme->resolve($fraction ?? $candidate['value']['value'], ['--inset', '--spacing']);
            // Handle fractions like `start-1/2`
            if ($value === null && $fraction !== null) {
                [$lhs, $rhs] = segment($fraction, '/');
                if (!isPositiveInteger($lhs) || !isPositiveInteger($rhs)) {
                    return null;
                }
                $value = "calc({$fraction} * 100%)";
            }
            // Handle bare spacing multiplier values like `start-4`
            if ($value === null && $negative) {
                $multiplier = $theme->resolve(null, ['--spacing']);
                if ($multiplier !== null && isValidSpacingMultiplier($candidate['value']['value'])) {
                    return [decl($property, "calc({$multiplier} * -{$candidate['value']['value']})")];
                }
            }
            if ($value === null) {
                $multiplier = $theme->resolve(null, ['--spacing']);
                if ($multiplier !== null && isValidSpacingMultiplier($candidate['value']['value'])) {
                    $value = "calc({$multiplier} * {$candidate['value']['value']})";
                }
            }
            if ($value === null) {
                return null;
            }
            return [decl($property, $negative ? "calc({$value} * -1)" : $value)];
        };
        $utilities->functional("-{$name}", $handleInset(\true));
        $utilities->functional($name, $handleInset(\false));
    }
}
