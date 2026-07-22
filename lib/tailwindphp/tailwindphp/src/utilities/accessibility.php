<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

/**
 * Accessibility Utilities
 *
 * Port of accessibility utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - sr-only: Visually hidden but accessible to screen readers
 * - not-sr-only: Undo sr-only
 */
/**
 * Register accessibility utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerAccessibilityUtilities(UtilityBuilder $builder): void
{
    // Screen reader only - visually hidden but accessible
    $builder->staticUtility('sr-only', [['position', 'absolute'], ['width', '1px'], ['height', '1px'], ['padding', '0'], ['margin', '-1px'], ['overflow', 'hidden'], ['clip-path', 'inset(50%)'], ['white-space', 'nowrap'], ['border-width', '0']]);
    // Not screen reader only - undo sr-only
    $builder->staticUtility('not-sr-only', [['position', 'static'], ['width', 'auto'], ['height', 'auto'], ['padding', '0'], ['margin', '0'], ['overflow', 'visible'], ['clip-path', 'none'], ['white-space', 'normal']]);
}
