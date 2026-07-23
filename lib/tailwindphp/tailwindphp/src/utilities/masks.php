<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\atRoot;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Utils\inferDataType;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
use function BlockstudioVendor\TailwindPHP\Utils\isValidSpacingMultiplier;
/**
 * Mask Utilities
 *
 * Port of mask utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - mask-linear-* (linear gradient masks)
 * - mask-radial-* (radial gradient masks)
 * - mask-conic-* (conic gradient masks)
 * - mask-x/y/t/r/b/l-* (edge masks)
 * - mask-circle, mask-ellipse (shape utilities)
 */
/**
 * Register mask utilities.
 */
function registerMaskUtilities(UtilityBuilder $builder): void
{
    $theme = $builder->getTheme();
    // ==================================================
    // Mask Image (base utilities)
    // ==================================================
    $builder->staticUtility('mask-none', [['mask-image', 'none']]);
    // mask functional utility (arbitrary values)
    $builder->getUtilities()->functional('mask', function ($candidate) use ($theme) {
        if (!isset($candidate['value'])) {
            return null;
        }
        if (isset($candidate['modifier'])) {
            return null;
        }
        if ($candidate['value']['kind'] !== 'arbitrary') {
            return null;
        }
        $value = $candidate['value']['value'];
        $type = $candidate['value']['dataType'] ?? inferDataType($value, ['image', 'percentage', 'position', 'bg-size', 'length', 'url']);
        switch ($type) {
            case 'percentage':
            case 'position':
                return [decl('mask-position', $value)];
            case 'bg-size':
            case 'length':
            case 'size':
                return [decl('mask-size', $value)];
            case 'image':
            case 'url':
            default:
                return [decl('mask-image', $value)];
        }
    });
    // ==================================================
    // Mask Composite
    // ==================================================
    $builder->staticUtility('mask-add', [['mask-composite', 'add']]);
    $builder->staticUtility('mask-subtract', [['mask-composite', 'subtract']]);
    $builder->staticUtility('mask-intersect', [['mask-composite', 'intersect']]);
    $builder->staticUtility('mask-exclude', [['mask-composite', 'exclude']]);
    // ==================================================
    // Mask Mode
    // ==================================================
    $builder->staticUtility('mask-alpha', [['mask-mode', 'alpha']]);
    $builder->staticUtility('mask-luminance', [['mask-mode', 'luminance']]);
    $builder->staticUtility('mask-match', [['mask-mode', 'match-source']]);
    // ==================================================
    // Mask Type
    // ==================================================
    $builder->staticUtility('mask-type-alpha', [['mask-type', 'alpha']]);
    $builder->staticUtility('mask-type-luminance', [['mask-type', 'luminance']]);
    // ==================================================
    // Mask Size
    // ==================================================
    $builder->staticUtility('mask-auto', [['mask-size', 'auto']]);
    $builder->staticUtility('mask-cover', [['mask-size', 'cover']]);
    $builder->staticUtility('mask-contain', [['mask-size', 'contain']]);
    $builder->functionalUtility('mask-size', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) {
        if ($value === null) {
            return null;
        }
        return [decl('mask-size', $value)];
    }]);
    // ==================================================
    // Mask Position
    // ==================================================
    $builder->staticUtility('mask-top', [['mask-position', 'top']]);
    $builder->staticUtility('mask-top-left', [['mask-position', 'left top']]);
    $builder->staticUtility('mask-top-right', [['mask-position', 'right top']]);
    $builder->staticUtility('mask-bottom', [['mask-position', 'bottom']]);
    $builder->staticUtility('mask-bottom-left', [['mask-position', 'left bottom']]);
    $builder->staticUtility('mask-bottom-right', [['mask-position', 'right bottom']]);
    $builder->staticUtility('mask-left', [['mask-position', 'left']]);
    $builder->staticUtility('mask-right', [['mask-position', 'right']]);
    $builder->staticUtility('mask-center', [['mask-position', 'center']]);
    $builder->functionalUtility('mask-position', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) {
        if ($value === null) {
            return null;
        }
        return [decl('mask-position', $value)];
    }]);
    // ==================================================
    // Mask Repeat
    // ==================================================
    $builder->staticUtility('mask-repeat', [['mask-repeat', 'repeat']]);
    $builder->staticUtility('mask-no-repeat', [['mask-repeat', 'no-repeat']]);
    $builder->staticUtility('mask-repeat-x', [['mask-repeat', 'repeat-x']]);
    $builder->staticUtility('mask-repeat-y', [['mask-repeat', 'repeat-y']]);
    $builder->staticUtility('mask-repeat-round', [['mask-repeat', 'round']]);
    $builder->staticUtility('mask-repeat-space', [['mask-repeat', 'space']]);
    // ==================================================
    // Mask Gradient Properties
    // ==================================================
    $maskPropertiesGradient = function () {
        return atRoot([property('--tw-mask-linear', 'linear-gradient(#fff, #fff)'), property('--tw-mask-radial', 'linear-gradient(#fff, #fff)'), property('--tw-mask-conic', 'linear-gradient(#fff, #fff)')]);
    };
    // ==================================================
    // Mask Stop Utility Helper
    // ==================================================
    $maskStopUtility = function (string $classRoot, callable $colorHandler, callable $positionHandler) use ($builder, $theme) {
        $builder->getUtilities()->functional($classRoot, function ($candidate) use ($theme, $colorHandler, $positionHandler) {
            if (!isset($candidate['value'])) {
                return null;
            }
            $candidateValue = $candidate['value'];
            $modifier = $candidate['modifier'] ?? null;
            // Arbitrary values
            if ($candidateValue['kind'] === 'arbitrary') {
                $value = $candidateValue['value'];
                $type = $candidateValue['dataType'] ?? inferDataType($value, ['length', 'percentage', 'color']);
                switch ($type) {
                    case 'color':
                        $value = asColor($value, $modifier, $theme);
                        if ($value === null) {
                            return null;
                        }
                        return $colorHandler($value);
                    case 'percentage':
                        if ($modifier !== null) {
                            return null;
                        }
                        $numPart = substr($value, 0, -1);
                        if (!isPositiveInteger($numPart)) {
                            return null;
                        }
                        return $positionHandler($value);
                    default:
                        if ($modifier !== null) {
                            return null;
                        }
                        return $positionHandler($value);
                }
            }
            $namedValue = $candidateValue['value'] ?? '';
            // Known values: Colors
            $colorValue = resolveThemeColor($candidate, $theme, ['--background-color', '--color']);
            if ($colorValue !== null) {
                return $colorHandler($colorValue);
            }
            // Known values: Positions
            if ($modifier !== null) {
                return null;
            }
            $type = inferDataType($namedValue, ['number', 'percentage']);
            if ($type === null) {
                return null;
            }
            switch ($type) {
                case 'number':
                    $multiplier = $theme->resolve(null, ['--spacing']);
                    if ($multiplier === null) {
                        return null;
                    }
                    if (!isValidSpacingMultiplier($namedValue)) {
                        return null;
                    }
                    return $positionHandler("calc({$multiplier} * {$namedValue})");
                case 'percentage':
                    $numPart = substr($namedValue, 0, -1);
                    if (!isPositiveInteger($numPart)) {
                        return null;
                    }
                    return $positionHandler($namedValue);
                default:
                    return null;
            }
        });
    };
    // ==================================================
    // Linear Mask Properties
    // ==================================================
    $maskPropertiesLinear = function () {
        return atRoot([property('--tw-mask-linear-position', '0deg'), property('--tw-mask-linear-from-position', '0%'), property('--tw-mask-linear-to-position', '100%'), property('--tw-mask-linear-from-color', 'black'), property('--tw-mask-linear-to-color', 'transparent')]);
    };
    // mask-linear-{angle} utility
    $builder->functionalUtility('mask-linear', ['themeKeys' => [], 'defaultValue' => null, 'supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "calc(1deg * {$value['value']})";
    }, 'handleNegativeBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "calc(1deg * -{$value['value']})";
    }, 'handle' => function ($value) use ($maskPropertiesGradient, $maskPropertiesLinear) {
        return [$maskPropertiesGradient(), $maskPropertiesLinear(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-linear', 'linear-gradient(var(--tw-mask-linear-stops, var(--tw-mask-linear-position)))'), decl('--tw-mask-linear-position', $value)];
    }]);
    // mask-linear-from utility using maskStopUtility
    $maskStopUtility('mask-linear-from', fn($value) => [$maskPropertiesGradient(), $maskPropertiesLinear(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-linear-stops', 'var(--tw-mask-linear-position), var(--tw-mask-linear-from-color) var(--tw-mask-linear-from-position), var(--tw-mask-linear-to-color) var(--tw-mask-linear-to-position)'), decl('--tw-mask-linear', 'linear-gradient(var(--tw-mask-linear-stops))'), decl('--tw-mask-linear-from-color', $value)], fn($value) => [$maskPropertiesGradient(), $maskPropertiesLinear(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-linear-stops', 'var(--tw-mask-linear-position), var(--tw-mask-linear-from-color) var(--tw-mask-linear-from-position), var(--tw-mask-linear-to-color) var(--tw-mask-linear-to-position)'), decl('--tw-mask-linear', 'linear-gradient(var(--tw-mask-linear-stops))'), decl('--tw-mask-linear-from-position', $value)]);
    // mask-linear-to utility using maskStopUtility
    $maskStopUtility('mask-linear-to', fn($value) => [$maskPropertiesGradient(), $maskPropertiesLinear(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-linear-stops', 'var(--tw-mask-linear-position), var(--tw-mask-linear-from-color) var(--tw-mask-linear-from-position), var(--tw-mask-linear-to-color) var(--tw-mask-linear-to-position)'), decl('--tw-mask-linear', 'linear-gradient(var(--tw-mask-linear-stops))'), decl('--tw-mask-linear-to-color', $value)], fn($value) => [$maskPropertiesGradient(), $maskPropertiesLinear(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-linear-stops', 'var(--tw-mask-linear-position), var(--tw-mask-linear-from-color) var(--tw-mask-linear-from-position), var(--tw-mask-linear-to-color) var(--tw-mask-linear-to-position)'), decl('--tw-mask-linear', 'linear-gradient(var(--tw-mask-linear-stops))'), decl('--tw-mask-linear-to-position', $value)]);
    // ==================================================
    // Radial Mask Properties
    // ==================================================
    $maskPropertiesRadial = function () {
        return atRoot([property('--tw-mask-radial-from-position', '0%'), property('--tw-mask-radial-to-position', '100%'), property('--tw-mask-radial-from-color', 'black'), property('--tw-mask-radial-to-color', 'transparent'), property('--tw-mask-radial-shape', 'ellipse'), property('--tw-mask-radial-size', 'farthest-corner'), property('--tw-mask-radial-position', 'center')]);
    };
    // mask-circle, mask-ellipse
    $builder->staticUtility('mask-circle', [['--tw-mask-radial-shape', 'circle']]);
    $builder->staticUtility('mask-ellipse', [['--tw-mask-radial-shape', 'ellipse']]);
    // mask-radial-* size utilities
    $builder->staticUtility('mask-radial-closest-side', [['--tw-mask-radial-size', 'closest-side']]);
    $builder->staticUtility('mask-radial-farthest-side', [['--tw-mask-radial-size', 'farthest-side']]);
    $builder->staticUtility('mask-radial-closest-corner', [['--tw-mask-radial-size', 'closest-corner']]);
    $builder->staticUtility('mask-radial-farthest-corner', [['--tw-mask-radial-size', 'farthest-corner']]);
    // mask-radial-at-* position utilities
    $builder->staticUtility('mask-radial-at-top', [['--tw-mask-radial-position', 'top']]);
    $builder->staticUtility('mask-radial-at-top-left', [['--tw-mask-radial-position', 'top left']]);
    $builder->staticUtility('mask-radial-at-top-right', [['--tw-mask-radial-position', 'top right']]);
    $builder->staticUtility('mask-radial-at-bottom', [['--tw-mask-radial-position', 'bottom']]);
    $builder->staticUtility('mask-radial-at-bottom-left', [['--tw-mask-radial-position', 'bottom left']]);
    $builder->staticUtility('mask-radial-at-bottom-right', [['--tw-mask-radial-position', 'bottom right']]);
    $builder->staticUtility('mask-radial-at-left', [['--tw-mask-radial-position', 'left']]);
    $builder->staticUtility('mask-radial-at-right', [['--tw-mask-radial-position', 'right']]);
    $builder->staticUtility('mask-radial-at-center', [['--tw-mask-radial-position', 'center']]);
    // mask-radial-at-[arbitrary] position
    $builder->functionalUtility('mask-radial-at', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('--tw-mask-radial-position', $value)];
    }]);
    // mask-radial-[size] utility
    $builder->functionalUtility('mask-radial', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) use ($maskPropertiesGradient, $maskPropertiesRadial) {
        return [$maskPropertiesGradient(), $maskPropertiesRadial(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-radial', 'radial-gradient(var(--tw-mask-radial-stops, var(--tw-mask-radial-size)))'), decl('--tw-mask-radial-size', $value)];
    }]);
    // mask-radial-from utility using maskStopUtility
    $maskStopUtility('mask-radial-from', fn($value) => [$maskPropertiesGradient(), $maskPropertiesRadial(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-radial-stops', 'var(--tw-mask-radial-shape) var(--tw-mask-radial-size) at var(--tw-mask-radial-position), var(--tw-mask-radial-from-color) var(--tw-mask-radial-from-position), var(--tw-mask-radial-to-color) var(--tw-mask-radial-to-position)'), decl('--tw-mask-radial', 'radial-gradient(var(--tw-mask-radial-stops))'), decl('--tw-mask-radial-from-color', $value)], fn($value) => [$maskPropertiesGradient(), $maskPropertiesRadial(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-radial-stops', 'var(--tw-mask-radial-shape) var(--tw-mask-radial-size) at var(--tw-mask-radial-position), var(--tw-mask-radial-from-color) var(--tw-mask-radial-from-position), var(--tw-mask-radial-to-color) var(--tw-mask-radial-to-position)'), decl('--tw-mask-radial', 'radial-gradient(var(--tw-mask-radial-stops))'), decl('--tw-mask-radial-from-position', $value)]);
    // mask-radial-to utility using maskStopUtility
    $maskStopUtility('mask-radial-to', fn($value) => [$maskPropertiesGradient(), $maskPropertiesRadial(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-radial-stops', 'var(--tw-mask-radial-shape) var(--tw-mask-radial-size) at var(--tw-mask-radial-position), var(--tw-mask-radial-from-color) var(--tw-mask-radial-from-position), var(--tw-mask-radial-to-color) var(--tw-mask-radial-to-position)'), decl('--tw-mask-radial', 'radial-gradient(var(--tw-mask-radial-stops))'), decl('--tw-mask-radial-to-color', $value)], fn($value) => [$maskPropertiesGradient(), $maskPropertiesRadial(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-radial-stops', 'var(--tw-mask-radial-shape) var(--tw-mask-radial-size) at var(--tw-mask-radial-position), var(--tw-mask-radial-from-color) var(--tw-mask-radial-from-position), var(--tw-mask-radial-to-color) var(--tw-mask-radial-to-position)'), decl('--tw-mask-radial', 'radial-gradient(var(--tw-mask-radial-stops))'), decl('--tw-mask-radial-to-position', $value)]);
    // ==================================================
    // Conic Mask Properties
    // ==================================================
    $maskPropertiesConic = function () {
        return atRoot([property('--tw-mask-conic-position', '0deg'), property('--tw-mask-conic-from-position', '0%'), property('--tw-mask-conic-to-position', '100%'), property('--tw-mask-conic-from-color', 'black'), property('--tw-mask-conic-to-color', 'transparent')]);
    };
    // mask-conic-{angle} utility
    $builder->functionalUtility('mask-conic', ['themeKeys' => [], 'defaultValue' => null, 'supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "calc(1deg * {$value['value']})";
    }, 'handleNegativeBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "calc(1deg * -{$value['value']})";
    }, 'handle' => function ($value) use ($maskPropertiesGradient, $maskPropertiesConic) {
        return [$maskPropertiesGradient(), $maskPropertiesConic(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-conic', 'conic-gradient(var(--tw-mask-conic-stops, var(--tw-mask-conic-position)))'), decl('--tw-mask-conic-position', $value)];
    }]);
    // mask-conic-from utility using maskStopUtility
    $maskStopUtility('mask-conic-from', fn($value) => [$maskPropertiesGradient(), $maskPropertiesConic(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-conic-stops', 'from var(--tw-mask-conic-position), var(--tw-mask-conic-from-color) var(--tw-mask-conic-from-position), var(--tw-mask-conic-to-color) var(--tw-mask-conic-to-position)'), decl('--tw-mask-conic', 'conic-gradient(var(--tw-mask-conic-stops))'), decl('--tw-mask-conic-from-color', $value)], fn($value) => [$maskPropertiesGradient(), $maskPropertiesConic(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-conic-stops', 'from var(--tw-mask-conic-position), var(--tw-mask-conic-from-color) var(--tw-mask-conic-from-position), var(--tw-mask-conic-to-color) var(--tw-mask-conic-to-position)'), decl('--tw-mask-conic', 'conic-gradient(var(--tw-mask-conic-stops))'), decl('--tw-mask-conic-from-position', $value)]);
    // mask-conic-to utility using maskStopUtility
    $maskStopUtility('mask-conic-to', fn($value) => [$maskPropertiesGradient(), $maskPropertiesConic(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-conic-stops', 'from var(--tw-mask-conic-position), var(--tw-mask-conic-from-color) var(--tw-mask-conic-from-position), var(--tw-mask-conic-to-color) var(--tw-mask-conic-to-position)'), decl('--tw-mask-conic', 'conic-gradient(var(--tw-mask-conic-stops))'), decl('--tw-mask-conic-to-color', $value)], fn($value) => [$maskPropertiesGradient(), $maskPropertiesConic(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-conic-stops', 'from var(--tw-mask-conic-position), var(--tw-mask-conic-from-color) var(--tw-mask-conic-from-position), var(--tw-mask-conic-to-color) var(--tw-mask-conic-to-position)'), decl('--tw-mask-conic', 'conic-gradient(var(--tw-mask-conic-stops))'), decl('--tw-mask-conic-to-position', $value)]);
    // ==================================================
    // Edge Mask Properties
    // ==================================================
    $maskPropertiesEdge = function () {
        return atRoot([property('--tw-mask-left', 'linear-gradient(#fff, #fff)'), property('--tw-mask-right', 'linear-gradient(#fff, #fff)'), property('--tw-mask-bottom', 'linear-gradient(#fff, #fff)'), property('--tw-mask-top', 'linear-gradient(#fff, #fff)')]);
    };
    // Helper function to create edge mask utilities
    $maskEdgeUtility = function (string $name, string $stop, array $edges) use ($maskStopUtility, $maskPropertiesGradient, $maskPropertiesEdge) {
        $createNodes = function (string $type, string $value) use ($maskPropertiesGradient, $maskPropertiesEdge, $stop, $edges) {
            $nodes = [$maskPropertiesGradient(), $maskPropertiesEdge(), decl('mask-image', 'var(--tw-mask-linear), var(--tw-mask-radial), var(--tw-mask-conic)'), decl('mask-composite', 'intersect'), decl('--tw-mask-linear', 'var(--tw-mask-left), var(--tw-mask-right), var(--tw-mask-bottom), var(--tw-mask-top)')];
            foreach (['top', 'right', 'bottom', 'left'] as $edge) {
                if (!($edges[$edge] ?? \false)) {
                    continue;
                }
                $nodes[] = decl("--tw-mask-{$edge}", "linear-gradient(to {$edge}, var(--tw-mask-{$edge}-from-color) var(--tw-mask-{$edge}-from-position), var(--tw-mask-{$edge}-to-color) var(--tw-mask-{$edge}-to-position))");
                $nodes[] = atRoot([property("--tw-mask-{$edge}-from-position", '0%'), property("--tw-mask-{$edge}-to-position", '100%'), property("--tw-mask-{$edge}-from-color", 'black'), property("--tw-mask-{$edge}-to-color", 'transparent')]);
                if ($type === 'color') {
                    $nodes[] = decl("--tw-mask-{$edge}-{$stop}-color", $value);
                } else {
                    $nodes[] = decl("--tw-mask-{$edge}-{$stop}-position", $value);
                }
            }
            return $nodes;
        };
        $maskStopUtility($name, fn($value) => $createNodes('color', $value), fn($value) => $createNodes('position', $value));
    };
    // Register edge mask utilities
    $maskEdgeUtility('mask-x-from', 'from', ['top' => \false, 'right' => \true, 'bottom' => \false, 'left' => \true]);
    $maskEdgeUtility('mask-x-to', 'to', ['top' => \false, 'right' => \true, 'bottom' => \false, 'left' => \true]);
    $maskEdgeUtility('mask-y-from', 'from', ['top' => \true, 'right' => \false, 'bottom' => \true, 'left' => \false]);
    $maskEdgeUtility('mask-y-to', 'to', ['top' => \true, 'right' => \false, 'bottom' => \true, 'left' => \false]);
    $maskEdgeUtility('mask-t-from', 'from', ['top' => \true, 'right' => \false, 'bottom' => \false, 'left' => \false]);
    $maskEdgeUtility('mask-t-to', 'to', ['top' => \true, 'right' => \false, 'bottom' => \false, 'left' => \false]);
    $maskEdgeUtility('mask-r-from', 'from', ['top' => \false, 'right' => \true, 'bottom' => \false, 'left' => \false]);
    $maskEdgeUtility('mask-r-to', 'to', ['top' => \false, 'right' => \true, 'bottom' => \false, 'left' => \false]);
    $maskEdgeUtility('mask-b-from', 'from', ['top' => \false, 'right' => \false, 'bottom' => \true, 'left' => \false]);
    $maskEdgeUtility('mask-b-to', 'to', ['top' => \false, 'right' => \false, 'bottom' => \true, 'left' => \false]);
    $maskEdgeUtility('mask-l-from', 'from', ['top' => \false, 'right' => \false, 'bottom' => \false, 'left' => \true]);
    $maskEdgeUtility('mask-l-to', 'to', ['top' => \false, 'right' => \false, 'bottom' => \false, 'left' => \true]);
}
