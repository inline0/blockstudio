<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
/**
 * Layout Utilities
 *
 * Port of layout utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - visibility
 * - position
 * - inset (top, right, bottom, left, inset-x, inset-y, start, end)
 * - isolation
 * - z-index
 * - order
 * - float
 * - clear
 * - box-sizing
 * - display
 * - aspect-ratio
 * - columns
 * - break-before, break-inside, break-after
 * - box-decoration-break
 * - overflow
 * - overscroll-behavior
 * - scroll-behavior
 * - object-fit
 * - object-position
 *
 * Note: pointer-events is in interactivity.php
 */
/**
 * Register layout utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerLayoutUtilities(UtilityBuilder $builder): void
{
    // Visibility
    $builder->staticUtility('visible', [['visibility', 'visible']]);
    $builder->staticUtility('invisible', [['visibility', 'hidden']]);
    $builder->staticUtility('collapse', [['visibility', 'collapse']]);
    // Position
    $builder->staticUtility('static', [['position', 'static']]);
    $builder->staticUtility('fixed', [['position', 'fixed']]);
    $builder->staticUtility('absolute', [['position', 'absolute']]);
    $builder->staticUtility('relative', [['position', 'relative']]);
    $builder->staticUtility('sticky', [['position', 'sticky']]);
    // Inset utilities (top, right, bottom, left, inset, inset-x, inset-y, start, end)
    $insetProperties = [['inset', 'inset'], ['inset-x', 'inset-inline'], ['inset-y', 'inset-block'], ['start', 'inset-inline-start'], ['end', 'inset-inline-end'], ['top', 'top'], ['right', 'right'], ['bottom', 'bottom'], ['left', 'left']];
    foreach ($insetProperties as [$name, $property]) {
        $builder->staticUtility("{$name}-auto", [[$property, 'auto']]);
        $builder->staticUtility("{$name}-full", [[$property, '100%']]);
        $builder->staticUtility("-{$name}-full", [[$property, '-100%']]);
        $builder->spacingUtility($name, ['--inset', '--spacing'], function ($value) use ($property) {
            return [decl($property, $value)];
        }, ['supportsNegative' => \true, 'supportsFractions' => \true]);
    }
    // Isolation
    $builder->staticUtility('isolate', [['isolation', 'isolate']]);
    $builder->staticUtility('isolation-auto', [['isolation', 'auto']]);
    // Z-Index
    $builder->functionalUtility('z', ['supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return $value['value'];
    }, 'themeKeys' => ['--z-index'], 'handle' => function ($value, $dataType) {
        return [decl('z-index', $value)];
    }, 'staticValues' => ['auto' => [decl('z-index', 'auto')]]]);
    $builder->suggest('z', fn() => [['supportsNegative' => \true, 'values' => ['0', '10', '20', '30', '40', '50'], 'valueThemeKeys' => ['--z-index']]]);
    // Order
    $builder->functionalUtility('order', ['supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return $value['value'];
    }, 'themeKeys' => ['--order'], 'handle' => function ($value, $dataType) {
        return [decl('order', $value)];
    }, 'staticValues' => ['first' => [decl('order', '-9999')], 'last' => [decl('order', '9999')], 'none' => [decl('order', '0')]]]);
    $builder->suggest('order', fn() => [['supportsNegative' => \true, 'values' => array_map(fn($i) => (string) ($i + 1), range(0, 11)), 'valueThemeKeys' => ['--order']]]);
    // Float
    $builder->staticUtility('float-start', [['float', 'inline-start']]);
    $builder->staticUtility('float-end', [['float', 'inline-end']]);
    $builder->staticUtility('float-right', [['float', 'right']]);
    $builder->staticUtility('float-left', [['float', 'left']]);
    $builder->staticUtility('float-none', [['float', 'none']]);
    // Clear
    $builder->staticUtility('clear-start', [['clear', 'inline-start']]);
    $builder->staticUtility('clear-end', [['clear', 'inline-end']]);
    $builder->staticUtility('clear-right', [['clear', 'right']]);
    $builder->staticUtility('clear-left', [['clear', 'left']]);
    $builder->staticUtility('clear-both', [['clear', 'both']]);
    $builder->staticUtility('clear-none', [['clear', 'none']]);
    // Box Sizing
    $builder->staticUtility('box-border', [['box-sizing', 'border-box']]);
    $builder->staticUtility('box-content', [['box-sizing', 'content-box']]);
    // Line Clamp
    $builder->functionalUtility('line-clamp', ['handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return $value['value'];
    }, 'themeKeys' => ['--line-clamp'], 'handle' => function ($value, $dataType) {
        return [decl('overflow', 'hidden'), decl('display', '-webkit-box'), decl('-webkit-box-orient', 'vertical'), decl('-webkit-line-clamp', $value)];
    }, 'staticValues' => ['none' => [decl('overflow', 'visible'), decl('display', 'block'), decl('-webkit-box-orient', 'horizontal'), decl('-webkit-line-clamp', 'unset')]]]);
    $builder->suggest('line-clamp', fn() => [['values' => array_map(fn($i) => (string) ($i + 1), range(0, 5)), 'valueThemeKeys' => ['--line-clamp']]]);
    // Display
    $builder->staticUtility('block', [['display', 'block']]);
    $builder->staticUtility('inline-block', [['display', 'inline-block']]);
    $builder->staticUtility('inline', [['display', 'inline']]);
    $builder->staticUtility('hidden', [['display', 'none']]);
    $builder->staticUtility('inline-flex', [['display', 'inline-flex']]);
    $builder->staticUtility('table', [['display', 'table']]);
    $builder->staticUtility('inline-table', [['display', 'inline-table']]);
    $builder->staticUtility('table-caption', [['display', 'table-caption']]);
    $builder->staticUtility('table-cell', [['display', 'table-cell']]);
    $builder->staticUtility('table-column', [['display', 'table-column']]);
    $builder->staticUtility('table-column-group', [['display', 'table-column-group']]);
    $builder->staticUtility('table-footer-group', [['display', 'table-footer-group']]);
    $builder->staticUtility('table-header-group', [['display', 'table-header-group']]);
    $builder->staticUtility('table-row-group', [['display', 'table-row-group']]);
    $builder->staticUtility('table-row', [['display', 'table-row']]);
    $builder->staticUtility('flow-root', [['display', 'flow-root']]);
    $builder->staticUtility('flex', [['display', 'flex']]);
    $builder->staticUtility('grid', [['display', 'grid']]);
    $builder->staticUtility('inline-grid', [['display', 'inline-grid']]);
    $builder->staticUtility('contents', [['display', 'contents']]);
    $builder->staticUtility('list-item', [['display', 'list-item']]);
    // Field Sizing
    $builder->staticUtility('field-sizing-content', [['field-sizing', 'content']]);
    $builder->staticUtility('field-sizing-fixed', [['field-sizing', 'fixed']]);
    // Color Scheme
    $builder->staticUtility('scheme-normal', [['color-scheme', 'normal']]);
    $builder->staticUtility('scheme-dark', [['color-scheme', 'dark']]);
    $builder->staticUtility('scheme-light', [['color-scheme', 'light']]);
    $builder->staticUtility('scheme-light-dark', [['color-scheme', 'light dark']]);
    $builder->staticUtility('scheme-only-dark', [['color-scheme', 'dark only']]);
    $builder->staticUtility('scheme-only-light', [['color-scheme', 'light only']]);
    // Contain
    $containVar = 'var(--tw-contain-size, ) var(--tw-contain-layout, ) var(--tw-contain-paint, ) var(--tw-contain-style, )';
    $builder->staticUtility('contain-none', [['contain', 'none']]);
    $builder->staticUtility('contain-content', [['contain', 'content']]);
    $builder->staticUtility('contain-strict', [['contain', 'strict']]);
    $builder->staticUtility('contain-size', [['--tw-contain-size', 'size'], ['contain', $containVar]]);
    $builder->staticUtility('contain-inline-size', [['--tw-contain-size', 'inline-size'], ['contain', $containVar]]);
    $builder->staticUtility('contain-layout', [['--tw-contain-layout', 'layout'], ['contain', $containVar]]);
    $builder->staticUtility('contain-paint', [['--tw-contain-paint', 'paint'], ['contain', $containVar]]);
    $builder->staticUtility('contain-style', [['--tw-contain-style', 'style'], ['contain', $containVar]]);
    $builder->functionalUtility('contain', ['themeKeys' => [], 'handle' => function ($value) {
        return [decl('contain', $value)];
    }]);
    // Aspect Ratio
    $builder->functionalUtility('aspect', ['themeKeys' => ['--aspect-ratio'], 'handleBareValue' => function ($value) {
        // Handle fractions like 4/3
        $fraction = $value['fraction'] ?? null;
        if ($fraction !== null) {
            $parts = \BlockstudioVendor\TailwindPHP\Utils\segment($fraction, '/');
            if (count($parts) === 2 && isPositiveInteger($parts[0]) && isPositiveInteger($parts[1])) {
                return $fraction;
            }
        }
        return null;
    }, 'handle' => function ($value, $dataType) {
        return [decl('aspect-ratio', $value)];
    }, 'staticValues' => ['auto' => [decl('aspect-ratio', 'auto')], 'square' => [decl('aspect-ratio', '1 / 1')], 'video' => [decl('aspect-ratio', '16 / 9')]]]);
    // Columns
    $builder->functionalUtility('columns', ['handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return $value['value'];
    }, 'themeKeys' => ['--columns', '--width'], 'handle' => function ($value, $dataType) {
        return [decl('columns', $value)];
    }, 'staticValues' => ['auto' => [decl('columns', 'auto')], '3xs' => [decl('columns', '16rem')], '2xs' => [decl('columns', '18rem')], 'xs' => [decl('columns', '20rem')], 'sm' => [decl('columns', '24rem')], 'md' => [decl('columns', '28rem')], 'lg' => [decl('columns', '32rem')], 'xl' => [decl('columns', '36rem')], '2xl' => [decl('columns', '42rem')], '3xl' => [decl('columns', '48rem')], '4xl' => [decl('columns', '56rem')], '5xl' => [decl('columns', '64rem')], '6xl' => [decl('columns', '72rem')], '7xl' => [decl('columns', '80rem')]]]);
    $builder->suggest('columns', fn() => [['values' => array_map(fn($i) => (string) ($i + 1), range(0, 11)), 'valueThemeKeys' => ['--columns', '--width']]]);
    // Break Before
    $builder->staticUtility('break-before-auto', [['break-before', 'auto']]);
    $builder->staticUtility('break-before-avoid', [['break-before', 'avoid']]);
    $builder->staticUtility('break-before-all', [['break-before', 'all']]);
    $builder->staticUtility('break-before-avoid-page', [['break-before', 'avoid-page']]);
    $builder->staticUtility('break-before-page', [['break-before', 'page']]);
    $builder->staticUtility('break-before-left', [['break-before', 'left']]);
    $builder->staticUtility('break-before-right', [['break-before', 'right']]);
    $builder->staticUtility('break-before-column', [['break-before', 'column']]);
    // Break Inside
    $builder->staticUtility('break-inside-auto', [['break-inside', 'auto']]);
    $builder->staticUtility('break-inside-avoid', [['break-inside', 'avoid']]);
    $builder->staticUtility('break-inside-avoid-page', [['break-inside', 'avoid-page']]);
    $builder->staticUtility('break-inside-avoid-column', [['break-inside', 'avoid-column']]);
    // Break After
    $builder->staticUtility('break-after-auto', [['break-after', 'auto']]);
    $builder->staticUtility('break-after-avoid', [['break-after', 'avoid']]);
    $builder->staticUtility('break-after-all', [['break-after', 'all']]);
    $builder->staticUtility('break-after-avoid-page', [['break-after', 'avoid-page']]);
    $builder->staticUtility('break-after-page', [['break-after', 'page']]);
    $builder->staticUtility('break-after-left', [['break-after', 'left']]);
    $builder->staticUtility('break-after-right', [['break-after', 'right']]);
    $builder->staticUtility('break-after-column', [['break-after', 'column']]);
    // Box Decoration Break
    $builder->staticUtility('box-decoration-clone', [['-webkit-box-decoration-break', 'clone'], ['box-decoration-break', 'clone']]);
    $builder->staticUtility('box-decoration-slice', [['-webkit-box-decoration-break', 'slice'], ['box-decoration-break', 'slice']]);
    // Overflow
    $overflowValues = ['auto', 'hidden', 'clip', 'visible', 'scroll'];
    foreach ($overflowValues as $val) {
        $builder->staticUtility("overflow-{$val}", [['overflow', $val]]);
        $builder->staticUtility("overflow-x-{$val}", [['overflow-x', $val]]);
        $builder->staticUtility("overflow-y-{$val}", [['overflow-y', $val]]);
    }
    // Overflow Wrap
    $builder->staticUtility('wrap-anywhere', [['overflow-wrap', 'anywhere']]);
    $builder->staticUtility('wrap-break-word', [['overflow-wrap', 'break-word']]);
    $builder->staticUtility('wrap-normal', [['overflow-wrap', 'normal']]);
    // Overscroll Behavior
    $overscrollValues = ['auto', 'contain', 'none'];
    foreach ($overscrollValues as $val) {
        $builder->staticUtility("overscroll-{$val}", [['overscroll-behavior', $val]]);
        $builder->staticUtility("overscroll-x-{$val}", [['overscroll-behavior-x', $val]]);
        $builder->staticUtility("overscroll-y-{$val}", [['overscroll-behavior-y', $val]]);
    }
    // Scroll Behavior
    $builder->staticUtility('scroll-auto', [['scroll-behavior', 'auto']]);
    $builder->staticUtility('scroll-smooth', [['scroll-behavior', 'smooth']]);
    // Object Fit
    $builder->staticUtility('object-contain', [['object-fit', 'contain']]);
    $builder->staticUtility('object-cover', [['object-fit', 'cover']]);
    $builder->staticUtility('object-fill', [['object-fit', 'fill']]);
    $builder->staticUtility('object-none', [['object-fit', 'none']]);
    $builder->staticUtility('object-scale-down', [['object-fit', 'scale-down']]);
    // Object Position
    $objectPositions = ['bottom' => 'bottom', 'bottom-left' => 'left bottom', 'bottom-right' => 'right bottom', 'center' => 'center', 'left' => 'left', 'left-bottom' => 'left bottom', 'left-top' => 'left top', 'right' => 'right', 'right-bottom' => 'right bottom', 'right-top' => 'right top', 'top' => 'top', 'top-left' => 'left top', 'top-right' => 'right top'];
    foreach ($objectPositions as $name => $value) {
        $builder->staticUtility("object-{$name}", [['object-position', $value]]);
    }
    $builder->functionalUtility('object', ['themeKeys' => ['--object-position'], 'handle' => function ($value, $dataType) {
        return [decl('object-position', $value)];
    }]);
    // Container Queries (@container)
    // @container -> container-type: inline-size
    // @container-normal -> container-type: normal
    // @container-size -> container-type: size
    // @container/name -> container: name / inline-size
    // @container-normal/name -> container: name
    // @container-size/name -> container: name / size
    // Register directly with utilities to bypass functionalUtility's modifier rejection
    $builder->getUtilities()->functional('@container', function ($candidate) {
        $containerType = 'inline-size';
        // default
        if ($candidate['value'] === null) {
            $containerType = 'inline-size';
        } elseif ($candidate['value']['kind'] === 'named') {
            if ($candidate['value']['value'] === 'normal') {
                $containerType = 'normal';
            } elseif ($candidate['value']['value'] === 'size') {
                $containerType = 'size';
            } else {
                return null;
                // Invalid value
            }
        } elseif ($candidate['value']['kind'] === 'arbitrary') {
            $containerType = $candidate['value']['value'];
        }
        // Check for modifier (container name)
        if (isset($candidate['modifier']) && $candidate['modifier'] !== null) {
            $containerName = $candidate['modifier']['value'];
            // Use container shorthand property
            if ($containerType === 'normal') {
                // For normal, just the name without type
                return [decl('container', $containerName)];
            } else {
                return [decl('container', "{$containerName} / {$containerType}")];
            }
        }
        return [decl('container-type', $containerType)];
    });
}
