<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/validators.ts
 *
 * Validation functions for Tailwind class values.
 *
 * @port-deviation:types Uses PHP regex patterns
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

// Standalone validator functions for external use
function isAny(string $value = ''): bool
{
    return Validators::isAny($value);
}
function isAnyNonArbitrary(string $value): bool
{
    return Validators::isAnyNonArbitrary($value);
}
function isArbitraryValue(string $value): bool
{
    return Validators::isArbitraryValue($value);
}
function isArbitraryVariable(string $value): bool
{
    return Validators::isArbitraryVariable($value);
}
function isArbitraryLength(string $value): bool
{
    return Validators::isArbitraryLength($value);
}
function isArbitraryNumber(string $value): bool
{
    return Validators::isArbitraryNumber($value);
}
function isArbitraryPosition(string $value): bool
{
    return Validators::isArbitraryPosition($value);
}
function isArbitrarySize(string $value): bool
{
    return Validators::isArbitrarySize($value);
}
function isArbitraryImage(string $value): bool
{
    return Validators::isArbitraryImage($value);
}
function isArbitraryShadow(string $value): bool
{
    return Validators::isArbitraryShadow($value);
}
function isArbitraryVariableLength(string $value): bool
{
    return Validators::isArbitraryVariableLength($value);
}
function isArbitraryVariableFamilyName(string $value): bool
{
    return Validators::isArbitraryVariableFamilyName($value);
}
function isArbitraryVariablePosition(string $value): bool
{
    return Validators::isArbitraryVariablePosition($value);
}
function isArbitraryVariableSize(string $value): bool
{
    return Validators::isArbitraryVariableSize($value);
}
function isArbitraryVariableImage(string $value): bool
{
    return Validators::isArbitraryVariableImage($value);
}
function isArbitraryVariableShadow(string $value): bool
{
    return Validators::isArbitraryVariableShadow($value);
}
function isFraction(string $value): bool
{
    return Validators::isFraction($value);
}
function isNumber(string $value): bool
{
    return Validators::isNumber($value);
}
function isInteger(string $value): bool
{
    return Validators::isInteger($value);
}
function isPercent(string $value): bool
{
    return Validators::isPercent($value);
}
function isTshirtSize(string $value): bool
{
    return Validators::isTshirtSize($value);
}
class Validators
{
    private const ARBITRARY_VALUE_REGEX = '/^\[(?:(\w[\w-]*):)?(.+)\]$/i';
    private const ARBITRARY_VARIABLE_REGEX = '/^\((?:(\w[\w-]*):)?(.+)\)$/i';
    private const FRACTION_REGEX = '/^\d+\/\d+$/';
    private const TSHIRT_UNIT_REGEX = '/^(\d+(\.\d+)?)?(xs|sm|md|lg|xl)$/';
    private const LENGTH_UNIT_REGEX = '/\d+(%|px|r?em|[sdl]?v([hwib]|min|max)|pt|pc|in|cm|mm|cap|ch|ex|r?lh|cq(w|h|i|b|min|max))|\b(calc|min|max|clamp)\(.+\)|^0$/';
    private const COLOR_FUNCTION_REGEX = '/^(rgba?|hsla?|hwb|(ok)?(lab|lch)|color-mix)\(.+\)$/';
    private const SHADOW_REGEX = '/^(inset_)?-?((\d+)?\.?(\d+)[a-z]+|0)_-?((\d+)?\.?(\d+)[a-z]+|0)/';
    private const IMAGE_REGEX = '/^(url|image|image-set|cross-fade|element|(repeating-)?(linear|radial|conic)-gradient)\(.+\)$/';
    public static function isFraction(string $value): bool
    {
        return (bool) preg_match(self::FRACTION_REGEX, $value);
    }
    public static function isNumber(string $value): bool
    {
        return $value !== '' && is_numeric($value);
    }
    public static function isInteger(string $value): bool
    {
        return $value !== '' && ctype_digit(ltrim($value, '-'));
    }
    public static function isPercent(string $value): bool
    {
        return str_ends_with($value, '%') && self::isNumber(substr($value, 0, -1));
    }
    public static function isTshirtSize(string $value): bool
    {
        return (bool) preg_match(self::TSHIRT_UNIT_REGEX, $value);
    }
    public static function isAny(string $value = ''): bool
    {
        return \true;
    }
    public static function isAnyNonArbitrary(string $value): bool
    {
        return !self::isArbitraryValue($value) && !self::isArbitraryVariable($value);
    }
    public static function isArbitraryValue(string $value): bool
    {
        return (bool) preg_match(self::ARBITRARY_VALUE_REGEX, $value);
    }
    public static function isArbitraryVariable(string $value): bool
    {
        return (bool) preg_match(self::ARBITRARY_VARIABLE_REGEX, $value);
    }
    public static function isArbitraryLength(string $value): bool
    {
        return self::getIsArbitraryValue($value, 'isLabelLength', 'isLengthOnly');
    }
    public static function isArbitraryNumber(string $value): bool
    {
        return self::getIsArbitraryValue($value, 'isLabelNumber', 'isNumber');
    }
    public static function isArbitraryPosition(string $value): bool
    {
        return self::getIsArbitraryValue($value, 'isLabelPosition', 'isNever');
    }
    public static function isArbitrarySize(string $value): bool
    {
        return self::getIsArbitraryValue($value, 'isLabelSize', 'isNever');
    }
    public static function isArbitraryImage(string $value): bool
    {
        return self::getIsArbitraryValue($value, 'isLabelImage', 'isImage');
    }
    public static function isArbitraryShadow(string $value): bool
    {
        return self::getIsArbitraryValue($value, 'isLabelShadow', 'isShadow');
    }
    public static function isArbitraryVariableLength(string $value): bool
    {
        return self::getIsArbitraryVariable($value, 'isLabelLength');
    }
    public static function isArbitraryVariableFamilyName(string $value): bool
    {
        return self::getIsArbitraryVariable($value, 'isLabelFamilyName');
    }
    public static function isArbitraryVariablePosition(string $value): bool
    {
        return self::getIsArbitraryVariable($value, 'isLabelPosition');
    }
    public static function isArbitraryVariableSize(string $value): bool
    {
        return self::getIsArbitraryVariable($value, 'isLabelSize');
    }
    public static function isArbitraryVariableImage(string $value): bool
    {
        return self::getIsArbitraryVariable($value, 'isLabelImage');
    }
    public static function isArbitraryVariableShadow(string $value): bool
    {
        return self::getIsArbitraryVariable($value, 'isLabelShadow', \true);
    }
    // Helper methods
    private static function getIsArbitraryValue(string $value, string $testLabel, string $testValue): bool
    {
        if (!preg_match(self::ARBITRARY_VALUE_REGEX, $value, $matches)) {
            return \false;
        }
        if (!empty($matches[1])) {
            return self::$testLabel($matches[1]);
        }
        return self::$testValue($matches[2] ?? '');
    }
    private static function getIsArbitraryVariable(string $value, string $testLabel, bool $shouldMatchNoLabel = \false): bool
    {
        if (!preg_match(self::ARBITRARY_VARIABLE_REGEX, $value, $matches)) {
            return \false;
        }
        if (!empty($matches[1])) {
            return self::$testLabel($matches[1]);
        }
        return $shouldMatchNoLabel;
    }
    // Label checks
    private static function isLabelPosition(string $label): bool
    {
        return $label === 'position' || $label === 'percentage';
    }
    private static function isLabelImage(string $label): bool
    {
        return $label === 'image' || $label === 'url';
    }
    private static function isLabelSize(string $label): bool
    {
        return $label === 'length' || $label === 'size' || $label === 'bg-size';
    }
    private static function isLabelLength(string $label): bool
    {
        return $label === 'length';
    }
    private static function isLabelNumber(string $label): bool
    {
        return $label === 'number';
    }
    private static function isLabelFamilyName(string $label): bool
    {
        return $label === 'family-name';
    }
    private static function isLabelShadow(string $label): bool
    {
        return $label === 'shadow';
    }
    // Value checks
    private static function isLengthOnly(string $value): bool
    {
        // Color function check is necessary because color functions can have percentages
        // which would be incorrectly classified as lengths.
        return (bool) preg_match(self::LENGTH_UNIT_REGEX, $value) && !preg_match(self::COLOR_FUNCTION_REGEX, $value);
    }
    private static function isNever(string $value = ''): bool
    {
        return \false;
    }
    private static function isShadow(string $value): bool
    {
        return (bool) preg_match(self::SHADOW_REGEX, $value);
    }
    private static function isImage(string $value): bool
    {
        return (bool) preg_match(self::IMAGE_REGEX, $value);
    }
}
