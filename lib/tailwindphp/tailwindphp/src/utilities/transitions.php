<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\decl;
use BlockstudioVendor\TailwindPHP\Theme;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
/**
 * Transitions Utilities
 *
 * Port of transition utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - transition (transition, transition-none, transition-all, transition-colors, etc.)
 * - transition-discrete, transition-normal
 * - delay (delay-*)
 * - duration (duration-*)
 * - ease (ease-*)
 * - will-change
 */
/**
 * Convert ms to seconds in Tailwind 4 format.
 * 123 -> ".123s", 200 -> ".2s", 1000 -> "1s", 1500 -> "1.5s"
 */
function msToSeconds(int $ms): string
{
    $seconds = $ms / 1000;
    // Format without trailing zeros
    $formatted = rtrim(rtrim(number_format($seconds, 3, '.', ''), '0'), '.');
    // Add leading dot if < 1 (e.g., 0.123 -> .123)
    if (str_starts_with($formatted, '0.')) {
        $formatted = substr($formatted, 1);
    }
    return $formatted . 's';
}
/**
 * Register transitions utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerTransitionsUtilities(UtilityBuilder $builder): void
{
    // ==================================================
    // Animation
    // ==================================================
    $builder->functionalUtility('animate', ['themeKeys' => ['--animate'], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('animation', $value)];
    }, 'staticValues' => ['none' => [decl('animation', 'none')]]]);
    $theme = $builder->getTheme();
    // Get default timing function and duration from theme
    $defaultTimingFunctionValue = $theme->resolve(null, ['--default-transition-timing-function']) ?? 'ease';
    $defaultTimingFunction = "var(--tw-ease, {$defaultTimingFunctionValue})";
    $defaultDurationValue = $theme->resolve(null, ['--default-transition-duration']) ?? '0s';
    $defaultDuration = "var(--tw-duration, {$defaultDurationValue})";
    // Default transition property value
    $defaultTransitionProperty = 'color, background-color, border-color, outline-color, text-decoration-color, fill, stroke, --tw-gradient-from, --tw-gradient-via, --tw-gradient-to, opacity, box-shadow, transform, translate, scale, rotate, filter, -webkit-backdrop-filter, backdrop-filter, display, content-visibility, overlay, pointer-events';
    // ==================================================
    // Transition
    // ==================================================
    $builder->functionalUtility('transition', ['themeKeys' => ['--transition-property'], 'defaultValue' => $defaultTransitionProperty, 'handle' => function ($value) use ($defaultTimingFunction, $defaultDuration) {
        return [decl('transition-property', $value), decl('transition-timing-function', $defaultTimingFunction), decl('transition-duration', $defaultDuration)];
    }, 'staticValues' => ['none' => [decl('transition-property', 'none')], 'all' => [decl('transition-property', 'all'), decl('transition-timing-function', $defaultTimingFunction), decl('transition-duration', $defaultDuration)], 'colors' => [decl('transition-property', 'color, background-color, border-color, outline-color, text-decoration-color, fill, stroke, --tw-gradient-from, --tw-gradient-via, --tw-gradient-to'), decl('transition-timing-function', $defaultTimingFunction), decl('transition-duration', $defaultDuration)], 'opacity' => [decl('transition-property', 'opacity'), decl('transition-timing-function', $defaultTimingFunction), decl('transition-duration', $defaultDuration)], 'shadow' => [decl('transition-property', 'box-shadow'), decl('transition-timing-function', $defaultTimingFunction), decl('transition-duration', $defaultDuration)], 'transform' => [decl('transition-property', 'transform, translate, scale, rotate'), decl('transition-timing-function', $defaultTimingFunction), decl('transition-duration', $defaultDuration)]]]);
    // Transition behavior
    $builder->staticUtility('transition-discrete', [['transition-behavior', 'allow-discrete']]);
    $builder->staticUtility('transition-normal', [['transition-behavior', 'normal']]);
    // ==================================================
    // Delay
    // ==================================================
    $builder->functionalUtility('delay', ['themeKeys' => ['--transition-delay'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return msToSeconds((int) $value['value']);
    }, 'handle' => function ($value) {
        // Convert [300ms] arbitrary values to .3s format
        if (preg_match('/^(\d+)ms$/', $value, $m)) {
            $value = msToSeconds((int) $m[1]);
        }
        return [decl('transition-delay', $value)];
    }]);
    // ==================================================
    // Duration
    // ==================================================
    $builder->staticUtility('duration-initial', [['--tw-duration', 'initial']]);
    $builder->functionalUtility('duration', ['themeKeys' => ['--transition-duration'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return msToSeconds((int) $value['value']);
    }, 'handle' => function ($value) {
        // Convert [300ms] arbitrary values to .3s format
        if (preg_match('/^(\d+)ms$/', $value, $m)) {
            $value = msToSeconds((int) $m[1]);
        }
        return [decl('--tw-duration', $value), decl('transition-duration', $value)];
    }]);
    // ==================================================
    // Ease (Timing Function)
    // ==================================================
    $builder->functionalUtility('ease', ['themeKeys' => ['--ease'], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('--tw-ease', $value), decl('transition-timing-function', $value)];
    }, 'staticValues' => ['initial' => [decl('--tw-ease', 'initial')], 'linear' => [decl('--tw-ease', 'linear'), decl('transition-timing-function', 'linear')]]]);
    // ==================================================
    // Will Change
    // ==================================================
    $builder->staticUtility('will-change-auto', [['will-change', 'auto']]);
    $builder->staticUtility('will-change-scroll', [['will-change', 'scroll-position']]);
    $builder->staticUtility('will-change-contents', [['will-change', 'contents']]);
    $builder->staticUtility('will-change-transform', [['will-change', 'transform']]);
    $builder->functionalUtility('will-change', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('will-change', $value)];
    }]);
}
