<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\atRoot;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Utils\inferDataType;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
use function BlockstudioVendor\TailwindPHP\Utils\replaceShadowColors;
/**
 * Replace shadow colors with a CSS variable reference.
 *
 * @param string $shadow The shadow value
 * @param string $varName The CSS variable name (without var())
 * @return string The transformed shadow value
 */
function replaceShadowColor(string $shadow, string $varName): string
{
    return replaceShadowColors($shadow, function (string $color) use ($varName) {
        // Convert color to hex if it's a named color
        $hexColor = colorToHex($color);
        return "var({$varName}, {$hexColor})";
    });
}
/**
 * Convert rgb() colors to hex for shadow fallbacks.
 *
 * @param string $color Color value (rgb(), hex, or named)
 * @return string Hex color or original value
 */
function colorToHex(string $color): string
{
    // Handle rgb(0 0 0 / .05) and similar formats
    if (str_starts_with($color, 'rgb(')) {
        // Pattern: rgb(r g b / alpha) or rgb(r g b / .alpha)
        if (preg_match('/rgb\(\s*(\d+)\s+(\d+)\s+(\d+)\s*\/\s*([\d.]+%?)\s*\)/', $color, $m)) {
            $r = (int) $m[1];
            $g = (int) $m[2];
            $b = (int) $m[3];
            $alphaStr = $m[4];
            // Parse alpha value
            if (str_ends_with($alphaStr, '%')) {
                $alpha = (float) rtrim($alphaStr, '%') / 100;
            } elseif (str_starts_with($alphaStr, '.')) {
                $alpha = (float) ('0' . $alphaStr);
            } elseif (str_starts_with($alphaStr, '0.')) {
                $alpha = (float) $alphaStr;
            } else {
                $alpha = (float) $alphaStr;
                if ($alpha > 1) {
                    $alpha = $alpha / 100;
                    // Assume it's a percentage without %
                }
            }
            // Convert to hex
            $rHex = str_pad(dechex($r), 2, '0', \STR_PAD_LEFT);
            $gHex = str_pad(dechex($g), 2, '0', \STR_PAD_LEFT);
            $bHex = str_pad(dechex($b), 2, '0', \STR_PAD_LEFT);
            $alphaHex = str_pad(dechex((int) round($alpha * 255)), 2, '0', \STR_PAD_LEFT);
            return "#{$rHex}{$gHex}{$bHex}{$alphaHex}";
        }
    }
    return $color;
}
/**
 * Effects Utilities
 *
 * Port of effects utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - opacity
 * - box-shadow (shadow-*)
 * - mix-blend-mode
 * - background-blend-mode
 */
