<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\atRoot;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use BlockstudioVendor\TailwindPHP\Theme;
/**
 * Background Utilities
 *
 * Port of background utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - background-color
 * - background-image (none, gradients)
 * - background-size
 * - background-attachment
 * - background-position
 * - background-repeat
 * - background-origin
 * - background-clip
 */
/**
 * Register background utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerBackgroundUtilities(UtilityBuilder $builder): void
{
    $theme = $builder->getTheme();
    // ==================================================
    // Background Color
    // ==================================================
    $builder->colorUtility('bg', ['themeKeys' => ['--background-color', '--color'], 'handle' => function ($value) {
        return [decl('background-color', $value)];
    }]);
    // ==================================================
    // Background Image
    // ==================================================
    $builder->staticUtility('bg-none', [['background-image', 'none']]);
    // ==================================================
    // Background Size
    // ==================================================
    $builder->staticUtility('bg-auto', [['background-size', 'auto']]);
    $builder->staticUtility('bg-cover', [['background-size', 'cover']]);
    $builder->staticUtility('bg-contain', [['background-size', 'contain']]);
    // bg-size-[value] arbitrary utility
    $builder->functionalUtility('bg-size', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) {
        if ($value === null) {
            return null;
        }
        return [decl('background-size', $value)];
    }]);
    // ==================================================
    // Background Attachment
    // ==================================================
    $builder->staticUtility('bg-fixed', [['background-attachment', 'fixed']]);
    $builder->staticUtility('bg-local', [['background-attachment', 'local']]);
    $builder->staticUtility('bg-scroll', [['background-attachment', 'scroll']]);
    // ==================================================
    // Background Position
    // ==================================================
    $builder->staticUtility('bg-top', [['background-position', 'top']]);
    $builder->staticUtility('bg-bottom', [['background-position', 'bottom']]);
    $builder->staticUtility('bg-center', [['background-position', 'center']]);
    $builder->staticUtility('bg-left', [['background-position', '0']]);
    $builder->staticUtility('bg-right', [['background-position', '100%']]);
    // Corner positions (new TailwindCSS 4.0 naming)
    $builder->staticUtility('bg-top-left', [['background-position', '0 0']]);
    $builder->staticUtility('bg-top-right', [['background-position', '100% 0']]);
    $builder->staticUtility('bg-bottom-left', [['background-position', '0 100%']]);
    $builder->staticUtility('bg-bottom-right', [['background-position', '100% 100%']]);
    // Legacy corner positions (v4.0 and earlier)
    $builder->staticUtility('bg-left-top', [['background-position', '0 0']]);
    $builder->staticUtility('bg-left-bottom', [['background-position', '0 100%']]);
    $builder->staticUtility('bg-right-top', [['background-position', '100% 0']]);
    $builder->staticUtility('bg-right-bottom', [['background-position', '100% 100%']]);
    // bg-position-[value] arbitrary utility
    $builder->functionalUtility('bg-position', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) {
        if ($value === null) {
            return null;
        }
        return [decl('background-position', $value)];
    }]);
    // ==================================================
    // Background Repeat
    // ==================================================
    $builder->staticUtility('bg-repeat', [['background-repeat', 'repeat']]);
    $builder->staticUtility('bg-no-repeat', [['background-repeat', 'no-repeat']]);
    $builder->staticUtility('bg-repeat-x', [['background-repeat', 'repeat-x']]);
    $builder->staticUtility('bg-repeat-y', [['background-repeat', 'repeat-y']]);
    $builder->staticUtility('bg-repeat-round', [['background-repeat', 'round']]);
    $builder->staticUtility('bg-repeat-space', [['background-repeat', 'space']]);
    // ==================================================
    // Background Origin
    // ==================================================
    $builder->staticUtility('bg-origin-border', [['background-origin', 'border-box']]);
    $builder->staticUtility('bg-origin-padding', [['background-origin', 'padding-box']]);
    $builder->staticUtility('bg-origin-content', [['background-origin', 'content-box']]);
    // ==================================================
    // Background Clip
    // ==================================================
    $builder->staticUtility('bg-clip-border', [['background-clip', 'border-box']]);
    $builder->staticUtility('bg-clip-padding', [['background-clip', 'padding-box']]);
    $builder->staticUtility('bg-clip-content', [['background-clip', 'content-box']]);
    $builder->staticUtility('bg-clip-text', [['-webkit-background-clip', 'text'], ['background-clip', 'text']]);
    // ==================================================
    // Linear Gradient (bg-linear-*)
    // ==================================================
    // Direction mappings for linear gradients
    $linearDirections = ['to-t' => 'to top', 'to-tr' => 'to top right', 'to-r' => 'to right', 'to-br' => 'to bottom right', 'to-b' => 'to bottom', 'to-bl' => 'to bottom left', 'to-l' => 'to left', 'to-tl' => 'to top left'];
    // Register bg-linear-to-* utilities
    foreach ($linearDirections as $suffix => $direction) {
        registerLinearGradientUtility($builder, "bg-linear-{$suffix}", $direction);
    }
    // Register bg-linear-{angle} utilities (e.g., bg-linear-45)
    // Note: supportsNegative is false here because arbitrary non-angle values like [to_bottom]
    // should not support negative. The handleBareValue already handles numeric angles.
    $builder->functionalUtility('bg-linear', ['themeKeys' => ['--gradient'], 'supportsNegative' => \false, 'handleBareValue' => function ($value) {
        // Check if it's a number (angle in degrees)
        if (is_numeric($value['value'])) {
            return "{$value['value']}deg";
        }
        return null;
    }, 'handle' => function ($value) use ($builder) {
        // For angle values, we need to set --tw-gradient-position
        // and use linear-gradient with var(--tw-gradient-stops)
        return registerGradientWithAngle($builder, 'linear-gradient', $value);
    }]);
    // ==================================================
    // Conic Gradient (bg-conic-*)
    // ==================================================
    registerConicGradientUtilities($builder);
    // ==================================================
    // Radial Gradient (bg-radial-*)
    // ==================================================
    registerRadialGradientUtilities($builder);
    // ==================================================
    // Gradient Color Stops (from-*, via-*, to-*)
    // ==================================================
    registerGradientColorStopUtilities($builder);
}
/**
 * Register a linear gradient utility with a specific direction.
 */
