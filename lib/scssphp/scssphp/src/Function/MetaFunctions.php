<?php

/**
 * SCSSPHP
 *
 * @copyright 2012-2020 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://scssphp.github.io/scssphp
 */
namespace BlockstudioVendor\ScssPhp\ScssPhp\Function;

use BlockstudioVendor\ScssPhp\ScssPhp\Collection\Map;
use BlockstudioVendor\ScssPhp\ScssPhp\Deprecation;
use BlockstudioVendor\ScssPhp\ScssPhp\Exception\SassScriptException;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassArgumentList;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassBoolean;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassCalculation;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassColor;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassFunction;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassList;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassMap;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassMixin;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassNull;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassNumber;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassString;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\Value;
use BlockstudioVendor\ScssPhp\ScssPhp\Warn;
/**
 * @internal
 */
final class MetaFunctions
{
    /**
     * @param list<Value> $arguments
     */
    public static function featureExists(array $arguments): Value
    {
        Warn::forDeprecation("The feature-exists() function is deprecated.\n\nMore info: https://sass-lang.com/d/feature-exists", Deprecation::featureExists);
        $feature = $arguments[0]->assertString('feature');
        return SassBoolean::create(\in_array($feature->getText(), ['global-variable-shadowing', 'extend-selector-pseudoclass', 'units-level-3', 'at-error', 'custom-property'], \true));
    }
    /**
     * @param list<Value> $arguments
     */
    public static function inspect(array $arguments): Value
    {
        return new SassString((string) $arguments[0], \false);
    }
    /**
     * @param list<Value> $arguments
     */
    public static function typeof(array $arguments): Value
    {
        $value = $arguments[0];
        return new SassString(match (\true) {
            $value instanceof SassArgumentList => 'arglist',
            $value instanceof SassBoolean => 'bool',
            $value instanceof SassColor => 'color',
            $value instanceof SassList => 'list',
            $value instanceof SassMap => 'map',
            $value instanceof SassNull => 'null',
            $value instanceof SassNumber => 'number',
            $value instanceof SassFunction => 'function',
            $value instanceof SassMixin => 'mixin',
            $value instanceof SassCalculation => 'calculation',
            $value instanceof SassString => 'string',
            default => throw new SassScriptException("[BUG] Unknown value type {$value}"),
        }, \false);
    }
    /**
     * @param list<Value> $arguments
     */
    public static function keywords(array $arguments): Value
    {
        if ($arguments[0] instanceof SassArgumentList) {
            $map = new Map();
            foreach ($arguments[0]->getKeywords() as $key => $value) {
                $map->put(new SassString($key, \false), $value);
            }
            return SassMap::create($map);
        }
        throw SassScriptException::forArgument("{$arguments[0]} is not an argument list.", 'args');
    }
}
