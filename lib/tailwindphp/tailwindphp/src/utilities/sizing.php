<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\decl;
/**
 * Sizing Utilities
 *
 * Port of sizing utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - width (w, min-w, max-w)
 * - height (h, min-h, max-h)
 * - size (size)
 */
/**
 * Register sizing utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerSizingUtilities(UtilityBuilder $builder): void
{
    // Static size/width/height utilities for common values
    // These are registered as static utilities, not as part of functional utilities
    foreach ([['full', '100%'], ['min', 'min-content'], ['max', 'max-content'], ['fit', 'fit-content']] as [$key, $value]) {
        $builder->staticUtility("size-{$key}", [['--tw-sort', 'size'], ['width', $value], ['height', $value]]);
        $builder->staticUtility("w-{$key}", [['width', $value]]);
        $builder->staticUtility("h-{$key}", [['height', $value]]);
        $builder->staticUtility("min-w-{$key}", [['min-width', $value]]);
        $builder->staticUtility("min-h-{$key}", [['min-height', $value]]);
        $builder->staticUtility("max-w-{$key}", [['max-width', $value]]);
        $builder->staticUtility("max-h-{$key}", [['max-height', $value]]);
    }
    // Auto utilities
    $builder->staticUtility('size-auto', [['--tw-sort', 'size'], ['width', 'auto'], ['height', 'auto']]);
    $builder->staticUtility('w-auto', [['width', 'auto']]);
    $builder->staticUtility('h-auto', [['height', 'auto']]);
    $builder->staticUtility('min-w-auto', [['min-width', 'auto']]);
    $builder->staticUtility('min-h-auto', [['min-height', 'auto']]);
    // Line height utilities
    $builder->staticUtility('h-lh', [['height', '1lh']]);
    $builder->staticUtility('min-h-lh', [['min-height', '1lh']]);
    $builder->staticUtility('max-h-lh', [['max-height', '1lh']]);
    // Screen utilities (viewport units)
    $builder->staticUtility('w-screen', [['width', '100vw']]);
    $builder->staticUtility('min-w-screen', [['min-width', '100vw']]);
    $builder->staticUtility('max-w-screen', [['max-width', '100vw']]);
    $builder->staticUtility('h-screen', [['height', '100vh']]);
    $builder->staticUtility('min-h-screen', [['min-height', '100vh']]);
    $builder->staticUtility('max-h-screen', [['max-height', '100vh']]);
    // Viewport-relative units (svw, lvw, dvw, svh, lvh, dvh)
    $builder->staticUtility('w-svw', [['width', '100svw']]);
    $builder->staticUtility('w-lvw', [['width', '100lvw']]);
    $builder->staticUtility('w-dvw', [['width', '100dvw']]);
    $builder->staticUtility('h-svh', [['height', '100svh']]);
    $builder->staticUtility('h-lvh', [['height', '100lvh']]);
    $builder->staticUtility('h-dvh', [['height', '100dvh']]);
    $builder->staticUtility('min-h-svh', [['min-height', '100svh']]);
    $builder->staticUtility('min-h-lvh', [['min-height', '100lvh']]);
    $builder->staticUtility('min-h-dvh', [['min-height', '100dvh']]);
    $builder->staticUtility('max-h-svh', [['max-height', '100svh']]);
    $builder->staticUtility('max-h-lvh', [['max-height', '100lvh']]);
    $builder->staticUtility('max-h-dvh', [['max-height', '100dvh']]);
    // None utilities
    $builder->staticUtility('max-w-none', [['max-width', 'none']]);
    $builder->staticUtility('max-h-none', [['max-height', 'none']]);
    // Spacing-based utilities (functional)
    $builder->spacingUtility('size', ['--size', '--spacing'], function ($value) {
        return [decl('--tw-sort', 'size'), decl('width', $value), decl('height', $value)];
    }, ['supportsFractions' => \true]);
    // Width functional utilities
    $builder->spacingUtility('w', ['--width', '--spacing', '--container'], function ($value) {
        return [decl('width', $value)];
    }, ['supportsFractions' => \true]);
    $builder->spacingUtility('min-w', ['--min-width', '--spacing', '--container'], function ($value) {
        return [decl('min-width', $value)];
    });
    $builder->spacingUtility('max-w', ['--max-width', '--spacing', '--container'], function ($value) {
        return [decl('max-width', $value)];
    });
    // Height functional utilities
    $builder->spacingUtility('h', ['--height', '--spacing'], function ($value) {
        return [decl('height', $value)];
    }, ['supportsFractions' => \true]);
    $builder->spacingUtility('min-h', ['--min-height', '--height', '--spacing'], function ($value) {
        return [decl('min-height', $value)];
    });
    $builder->spacingUtility('max-h', ['--max-height', '--height', '--spacing'], function ($value) {
        return [decl('max-height', $value)];
    });
}
