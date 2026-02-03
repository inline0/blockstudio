<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\atRoot;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Ast\styleRule;
use function BlockstudioVendor\TailwindPHP\Utils\inferDataType;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
use function BlockstudioVendor\TailwindPHP\Utils\isValidSpacingMultiplier;
use function BlockstudioVendor\TailwindPHP\Utils\replaceShadowColors;
/**
 * Typography Utilities
 *
 * Port of typography utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - font-family (font-sans, font-serif, font-mono)
 * - font-style (italic, not-italic)
 * - font-weight (font-thin, font-bold, etc.)
 * - font-size (text-sm, text-lg, etc.)
 * - line-height (leading-*)
 * - letter-spacing (tracking-*)
 * - text-decoration (underline, line-through, etc.)
 * - text-transform (uppercase, lowercase, capitalize)
 * - text-align (text-left, text-center, etc.)
 * - text-wrap (text-wrap, text-nowrap, etc.)
 * - whitespace (whitespace-normal, whitespace-nowrap, etc.)
 * - word-break (break-normal, break-words, etc.)
 * - hyphens (hyphens-none, hyphens-manual, hyphens-auto)
 * - list-style (list-none, list-disc, list-decimal)
 * - vertical-align (align-baseline, align-top, etc.)
 */
/**
 * Register typography utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerTypographyUtilities(UtilityBuilder $builder): void
{
    // ==================================================
    // Text (color and font-size)
    // ==================================================
    $theme = $builder->getTheme();
    $utilities = $builder->getUtilities();
    $utilities->functional('text', function (array $candidate) use ($theme) {
        if (!isset($candidate['value'])) {
            return null;
        }
        // Handle arbitrary values
        if ($candidate['value']['kind'] === 'arbitrary') {
            $value = $candidate['value']['value'];
            $type = $candidate['value']['dataType'] ?? inferDataType($value, ['color', 'length', 'percentage', 'absolute-size', 'relative-size']);
            switch ($type) {
                case 'size':
                case 'length':
                case 'percentage':
                case 'absolute-size':
                case 'relative-size':
                    if (isset($candidate['modifier'])) {
                        $modifier = null;
                        if ($candidate['modifier']['kind'] === 'arbitrary') {
                            $modifier = $candidate['modifier']['value'];
                        } else {
                            $modifier = $theme->resolve($candidate['modifier']['value'], ['--leading']);
                            if (!$modifier && isValidSpacingMultiplier($candidate['modifier']['value'])) {
                                $multiplier = $theme->resolve(null, ['--spacing']);
                                if ($multiplier) {
                                    $modifier = "calc({$multiplier} * {$candidate['modifier']['value']})";
                                }
                            }
                            // Shorthand for leading-none
                            if (!$modifier && $candidate['modifier']['value'] === 'none') {
                                $modifier = '1';
                            }
                        }
                        if ($modifier) {
                            return [decl('font-size', $value), decl('line-height', $modifier)];
                        }
                        return null;
                    }
                    return [decl('font-size', $value)];
                default:
                    $value = asColor($value, $candidate['modifier'] ?? null, $theme);
                    if ($value === null) {
                        return null;
                    }
                    return [decl('color', $value)];
            }
        }
        // Try color first (--text-color, --color)
        $value = resolveThemeColor($candidate, $theme, ['--text-color', '--color']);
        if ($value !== null) {
            return [decl('color', $value)];
        }
        // Try font-size (--text namespace)
        $result = $theme->resolveWith($candidate['value']['value'], ['--text'], ['--line-height', '--letter-spacing', '--font-weight']);
        if ($result !== null) {
            [$fontSize, $options] = $result;
            if (isset($candidate['modifier'])) {
                $modifier = null;
                if ($candidate['modifier']['kind'] === 'arbitrary') {
                    $modifier = $candidate['modifier']['value'];
                } else {
                    $modifier = $theme->resolve($candidate['modifier']['value'], ['--leading']);
                    if (!$modifier && isValidSpacingMultiplier($candidate['modifier']['value'])) {
                        $multiplier = $theme->resolve(null, ['--spacing']);
                        if ($multiplier) {
                            $modifier = "calc({$multiplier} * {$candidate['modifier']['value']})";
                        }
                    }
                    // Shorthand for leading-none
                    if (!$modifier && $candidate['modifier']['value'] === 'none') {
                        $modifier = '1';
                    }
                }
                if (!$modifier) {
                    return null;
                }
                return [decl('font-size', $fontSize), decl('line-height', $modifier)];
            }
            if (is_string($options)) {
                return [decl('font-size', $fontSize), decl('line-height', $options)];
            }
            $declarations = [decl('font-size', $fontSize)];
            if (isset($options['--line-height'])) {
                $declarations[] = decl('line-height', "var(--tw-leading, {$options['--line-height']})");
            }
            if (isset($options['--letter-spacing'])) {
                $declarations[] = decl('letter-spacing', "var(--tw-tracking, {$options['--letter-spacing']})");
            }
            if (isset($options['--font-weight'])) {
                $declarations[] = decl('font-weight', "var(--tw-font-weight, {$options['--font-weight']})");
            }
            return $declarations;
        }
        return null;
    });
    $builder->suggest('text', fn() => [['values' => ['current', 'inherit', 'transparent'], 'valueThemeKeys' => ['--text-color', '--color'], 'modifiers' => array_map(fn($i) => (string) ($i * 5), range(0, 20))], ['values' => [], 'valueThemeKeys' => ['--text'], 'modifiers' => [], 'modifierThemeKeys' => ['--leading']]]);
    // Font Style
    $builder->staticUtility('italic', [['font-style', 'italic']]);
    $builder->staticUtility('not-italic', [['font-style', 'normal']]);
    // Font Stretch
    $fontStretchKeywords = ['ultra-condensed', 'extra-condensed', 'condensed', 'semi-condensed', 'normal', 'semi-expanded', 'expanded', 'extra-expanded', 'ultra-expanded'];
    $builder->functionalUtility('font-stretch', ['themeKeys' => [], 'handleBareValue' => function ($value) use ($fontStretchKeywords) {
        // Handle percentage values (50%, 100%, 200%)
        if (preg_match('/^(\d+)%$/', $value['value'], $m)) {
            $percent = (int) $m[1];
            // Valid range is 50% to 200%
            if ($percent >= 50 && $percent <= 200 && $percent % 1 === 0) {
                return $value['value'];
            }
            return null;
        }
        return null;
    }, 'handle' => function ($value) {
        return [decl('font-stretch', $value)];
    }, 'staticValues' => array_combine($fontStretchKeywords, array_map(fn($kw) => [decl('font-stretch', $kw)], $fontStretchKeywords))]);
    // Font Family and Font Weight (combined 'font' utility)
    // This utility handles both font-family (from --font-*) and font-weight (from --font-weight-*)
    $theme = $builder->getTheme();
    $utilities = $builder->getUtilities();
    // Static font-weight values
    $staticWeights = ['thin' => '100', 'extralight' => '200', 'light' => '300', 'normal' => '400', 'medium' => '500', 'semibold' => '600', 'bold' => '700', 'extrabold' => '800', 'black' => '900'];
    $utilities->functional('font', function ($candidate) use ($theme, $staticWeights) {
        if (!isset($candidate['value']) || $candidate['modifier'] !== null) {
            return null;
        }
        if ($candidate['value']['kind'] === 'named') {
            $name = $candidate['value']['value'];
            // Try to resolve from --font-* (font family) first
            $fontFamilyValue = $theme->resolve($name, ['--font']);
            if ($fontFamilyValue !== null) {
                return [decl('font-family', $fontFamilyValue)];
            }
            // Try to resolve from --font-weight-* (theme values)
            $fontWeightValue = $theme->resolve($name, ['--font-weight']);
            if ($fontWeightValue !== null) {
                return [atRoot([property('--tw-font-weight')]), decl('--tw-font-weight', $fontWeightValue), decl('font-weight', $fontWeightValue)];
            }
            // Fall back to static font-weight values (thin, bold, etc.)
            if (isset($staticWeights[$name])) {
                return [atRoot([property('--tw-font-weight')]), decl('--tw-font-weight', $staticWeights[$name]), decl('font-weight', $staticWeights[$name])];
            }
            return null;
        }
        if ($candidate['value']['kind'] === 'arbitrary') {
            $value = $candidate['value']['value'];
            $dataType = $candidate['value']['dataType'] ?? null;
            // If data type indicates font family
            if ($dataType === 'generic-name' || $dataType === 'family-name') {
                return [decl('font-family', $value)];
            }
            // Default to font-weight for arbitrary values
            return [atRoot([property('--tw-font-weight')]), decl('--tw-font-weight', $value), decl('font-weight', $value)];
        }
        return null;
    }, ['types' => ['font-weight', 'generic-name', 'family-name']]);
    // Add suggestions for font utility
    $builder->suggest('font', function () {
        return [['values' => [], 'valueThemeKeys' => ['--font']], ['values' => [], 'valueThemeKeys' => ['--font-weight']]];
    });
    // Text Decoration Line
    $builder->staticUtility('underline', [['text-decoration-line', 'underline']]);
    $builder->staticUtility('overline', [['text-decoration-line', 'overline']]);
    $builder->staticUtility('line-through', [['text-decoration-line', 'line-through']]);
    $builder->staticUtility('no-underline', [['text-decoration-line', 'none']]);
    // Text Decoration Style
    $builder->staticUtility('decoration-solid', [['text-decoration-style', 'solid']]);
    $builder->staticUtility('decoration-double', [['text-decoration-style', 'double']]);
    $builder->staticUtility('decoration-dotted', [['text-decoration-style', 'dotted']]);
    $builder->staticUtility('decoration-dashed', [['text-decoration-style', 'dashed']]);
    $builder->staticUtility('decoration-wavy', [['text-decoration-style', 'wavy']]);
    // Text Decoration Thickness
    $builder->staticUtility('decoration-auto', [['text-decoration-thickness', 'auto']]);
    $builder->staticUtility('decoration-from-font', [['text-decoration-thickness', 'from-font']]);
    // Text Decoration Color / Thickness (functional)
    $builder->getUtilities()->functional('decoration', function ($candidate) use ($theme) {
        if (!isset($candidate['value'])) {
            return null;
        }
        $candidateValue = $candidate['value'];
        // Handle arbitrary values
        if ($candidateValue['kind'] === 'arbitrary') {
            $value = $candidateValue['value'];
            $type = $candidateValue['dataType'] ?? inferDataType($value, ['color', 'length', 'percentage']);
            switch ($type) {
                case 'length':
                case 'percentage':
                    if (isset($candidate['modifier'])) {
                        return null;
                    }
                    return [decl('text-decoration-thickness', $value)];
                default:
                    $colorValue = asColor($value, $candidate['modifier'] ?? null, $theme);
                    if ($colorValue === null) {
                        return null;
                    }
                    return [decl('text-decoration-color', $colorValue)];
            }
        }
        // text-decoration-thickness from theme
        $thicknessValue = $theme->resolve($candidateValue['value'] ?? null, ['--text-decoration-thickness']);
        if ($thicknessValue) {
            if (isset($candidate['modifier'])) {
                return null;
            }
            return [decl('text-decoration-thickness', $thicknessValue)];
        }
        // Bare integer values for thickness (e.g., decoration-2 = 2px)
        if (isPositiveInteger($candidateValue['value'] ?? '')) {
            if (isset($candidate['modifier'])) {
                return null;
            }
            return [decl('text-decoration-thickness', "{$candidateValue['value']}px")];
        }
        // text-decoration-color from theme
        $colorValue = resolveThemeColor($candidate, $theme, ['--text-decoration-color', '--color']);
        if ($colorValue) {
            return [decl('text-decoration-color', $colorValue)];
        }
        return null;
    });
    // Text Underline Offset
    $builder->functionalUtility('underline-offset', ['themeKeys' => ['--text-underline-offset'], 'defaultValue' => null, 'supportsNegative' => \true, 'handleBareValue' => function ($value) {
        if (!isPositiveInteger($value['value'])) {
            return null;
        }
        return "{$value['value']}px";
    }, 'handle' => function ($value) {
        return [decl('text-underline-offset', $value)];
    }, 'staticValues' => ['auto' => [decl('text-underline-offset', 'auto')]]]);
    // Text Transform
    $builder->staticUtility('uppercase', [['text-transform', 'uppercase']]);
    $builder->staticUtility('lowercase', [['text-transform', 'lowercase']]);
    $builder->staticUtility('capitalize', [['text-transform', 'capitalize']]);
    $builder->staticUtility('normal-case', [['text-transform', 'none']]);
    // Text Align
    $builder->staticUtility('text-left', [['text-align', 'left']]);
    $builder->staticUtility('text-center', [['text-align', 'center']]);
    $builder->staticUtility('text-right', [['text-align', 'right']]);
    $builder->staticUtility('text-justify', [['text-align', 'justify']]);
    $builder->staticUtility('text-start', [['text-align', 'start']]);
    $builder->staticUtility('text-end', [['text-align', 'end']]);
    // Text Wrap
    $builder->staticUtility('text-wrap', [['text-wrap', 'wrap']]);
    $builder->staticUtility('text-nowrap', [['text-wrap', 'nowrap']]);
    $builder->staticUtility('text-balance', [['text-wrap', 'balance']]);
    $builder->staticUtility('text-pretty', [['text-wrap', 'pretty']]);
    // Whitespace
    $builder->staticUtility('whitespace-normal', [['white-space', 'normal']]);
    $builder->staticUtility('whitespace-nowrap', [['white-space', 'nowrap']]);
    $builder->staticUtility('whitespace-pre', [['white-space', 'pre']]);
    $builder->staticUtility('whitespace-pre-line', [['white-space', 'pre-line']]);
    $builder->staticUtility('whitespace-pre-wrap', [['white-space', 'pre-wrap']]);
    $builder->staticUtility('whitespace-break-spaces', [['white-space', 'break-spaces']]);
    // Word Break
    $builder->staticUtility('break-normal', [['overflow-wrap', 'normal'], ['word-break', 'normal']]);
    $builder->staticUtility('break-words', [['overflow-wrap', 'break-word']]);
    $builder->staticUtility('break-all', [['word-break', 'break-all']]);
    $builder->staticUtility('break-keep', [['word-break', 'keep-all']]);
    // Hyphens
    $builder->staticUtility('hyphens-none', [['-webkit-hyphens', 'none'], ['hyphens', 'none']]);
    $builder->staticUtility('hyphens-manual', [['-webkit-hyphens', 'manual'], ['hyphens', 'manual']]);
    $builder->staticUtility('hyphens-auto', [['-webkit-hyphens', 'auto'], ['hyphens', 'auto']]);
    // List Style Type
    $builder->functionalUtility('list', ['themeKeys' => ['--list-style-type'], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('list-style-type', $value)];
    }, 'staticValues' => ['none' => [decl('list-style-type', 'none')], 'disc' => [decl('list-style-type', 'disc')], 'decimal' => [decl('list-style-type', 'decimal')]]]);
    // List Style Position
    $builder->staticUtility('list-inside', [['list-style-position', 'inside']]);
    $builder->staticUtility('list-outside', [['list-style-position', 'outside']]);
    // List Style Image
    $builder->functionalUtility('list-image', ['themeKeys' => ['--list-style-image'], 'handle' => function ($value) {
        return [decl('list-style-image', $value)];
    }, 'staticValues' => ['none' => [decl('list-style-image', 'none')]]]);
    // Vertical Align
    $builder->functionalUtility('align', ['themeKeys' => ['--vertical-align'], 'handle' => function ($value) {
        return [decl('vertical-align', $value)];
    }, 'staticValues' => ['baseline' => [decl('vertical-align', 'baseline')], 'top' => [decl('vertical-align', 'top')], 'middle' => [decl('vertical-align', 'middle')], 'bottom' => [decl('vertical-align', 'bottom')], 'text-top' => [decl('vertical-align', 'text-top')], 'text-bottom' => [decl('vertical-align', 'text-bottom')], 'sub' => [decl('vertical-align', 'sub')], 'super' => [decl('vertical-align', 'super')]]]);
    // Line Height (leading)
    $builder->functionalUtility('leading', ['themeKeys' => ['--leading', '--spacing'], 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('--tw-leading', $value), decl('line-height', $value)];
    }, 'handleBareValue' => function ($value) use ($theme) {
        $multiplier = $theme->resolve(null, ['--spacing']);
        if ($multiplier === null) {
            return null;
        }
        if (!isValidSpacingMultiplier($value['value'])) {
            return null;
        }
        return "calc({$multiplier} * {$value['value']})";
    }, 'staticValues' => ['none' => [decl('--tw-leading', '1'), decl('line-height', '1')], 'tight' => [decl('--tw-leading', '1.25'), decl('line-height', '1.25')], 'snug' => [decl('--tw-leading', '1.375'), decl('line-height', '1.375')], 'normal' => [decl('--tw-leading', '1.5'), decl('line-height', '1.5')], 'relaxed' => [decl('--tw-leading', '1.625'), decl('line-height', '1.625')], 'loose' => [decl('--tw-leading', '2'), decl('line-height', '2')]]]);
    // Letter Spacing (tracking)
    $builder->functionalUtility('tracking', ['themeKeys' => ['--tracking', '--letter-spacing'], 'supportsNegative' => \true, 'defaultValue' => null, 'handle' => function ($value) {
        return [decl('--tw-tracking', $value), decl('letter-spacing', $value)];
    }, 'staticValues' => ['tighter' => [decl('--tw-tracking', '-0.05em'), decl('letter-spacing', '-0.05em')], 'tight' => [decl('--tw-tracking', '-0.025em'), decl('letter-spacing', '-0.025em')], 'normal' => [decl('--tw-tracking', '0em'), decl('letter-spacing', '0em')], 'wide' => [decl('--tw-tracking', '0.025em'), decl('letter-spacing', '0.025em')], 'wider' => [decl('--tw-tracking', '0.05em'), decl('letter-spacing', '0.05em')], 'widest' => [decl('--tw-tracking', '0.1em'), decl('letter-spacing', '0.1em')]]]);
    // Text Indent
    $builder->spacingUtility('indent', ['--text-indent', '--spacing'], function ($value) {
        return [decl('text-indent', $value)];
    }, ['supportsNegative' => \true]);
    // Truncate - declaration order matches lightningcss output
    $builder->staticUtility('truncate', [['text-overflow', 'ellipsis'], ['white-space', 'nowrap'], ['overflow', 'hidden']]);
    // Text Overflow
    $builder->staticUtility('text-ellipsis', [['text-overflow', 'ellipsis']]);
    $builder->staticUtility('text-clip', [['text-overflow', 'clip']]);
    // Font Variant Numeric
    // Uses CSS variables to compose multiple numeric features
    $numericVar = 'var(--tw-ordinal, ) var(--tw-slashed-zero, ) var(--tw-numeric-figure, ) var(--tw-numeric-spacing, ) var(--tw-numeric-fraction, )';
    $builder->staticUtility('normal-nums', [['--tw-ordinal', 'initial'], ['--tw-slashed-zero', 'initial'], ['--tw-numeric-figure', 'initial'], ['--tw-numeric-spacing', 'initial'], ['--tw-numeric-fraction', 'initial'], ['font-variant-numeric', 'normal']]);
    $builder->staticUtility('ordinal', [['--tw-ordinal', 'ordinal'], ['font-variant-numeric', $numericVar]]);
    $builder->staticUtility('slashed-zero', [['--tw-slashed-zero', 'slashed-zero'], ['font-variant-numeric', $numericVar]]);
    $builder->staticUtility('lining-nums', [['--tw-numeric-figure', 'lining-nums'], ['font-variant-numeric', $numericVar]]);
    $builder->staticUtility('oldstyle-nums', [['--tw-numeric-figure', 'oldstyle-nums'], ['font-variant-numeric', $numericVar]]);
    $builder->staticUtility('proportional-nums', [['--tw-numeric-spacing', 'proportional-nums'], ['font-variant-numeric', $numericVar]]);
    $builder->staticUtility('tabular-nums', [['--tw-numeric-spacing', 'tabular-nums'], ['font-variant-numeric', $numericVar]]);
    $builder->staticUtility('diagonal-fractions', [['--tw-numeric-fraction', 'diagonal-fractions'], ['font-variant-numeric', $numericVar]]);
    $builder->staticUtility('stacked-fractions', [['--tw-numeric-fraction', 'stacked-fractions'], ['font-variant-numeric', $numericVar]]);
    // Font Smoothing
    $builder->staticUtility('antialiased', [['-webkit-font-smoothing', 'antialiased'], ['-moz-osx-font-smoothing', 'grayscale']]);
    $builder->staticUtility('subpixel-antialiased', [['-webkit-font-smoothing', 'auto'], ['-moz-osx-font-smoothing', 'auto']]);
    // ==================================================
    // Text Shadow
    // ==================================================
    $textShadowProperties = function () {
        return atRoot([property('--tw-text-shadow-color'), property('--tw-text-shadow-alpha', '100%', '<percentage>')]);
    };
    // text-shadow-initial
    $builder->staticUtility('text-shadow-initial', [fn() => $textShadowProperties(), ['--tw-text-shadow-color', 'initial']]);
    // text-shadow - single functional utility that handles all cases
    // Port of TailwindCSS's utilities.functional('text-shadow', ...) from utilities.ts:5180
    $builder->getUtilities()->functional('text-shadow', function ($candidate) use ($theme, $textShadowProperties) {
        // Handle alpha modifier
        $alpha = null;
        $modifier = $candidate['modifier'] ?? null;
        if ($modifier !== null) {
            if (($modifier['kind'] ?? null) === 'arbitrary') {
                $alpha = $modifier['value'] ?? null;
            } else {
                $modValue = $modifier['value'] ?? null;
                if ($modValue !== null && isPositiveInteger($modValue)) {
                    $alpha = "{$modValue}%";
                }
            }
        }
        $candidateValue = $candidate['value'] ?? null;
        // No value = default shadow from theme
        if ($candidateValue === null) {
            $value = $theme->get(['--text-shadow']);
            if ($value === null) {
                return null;
            }
            $result = [$textShadowProperties()];
            if ($alpha !== null) {
                $result[] = decl('--tw-text-shadow-alpha', $alpha);
            }
            $replacedValue = replaceShadowColors($value, fn($color) => "var(--tw-text-shadow-color, {$color})");
            $result[] = decl('text-shadow', $replacedValue);
            return $result;
        }
        // Handle arbitrary values
        if ($candidateValue['kind'] === 'arbitrary') {
            $value = $candidateValue['value'];
            $type = $candidateValue['dataType'] ?? inferDataType($value, ['color']);
            if ($type === 'color') {
                // Arbitrary color value
                $value = asColor($value, $modifier, $theme);
                if ($value === null) {
                    return null;
                }
                return [$textShadowProperties(), decl('--tw-text-shadow-color', withAlpha($value, 'var(--tw-text-shadow-alpha)'))];
            }
            // Arbitrary shadow value
            $result = [$textShadowProperties()];
            if ($alpha !== null) {
                $result[] = decl('--tw-text-shadow-alpha', $alpha);
            }
            $replacedValue = replaceShadowColors($value, function ($color) use ($alpha) {
                if ($alpha === null) {
                    return "var(--tw-text-shadow-color, {$color})";
                }
                if (str_starts_with($color, 'current')) {
                    return 'var(--tw-text-shadow-color, ' . withAlpha($color, $alpha) . ')';
                }
                return 'var(--tw-text-shadow-color, ' . replaceAlpha($color, $alpha) . ')';
            });
            $result[] = decl('text-shadow', $replacedValue);
            return $result;
        }
        // Static values: none, inherit
        $namedValue = $candidateValue['value'] ?? null;
        if ($namedValue === 'none') {
            if ($modifier !== null) {
                return null;
            }
            return [$textShadowProperties(), decl('text-shadow', 'none')];
        }
        if ($namedValue === 'inherit') {
            if ($modifier !== null) {
                return null;
            }
            return [$textShadowProperties(), decl('--tw-text-shadow-color', 'inherit')];
        }
        // Shadow size (e.g., text-shadow-2xs, text-shadow-sm, etc.)
        // Check this BEFORE color to avoid color utility short-circuiting
        $shadowValue = $theme->get(["--text-shadow-{$namedValue}"]);
        if ($shadowValue !== null) {
            $result = [$textShadowProperties()];
            if ($alpha !== null) {
                $result[] = decl('--tw-text-shadow-alpha', $alpha);
            }
            $replacedValue = replaceShadowColors($shadowValue, function ($color) use ($alpha) {
                if ($alpha === null) {
                    return "var(--tw-text-shadow-color, {$color})";
                }
                if (str_starts_with($color, 'current')) {
                    return 'var(--tw-text-shadow-color, ' . withAlpha($color, $alpha) . ')';
                }
                return 'var(--tw-text-shadow-color, ' . replaceAlpha($color, $alpha) . ')';
            });
            $result[] = decl('text-shadow', $replacedValue);
            return $result;
        }
        // Shadow color (e.g., text-shadow-red-500)
        $colorValue = resolveThemeColor($candidate, $theme, ['--text-shadow-color', '--color']);
        if ($colorValue !== null) {
            return [$textShadowProperties(), decl('--tw-text-shadow-color', withAlpha($colorValue, 'var(--tw-text-shadow-alpha)'))];
        }
        // No match
        return null;
    });
    // ==================================================
    // Placeholder Color
    // ==================================================
    $builder->colorUtility('placeholder', ['themeKeys' => ['--background-color', '--color'], 'handle' => function ($value) {
        return [styleRule('&::placeholder', [decl('--tw-sort', 'placeholder-color'), decl('color', $value)])];
    }]);
}
