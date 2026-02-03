<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\atRoot;
use function BlockstudioVendor\TailwindPHP\Ast\atRule;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Ast\styleRule;
use function BlockstudioVendor\TailwindPHP\Utils\inferDataType;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
/**
 * Border Utilities
 *
 * Port of border utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - border-radius (rounded-*)
 * - border-width (border-*)
 * - border-style (border-solid, border-dashed, etc.)
 * - border-collapse
 * - outline
 */
// Very large number for "full" radius when --radius-full is not defined
const RADIUS_FULL_DEFAULT = '3.40282e38px';
/**
 * Register border utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerBorderUtilities(UtilityBuilder $builder): void
{
    // ==================================================
    // Border Radius
    // ==================================================
    // Helper function to create radius utility
    $createRadiusUtility = function (string $name, array $properties, bool $hasDefault = \false) use ($builder) {
        $builder->functionalUtility($name, ['themeKeys' => ['--radius'], 'defaultValue' => $hasDefault ? 'var(--radius)' : null, 'handle' => function ($value) use ($properties) {
            $decls = [];
            foreach ($properties as $prop) {
                $decls[] = decl($prop, $value);
            }
            return $decls;
        }, 'staticValues' => ['none' => array_map(fn($p) => decl($p, '0'), $properties), 'full' => array_map(fn($p) => decl($p, RADIUS_FULL_DEFAULT), $properties)]]);
    };
    // rounded (all corners)
    $createRadiusUtility('rounded', ['border-radius'], \true);
    // rounded-s (start corners: top-left and bottom-left in LTR)
    $createRadiusUtility('rounded-s', ['border-start-start-radius', 'border-end-start-radius'], \true);
    // rounded-e (end corners)
    $createRadiusUtility('rounded-e', ['border-start-end-radius', 'border-end-end-radius'], \true);
    // rounded-t (top corners)
    $createRadiusUtility('rounded-t', ['border-top-left-radius', 'border-top-right-radius'], \true);
    // rounded-r (right corners)
    $createRadiusUtility('rounded-r', ['border-top-right-radius', 'border-bottom-right-radius'], \true);
    // rounded-b (bottom corners)
    $createRadiusUtility('rounded-b', ['border-bottom-right-radius', 'border-bottom-left-radius'], \true);
    // rounded-l (left corners)
    $createRadiusUtility('rounded-l', ['border-top-left-radius', 'border-bottom-left-radius'], \true);
    // Individual corners (logical)
    $createRadiusUtility('rounded-ss', ['border-start-start-radius'], \true);
    $createRadiusUtility('rounded-se', ['border-start-end-radius'], \true);
    $createRadiusUtility('rounded-ee', ['border-end-end-radius'], \true);
    $createRadiusUtility('rounded-es', ['border-end-start-radius'], \true);
    // Individual corners (physical)
    $createRadiusUtility('rounded-tl', ['border-top-left-radius'], \true);
    $createRadiusUtility('rounded-tr', ['border-top-right-radius'], \true);
    $createRadiusUtility('rounded-br', ['border-bottom-right-radius'], \true);
    $createRadiusUtility('rounded-bl', ['border-bottom-left-radius'], \true);
    // ==================================================
    // Border Width and Color (combined utility like Tailwind 4)
    // ==================================================
    $borderProperties = function () {
        return atRoot([property('--tw-border-style', 'solid')]);
    };
    $theme = $builder->getTheme();
    // Helper for border side utilities that handle both width and color
    $createBorderSideUtility = function (string $name, array $widthProps, array $colorProps, ?string $styleProps = null) use ($builder, $borderProperties, $theme) {
        $builder->getUtilities()->functional($name, function ($candidate) use ($widthProps, $colorProps, $styleProps, $borderProperties, $theme) {
            $modifier = $candidate['modifier'] ?? null;
            // No value - bare 'border' class (default width)
            if (!isset($candidate['value'])) {
                if ($modifier !== null) {
                    return null;
                }
                $defaultWidth = $theme->get(['--default-border-width']) ?? '1px';
                $decls = [$borderProperties()];
                if ($styleProps) {
                    $decls[] = decl($styleProps, 'var(--tw-border-style)');
                }
                foreach ($widthProps as $prop) {
                    $decls[] = decl($prop, $defaultWidth);
                }
                return $decls;
            }
            $candidateValue = $candidate['value'];
            // Arbitrary values
            if ($candidateValue['kind'] === 'arbitrary') {
                $value = $candidateValue['value'];
                $type = $candidateValue['dataType'] ?? inferDataType($value, ['color', 'line-width', 'length']);
                switch ($type) {
                    case 'line-width':
                    case 'length':
                        if ($modifier !== null) {
                            return null;
                        }
                        $decls = [$borderProperties()];
                        if ($styleProps) {
                            $decls[] = decl($styleProps, 'var(--tw-border-style)');
                        }
                        foreach ($widthProps as $prop) {
                            $decls[] = decl($prop, $value);
                        }
                        return $decls;
                    default:
                        // Color
                        $colorValue = asColor($value, $modifier, $theme);
                        if ($colorValue === null) {
                            return null;
                        }
                        $decls = [];
                        foreach ($colorProps as $prop) {
                            $decls[] = decl($prop, $colorValue);
                        }
                        return $decls;
                }
            }
            $namedValue = $candidateValue['value'] ?? null;
            // Try to resolve as color first
            $colorValue = resolveThemeColor($candidate, $theme, ['--border-color', '--color']);
            if ($colorValue !== null) {
                $decls = [];
                foreach ($colorProps as $prop) {
                    $decls[] = decl($prop, $colorValue);
                }
                return $decls;
            }
            // Try to resolve as width
            if ($modifier !== null) {
                return null;
            }
            $widthValue = $theme->resolve($namedValue, ['--border-width']);
            if ($widthValue !== null) {
                $decls = [$borderProperties()];
                if ($styleProps) {
                    $decls[] = decl($styleProps, 'var(--tw-border-style)');
                }
                foreach ($widthProps as $prop) {
                    $decls[] = decl($prop, $widthValue);
                }
                return $decls;
            }
            // Check for bare integer widths (0, 2, 4, 8)
            if (isPositiveInteger($namedValue)) {
                $decls = [$borderProperties()];
                if ($styleProps) {
                    $decls[] = decl($styleProps, 'var(--tw-border-style)');
                }
                foreach ($widthProps as $prop) {
                    $decls[] = decl($prop, "{$namedValue}px");
                }
                return $decls;
            }
            return null;
        });
    };
    $createBorderSideUtility('border', ['border-width'], ['border-color'], 'border-style');
    $createBorderSideUtility('border-x', ['border-inline-width'], ['border-inline-color'], 'border-inline-style');
    $createBorderSideUtility('border-y', ['border-block-width'], ['border-block-color'], 'border-block-style');
    $createBorderSideUtility('border-s', ['border-inline-start-width'], ['border-inline-start-color'], 'border-inline-start-style');
    $createBorderSideUtility('border-e', ['border-inline-end-width'], ['border-inline-end-color'], 'border-inline-end-style');
    $createBorderSideUtility('border-t', ['border-top-width'], ['border-top-color'], 'border-top-style');
    $createBorderSideUtility('border-r', ['border-right-width'], ['border-right-color'], 'border-right-style');
    $createBorderSideUtility('border-b', ['border-bottom-width'], ['border-bottom-color'], 'border-bottom-style');
    $createBorderSideUtility('border-l', ['border-left-width'], ['border-left-color'], 'border-left-style');
    // ==================================================
    // Border Style
    // ==================================================
    $builder->staticUtility('border-solid', [['--tw-border-style', 'solid'], ['border-style', 'solid']]);
    $builder->staticUtility('border-dashed', [['--tw-border-style', 'dashed'], ['border-style', 'dashed']]);
    $builder->staticUtility('border-dotted', [['--tw-border-style', 'dotted'], ['border-style', 'dotted']]);
    $builder->staticUtility('border-double', [['--tw-border-style', 'double'], ['border-style', 'double']]);
    $builder->staticUtility('border-hidden', [['--tw-border-style', 'hidden'], ['border-style', 'hidden']]);
    $builder->staticUtility('border-none', [['--tw-border-style', 'none'], ['border-style', 'none']]);
    // ==================================================
    // Border Collapse
    // ==================================================
    $builder->staticUtility('border-collapse', [['border-collapse', 'collapse']]);
    $builder->staticUtility('border-separate', [['border-collapse', 'separate']]);
    // ==================================================
    // Outline Style
    // ==================================================
    // Outline style static utilities
    $builder->staticUtility('outline-none', [['--tw-outline-style', 'none'], ['outline-style', 'none']]);
    $builder->staticUtility('outline-solid', [['--tw-outline-style', 'solid'], ['outline-style', 'solid']]);
    $builder->staticUtility('outline-dashed', [['--tw-outline-style', 'dashed'], ['outline-style', 'dashed']]);
    $builder->staticUtility('outline-dotted', [['--tw-outline-style', 'dotted'], ['outline-style', 'dotted']]);
    $builder->staticUtility('outline-double', [['--tw-outline-style', 'double'], ['outline-style', 'double']]);
    // Outline properties for @property rules
    $outlineProperties = function () {
        return atRoot([property('--tw-outline-style', 'solid')]);
    };
    // Outline functional utility - handles both colors and widths
    $builder->getUtilities()->functional('outline', function ($candidate) use ($theme, $outlineProperties) {
        $modifier = $candidate['modifier'] ?? null;
        // No value - bare 'outline' class
        if (!isset($candidate['value'])) {
            if ($modifier !== null) {
                return null;
            }
            $defaultWidth = $theme->get(['--default-outline-width']) ?? '1px';
            return [$outlineProperties(), decl('outline-style', 'var(--tw-outline-style)'), decl('outline-width', $defaultWidth)];
        }
        $candidateValue = $candidate['value'];
        // Arbitrary values
        if ($candidateValue['kind'] === 'arbitrary') {
            $value = $candidateValue['value'];
            $type = $candidateValue['dataType'] ?? inferDataType($value, ['color', 'length', 'number', 'percentage']);
            switch ($type) {
                case 'length':
                case 'number':
                case 'percentage':
                    if ($modifier !== null) {
                        return null;
                    }
                    return [$outlineProperties(), decl('outline-style', 'var(--tw-outline-style)'), decl('outline-width', $value)];
                default:
                    // Color
                    $value = asColor($value, $modifier, $theme);
                    if ($value === null) {
                        return null;
                    }
                    return [decl('outline-color', $value)];
            }
        }
        $namedValue = $candidateValue['value'] ?? null;
        // Try to resolve as color first
        $colorValue = resolveThemeColor($candidate, $theme, ['--outline-color', '--color']);
        if ($colorValue !== null) {
            return [decl('outline-color', $colorValue)];
        }
        // Try to resolve as width
        if ($modifier !== null) {
            return null;
        }
        $widthValue = $theme->resolve($namedValue, ['--outline-width']);
        if ($widthValue !== null) {
            return [$outlineProperties(), decl('outline-style', 'var(--tw-outline-style)'), decl('outline-width', $widthValue)];
        }
        // Check for bare integer widths (0, 1, 2, 4, 8)
        if (isPositiveInteger($namedValue)) {
            return [$outlineProperties(), decl('outline-style', 'var(--tw-outline-style)'), decl('outline-width', "{$namedValue}px")];
        }
        return null;
    });
    // Outline Offset
    // Note: For bare values like -outline-offset-4, Tailwind outputs calc(4px * -1)
    // not -4px. This is because the handleBareValue returns "4px" which then gets
    // wrapped in calc() by the negation logic.
    $builder->functionalUtility('outline-offset', ['themeKeys' => ['--outline-offset'], 'defaultValue' => null, 'supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}px";
    }, 'handle' => function ($value) {
        return [decl('outline-offset', $value)];
    }, 'staticValues' => ['0' => [decl('outline-offset', '0px')], '1' => [decl('outline-offset', '1px')], '2' => [decl('outline-offset', '2px')], '4' => [decl('outline-offset', '4px')], '8' => [decl('outline-offset', '8px')]]]);
    // ==================================================
    // Divide Width (space between children)
    // ==================================================
    // divide-x
    $builder->functionalUtility('divide-x', ['themeKeys' => ['--divide-width', '--border-width'], 'defaultValue' => '1px', 'handleBareValue' => function ($value) {
        // Only positive integers are treated as pixel widths
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return $value['value'] . 'px';
    }, 'handle' => function ($value) {
        return [atRoot([property('--tw-divide-x-reverse', '0')]), styleRule(':where(& > :not(:last-child))', [decl('--tw-sort', 'divide-x-width'), atRoot([property('--tw-border-style', 'solid')]), decl('--tw-divide-x-reverse', '0'), decl('border-inline-style', 'var(--tw-border-style)'), decl('border-inline-start-width', "calc({$value} * var(--tw-divide-x-reverse))"), decl('border-inline-end-width', "calc({$value} * calc(1 - var(--tw-divide-x-reverse)))")])];
    }]);
    // divide-y
    $builder->functionalUtility('divide-y', ['themeKeys' => ['--divide-width', '--border-width'], 'defaultValue' => '1px', 'handleBareValue' => function ($value) {
        // Only positive integers are treated as pixel widths
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return $value['value'] . 'px';
    }, 'handle' => function ($value) {
        return [atRoot([property('--tw-divide-y-reverse', '0')]), styleRule(':where(& > :not(:last-child))', [decl('--tw-sort', 'divide-y-width'), atRoot([property('--tw-border-style', 'solid')]), decl('--tw-divide-y-reverse', '0'), decl('border-bottom-style', 'var(--tw-border-style)'), decl('border-top-style', 'var(--tw-border-style)'), decl('border-top-width', "calc({$value} * var(--tw-divide-y-reverse))"), decl('border-bottom-width', "calc({$value} * calc(1 - var(--tw-divide-y-reverse)))")])];
    }]);
    // divide-x-reverse, divide-y-reverse
    // These use :where(& > :not(:last-child)) selector
    $builder->staticUtility('divide-x-reverse', [fn() => atRoot([property('--tw-divide-x-reverse', '0')]), fn() => styleRule(':where(& > :not(:last-child))', [decl('--tw-divide-x-reverse', '1')])]);
    $builder->staticUtility('divide-y-reverse', [fn() => atRoot([property('--tw-divide-y-reverse', '0')]), fn() => styleRule(':where(& > :not(:last-child))', [decl('--tw-divide-y-reverse', '1')])]);
    // Divide Style - also uses :where(& > :not(:last-child)) selector
    $builder->staticUtility('divide-solid', [fn() => styleRule(':where(& > :not(:last-child))', [decl('--tw-border-style', 'solid'), decl('border-style', 'solid')])]);
    $builder->staticUtility('divide-dashed', [fn() => styleRule(':where(& > :not(:last-child))', [decl('--tw-border-style', 'dashed'), decl('border-style', 'dashed')])]);
    $builder->staticUtility('divide-dotted', [fn() => styleRule(':where(& > :not(:last-child))', [decl('--tw-border-style', 'dotted'), decl('border-style', 'dotted')])]);
    $builder->staticUtility('divide-double', [fn() => styleRule(':where(& > :not(:last-child))', [decl('--tw-border-style', 'double'), decl('border-style', 'double')])]);
    $builder->staticUtility('divide-none', [fn() => styleRule(':where(& > :not(:last-child))', [decl('--tw-border-style', 'none'), decl('border-style', 'none')])]);
    // Divide Color
    $builder->colorUtility('divide', ['themeKeys' => ['--divide-color', '--border-color', '--color'], 'handle' => function ($value) {
        return [styleRule(':where(& > :not(:last-child))', [decl('--tw-sort', 'divide-color'), decl('border-color', $value)])];
    }]);
    // ==================================================
    // Outline Hidden (special utility with @media query)
    // ==================================================
    $builder->getUtilities()->static('outline-hidden', function () {
        return [decl('--tw-outline-style', 'none'), decl('outline-style', 'none'), atRule('@media', '(forced-colors: active)', [decl('outline', '2px solid transparent'), decl('outline-offset', '2px')])];
    });
}
