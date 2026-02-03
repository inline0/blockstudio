<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Data type inference for CSS values.
 *
 * Port of: packages/tailwindcss/src/utils/infer-data-type.ts
 *
 * @port-deviation:dispatch TypeScript uses callback functions directly in type checking.
 * PHP uses match expression with fully-qualified function names for clarity.
 *
 * @port-deviation:none Otherwise this is a direct 1:1 port.
 */
const HAS_NUMBER_PATTERN = '[+-]?\d*\.?\d+(?:[eE][+-]?\d+)?';
const LENGTH_UNITS = ['cm', 'mm', 'Q', 'in', 'pc', 'pt', 'px', 'em', 'ex', 'ch', 'rem', 'lh', 'rlh', 'vw', 'vh', 'vmin', 'vmax', 'vb', 'vi', 'svw', 'svh', 'lvw', 'lvh', 'dvw', 'dvh', 'cqw', 'cqh', 'cqi', 'cqb', 'cqmin', 'cqmax'];
const ANGLE_UNITS = ['deg', 'rad', 'grad', 'turn'];
/**
 * Determine the type of a value using syntax rules from CSS specs.
 *
 * @param string $value
 * @param string[] $types
 * @return string|null
 */
function inferDataType(string $value, array $types): ?string
{
    if (str_starts_with($value, 'var(')) {
        return null;
    }
    foreach ($types as $type) {
        $check = match ($type) {
            'color' => 'TailwindPHP\Utils\isColor',
            'length' => 'TailwindPHP\Utils\isLengthValue',
            'percentage' => 'TailwindPHP\Utils\isPercentage',
            'ratio' => 'TailwindPHP\Utils\isFraction',
            'number' => 'TailwindPHP\Utils\isNumberValue',
            'integer' => 'TailwindPHP\Utils\isPositiveInteger',
            'url' => 'TailwindPHP\Utils\isUrl',
            'position' => 'TailwindPHP\Utils\isBackgroundPosition',
            'bg-size' => 'TailwindPHP\Utils\isBackgroundSize',
            'line-width' => 'TailwindPHP\Utils\isLineWidth',
            'image' => 'TailwindPHP\Utils\isImage',
            'family-name' => 'TailwindPHP\Utils\isFamilyName',
            'generic-name' => 'TailwindPHP\Utils\isGenericName',
            'absolute-size' => 'TailwindPHP\Utils\isAbsoluteSize',
            'relative-size' => 'TailwindPHP\Utils\isRelativeSize',
            'angle' => 'TailwindPHP\Utils\isAngle',
            'vector' => 'TailwindPHP\Utils\isVector',
            default => null,
        };
        if ($check !== null && $check($value)) {
            return $type;
        }
    }
    return null;
}
/**
 * Check if value is a CSS url() function.
 */
function isUrl(string $value): bool
{
    return (bool) preg_match('/^url\(.*\)$/', $value);
}
/**
 * Check if value is a valid CSS line-width (thin, medium, thick, or length).
 */
function isLineWidth(string $value): bool
{
    foreach (segment($value, ' ') as $part) {
        if (!isLengthValue($part) && !isNumberValue($part) && $part !== 'thin' && $part !== 'medium' && $part !== 'thick') {
            return \false;
        }
    }
    return \true;
}
/**
 * Check if value is a CSS image (url, gradient, or image function).
 */
function isImage(string $value): bool
{
    $count = 0;
    foreach (segment($value, ',') as $part) {
        if (str_starts_with($part, 'var(')) {
            continue;
        }
        if (isUrl($part)) {
            $count++;
            continue;
        }
        if (preg_match('/^(repeating-)?(conic|linear|radial)-gradient\(/', $part)) {
            $count++;
            continue;
        }
        if (preg_match('/^(?:element|image|cross-fade|image-set)\(/', $part)) {
            $count++;
            continue;
        }
        return \false;
    }
    return $count > 0;
}
/**
 * Check if value is a generic font family name.
 */
