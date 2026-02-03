<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/lukeed/clsx
 *
 * A tiny utility for constructing className strings conditionally.
 *
 * @port-deviation:types PHP uses mixed types instead of TypeScript generics
 */
namespace BlockstudioVendor\TailwindPHP\Lib\Clsx;

/**
 * Constructs className strings conditionally.
 *
 * Accepts any number of arguments which can be:
 * - string: added as-is
 * - int/float: converted to string and added
 * - array (sequential): each element is processed recursively
 * - array (associative): keys are added if values are truthy
 * - null/false/0/'': ignored
 *
 * @param mixed ...$args Class values to process
 * @return string Space-separated class string
 */
function clsx(mixed ...$args): string
{
    $result = '';
    foreach ($args as $arg) {
        if (!$arg) {
            continue;
        }
        $value = toValue($arg);
        if ($value !== '') {
            $result .= ($result !== '' ? ' ' : '') . $value;
        }
    }
    return $result;
}
/**
 * Convert a mixed value to a class string.
 *
 * @param mixed $mix The value to convert
 * @return string The resulting class string
 */
function toValue(mixed $mix): string
{
    // Fast path for strings and numbers
    if (is_string($mix)) {
        return $mix;
    }
    if (is_numeric($mix)) {
        // NaN is considered numeric in PHP but should be ignored like in JS
        if (is_float($mix) && is_nan($mix)) {
            return '';
        }
        return (string) $mix;
    }
    if (!is_array($mix)) {
        return '';
    }
    $result = '';
    // Check if array is sequential (list) or associative
    // PHP 8.0 compatible check (array_is_list is 8.1+)
    if ($mix === [] || array_keys($mix) === range(0, count($mix) - 1)) {
        // Sequential array - process each element recursively
        foreach ($mix as $item) {
            if ($item) {
                $value = toValue($item);
                if ($value !== '') {
                    $result .= ($result !== '' ? ' ' : '') . $value;
                }
            }
        }
    } else {
        // Associative array - add keys where values are truthy
        // In JS, empty objects {} and empty arrays [] are truthy
        // We need to match that behavior
        foreach ($mix as $key => $value) {
            if (isTruthy($value)) {
                $result .= ($result !== '' ? ' ' : '') . $key;
            }
        }
    }
    return $result;
}
/**
 * Check if a value is truthy according to JavaScript semantics.
 *
 * In JavaScript, empty arrays [] and empty objects {} are truthy.
 * In PHP, empty arrays are falsy.
 * This function bridges the behavior.
 *
 * @param mixed $value The value to check
 * @return bool True if the value is truthy in JavaScript semantics
 */
function isTruthy(mixed $value): bool
{
    // null, false, 0, '', undefined are falsy in both JS and PHP
    if ($value === null || $value === \false || $value === 0 || $value === '' || $value === 0.0) {
        return \false;
    }
    // NaN is falsy in JS
    if (is_float($value) && is_nan($value)) {
        return \false;
    }
    // Empty arrays are truthy in JS but falsy in PHP
    // This is the key difference we need to handle
    if (is_array($value)) {
        return \true;
        // All arrays (including empty) are truthy in JS
    }
    // Everything else follows PHP's truthiness
    return (bool) $value;
}