function registerLinearGradientUtility(UtilityBuilder $builder, string $name, string $direction): void
{
    // Register as functional to support modifiers like /oklch, /shorter, etc.
    $builder->functionalUtility($name, ['themeKeys' => [], 'handleBareValue' => function ($value) use ($direction) {
        // The bare form just uses the direction
        if ($value === null || $value === '') {
            return $direction;
        }
        return null;
    }, 'handle' => function ($value, $modifier = null) use ($direction) {
        // Get interpolation mode from modifier
        $interpolation = getGradientInterpolationMode($modifier);
        $interpolationWithIn = $interpolation ? " in {$interpolation}" : ' in oklab';
        $declarations = [];
        // Base: --tw-gradient-position without @supports
        $declarations[] = decl('--tw-gradient-position', $direction);
        // With @supports, add interpolation mode
        // Note: This is a simplified version - full implementation would need @supports
        // For now, we'll include the interpolation in the position
        if ($interpolation || $modifier === null) {
            $declarations[0] = decl('--tw-gradient-position', $direction . $interpolationWithIn);
        }
        $declarations[] = decl('background-image', 'linear-gradient(var(--tw-gradient-stops))');
        return $declarations;
    }]);
    // Also register the bare version without modifier
    $builder->staticUtility($name, [['--tw-gradient-position', $direction], ['background-image', 'linear-gradient(var(--tw-gradient-stops))']]);
}
/**
 * Register gradient utilities with angle support.
 */
function registerGradientWithAngle(UtilityBuilder $builder, string $gradientType, string $value): array
{
    $declarations = [];
    // Check if value is negative (starts with calc(...*-1))
    $isNegative = str_starts_with($value, 'calc(') && str_contains($value, '* -1');
    // For degree angles
    if (str_ends_with($value, 'deg')) {
        $angle = $value;
        if ($isNegative) {
            $angle = "calc({$value} * -1)";
        }
        $declarations[] = decl('--tw-gradient-position', $angle);
        $declarations[] = decl('background-image', "{$gradientType}(var(--tw-gradient-stops))");
    } else {
        // For other values like "to bottom", radians, etc.
        $declarations[] = decl('--tw-gradient-position', $value);
        $declarations[] = decl('background-image', "{$gradientType}(var(--tw-gradient-stops, {$value}))");
    }
    return $declarations;
}
/**
 * Register conic gradient utilities.
 */
