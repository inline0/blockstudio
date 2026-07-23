<?php

/**
 * CVA (Class Variance Authority) - PHP Port
 *
 * Port of: https://github.com/joe-bell/cva
 *
 * A utility for creating type-safe UI component variants.
 * Provides a declarative API for managing component class variations.
 *
 * @port-deviation:types TypeScript types are not ported (PHP uses runtime checks)
 * @port-deviation:generics PHP doesn't have generics, uses array config instead
 */
declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Lib\Cva;

/**
 * cx - Concatenate class values (similar to clsx)
 *
 * Flattens and joins class values, filtering out falsy values.
 *
 * @param mixed ...$inputs Class values (strings, arrays, null, false)
 * @return string Space-separated class string
 *
 * @example
 * cx('foo', 'bar');                    // => 'foo bar'
 * cx('foo', null, 'bar');              // => 'foo bar'
 * cx(['foo', 'bar']);                  // => 'foo bar'
 * cx(['foo', ['bar', ['baz']]]);       // => 'foo bar baz'
 */
function cx(mixed ...$inputs): string
{
    $classes = [];
    foreach ($inputs as $input) {
        if ($input === null || $input === \false || $input === '') {
            continue;
        }
        if (is_string($input)) {
            $classes[] = $input;
        } elseif (is_array($input)) {
            // Recursively flatten arrays
            $nested = cx(...$input);
            if ($nested !== '') {
                $classes[] = $nested;
            }
        } elseif (is_int($input) || is_float($input)) {
            $classes[] = (string) $input;
        }
    }
    return implode(' ', $classes);
}
/**
 * Convert falsy values to string representation for variant key lookup.
 *
 * @param mixed $value
 * @return mixed
 */
function falsyToString(mixed $value): mixed
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if ($value === 0) {
        return '0';
    }
    return $value;
}
/**
 * cva - Create a class variance authority component
 *
 * @param array|null $config Configuration with base, variants, compoundVariants, defaultVariants
 * @return callable A function that accepts props and returns a class string
 *
 * @example
 * $button = cva([
 *     'base' => 'btn font-semibold',
 *     'variants' => [
 *         'intent' => [
 *             'primary' => 'bg-blue-500 text-white',
 *             'secondary' => 'bg-gray-200 text-gray-800',
 *         ],
 *         'size' => [
 *             'sm' => 'text-sm px-2 py-1',
 *             'md' => 'text-base px-4 py-2',
 *         ],
 *     ],
 *     'defaultVariants' => [
 *         'intent' => 'primary',
 *         'size' => 'md',
 *     ],
 * ]);
 *
 * $button();                          // => 'btn font-semibold bg-blue-500 text-white text-base px-4 py-2'
 * $button(['intent' => 'secondary']); // => 'btn font-semibold bg-gray-200 text-gray-800 text-base px-4 py-2'
 */