function isGenericName(string $value): bool
{
    return in_array($value, ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', 'math', 'emoji', 'fangsong'], \true);
}
/**
 * Check if value is a valid font family name.
 */
function isFamilyName(string $value): bool
{
    $count = 0;
    foreach (segment($value, ',') as $part) {
        // If it starts with a digit, then it's not a family name
        if (strlen($part) > 0 && ord($part[0]) >= 48 && ord($part[0]) <= 57) {
            return \false;
        }
        if (str_starts_with($part, 'var(')) {
            continue;
        }
        $count++;
    }
    return $count > 0;
}
/**
 * Check if value is an absolute font size keyword.
 */
function isAbsoluteSize(string $value): bool
{
    return in_array($value, ['xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large', 'xxx-large'], \true);
}
/**
 * Check if value is a relative font size keyword.
 */
function isRelativeSize(string $value): bool
{
    return $value === 'larger' || $value === 'smaller';
}
/**
 * Check if value is a CSS number.
 */
function isNumberValue(string $value): bool
{
    $pattern = '/^' . HAS_NUMBER_PATTERN . '$/';
    return (bool) preg_match($pattern, $value) || hasMathFn($value);
}
/**
 * Check if value is a CSS percentage.
 */
function isPercentage(string $value): bool
{
    $pattern = '/^' . HAS_NUMBER_PATTERN . '%$/';
    return (bool) preg_match($pattern, $value) || hasMathFn($value);
}
/**
 * Check if value is a CSS fraction (ratio).
 */
function isFraction(string $value): bool
{
    $pattern = '/^' . HAS_NUMBER_PATTERN . '\s*\/\s*' . HAS_NUMBER_PATTERN . '$/';
    return (bool) preg_match($pattern, $value) || hasMathFn($value);
}
/**
 * Check if value is a CSS length with units.
 */
function isLengthValue(string $value): bool
{
    $unitsPattern = implode('|', LENGTH_UNITS);
    $pattern = '/^' . HAS_NUMBER_PATTERN . '(' . $unitsPattern . ')$/';
    return (bool) preg_match($pattern, $value) || hasMathFn($value);
}
/**
 * Check if value is a valid CSS background-position.
 */
function isBackgroundPosition(string $value): bool
{
    $count = 0;
    foreach (segment($value, ' ') as $part) {
        if (in_array($part, ['center', 'top', 'right', 'bottom', 'left'], \true)) {
            $count++;
            continue;
        }
        if (str_starts_with($part, 'var(')) {
            continue;
        }
        if (isLengthValue($part) || isPercentage($part)) {
            $count++;
            continue;
        }
        return \false;
    }
    return $count > 0;
}
/**
 * Check if value is a valid CSS background-size.
 */
function isBackgroundSize(string $value): bool
{
    $count = 0;
    foreach (segment($value, ',') as $size) {
        if ($size === 'cover' || $size === 'contain') {
            $count++;
            continue;
        }
        $values = segment($size, ' ');
        if (count($values) !== 1 && count($values) !== 2) {
            return \false;
        }
        $allValid = \true;
        foreach ($values as $v) {
            if ($v !== 'auto' && !isLengthValue($v) && !isPercentage($v)) {
                $allValid = \false;
                break;
            }
        }
        if ($allValid) {
            $count++;
            continue;
        }
    }
    return $count > 0;
}
/**
 * Check if value is a CSS angle with units.
 */
function isAngle(string $value): bool
{
    $unitsPattern = implode('|', ANGLE_UNITS);
    $pattern = '/^' . HAS_NUMBER_PATTERN . '(' . $unitsPattern . ')$/';
    return (bool) preg_match($pattern, $value);
}
/**
 * Check if value is a 3D vector (three space-separated numbers).
 */
function isVector(string $value): bool
{
    $pattern = '/^' . HAS_NUMBER_PATTERN . ' +' . HAS_NUMBER_PATTERN . ' +' . HAS_NUMBER_PATTERN . '$/';
    return (bool) preg_match($pattern, $value);
}
/**
 * Check if value is a non-negative integer (0 or positive).
 */
function isPositiveInteger(mixed $value): bool
{
    $num = is_numeric($value) ? (int) $value : null;
    return $num !== null && $num >= 0 && (string) $num === (string) $value;
}
/**
 * Check if value is a strictly positive integer (> 0).
 */
function isStrictPositiveInteger(mixed $value): bool
{
    $num = is_numeric($value) ? (int) $value : null;
    return $num !== null && $num > 0 && (string) $num === (string) $value;
}
/**
 * Check if value is a valid spacing multiplier (multiple of 0.25).
 */
function isValidSpacingMultiplier(mixed $value): bool
{
    return isMultipleOf($value, 0.25);
}
/**
 * Check if value is a valid opacity (0-100, multiple of 0.25).
 */
function isValidOpacityValue(mixed $value): bool
{
    if (!is_numeric($value)) {
        return \false;
    }
    $num = (float) $value;
    // Opacity values must be 0-100 (percentage) and multiples of 0.25
    return $num >= 0 && $num <= 100 && fmod($num, 0.25) === 0.0 && (string) $num === (string) $value;
}
/**
 * Check if value is a multiple of a given divisor.
 */
function isMultipleOf(mixed $value, float $divisor): bool
{
    if (!is_numeric($value)) {
        return \false;
    }
    $num = (float) $value;
    return $num >= 0 && fmod($num, $divisor) === 0.0 && (string) $num === (string) $value;
}
