<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\atRoot;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Utils\inferDataType;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
use function BlockstudioVendor\TailwindPHP\Utils\isValidOpacityValue;
use function BlockstudioVendor\TailwindPHP\Utils\replaceShadowColors;
use function BlockstudioVendor\TailwindPHP\Utils\segment;
/**
 * Filters Utilities
 *
 * Port of filter utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - filter, backdrop-filter
 * - blur, backdrop-blur
 * - brightness, backdrop-brightness
 * - contrast, backdrop-contrast
 * - grayscale, backdrop-grayscale
 * - hue-rotate, backdrop-hue-rotate
 * - invert, backdrop-invert
 * - saturate, backdrop-saturate
 * - sepia, backdrop-sepia
 * - drop-shadow
 * - backdrop-opacity
 */
/**
 * Register filters utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerFiltersUtilities(UtilityBuilder $builder): void
{
    $theme = $builder->getTheme();
    // CSS filter value
    $cssFilterValue = implode(' ', ['var(--tw-blur,)', 'var(--tw-brightness,)', 'var(--tw-contrast,)', 'var(--tw-grayscale,)', 'var(--tw-hue-rotate,)', 'var(--tw-invert,)', 'var(--tw-saturate,)', 'var(--tw-sepia,)', 'var(--tw-drop-shadow,)']);
    // CSS backdrop filter value
    $cssBackdropFilterValue = implode(' ', ['var(--tw-backdrop-blur,)', 'var(--tw-backdrop-brightness,)', 'var(--tw-backdrop-contrast,)', 'var(--tw-backdrop-grayscale,)', 'var(--tw-backdrop-hue-rotate,)', 'var(--tw-backdrop-invert,)', 'var(--tw-backdrop-opacity,)', 'var(--tw-backdrop-saturate,)', 'var(--tw-backdrop-sepia,)']);
    // ==================================================
    // filter
    // ==================================================
    // filter (default)
    $builder->staticUtility('filter', [['filter', $cssFilterValue]]);
    // filter-none
    $builder->staticUtility('filter-none', [['filter', 'none']]);
    // filter-[arbitrary]
    $builder->functionalUtility('filter', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('filter', $value)];
    }]);
    // ==================================================
    // backdrop-filter
    // ==================================================
    // backdrop-filter (default)
    $builder->staticUtility('backdrop-filter', [['-webkit-backdrop-filter', $cssBackdropFilterValue], ['backdrop-filter', $cssBackdropFilterValue]]);
    // backdrop-filter-none
    $builder->staticUtility('backdrop-filter-none', [['-webkit-backdrop-filter', 'none'], ['backdrop-filter', 'none']]);
    // backdrop-filter-[arbitrary]
    $builder->functionalUtility('backdrop-filter', ['themeKeys' => [], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('-webkit-backdrop-filter', $value), decl('backdrop-filter', $value)];
    }]);
    // ==================================================
    // blur
    // ==================================================
    $builder->functionalUtility('blur', ['themeKeys' => ['--blur'], 'handle' => function ($value) use ($cssFilterValue) {
        return [decl('--tw-blur', "blur({$value})"), decl('filter', $cssFilterValue)];
    }, 'staticValues' => ['none' => [decl('--tw-blur', ' '), decl('filter', $cssFilterValue)]]]);
    // ==================================================
    // backdrop-blur
    // ==================================================
    $builder->functionalUtility('backdrop-blur', ['themeKeys' => ['--backdrop-blur', '--blur'], 'handle' => function ($value) use ($cssBackdropFilterValue) {
        return [decl('--tw-backdrop-blur', "blur({$value})"), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)];
    }, 'staticValues' => ['none' => [decl('--tw-backdrop-blur', ' '), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)]]]);
    // ==================================================
    // brightness
    // ==================================================
    $builder->functionalUtility('brightness', ['themeKeys' => ['--brightness'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssFilterValue) {
        return [decl('--tw-brightness', "brightness({$value})"), decl('filter', $cssFilterValue)];
    }]);
    // ==================================================
    // backdrop-brightness
    // ==================================================
    $builder->functionalUtility('backdrop-brightness', ['themeKeys' => ['--backdrop-brightness', '--brightness'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssBackdropFilterValue) {
        return [decl('--tw-backdrop-brightness', "brightness({$value})"), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)];
    }]);
    // ==================================================
    // contrast
    // ==================================================
    $builder->functionalUtility('contrast', ['themeKeys' => ['--contrast'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssFilterValue) {
        return [decl('--tw-contrast', "contrast({$value})"), decl('filter', $cssFilterValue)];
    }]);
    // ==================================================
    // backdrop-contrast
    // ==================================================
    $builder->functionalUtility('backdrop-contrast', ['themeKeys' => ['--backdrop-contrast', '--contrast'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssBackdropFilterValue) {
        return [decl('--tw-backdrop-contrast', "contrast({$value})"), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)];
    }]);
    // ==================================================
    // grayscale
    // ==================================================
    $builder->functionalUtility('grayscale', ['themeKeys' => ['--grayscale'], 'defaultValue' => '100%', 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssFilterValue) {
        return [decl('--tw-grayscale', "grayscale({$value})"), decl('filter', $cssFilterValue)];
    }]);
    // ==================================================
    // backdrop-grayscale
    // ==================================================
    $builder->functionalUtility('backdrop-grayscale', ['themeKeys' => ['--backdrop-grayscale', '--grayscale'], 'defaultValue' => '100%', 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssBackdropFilterValue) {
        return [decl('--tw-backdrop-grayscale', "grayscale({$value})"), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)];
    }]);
    // ==================================================
    // hue-rotate
    // ==================================================
    $builder->functionalUtility('hue-rotate', ['themeKeys' => ['--hue-rotate'], 'defaultValue' => null, 'supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}deg";
    }, 'handle' => function ($value) use ($cssFilterValue) {
        return [decl('--tw-hue-rotate', "hue-rotate({$value})"), decl('filter', $cssFilterValue)];
    }]);
    // ==================================================
    // backdrop-hue-rotate
    // ==================================================
    $builder->functionalUtility('backdrop-hue-rotate', ['themeKeys' => ['--backdrop-hue-rotate', '--hue-rotate'], 'defaultValue' => null, 'supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}deg";
    }, 'handle' => function ($value) use ($cssBackdropFilterValue) {
        return [decl('--tw-backdrop-hue-rotate', "hue-rotate({$value})"), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)];
    }]);
    // ==================================================
    // invert
    // ==================================================
    $builder->functionalUtility('invert', ['themeKeys' => ['--invert'], 'defaultValue' => '100%', 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssFilterValue) {
        return [decl('--tw-invert', "invert({$value})"), decl('filter', $cssFilterValue)];
    }]);
    // ==================================================
    // backdrop-invert
    // ==================================================
    $builder->functionalUtility('backdrop-invert', ['themeKeys' => ['--backdrop-invert', '--invert'], 'defaultValue' => '100%', 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssBackdropFilterValue) {
        return [decl('--tw-backdrop-invert', "invert({$value})"), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)];
    }]);
    // ==================================================
    // saturate
    // ==================================================
    $builder->functionalUtility('saturate', ['themeKeys' => ['--saturate'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssFilterValue) {
        return [decl('--tw-saturate', "saturate({$value})"), decl('filter', $cssFilterValue)];
    }]);
    // ==================================================
    // backdrop-saturate
    // ==================================================
    $builder->functionalUtility('backdrop-saturate', ['themeKeys' => ['--backdrop-saturate', '--saturate'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssBackdropFilterValue) {
        return [decl('--tw-backdrop-saturate', "saturate({$value})"), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)];
    }]);
    // ==================================================
    // sepia
    // ==================================================
    $builder->functionalUtility('sepia', ['themeKeys' => ['--sepia'], 'defaultValue' => '100%', 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssFilterValue) {
        return [decl('--tw-sepia', "sepia({$value})"), decl('filter', $cssFilterValue)];
    }]);
    // ==================================================
    // backdrop-sepia
    // ==================================================
    $builder->functionalUtility('backdrop-sepia', ['themeKeys' => ['--backdrop-sepia', '--sepia'], 'defaultValue' => '100%', 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssBackdropFilterValue) {
        return [decl('--tw-backdrop-sepia', "sepia({$value})"), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)];
    }]);
    // ==================================================
    // drop-shadow
    // ==================================================
    // Filter properties for @property rules
    $filterProperties = function () {
        return atRoot([property('--tw-blur'), property('--tw-brightness'), property('--tw-contrast'), property('--tw-grayscale'), property('--tw-hue-rotate'), property('--tw-invert'), property('--tw-opacity'), property('--tw-saturate'), property('--tw-sepia'), property('--tw-drop-shadow'), property('--tw-drop-shadow-color'), property('--tw-drop-shadow-alpha', '100%', '<percentage>'), property('--tw-drop-shadow-size')]);
    };
    // Helper for alpha-replaced drop shadow properties
    $alphaReplacedDropShadowProperties = function (string $property, string $value, ?string $alpha, callable $varInjector): array {
        // Parse drop shadow(s) and replace colors
        // The value is the raw shadow values like "0 1px 1px rgb(0 0 0 / 0.05)"
        $parts = segment($value, ',');
        $replacedParts = [];
        foreach ($parts as $part) {
            $part = trim($part);
            // Use replaceShadowColors to replace colors in shadow value
            $replaced = replaceShadowColors($part, function ($color) use ($alpha, $varInjector) {
                if ($alpha === null) {
                    // Convert rgb to hex for fallback
                    return $varInjector(colorToHex($color));
                }
                // When the input is currentcolor, use color-mix approach
                if (str_starts_with($color, 'current')) {
                    return $varInjector(withAlpha($color, $alpha));
                }
                return $varInjector(replaceAlpha($color, $alpha));
            });
            $replacedParts[] = "drop-shadow({$replaced})";
        }
        return [decl($property, implode(' ', $replacedParts))];
    };
    // drop-shadow-none
    $builder->staticUtility('drop-shadow-none', [fn() => $filterProperties(), ['--tw-drop-shadow', ' '], ['filter', $cssFilterValue]]);
    // drop-shadow functional utility
    $builder->getUtilities()->functional('drop-shadow', function ($candidate) use ($theme, $filterProperties, $cssFilterValue, $alphaReplacedDropShadowProperties) {
        $modifier = $candidate['modifier'] ?? null;
        $alpha = null;
        // Parse alpha from modifier
        if ($modifier !== null) {
            if ($modifier['kind'] === 'arbitrary') {
                $alpha = $modifier['value'];
            } elseif (isPositiveInteger($modifier['value'])) {
                $alpha = "{$modifier['value']}%";
            }
        }
        // No value - default drop-shadow
        if (!isset($candidate['value'])) {
            $value = $theme->get(['--drop-shadow']);
            $resolved = $theme->resolve(null, ['--drop-shadow']);
            if ($value === null || $resolved === null) {
                return null;
            }
            $segments = segment($resolved, ',');
            $dropShadowParts = array_map(fn($v) => "drop-shadow({$v})", array_map('trim', $segments));
            return array_merge([$filterProperties()], $alpha !== null ? [decl('--tw-drop-shadow-alpha', $alpha)] : [], $alphaReplacedDropShadowProperties('--tw-drop-shadow-size', $value, $alpha, fn($color) => "var(--tw-drop-shadow-color, {$color})"), [decl('--tw-drop-shadow', implode(' ', $dropShadowParts))], [decl('filter', $cssFilterValue)]);
        }
        $candidateValue = $candidate['value'];
        // Arbitrary values
        if ($candidateValue['kind'] === 'arbitrary') {
            $value = $candidateValue['value'];
            $type = $candidateValue['dataType'] ?? inferDataType($value, ['color']);
            if ($type === 'color') {
                $value = asColor($value, $modifier, $theme);
                if ($value === null) {
                    return null;
                }
                return [$filterProperties(), decl('--tw-drop-shadow-color', withAlpha($value, 'var(--tw-drop-shadow-alpha)')), decl('--tw-drop-shadow', 'var(--tw-drop-shadow-size)')];
            }
            // Shadow arbitrary value
            if ($modifier !== null && $alpha === null) {
                return null;
            }
            return array_merge([$filterProperties()], $alpha !== null ? [decl('--tw-drop-shadow-alpha', $alpha)] : [], $alphaReplacedDropShadowProperties('--tw-drop-shadow-size', $value, $alpha, fn($color) => "var(--tw-drop-shadow-color, {$color})"), [decl('--tw-drop-shadow', 'var(--tw-drop-shadow-size)')], [decl('filter', $cssFilterValue)]);
        }
        $namedValue = $candidateValue['value'] ?? null;
        // Shadow size (xl, lg, etc.)
        $shadowValue = $theme->get(["--drop-shadow-{$namedValue}"]);
        $resolved = $theme->resolve($namedValue, ['--drop-shadow']);
        if ($shadowValue !== null && $resolved !== null) {
            if ($modifier !== null && $alpha === null) {
                return null;
            }
            if ($alpha !== null) {
                return array_merge([$filterProperties()], [decl('--tw-drop-shadow-alpha', $alpha)], $alphaReplacedDropShadowProperties('--tw-drop-shadow-size', $shadowValue, $alpha, fn($color) => "var(--tw-drop-shadow-color, {$color})"), [decl('--tw-drop-shadow', 'var(--tw-drop-shadow-size)')], [decl('filter', $cssFilterValue)]);
            }
            $segments = segment($resolved, ',');
            $dropShadowParts = array_map(fn($v) => "drop-shadow({$v})", array_map('trim', $segments));
            return array_merge([$filterProperties()], $alpha !== null ? [decl('--tw-drop-shadow-alpha', $alpha)] : [], $alphaReplacedDropShadowProperties('--tw-drop-shadow-size', $shadowValue, $alpha, fn($color) => "var(--tw-drop-shadow-color, {$color})"), [decl('--tw-drop-shadow', implode(' ', $dropShadowParts))], [decl('filter', $cssFilterValue)]);
        }
        // Shadow color (red-500, current, inherit, transparent, etc.)
        $colorValue = resolveThemeColor($candidate, $theme, ['--drop-shadow-color', '--color']);
        if ($colorValue !== null) {
            if ($colorValue === 'inherit') {
                return [$filterProperties(), decl('--tw-drop-shadow-color', 'inherit'), decl('--tw-drop-shadow', 'var(--tw-drop-shadow-size)')];
            }
            return [$filterProperties(), decl('--tw-drop-shadow-color', withAlpha($colorValue, 'var(--tw-drop-shadow-alpha)')), decl('--tw-drop-shadow', 'var(--tw-drop-shadow-size)')];
        }
        return null;
    });
    // ==================================================
    // backdrop-opacity
    // ==================================================
    $builder->functionalUtility('backdrop-opacity', ['themeKeys' => ['--backdrop-opacity', '--opacity'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        if (!isValidOpacityValue($value['value'])) {
            return null;
        }
        return "{$value['value']}%";
    }, 'handle' => function ($value) use ($cssBackdropFilterValue) {
        return [decl('--tw-backdrop-opacity', "opacity({$value})"), decl('-webkit-backdrop-filter', $cssBackdropFilterValue), decl('backdrop-filter', $cssBackdropFilterValue)];
    }]);
}