function registerConicGradientUtilities(UtilityBuilder $builder): void
{
    // Base bg-conic (no value)
    $builder->staticUtility('bg-conic', [['--tw-gradient-position', 'in oklab'], ['background-image', 'conic-gradient(var(--tw-gradient-stops))']]);
    // bg-conic with interpolation modifiers
    $interpolationModes = ['oklch', 'oklab', 'hsl', 'srgb', 'longer', 'shorter', 'increasing', 'decreasing'];
    foreach ($interpolationModes as $mode) {
        $interpolation = getGradientInterpolationMode($mode);
        $builder->staticUtility("bg-conic/{$mode}", [['--tw-gradient-position', "in {$interpolation}"], ['background-image', 'conic-gradient(var(--tw-gradient-stops))']]);
    }
    // bg-conic-{angle} utilities (e.g., bg-conic-45)
    $builder->functionalUtility('bg-conic', ['themeKeys' => ['--gradient'], 'supportsNegative' => \true, 'handleBareValue' => function ($value) {
        // Numeric values are angles
        if (is_numeric($value['value'] ?? '')) {
            return $value['value'] . 'deg';
        }
        return null;
    }, 'handle' => function ($value, $modifier = null, $negative = \false) {
        if ($value === null) {
            return null;
        }
        $interpolation = getGradientInterpolationMode($modifier);
        $interpolationStr = $interpolation ? " in {$interpolation}" : '';
        // Handle arbitrary values - full gradient syntax
        if (str_contains($value, ',') || str_starts_with($value, 'from') || str_starts_with($value, 'at')) {
            return [decl('--tw-gradient-position', $value), decl('background-image', "conic-gradient(var(--tw-gradient-stops, {$value}))")];
        }
        // Handle angle values (like "45deg" or just "45")
        $angleValue = $value;
        if (!str_contains($value, 'deg')) {
            $angleValue = "{$value}deg";
        }
        if ($negative) {
            $angleValue = "calc({$angleValue} * -1)";
        }
        return [decl('--tw-gradient-position', "from {$angleValue}{$interpolationStr}"), decl('background-image', 'conic-gradient(var(--tw-gradient-stops))')];
    }]);
}
/**
 * Register radial gradient utilities.
 */
function registerRadialGradientUtilities(UtilityBuilder $builder): void
{
    // Base bg-radial (no value)
    $builder->staticUtility('bg-radial', [['--tw-gradient-position', 'in oklab'], ['background-image', 'radial-gradient(var(--tw-gradient-stops))']]);
    // bg-radial with interpolation modifiers
    $interpolationModes = ['oklch', 'oklab', 'hsl', 'srgb', 'longer', 'shorter', 'increasing', 'decreasing'];
    foreach ($interpolationModes as $mode) {
        $interpolation = getGradientInterpolationMode($mode);
        $builder->staticUtility("bg-radial/{$mode}", [['--tw-gradient-position', "in {$interpolation}"], ['background-image', 'radial-gradient(var(--tw-gradient-stops))']]);
    }
    // bg-radial-[shape] utilities - only for arbitrary values
    $builder->functionalUtility('bg-radial', ['themeKeys' => ['--gradient'], 'handle' => function ($value, $modifier = null) {
        if ($value === null) {
            return null;
        }
        $interpolation = getGradientInterpolationMode($modifier);
        // Handle arbitrary values - full gradient syntax or position
        if (str_contains($value, ',') || str_starts_with($value, 'at') || str_starts_with($value, 'circle') || str_starts_with($value, 'ellipse')) {
            // If "at center" (default position), just use default without position
            if ($value === 'at center') {
                return [decl('--tw-gradient-position', ''), decl('background-image', 'radial-gradient(var(--tw-gradient-stops))')];
            }
            return [decl('--tw-gradient-position', $value), decl('background-image', "radial-gradient(var(--tw-gradient-stops, {$value}))")];
        }
        return null;
    }]);
}
/**
 * Get the CSS interpolation mode string for a modifier.
 */
function getGradientInterpolationMode(?string $modifier): ?string
{
    if ($modifier === null) {
        return null;
    }
    return match ($modifier) {
        'oklch' => 'oklch',
        'oklab' => 'oklab',
        'hsl' => 'hsl',
        'srgb' => 'srgb',
        'longer' => 'oklch longer hue',
        'shorter' => 'oklch shorter hue',
        'increasing' => 'oklch increasing hue',
        'decreasing' => 'oklch decreasing hue',
        default => $modifier,
    };
}
/**
 * Register gradient color stop utilities (from-*, via-*, to-*).
 */
