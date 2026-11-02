<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utilities;

use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Utils\inferDataType;
use function BlockstudioVendor\TailwindPHP\Utils\isPositiveInteger;
/**
 * SVG Utilities
 *
 * Port of SVG utilities from: packages/tailwindcss/src/utilities.ts
 *
 * Includes:
 * - fill
 * - stroke (color and width)
 */
/**
 * Register SVG utilities.
 *
 * @param UtilityBuilder $builder
 * @return void
 */
function registerSvgUtilities(UtilityBuilder $builder): void
{
    $theme = $builder->getTheme();
    // ==================================================
    // Fill
    // ==================================================
    $builder->staticUtility('fill-none', [['fill', 'none']]);
    $builder->colorUtility('fill', ['themeKeys' => ['--fill', '--color'], 'handle' => function ($value) {
        return [decl('fill', $value)];
    }]);
    // ==================================================
    // Stroke
    // ==================================================
    $builder->staticUtility('stroke-none', [['stroke', 'none']]);
    $builder->getUtilities()->functional('stroke', function (array $candidate) use ($theme) {
        if (!isset($candidate['value'])) {
            return null;
        }
        $modifier = $candidate['modifier'] ?? null;
        $value = $candidate['value']['value'];
        if ($candidate['value']['kind'] === 'arbitrary') {
            $type = $candidate['value']['dataType'] ?? inferDataType($value, ['color', 'number', 'length', 'percentage']);
            if (in_array($type, ['number', 'length', 'percentage'], \true)) {
                return $modifier === null ? [decl('stroke-width', $value)] : null;
            }
            $value = asColor($value, $modifier, $theme);
            return $value !== null ? [decl('stroke', $value)] : null;
        }
        $color = resolveThemeColor($candidate, $theme, ['--stroke', '--color']);
        if ($color !== null) {
            return [decl('stroke', $color)];
        }
        $width = $theme->resolve($value, ['--stroke-width']);
        if ($width !== null) {
            return [decl('stroke-width', $width)];
        }
        return isPositiveInteger($value) ? [decl('stroke-width', $value)] : null;
    });
}