/**
 * Register effects utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerEffectsUtilities(UtilityBuilder $builder): void
{
    // ==================================================
    // Opacity
    // ==================================================
    $builder->functionalUtility('opacity', ['themeKeys' => ['--opacity'], 'defaultValue' => null, 'handleBareValue' => function ($value) {
        // Handle both integers and decimals: opacity-15 (=0.15), opacity-2.5 (=0.025)
        // Valid decimal increments: .5, .25, .75 only
        if (preg_match('/^(\d+)(\.(?:5|25|75))?$/', $value['value'], $m)) {
            $numericVal = (float) $value['value'];
            // Reject invalid values (> 100%)
            if ($numericVal > 100) {
                return null;
            }
            // Divide by 100 to get decimal (15 -> 0.15, 2.5 -> 0.025)
            $val = $numericVal / 100;
            // Format the value properly (e.g., 0.15 -> .15, 0.025 -> .025)
            $formatted = rtrim(number_format($val, 4, '.', ''), '0');
            $formatted = rtrim($formatted, '.');
            // Remove leading zero
            if (str_starts_with($formatted, '0.')) {
                $formatted = substr($formatted, 1);
            }
            return $formatted ?: '0';
        }
        return null;
    }, 'handle' => function ($value) {
        return [decl('opacity', $value)];
    }]);
    // ==================================================
    // Box Shadow
    // ==================================================
    // Shadow/Ring stacking system - combined box-shadow value
    $cssBoxShadowValue = 'var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow)';
    $nullShadow = '0 0 #0000';
    // Box shadow property rules for stacking
    $boxShadowProperties = function () use ($nullShadow) {
        return atRoot([
            property('--tw-shadow', $nullShadow),
            property('--tw-shadow-color'),
            property('--tw-shadow-alpha', '100%', '<percentage>'),
            property('--tw-inset-shadow', $nullShadow),
            property('--tw-inset-shadow-color'),
            property('--tw-inset-shadow-alpha', '100%', '<percentage>'),
            property('--tw-ring-color'),
            property('--tw-ring-shadow', $nullShadow),
            property('--tw-inset-ring-color'),
            property('--tw-inset-ring-shadow', $nullShadow),
            // Legacy
            property('--tw-ring-inset'),
            property('--tw-ring-offset-width', '0px', '<length>'),
            property('--tw-ring-offset-color', '#fff'),
            property('--tw-ring-offset-shadow', $nullShadow),
        ]);
    };
    $theme = $builder->getTheme();
    // Helper to create alpha-replaced shadow properties
    $alphaReplacedShadowProperties = function (string $property, string $value, ?string $alpha, callable $varInjector, string $prefix = '') use ($theme): array {
        $replacedValue = replaceShadowColors($value, function ($color) use ($alpha, $varInjector, $theme) {
            if ($alpha === null) {
                // Convert rgb() colors to hex for the fallback value
                return $varInjector(colorToHex($color));
            }
            // When the input is currentcolor, use color-mix approach
            if (str_starts_with($color, 'current')) {
                return $varInjector(withAlpha($color, $alpha));
            }
            return $varInjector(replaceAlpha($color, $alpha));
        });
        // Apply prefix if needed (for inset shadows)
        if ($prefix !== '') {
            $parts = array_map(function ($part) use ($prefix) {
                return $prefix . trim($part);
            }, explode(',', $replacedValue));
            $replacedValue = implode(',', $parts);
        }
        return [decl($property, $replacedValue)];
    };
    // shadow-initial static utility
    $builder->staticUtility('shadow-initial', [fn() => $boxShadowProperties(), ['--tw-shadow-color', 'initial']]);
    // Shadow utility - handles size with alpha modifiers and colors
    $builder->getUtilities()->functional('shadow', function ($candidate) use ($theme, $boxShadowProperties, $cssBoxShadowValue, $alphaReplacedShadowProperties, $nullShadow) {
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
        // No value - default shadow
        if (!isset($candidate['value'])) {
            $value = $theme->get(['--shadow']);
            if ($value === null) {
                return null;
            }
            return array_merge([$boxShadowProperties()], $alpha !== null ? [decl('--tw-shadow-alpha', $alpha)] : [], $alphaReplacedShadowProperties('--tw-shadow', $value, $alpha, fn($color) => "var(--tw-shadow-color, {$color})"), [decl('box-shadow', $cssBoxShadowValue)]);
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
                return [$boxShadowProperties(), decl('--tw-shadow-color', withAlpha($value, 'var(--tw-shadow-alpha)'))];
            }
            // Shadow arbitrary value
            return array_merge([$boxShadowProperties()], $alpha !== null ? [decl('--tw-shadow-alpha', $alpha)] : [], $alphaReplacedShadowProperties('--tw-shadow', $value, $alpha, fn($color) => "var(--tw-shadow-color, {$color})"), [decl('box-shadow', $cssBoxShadowValue)]);
        }
        $namedValue = $candidateValue['value'] ?? null;
        // Static values
        switch ($namedValue) {
            case 'none':
                if ($modifier !== null) {
                    return null;
                }
                return [$boxShadowProperties(), decl('--tw-shadow', $nullShadow), decl('box-shadow', $cssBoxShadowValue)];
            case 'inherit':
                if ($modifier !== null) {
                    return null;
                }
                return [$boxShadowProperties(), decl('--tw-shadow-color', 'inherit')];
        }
        // Shadow size (sm, md, lg, xl, 2xl)
        $shadowValue = $theme->get(["--shadow-{$namedValue}"]);
        if ($shadowValue !== null) {
            return array_merge([$boxShadowProperties()], $alpha !== null ? [decl('--tw-shadow-alpha', $alpha)] : [], $alphaReplacedShadowProperties('--tw-shadow', $shadowValue, $alpha, fn($color) => "var(--tw-shadow-color, {$color})"), [decl('box-shadow', $cssBoxShadowValue)]);
        }
        // Shadow color (red-500, current, transparent, etc.)
        $colorValue = resolveThemeColor($candidate, $theme, ['--box-shadow-color', '--color']);
        if ($colorValue !== null) {
            return [$boxShadowProperties(), decl('--tw-shadow-color', withAlpha($colorValue, 'var(--tw-shadow-alpha)'))];
        }
        return null;
    });
    // inset-shadow-initial static utility
    $builder->staticUtility('inset-shadow-initial', [fn() => $boxShadowProperties(), ['--tw-inset-shadow-color', 'initial']]);
    // Inset shadow utility
    $builder->getUtilities()->functional('inset-shadow', function ($candidate) use ($theme, $boxShadowProperties, $cssBoxShadowValue, $alphaReplacedShadowProperties, $nullShadow) {
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
        // No value - default inset shadow
        if (!isset($candidate['value'])) {
            $value = $theme->get(['--inset-shadow']);
            if ($value === null) {
                return null;
            }
            return array_merge([$boxShadowProperties()], $alpha !== null ? [decl('--tw-inset-shadow-alpha', $alpha)] : [], $alphaReplacedShadowProperties('--tw-inset-shadow', $value, $alpha, fn($color) => "var(--tw-inset-shadow-color, {$color})"), [decl('box-shadow', $cssBoxShadowValue)]);
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
                return [$boxShadowProperties(), decl('--tw-inset-shadow-color', withAlpha($value, 'var(--tw-inset-shadow-alpha)'))];
            }
            // Shadow arbitrary value
            return array_merge([$boxShadowProperties()], $alpha !== null ? [decl('--tw-inset-shadow-alpha', $alpha)] : [], $alphaReplacedShadowProperties('--tw-inset-shadow', $value, $alpha, fn($color) => "var(--tw-inset-shadow-color, {$color})", 'inset '), [decl('box-shadow', $cssBoxShadowValue)]);
        }
        $namedValue = $candidateValue['value'] ?? null;
        // Static values
        switch ($namedValue) {
            case 'none':
                if ($modifier !== null) {
                    return null;
                }
                return [$boxShadowProperties(), decl('--tw-inset-shadow', $nullShadow), decl('box-shadow', $cssBoxShadowValue)];
            case 'inherit':
                if ($modifier !== null) {
                    return null;
                }
                return [$boxShadowProperties(), decl('--tw-inset-shadow-color', 'inherit')];
        }
        // Inset shadow size
        $shadowValue = $theme->get(["--inset-shadow-{$namedValue}"]);
        if ($shadowValue !== null) {
            return array_merge([$boxShadowProperties()], $alpha !== null ? [decl('--tw-inset-shadow-alpha', $alpha)] : [], $alphaReplacedShadowProperties('--tw-inset-shadow', $shadowValue, $alpha, fn($color) => "var(--tw-inset-shadow-color, {$color})"), [decl('box-shadow', $cssBoxShadowValue)]);
        }
        // Inset shadow color
        $colorValue = resolveThemeColor($candidate, $theme, ['--box-shadow-color', '--color']);
        if ($colorValue !== null) {
            return [$boxShadowProperties(), decl('--tw-inset-shadow-color', withAlpha($colorValue, 'var(--tw-inset-shadow-alpha)'))];
        }
        return null;
    });
    // ==================================================
    // Ring utilities
    // ==================================================
    // ring-inset static utility
    $builder->staticUtility('ring-inset', [fn() => $boxShadowProperties(), ['--tw-ring-inset', 'inset']]);
    // Ring shadow value generator
    $defaultRingColor = $theme->get(['--default-ring-color']) ?? 'currentcolor';
    $ringShadowValue = function (string $value) use ($defaultRingColor) {
        return "var(--tw-ring-inset,) 0 0 0 calc({$value} + var(--tw-ring-offset-width)) var(--tw-ring-color, {$defaultRingColor})";
    };
    // ring utility - width and color
    $builder->getUtilities()->functional('ring', function ($candidate) use ($theme, $boxShadowProperties, $cssBoxShadowValue, $ringShadowValue) {
        $candidateValue = $candidate['value'] ?? null;
        $modifier = $candidate['modifier'] ?? null;
        // No value = default ring width
        if ($candidateValue === null) {
            if ($modifier !== null) {
                return null;
            }
            $value = $theme->get(['--default-ring-width']) ?? '1px';
            return [$boxShadowProperties(), decl('--tw-ring-shadow', $ringShadowValue($value)), decl('box-shadow', $cssBoxShadowValue)];
        }
        // Handle arbitrary values
        if ($candidateValue['kind'] === 'arbitrary') {
            $value = $candidateValue['value'];
            $type = $candidateValue['dataType'] ?? inferDataType($value, ['color', 'length']);
            if ($type === 'length') {
                if ($modifier !== null) {
                    return null;
                }
                return [$boxShadowProperties(), decl('--tw-ring-shadow', $ringShadowValue($value)), decl('box-shadow', $cssBoxShadowValue)];
            }
            // Color arbitrary value
            $value = asColor($value, $modifier, $theme);
            if ($value === null) {
                return null;
            }
            return [decl('--tw-ring-color', $value)];
        }
        $namedValue = $candidateValue['value'] ?? null;
        // Ring color
        $colorValue = resolveThemeColor($candidate, $theme, ['--ring-color', '--color']);
        if ($colorValue !== null) {
            return [decl('--tw-ring-color', $colorValue)];
        }
        // Ring width
        if ($modifier !== null) {
            return null;
        }
        $widthValue = $theme->resolve($namedValue, ['--ring-width']);
        if ($widthValue === null && isPositiveInteger($namedValue)) {
            $widthValue = "{$namedValue}px";
        }
        if ($widthValue !== null) {
            return [$boxShadowProperties(), decl('--tw-ring-shadow', $ringShadowValue($widthValue)), decl('box-shadow', $cssBoxShadowValue)];
        }
        return null;
    });
    // inset-ring utility
    $insetRingShadowValue = function (string $value) {
        return "inset 0 0 0 {$value} var(--tw-inset-ring-color, currentcolor)";
    };
    $builder->getUtilities()->functional('inset-ring', function ($candidate) use ($theme, $boxShadowProperties, $cssBoxShadowValue, $insetRingShadowValue) {
        $candidateValue = $candidate['value'] ?? null;
        $modifier = $candidate['modifier'] ?? null;
        // No value = default 1px
        if ($candidateValue === null) {
            if ($modifier !== null) {
                return null;
            }
            return [$boxShadowProperties(), decl('--tw-inset-ring-shadow', $insetRingShadowValue('1px')), decl('box-shadow', $cssBoxShadowValue)];
        }
        // Handle arbitrary values
        if ($candidateValue['kind'] === 'arbitrary') {
            $value = $candidateValue['value'];
            $type = $candidateValue['dataType'] ?? inferDataType($value, ['color', 'length']);
            if ($type === 'length') {
                if ($modifier !== null) {
                    return null;
                }
                return [$boxShadowProperties(), decl('--tw-inset-ring-shadow', $insetRingShadowValue($value)), decl('box-shadow', $cssBoxShadowValue)];
            }
            // Color arbitrary value
            $value = asColor($value, $modifier, $theme);
            if ($value === null) {
                return null;
            }
            return [decl('--tw-inset-ring-color', $value)];
        }
        $namedValue = $candidateValue['value'] ?? null;
        // Ring color
        $colorValue = resolveThemeColor($candidate, $theme, ['--ring-color', '--color']);
        if ($colorValue !== null) {
            return [decl('--tw-inset-ring-color', $colorValue)];
        }
        // Ring width
        if ($modifier !== null) {
            return null;
        }
        $widthValue = $theme->resolve($namedValue, ['--ring-width']);
        if ($widthValue === null && isPositiveInteger($namedValue)) {
            $widthValue = "{$namedValue}px";
        }
        if ($widthValue !== null) {
            return [$boxShadowProperties(), decl('--tw-inset-ring-shadow', $insetRingShadowValue($widthValue)), decl('box-shadow', $cssBoxShadowValue)];
        }
        return null;
    });
    // ring-offset utility
    $ringOffsetShadowValue = 'var(--tw-ring-inset,) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color)';
    $builder->getUtilities()->functional('ring-offset', function ($candidate) use ($theme, $boxShadowProperties, $cssBoxShadowValue, $ringOffsetShadowValue) {
        $candidateValue = $candidate['value'] ?? null;
        $modifier = $candidate['modifier'] ?? null;
        // No value = no match
        if ($candidateValue === null) {
            return null;
        }
        // Handle arbitrary values
        if ($candidateValue['kind'] === 'arbitrary') {
            $value = $candidateValue['value'];
            $type = $candidateValue['dataType'] ?? inferDataType($value, ['color', 'length']);
            if ($type === 'length') {
                if ($modifier !== null) {
                    return null;
                }
                return [$boxShadowProperties(), decl('--tw-ring-offset-width', $value), decl('--tw-ring-offset-shadow', $ringOffsetShadowValue), decl('box-shadow', $cssBoxShadowValue)];
            }
            // Color arbitrary value
            $value = asColor($value, $modifier, $theme);
            if ($value === null) {
                return null;
            }
            return [decl('--tw-ring-offset-color', $value)];
        }
        $namedValue = $candidateValue['value'] ?? null;
        // ring-offset-inset
        if ($namedValue === 'inset') {
            return [decl('--tw-ring-inset', 'inset')];
        }
        // Offset color
        $colorValue = resolveThemeColor($candidate, $theme, ['--ring-offset-color', '--color']);
        if ($colorValue !== null) {
            return [decl('--tw-ring-offset-color', $colorValue)];
        }
        // Offset width
        if ($modifier !== null) {
            return null;
        }
        $widthValue = $theme->resolve($namedValue, ['--ring-offset-width']);
        if ($widthValue === null && isPositiveInteger($namedValue)) {
            $widthValue = "{$namedValue}px";
        }
        if ($widthValue !== null) {
            return [$boxShadowProperties(), decl('--tw-ring-offset-width', $widthValue), decl('--tw-ring-offset-shadow', $ringOffsetShadowValue), decl('box-shadow', $cssBoxShadowValue)];
        }
        return null;
    });
    // NOTE: drop-shadow utility is implemented in filters.php with full color and modifier support
    // ==================================================
    // Mix Blend Mode
    // ==================================================
    $blendModes = ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity', 'plus-darker', 'plus-lighter'];
    foreach ($blendModes as $mode) {
        $builder->staticUtility("mix-blend-{$mode}", [['mix-blend-mode', $mode]]);
    }
    // ==================================================
    // Background Blend Mode
    // ==================================================
    foreach ($blendModes as $mode) {
        $builder->staticUtility("bg-blend-{$mode}", [['background-blend-mode', $mode]]);
    }
    // ==================================================
    // Mask Clip
    // ==================================================
    $maskClipValues = ['border' => 'border-box', 'padding' => 'padding-box', 'content' => 'content-box', 'fill' => 'fill-box', 'stroke' => 'stroke-box', 'view' => 'view-box'];
    foreach ($maskClipValues as $name => $value) {
        $builder->staticUtility("mask-clip-{$name}", [['-webkit-mask-clip', $value], ['mask-clip', $value]]);
    }
    $builder->staticUtility('mask-no-clip', [['-webkit-mask-clip', 'no-clip'], ['mask-clip', 'no-clip']]);
    // ==================================================
    // Mask Origin
    // ==================================================
    $maskOriginValues = ['border' => 'border-box', 'padding' => 'padding-box', 'content' => 'content-box', 'fill' => 'fill-box', 'stroke' => 'stroke-box', 'view' => 'view-box'];
    foreach ($maskOriginValues as $name => $value) {
        $builder->staticUtility("mask-origin-{$name}", [['-webkit-mask-origin', $value], ['mask-origin', $value]]);
    }
}
