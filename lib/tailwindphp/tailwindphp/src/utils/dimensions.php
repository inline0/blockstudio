<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Utils;

/**
 * Dimension parsing utilities.
 *
 * Port of: packages/tailwindcss/src/utils/dimensions.ts
 *
 * @port-deviation:cache TypeScript uses DefaultMap for caching parsed dimensions.
 * PHP uses simple static variable caching for equivalent behavior.
 */
const DIMENSION_REGEX = '/^(?<value>[-+]?(?:\d*\.)?\d+)(?<unit>[a-z]+|%)?$/i';
/**
 * Parse a dimension such as `64rem` into [64, 'rem'].
 *
 * @param string $input
 * @return array{float, string|null}|null
 */
function parseDimension(string $input): ?array
{
    if (!preg_match(DIMENSION_REGEX, $input, $match)) {
        return null;
    }
    $value = $match['value'] ?? null;
    if ($value === null) {
        return null;
    }
    $valueAsNumber = (float) $value;
    if (is_nan($valueAsNumber)) {
        return null;
    }
    $unit = $match['unit'] ?? null;
    if ($unit === null || $unit === '') {
        return [$valueAsNumber, null];
    }
    return [$valueAsNumber, $unit];
}
/**
 * DefaultMap wrapper for dimensions parsing with caching.
 */
class Dimensions
{
    private static ?DefaultMap $cache = null;
    public static function get(string $input): ?array
    {
        if (self::$cache === null) {
            self::$cache = new DefaultMap(fn($key) => parseDimension($key));
        }
        return self::$cache->get($input);
    }
}
