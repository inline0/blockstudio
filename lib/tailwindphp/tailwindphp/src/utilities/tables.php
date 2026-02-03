<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\decl;
/**
 * Tables Utilities
 *
 * Port of table utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - table-layout (table-auto, table-fixed)
 * - caption-side (caption-top, caption-bottom)
 * - border-collapse (border-collapse, border-separate)
 * - border-spacing (border-spacing-*, border-spacing-x-*, border-spacing-y-*)
 */
/**
 * Register tables utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerTablesUtilities(UtilityBuilder $builder): void
{
    // ==================================================
    // Table Layout
    // ==================================================
    $builder->staticUtility('table-auto', [['table-layout', 'auto']]);
    $builder->staticUtility('table-fixed', [['table-layout', 'fixed']]);
    // ==================================================
    // Caption Side
    // ==================================================
    $builder->staticUtility('caption-top', [['caption-side', 'top']]);
    $builder->staticUtility('caption-bottom', [['caption-side', 'bottom']]);
    // ==================================================
    // Border Collapse
    // ==================================================
    $builder->staticUtility('border-collapse', [['border-collapse', 'collapse']]);
    $builder->staticUtility('border-separate', [['border-collapse', 'separate']]);
    // ==================================================
    // Border Spacing
    // ==================================================
    // border-spacing-*
    $builder->spacingUtility('border-spacing', ['--border-spacing', '--spacing'], function ($value) {
        return [decl('--tw-border-spacing-x', $value), decl('--tw-border-spacing-y', $value), decl('border-spacing', 'var(--tw-border-spacing-x) var(--tw-border-spacing-y)')];
    });
    // border-spacing-x-*
    $builder->spacingUtility('border-spacing-x', ['--border-spacing', '--spacing'], function ($value) {
        return [decl('--tw-border-spacing-x', $value), decl('border-spacing', 'var(--tw-border-spacing-x) var(--tw-border-spacing-y)')];
    });
    // border-spacing-y-*
    $builder->spacingUtility('border-spacing-y', ['--border-spacing', '--spacing'], function ($value) {
        return [decl('--tw-border-spacing-y', $value), decl('border-spacing', 'var(--tw-border-spacing-x) var(--tw-border-spacing-y)')];
    });
}
