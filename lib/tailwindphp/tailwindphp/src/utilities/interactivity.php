<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\atRoot;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
/**
 * Interactivity Utilities
 *
 * Port of interactivity utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - cursor
 * - touch-action
 * - user-select
 * - resize
 * - scroll-snap
 * - scroll-margin
 * - scroll-padding
 * - scroll-behavior
 * - pointer-events
 * - appearance
 */
/**
 * Register interactivity utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerInteractivityUtilities(UtilityBuilder $builder): void
{
    $theme = $builder->getTheme();
    // ==================================================
    // Cursor
    // ==================================================
    $cursorValues = ['auto', 'default', 'pointer', 'wait', 'text', 'move', 'help', 'not-allowed', 'none', 'context-menu', 'progress', 'cell', 'crosshair', 'vertical-text', 'alias', 'copy', 'no-drop', 'grab', 'grabbing', 'all-scroll', 'col-resize', 'row-resize', 'n-resize', 'e-resize', 's-resize', 'w-resize', 'ne-resize', 'nw-resize', 'se-resize', 'sw-resize', 'ew-resize', 'ns-resize', 'nesw-resize', 'nwse-resize', 'zoom-in', 'zoom-out'];
    foreach ($cursorValues as $value) {
        $builder->staticUtility("cursor-{$value}", [['cursor', $value]]);
    }
    $builder->functionalUtility('cursor', ['themeKeys' => ['--cursor'], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('cursor', $value)];
    }]);
    // ==================================================
    // Touch Action
    // ==================================================
    foreach (['auto', 'none', 'manipulation'] as $value) {
        $builder->staticUtility("touch-{$value}", [['touch-action', $value]]);
    }
    $touchActionValue = 'var(--tw-pan-x, ) var(--tw-pan-y, ) var(--tw-pinch-zoom, )';
    foreach (['x', 'left', 'right'] as $value) {
        $builder->staticUtility("touch-pan-{$value}", [['--tw-pan-x', "pan-{$value}"], ['touch-action', $touchActionValue]]);
    }
    foreach (['y', 'up', 'down'] as $value) {
        $builder->staticUtility("touch-pan-{$value}", [['--tw-pan-y', "pan-{$value}"], ['touch-action', $touchActionValue]]);
    }
    $builder->staticUtility('touch-pinch-zoom', [['--tw-pinch-zoom', 'pinch-zoom'], ['touch-action', $touchActionValue]]);
    // ==================================================
    // User Select
    // ==================================================
    foreach (['none', 'text', 'all', 'auto'] as $value) {
        $builder->staticUtility("select-{$value}", [['-webkit-user-select', $value], ['user-select', $value]]);
    }
    // ==================================================
    // Resize
    // ==================================================
    $builder->staticUtility('resize-none', [['resize', 'none']]);
    $builder->staticUtility('resize-x', [['resize', 'horizontal']]);
    $builder->staticUtility('resize-y', [['resize', 'vertical']]);
    $builder->staticUtility('resize', [['resize', 'both']]);
    // ==================================================
    // Scroll Snap Type
    // ==================================================
    $builder->staticUtility('snap-none', [['scroll-snap-type', 'none']]);
    foreach (['x', 'y', 'both'] as $value) {
        $builder->staticUtility("snap-{$value}", [['scroll-snap-type', "{$value} var(--tw-scroll-snap-strictness)"]]);
    }
    $builder->staticUtility('snap-mandatory', [['--tw-scroll-snap-strictness', 'mandatory']]);
    $builder->staticUtility('snap-proximity', [['--tw-scroll-snap-strictness', 'proximity']]);
    // ==================================================
    // Scroll Snap Align
    // ==================================================
    $builder->staticUtility('snap-align-none', [['scroll-snap-align', 'none']]);
    $builder->staticUtility('snap-start', [['scroll-snap-align', 'start']]);
    $builder->staticUtility('snap-end', [['scroll-snap-align', 'end']]);
    $builder->staticUtility('snap-center', [['scroll-snap-align', 'center']]);
    // ==================================================
    // Scroll Snap Stop
    // ==================================================
    $builder->staticUtility('snap-normal', [['scroll-snap-stop', 'normal']]);
    $builder->staticUtility('snap-always', [['scroll-snap-stop', 'always']]);
    // ==================================================
    // Scroll Margin
    // ==================================================
    $scrollMarginProps = ['scroll-m' => 'scroll-margin', 'scroll-mx' => 'scroll-margin-inline', 'scroll-my' => 'scroll-margin-block', 'scroll-ms' => 'scroll-margin-inline-start', 'scroll-me' => 'scroll-margin-inline-end', 'scroll-mt' => 'scroll-margin-top', 'scroll-mr' => 'scroll-margin-right', 'scroll-mb' => 'scroll-margin-bottom', 'scroll-ml' => 'scroll-margin-left'];
    foreach ($scrollMarginProps as $namespace => $property) {
        $builder->spacingUtility($namespace, ['--scroll-margin', '--spacing'], function ($value) use ($property) {
            return [decl($property, $value)];
        }, ['supportsNegative' => \true]);
    }
    // ==================================================
    // Scroll Padding
    // ==================================================
    $scrollPaddingProps = ['scroll-p' => 'scroll-padding', 'scroll-px' => 'scroll-padding-inline', 'scroll-py' => 'scroll-padding-block', 'scroll-ps' => 'scroll-padding-inline-start', 'scroll-pe' => 'scroll-padding-inline-end', 'scroll-pt' => 'scroll-padding-top', 'scroll-pr' => 'scroll-padding-right', 'scroll-pb' => 'scroll-padding-bottom', 'scroll-pl' => 'scroll-padding-left'];
    foreach ($scrollPaddingProps as $namespace => $property) {
        $builder->spacingUtility($namespace, ['--scroll-padding', '--spacing'], function ($value) use ($property) {
            return [decl($property, $value)];
        });
    }
    // ==================================================
    // Scroll Behavior
    // ==================================================
    $builder->staticUtility('scroll-auto', [['scroll-behavior', 'auto']]);
    $builder->staticUtility('scroll-smooth', [['scroll-behavior', 'smooth']]);
    // ==================================================
    // Overscroll Behavior
    // ==================================================
    foreach (['auto', 'contain', 'none'] as $value) {
        $builder->staticUtility("overscroll-{$value}", [['overscroll-behavior', $value]]);
        $builder->staticUtility("overscroll-x-{$value}", [['overscroll-behavior-x', $value]]);
        $builder->staticUtility("overscroll-y-{$value}", [['overscroll-behavior-y', $value]]);
    }
    // ==================================================
    // Pointer Events
    // ==================================================
    $builder->staticUtility('pointer-events-none', [['pointer-events', 'none']]);
    $builder->staticUtility('pointer-events-auto', [['pointer-events', 'auto']]);
    // ==================================================
    // Appearance
    // ==================================================
    $builder->staticUtility('appearance-none', [['appearance', 'none']]);
    $builder->staticUtility('appearance-auto', [['appearance', 'auto']]);
    // ==================================================
    // Forced Color Adjust
    // ==================================================
    $builder->staticUtility('forced-color-adjust-auto', [['forced-color-adjust', 'auto']]);
    $builder->staticUtility('forced-color-adjust-none', [['forced-color-adjust', 'none']]);
    // ==================================================
    // Accent Color
    // ==================================================
    $builder->staticUtility('accent-auto', [['accent-color', 'auto']]);
    $builder->colorUtility('accent', ['themeKeys' => ['--accent-color', '--color'], 'handle' => function ($value) {
        return [decl('accent-color', $value)];
    }]);
    // ==================================================
    // Caret Color
    // ==================================================
    $builder->colorUtility('caret', ['themeKeys' => ['--caret-color', '--color'], 'handle' => function ($value) {
        return [decl('caret-color', $value)];
    }]);
    // ==================================================
    // Will Change
    // ==================================================
    $builder->staticUtility('will-change-auto', [['will-change', 'auto']]);
    $builder->staticUtility('will-change-contents', [['will-change', 'contents']]);
    $builder->staticUtility('will-change-transform', [['will-change', 'transform']]);
    $builder->staticUtility('will-change-scroll', [['will-change', 'scroll-position']]);
    $builder->functionalUtility('will-change', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('will-change', $value)];
    }]);
    // ==================================================
    // Content
    // ==================================================
    $builder->staticUtility('content-none', [['--tw-content', 'none'], ['content', 'none']]);
    $builder->functionalUtility('content', ['themeKeys' => ['--content'], 'defaultValue' => null, 'handle' => function ($value) {
        return [atRoot([property('--tw-content', '""')]), decl('--tw-content', $value), decl('content', 'var(--tw-content)')];
    }]);
}