function registerGradientColorStopUtilities(UtilityBuilder $builder): void
{
    $theme = $builder->getTheme();
    // Helper function to create gradient stop @property declarations
    $gradientStopProperties = function () {
        return atRoot([property('--tw-gradient-position'), property('--tw-gradient-from', '#0000', '<color>'), property('--tw-gradient-via', '#0000', '<color>'), property('--tw-gradient-to', '#0000', '<color>'), property('--tw-gradient-stops'), property('--tw-gradient-via-stops'), property('--tw-gradient-from-position', '0%', '<length-percentage>'), property('--tw-gradient-via-position', '50%', '<length-percentage>'), property('--tw-gradient-to-position', '100%', '<length-percentage>')]);
    };
    // Helper to check if a string is a positive integer
    $isPositiveInteger = function ($value) {
        if (!is_numeric($value)) {
            return \false;
        }
        $num = (int) $value;
        return $num >= 0 && (string) $num === (string) $value;
    };
    // Generic gradient stop utility creator
    $gradientStopUtility = function (string $classRoot, callable $colorHandler, callable $positionHandler) use ($builder, $theme, $isPositiveInteger) {
        $builder->getUtilities()->functional($classRoot, function ($candidate) use ($theme, $colorHandler, $positionHandler, $isPositiveInteger) {
            if (!isset($candidate['value'])) {
                return null;
            }
            $candidateValue = $candidate['value'];
            $modifier = $candidate['modifier'] ?? null;
            // Arbitrary values
            if ($candidateValue['kind'] === 'arbitrary') {
                $value = $candidateValue['value'];
                $type = $candidateValue['dataType'] ?? \BlockstudioVendor\TailwindPHP\Utils\inferDataType($value, ['length', 'percentage', 'color']);
                switch ($type) {
                    case 'length':
                    case 'percentage':
                        if ($modifier !== null) {
                            return null;
                        }
                        return $positionHandler($value);
                    default:
                        $value = asColor($value, $modifier, $theme);
                        if ($value === null) {
                            return null;
                        }
                        return $colorHandler($value);
                }
            }
            $namedValue = $candidateValue['value'] ?? '';
            // Known values: Colors
            $colorValue = resolveThemeColor($candidate, $theme, ['--background-color', '--color']);
            if ($colorValue !== null) {
                return $colorHandler($colorValue);
            }
            // Known values: Positions from theme
            if ($modifier === null) {
                $positionValue = $theme->resolve($namedValue, ['--gradient-color-stop-positions']);
                if ($positionValue !== null) {
                    return $positionHandler($positionValue);
                }
                // Check for percentage values like 0%, 5%, 100%
                if (str_ends_with($namedValue, '%')) {
                    $numPart = substr($namedValue, 0, -1);
                    if ($isPositiveInteger($numPart)) {
                        return $positionHandler($namedValue);
                    }
                }
            }
            return null;
        });
    };
    // from-* utility (color and position)
    $gradientStopUtility('from', fn($value) => [$gradientStopProperties(), decl('--tw-sort', '--tw-gradient-from'), decl('--tw-gradient-from', $value), decl('--tw-gradient-stops', 'var(--tw-gradient-via-stops, var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position))')], fn($value) => [$gradientStopProperties(), decl('--tw-gradient-from-position', $value)]);
    // via-none utility
    $builder->staticUtility('via-none', [['--tw-gradient-via-stops', 'initial']]);
    // via-* utility (color and position)
    $gradientStopUtility('via', fn($value) => [$gradientStopProperties(), decl('--tw-sort', '--tw-gradient-via'), decl('--tw-gradient-via', $value), decl('--tw-gradient-via-stops', 'var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-via) var(--tw-gradient-via-position), var(--tw-gradient-to) var(--tw-gradient-to-position)'), decl('--tw-gradient-stops', 'var(--tw-gradient-via-stops)')], fn($value) => [$gradientStopProperties(), decl('--tw-gradient-via-position', $value)]);
    // to-* utility (color and position)
    $gradientStopUtility('to', fn($value) => [$gradientStopProperties(), decl('--tw-sort', '--tw-gradient-to'), decl('--tw-gradient-to', $value), decl('--tw-gradient-stops', 'var(--tw-gradient-via-stops, var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position))')], fn($value) => [$gradientStopProperties(), decl('--tw-gradient-to-position', $value)]);
}