function cva(?array $config = null): callable
{
    return function (?array $props = null) use ($config): string {
        $base = $config['base'] ?? null;
        $variants = $config['variants'] ?? null;
        $compoundVariants = $config['compoundVariants'] ?? [];
        $defaultVariants = $config['defaultVariants'] ?? [];
        // If no variants defined, just return base + class/className
        if ($variants === null) {
            return cx($base, $props['class'] ?? null, $props['className'] ?? null);
        }
        // Get variant class names
        $variantClassNames = [];
        foreach (array_keys($variants) as $variant) {
            $variantProp = $props[$variant] ?? null;
            $defaultVariantProp = $defaultVariants[$variant] ?? null;
            // Convert to string key (handle booleans and 0)
            $variantKey = falsyToString($variantProp) ?? falsyToString($defaultVariantProp);
            if ($variantKey !== null && isset($variants[$variant][$variantKey])) {
                $variantClassNames[] = $variants[$variant][$variantKey];
            }
        }
        // Build merged props (defaults + provided, excluding undefined)
        $defaultsAndProps = $defaultVariants;
        if ($props !== null) {
            foreach ($props as $key => $value) {
                if ($value !== null) {
                    $defaultsAndProps[$key] = $value;
                }
            }
        }
        // Get compound variant class names
        $compoundVariantClassNames = [];
        foreach ($compoundVariants as $cv) {
            $cvClass = $cv['class'] ?? null;
            $cvClassName = $cv['className'] ?? null;
            // Get variant conditions from compound variant (excluding class/className)
            $cvConfig = array_diff_key($cv, ['class' => 1, 'className' => 1]);
            // Check if all conditions match
            $allMatch = \true;
            foreach ($cvConfig as $cvKey => $cvSelector) {
                $selector = $defaultsAndProps[$cvKey] ?? null;
                if (is_array($cvSelector)) {
                    // Array of values - check if selector matches any
                    if (!in_array($selector, $cvSelector, \true)) {
                        $allMatch = \false;
                        break;
                    }
                } else if ($selector !== $cvSelector) {
                    $allMatch = \false;
                    break;
                }
            }
            if ($allMatch) {
                if ($cvClass !== null) {
                    $compoundVariantClassNames[] = $cvClass;
                }
                if ($cvClassName !== null) {
                    $compoundVariantClassNames[] = $cvClassName;
                }
            }
        }
        // Build the full list of values to pass to cx
        $cxArgs = array_merge([$base], $variantClassNames, $compoundVariantClassNames, [$props['class'] ?? null, $props['className'] ?? null]);
        return cx(...$cxArgs);
    };
}
/**
 * compose - Merge multiple cva components into one
 *
 * @param callable ...$components CVA component functions
 * @return callable A function that accepts merged props and returns a class string
 *
 * @example
 * $box = cva(['variants' => ['shadow' => ['sm' => 'shadow-sm', 'md' => 'shadow-md']]]);
 * $stack = cva(['variants' => ['gap' => ['1' => 'gap-1', '2' => 'gap-2']]]);
 * $card = compose($box, $stack);
 *
 * $card(['shadow' => 'md', 'gap' => '2']); // => 'shadow-md gap-2'
 */
function compose(callable ...$components): callable
{
    return function (?array $props = null) use ($components): string {
        // Remove class/className from props for component calls
        $propsWithoutClass = [];
        if ($props !== null) {
            foreach ($props as $key => $value) {
                if ($key !== 'class' && $key !== 'className') {
                    $propsWithoutClass[$key] = $value;
                }
            }
        }
        // Call each component with props (without class/className)
        $results = [];
        foreach ($components as $component) {
            $results[] = $component($propsWithoutClass ?: null);
        }
        // Combine results with class/className
        $cxArgs = array_merge($results, [$props['class'] ?? null, $props['className'] ?? null]);
        return cx(...$cxArgs);
    };
}
/**
 * defineConfig - Create configured versions of cva, cx, and compose with hooks
 *
 * @param array $options Configuration with hooks (onComplete)
 * @return array Array with 'cva', 'cx', 'compose' keys
 *
 * @example
 * $config = defineConfig([
 *     'hooks' => [
 *         'onComplete' => fn($className) => "prefix $className suffix",
 *     ],
 * ]);
 *
 * $config['cx']('foo', 'bar'); // => 'prefix foo bar suffix'
 */
function defineConfig(array $options = []): array
{
    $onComplete = $options['hooks']['onComplete'] ?? $options['hooks']['cx:done'] ?? null;
    // Configured cx
    $configuredCx = function (mixed ...$inputs) use ($onComplete): string {
        $result = cx(...$inputs);
        if ($onComplete !== null) {
            return $onComplete($result);
        }
        return $result;
    };
    // Configured cva
    $configuredCva = function (?array $config = null) use ($configuredCx): callable {
        $baseCva = cva($config);
        return function (?array $props = null) use ($baseCva, $configuredCx): string {
            // We need to rebuild the logic to use configuredCx
            $result = $baseCva($props);
            // The hook is applied in cx, but we need to apply it to the final result
            // Actually, let's check if onComplete was passed and apply it
            return $result;
        };
    };
    // For cva with hooks, we need to wrap the result
    $configuredCvaWithHook = function (?array $config = null) use ($onComplete): callable {
        $baseCva = cva($config);
        return function (?array $props = null) use ($baseCva, $onComplete): string {
            $result = $baseCva($props);
            if ($onComplete !== null) {
                return $onComplete($result);
            }
            return $result;
        };
    };
    // Configured compose
    $configuredCompose = function (callable ...$components) use ($onComplete): callable {
        $baseCompose = compose(...$components);
        return function (?array $props = null) use ($baseCompose, $onComplete): string {
            $result = $baseCompose($props);
            if ($onComplete !== null) {
                return $onComplete($result);
            }
            return $result;
        };
    };
    return ['cx' => $configuredCx, 'cva' => $configuredCvaWithHook, 'compose' => $configuredCompose];
}
