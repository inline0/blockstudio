<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\atRoot;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Ast\styleRule;
/**
 * Spacing Utilities
 *
 * Port of spacing utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - margin (m, mx, my, mt, mr, mb, ml, ms, me)
 * - padding (p, px, py, pt, pr, pb, pl, ps, pe)
 * - space-x, space-y
 */
/**
 * Register spacing utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerSpacingUtilities(UtilityBuilder $builder): void
{
    // Margin utilities
    $marginProperties = [['m', 'margin'], ['mx', 'margin-inline'], ['my', 'margin-block'], ['mt', 'margin-top'], ['mr', 'margin-right'], ['mb', 'margin-bottom'], ['ml', 'margin-left'], ['ms', 'margin-inline-start'], ['me', 'margin-inline-end']];
    foreach ($marginProperties as [$name, $property]) {
        // Static auto value
        $builder->staticUtility("{$name}-auto", [[$property, 'auto']]);
        // Spacing utility with negative support
        $builder->spacingUtility($name, ['--margin', '--spacing'], function ($value) use ($property) {
            return [decl($property, $value)];
        }, ['supportsNegative' => \true]);
    }
    // Padding utilities
    $paddingProperties = [['p', 'padding'], ['px', 'padding-inline'], ['py', 'padding-block'], ['pt', 'padding-top'], ['pr', 'padding-right'], ['pb', 'padding-bottom'], ['pl', 'padding-left'], ['ps', 'padding-inline-start'], ['pe', 'padding-inline-end']];
    foreach ($paddingProperties as [$name, $property]) {
        $builder->spacingUtility($name, ['--padding', '--spacing'], function ($value) use ($property) {
            return [decl($property, $value)];
        });
    }
    // Space Between utilities
    // space-x-* uses margin-inline-start on all but first child
    // Returns atRoot for property + styleRule with :where selector
    $builder->spacingUtility('space-x', ['--space', '--spacing'], function ($value) {
        return [atRoot([property('--tw-space-x-reverse', '0')]), styleRule(':where(& > :not(:last-child))', [decl('--tw-sort', 'row-gap'), decl('--tw-space-x-reverse', '0'), decl('margin-inline-start', "calc({$value} * var(--tw-space-x-reverse))"), decl('margin-inline-end', "calc({$value} * calc(1 - var(--tw-space-x-reverse)))")])];
    }, ['supportsNegative' => \true]);
    // space-y-* uses margin-block-start on all but first child
    $builder->spacingUtility('space-y', ['--space', '--spacing'], function ($value) {
        return [atRoot([property('--tw-space-y-reverse', '0')]), styleRule(':where(& > :not(:last-child))', [decl('--tw-sort', 'column-gap'), decl('--tw-space-y-reverse', '0'), decl('margin-block-start', "calc({$value} * var(--tw-space-y-reverse))"), decl('margin-block-end', "calc({$value} * calc(1 - var(--tw-space-y-reverse)))")])];
    }, ['supportsNegative' => \true]);
    // Space reverse utilities - use :where(& > :not(:last-child)) selector
    $builder->staticUtility('space-x-reverse', [fn() => atRoot([property('--tw-space-x-reverse', '0')]), fn() => styleRule(':where(& > :not(:last-child))', [decl('--tw-sort', 'row-gap'), decl('--tw-space-x-reverse', '1')])]);
    $builder->staticUtility('space-y-reverse', [fn() => atRoot([property('--tw-space-y-reverse', '0')]), fn() => styleRule(':where(& > :not(:last-child))', [decl('--tw-sort', 'column-gap'), decl('--tw-space-y-reverse', '1')])]);
}
