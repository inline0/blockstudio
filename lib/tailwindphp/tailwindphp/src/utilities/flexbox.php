<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
/**
 * Flexbox & Grid Utilities
 *
 * Port of flexbox/grid utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - flex-direction (flex-row, flex-col, etc.)
 * - flex-wrap
 * - flex (flex-1, flex-auto, flex-none, etc.)
 * - flex-grow (grow, grow-0)
 * - flex-shrink (shrink, shrink-0)
 * - flex-basis
 * - grid-flow
 * - grid-cols
 * - grid-rows
 * - col-span, row-span
 * - auto-cols, auto-rows
 * - justify-content, align-items, etc.
 * - gap
 */
/**
 * Register flexbox and grid utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerFlexboxUtilities(UtilityBuilder $builder): void
{
    // Flex Direction
    $builder->staticUtility('flex-row', [['flex-direction', 'row']]);
    $builder->staticUtility('flex-row-reverse', [['flex-direction', 'row-reverse']]);
    $builder->staticUtility('flex-col', [['flex-direction', 'column']]);
    $builder->staticUtility('flex-col-reverse', [['flex-direction', 'column-reverse']]);
    // Flex Wrap
    $builder->staticUtility('flex-wrap', [['flex-wrap', 'wrap']]);
    $builder->staticUtility('flex-wrap-reverse', [['flex-wrap', 'wrap-reverse']]);
    $builder->staticUtility('flex-nowrap', [['flex-wrap', 'nowrap']]);
    // Flex (shorthand)
    $builder->functionalUtility('flex', ['handleBareValue' => function ($value) {
        // Handle fractions like flex-1/2 -> 50% (check first, before bare integers)
        if (isset($value['fraction'])) {
            $parts = explode('/', $value['fraction']);
            if (count($parts) === 2 && isPositiveInteger($parts[0]) && isPositiveInteger($parts[1])) {
                $percent = (int) $parts[0] / (int) $parts[1] * 100;
                return $percent . '%';
            }
        }
        // Handle bare integers like flex-1, flex-99
        if (isPositiveInteger($value['value'])) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => ['--flex'], 'handle' => function ($value, $dataType) {
        return [decl('flex', $value)];
    }, 'staticValues' => ['auto' => [decl('flex', 'auto')], 'initial' => [decl('flex', '0 auto')], 'none' => [decl('flex', 'none')]]]);
    // Flex Grow
    $builder->functionalUtility('grow', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value'])) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => ['--flex-grow'], 'defaultValue' => '1', 'handle' => function ($value, $dataType) {
        return [decl('flex-grow', $value)];
    }]);
    // Flex Shrink
    $builder->functionalUtility('shrink', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value'])) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => ['--flex-shrink'], 'defaultValue' => '1', 'handle' => function ($value, $dataType) {
        return [decl('flex-shrink', $value)];
    }]);
    // Flex Basis - use spacingUtility like Tailwind does
    $builder->staticUtility('basis-auto', [['flex-basis', 'auto']]);
    $builder->staticUtility('basis-full', [['flex-basis', '100%']]);
    $builder->spacingUtility('basis', ['--flex-basis', '--spacing', '--container'], function ($value) {
        return [decl('flex-basis', $value)];
    }, ['supportsFractions' => \true]);
    // Grid Auto Flow
    $builder->staticUtility('grid-flow-row', [['grid-auto-flow', 'row']]);
    $builder->staticUtility('grid-flow-col', [['grid-auto-flow', 'column']]);
    $builder->staticUtility('grid-flow-dense', [['grid-auto-flow', 'dense']]);
    $builder->staticUtility('grid-flow-row-dense', [['grid-auto-flow', 'row dense']]);
    $builder->staticUtility('grid-flow-col-dense', [['grid-auto-flow', 'column dense']]);
    // Grid Template Columns
    $builder->functionalUtility('grid-cols', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value']) && (int) $value['value'] > 0) {
            return "repeat({$value['value']}, minmax(0, 1fr))";
        }
        return null;
    }, 'themeKeys' => ['--grid-template-columns'], 'handle' => function ($value, $dataType) {
        return [decl('grid-template-columns', $value)];
    }, 'staticValues' => ['none' => [decl('grid-template-columns', 'none')], 'subgrid' => [decl('grid-template-columns', 'subgrid')]]]);
    // Grid Template Rows
    $builder->functionalUtility('grid-rows', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value']) && (int) $value['value'] > 0) {
            return "repeat({$value['value']}, minmax(0, 1fr))";
        }
        return null;
    }, 'themeKeys' => ['--grid-template-rows'], 'handle' => function ($value, $dataType) {
        return [decl('grid-template-rows', $value)];
    }, 'staticValues' => ['none' => [decl('grid-template-rows', 'none')], 'subgrid' => [decl('grid-template-rows', 'subgrid')]]]);
    // Grid Column (col-{n} for position, col-auto)
    $builder->functionalUtility('col', ['supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value']) && (int) $value['value'] > 0) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => ['--grid-column'], 'handle' => function ($value, $dataType) {
        return [decl('grid-column', $value)];
    }, 'staticValues' => ['auto' => [decl('grid-column', 'auto')]]]);
    // Grid Column Span (col-span-{n})
    $builder->functionalUtility('col-span', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value']) && (int) $value['value'] > 0) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => [], 'handle' => function ($value, $dataType) {
        return [decl('grid-column', "span {$value} / span {$value}")];
    }, 'staticValues' => ['full' => [decl('grid-column', '1 / -1')]]]);
    // Grid Row (row-{n} for position, row-auto)
    $builder->functionalUtility('row', ['supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value']) && (int) $value['value'] > 0) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => ['--grid-row'], 'handle' => function ($value, $dataType) {
        return [decl('grid-row', $value)];
    }, 'staticValues' => ['auto' => [decl('grid-row', 'auto')]]]);
    // Grid Row Span (row-span-{n})
    $builder->functionalUtility('row-span', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value']) && (int) $value['value'] > 0) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => [], 'handle' => function ($value, $dataType) {
        return [decl('grid-row', "span {$value} / span {$value}")];
    }, 'staticValues' => ['full' => [decl('grid-row', '1 / -1')]]]);
    // Col Start/End
    $builder->functionalUtility('col-start', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value'])) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => ['--grid-column-start'], 'handle' => function ($value, $dataType) {
        return [decl('grid-column-start', $value)];
    }, 'staticValues' => ['auto' => [decl('grid-column-start', 'auto')]]]);
    $builder->functionalUtility('col-end', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value'])) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => ['--grid-column-end'], 'handle' => function ($value, $dataType) {
        return [decl('grid-column-end', $value)];
    }, 'staticValues' => ['auto' => [decl('grid-column-end', 'auto')]]]);
    // Row Start/End
    $builder->functionalUtility('row-start', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value'])) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => ['--grid-row-start'], 'handle' => function ($value, $dataType) {
        return [decl('grid-row-start', $value)];
    }, 'staticValues' => ['auto' => [decl('grid-row-start', 'auto')]]]);
    $builder->functionalUtility('row-end', ['handleBareValue' => function ($value) {
        if (isPositiveInteger($value['value'])) {
            return $value['value'];
        }
        return null;
    }, 'themeKeys' => ['--grid-row-end'], 'handle' => function ($value, $dataType) {
        return [decl('grid-row-end', $value)];
    }, 'staticValues' => ['auto' => [decl('grid-row-end', 'auto')]]]);
    // Auto Columns
    $builder->functionalUtility('auto-cols', ['themeKeys' => ['--grid-auto-columns'], 'handle' => function ($value, $dataType) {
        return [decl('grid-auto-columns', $value)];
    }, 'staticValues' => ['auto' => [decl('grid-auto-columns', 'auto')], 'min' => [decl('grid-auto-columns', 'min-content')], 'max' => [decl('grid-auto-columns', 'max-content')], 'fr' => [decl('grid-auto-columns', 'minmax(0, 1fr)')]]]);
    // Auto Rows
    $builder->functionalUtility('auto-rows', ['themeKeys' => ['--grid-auto-rows'], 'handle' => function ($value, $dataType) {
        return [decl('grid-auto-rows', $value)];
    }, 'staticValues' => ['auto' => [decl('grid-auto-rows', 'auto')], 'min' => [decl('grid-auto-rows', 'min-content')], 'max' => [decl('grid-auto-rows', 'max-content')], 'fr' => [decl('grid-auto-rows', 'minmax(0, 1fr)')]]]);
    // Gap
    $builder->spacingUtility('gap', ['--gap', '--spacing'], function ($value) {
        return [decl('gap', $value)];
    });
    $builder->spacingUtility('gap-x', ['--gap', '--spacing'], function ($value) {
        return [decl('column-gap', $value)];
    });
    $builder->spacingUtility('gap-y', ['--gap', '--spacing'], function ($value) {
        return [decl('row-gap', $value)];
    });
    // Justify Content
    $builder->staticUtility('justify-normal', [['justify-content', 'normal']]);
    $builder->staticUtility('justify-start', [['justify-content', 'flex-start']]);
    $builder->staticUtility('justify-end', [['justify-content', 'flex-end']]);
    $builder->staticUtility('justify-end-safe', [['justify-content', 'safe flex-end']]);
    $builder->staticUtility('justify-center', [['justify-content', 'center']]);
    $builder->staticUtility('justify-center-safe', [['justify-content', 'safe center']]);
    $builder->staticUtility('justify-between', [['justify-content', 'space-between']]);
    $builder->staticUtility('justify-around', [['justify-content', 'space-around']]);
    $builder->staticUtility('justify-evenly', [['justify-content', 'space-evenly']]);
    $builder->staticUtility('justify-stretch', [['justify-content', 'stretch']]);
    // Justify Items
    $builder->staticUtility('justify-items-start', [['justify-items', 'start']]);
    $builder->staticUtility('justify-items-end', [['justify-items', 'end']]);
    $builder->staticUtility('justify-items-end-safe', [['justify-items', 'safe end']]);
    $builder->staticUtility('justify-items-center', [['justify-items', 'center']]);
    $builder->staticUtility('justify-items-center-safe', [['justify-items', 'safe center']]);
    $builder->staticUtility('justify-items-stretch', [['justify-items', 'stretch']]);
    $builder->staticUtility('justify-items-normal', [['justify-items', 'normal']]);
    // Justify Self
    $builder->staticUtility('justify-self-auto', [['justify-self', 'auto']]);
    $builder->staticUtility('justify-self-start', [['justify-self', 'flex-start']]);
    $builder->staticUtility('justify-self-end', [['justify-self', 'flex-end']]);
    $builder->staticUtility('justify-self-end-safe', [['justify-self', 'safe flex-end']]);
    $builder->staticUtility('justify-self-center', [['justify-self', 'center']]);
    $builder->staticUtility('justify-self-center-safe', [['justify-self', 'safe center']]);
    $builder->staticUtility('justify-self-stretch', [['justify-self', 'stretch']]);
    // Align Content
    $builder->staticUtility('content-normal', [['align-content', 'normal']]);
    $builder->staticUtility('content-start', [['align-content', 'flex-start']]);
    $builder->staticUtility('content-end', [['align-content', 'flex-end']]);
    $builder->staticUtility('content-end-safe', [['align-content', 'safe flex-end']]);
    $builder->staticUtility('content-center', [['align-content', 'center']]);
    $builder->staticUtility('content-center-safe', [['align-content', 'safe center']]);
    $builder->staticUtility('content-between', [['align-content', 'space-between']]);
    $builder->staticUtility('content-around', [['align-content', 'space-around']]);
    $builder->staticUtility('content-evenly', [['align-content', 'space-evenly']]);
    $builder->staticUtility('content-baseline', [['align-content', 'baseline']]);
    $builder->staticUtility('content-stretch', [['align-content', 'stretch']]);
    // Align Items
    $builder->staticUtility('items-start', [['align-items', 'flex-start']]);
    $builder->staticUtility('items-end', [['align-items', 'flex-end']]);
    $builder->staticUtility('items-end-safe', [['align-items', 'safe flex-end']]);
    $builder->staticUtility('items-center', [['align-items', 'center']]);
    $builder->staticUtility('items-center-safe', [['align-items', 'safe center']]);
    $builder->staticUtility('items-baseline', [['align-items', 'baseline']]);
    $builder->staticUtility('items-baseline-last', [['align-items', 'last baseline']]);
    $builder->staticUtility('items-stretch', [['align-items', 'stretch']]);
    // Align Self
    $builder->staticUtility('self-auto', [['align-self', 'auto']]);
    $builder->staticUtility('self-start', [['align-self', 'flex-start']]);
    $builder->staticUtility('self-end', [['align-self', 'flex-end']]);
    $builder->staticUtility('self-end-safe', [['align-self', 'safe flex-end']]);
    $builder->staticUtility('self-center', [['align-self', 'center']]);
    $builder->staticUtility('self-center-safe', [['align-self', 'safe center']]);
    $builder->staticUtility('self-stretch', [['align-self', 'stretch']]);
    $builder->staticUtility('self-baseline', [['align-self', 'baseline']]);
    $builder->staticUtility('self-baseline-last', [['align-self', 'last baseline']]);
    // Place Content
    $builder->staticUtility('place-content-center', [['place-content', 'center']]);
    $builder->staticUtility('place-content-center-safe', [['place-content', 'safe center']]);
    $builder->staticUtility('place-content-start', [['place-content', 'start']]);
    $builder->staticUtility('place-content-end', [['place-content', 'end']]);
    $builder->staticUtility('place-content-end-safe', [['place-content', 'safe end']]);
    $builder->staticUtility('place-content-between', [['place-content', 'space-between']]);
    $builder->staticUtility('place-content-around', [['place-content', 'space-around']]);
    $builder->staticUtility('place-content-evenly', [['place-content', 'space-evenly']]);
    $builder->staticUtility('place-content-baseline', [['place-content', 'baseline start']]);
    $builder->staticUtility('place-content-stretch', [['place-content', 'stretch']]);
    // Place Items
    $builder->staticUtility('place-items-start', [['place-items', 'start']]);
    $builder->staticUtility('place-items-end', [['place-items', 'end']]);
    $builder->staticUtility('place-items-end-safe', [['place-items', 'safe end']]);
    $builder->staticUtility('place-items-center', [['place-items', 'center']]);
    $builder->staticUtility('place-items-center-safe', [['place-items', 'safe center']]);
    $builder->staticUtility('place-items-baseline', [['place-items', 'baseline']]);
    $builder->staticUtility('place-items-stretch', [['place-items', 'stretch stretch']]);
    // Place Self
    $builder->staticUtility('place-self-auto', [['place-self', 'auto']]);
    $builder->staticUtility('place-self-start', [['place-self', 'start']]);
    $builder->staticUtility('place-self-end', [['place-self', 'end']]);
    $builder->staticUtility('place-self-end-safe', [['place-self', 'safe end']]);
    $builder->staticUtility('place-self-center', [['place-self', 'center']]);
    $builder->staticUtility('place-self-center-safe', [['place-self', 'safe center']]);
    $builder->staticUtility('place-self-stretch', [['place-self', 'stretch stretch']]);
}
