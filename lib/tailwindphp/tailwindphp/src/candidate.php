<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Candidate;

use function BlockstudioVendor\TailwindPHP\DecodeArbitraryValue\decodeArbitraryValue;
use BlockstudioVendor\TailwindPHP\Theme;
use function BlockstudioVendor\TailwindPHP\Utils\isValidArbitrary;
use function BlockstudioVendor\TailwindPHP\Utils\segment;
use function BlockstudioVendor\TailwindPHP\ValueParser\parse as valueParse;
use function BlockstudioVendor\TailwindPHP\ValueParser\toCss as valueToCss;
use function BlockstudioVendor\TailwindPHP\Walk\walk;
/**
 * Candidate - Class name parsing for Tailwind utilities.
 *
 * Port of: packages/tailwindcss/src/candidate.ts
 *
 * @port-deviation:caching TypeScript uses DefaultMap for automatic lazy caching.
 * PHP uses global arrays with manual cache checks (e.g., $printArbitraryValueCache).
 *
 * @port-deviation:node-filtering TypeScript uses Set<node> + WalkAction.ReplaceSkip to filter AST.
 * PHP uses spl_object_hash() workaround since PHP arrays are value types, not references.
 *
 * @port-deviation:types TypeScript has full type definitions for Candidate, Variant, etc.
 * PHP uses interfaces (DesignSystemInterface, UtilitiesInterface, VariantsInterface)
 * and PHPDoc annotations for IDE support.
 */
const COLON = 0x3a;
// ':'
const DASH = 0x2d;
// '-'
const LOWER_A = 0x61;
// 'a'
const LOWER_Z = 0x7a;
// 'z'
/**
 * Interface for Utilities registry.
 * Actual implementation will be in utilities.php.
 */
interface UtilitiesInterface
{
    public function has(string $name, string $kind): bool;
}
/**
 * Interface for Variants registry.
 * Actual implementation will be in variants.php.
 */
interface VariantsInterface
{
    public function has(string $root): bool;
    public function kind(string $root): string;
    public function compoundsWith(string $root, array $variant): bool;
}
/**
 * Interface for DesignSystem.
 * Actual implementation will be in design-system.php.
 */
interface DesignSystemInterface
{
    public function getTheme(): Theme;
    public function getUtilities(): UtilitiesInterface;
    public function getVariants(): VariantsInterface;
    public function parseVariant(string $variant): ?array;
}
/**
 * Clone a candidate to create a deep copy.
 *
 * @param array $candidate
 * @return array
 */
function cloneCandidate(array $candidate): array
{
    switch ($candidate['kind']) {
        case 'arbitrary':
            return ['kind' => $candidate['kind'], 'property' => $candidate['property'], 'value' => $candidate['value'], 'modifier' => $candidate['modifier'] ? ['kind' => $candidate['modifier']['kind'], 'value' => $candidate['modifier']['value']] : null, 'variants' => array_map('BlockstudioVendor\TailwindPHP\Candidate\cloneVariant', $candidate['variants']), 'important' => $candidate['important'], 'raw' => $candidate['raw']];
        case 'static':
            return ['kind' => $candidate['kind'], 'root' => $candidate['root'], 'variants' => array_map('BlockstudioVendor\TailwindPHP\Candidate\cloneVariant', $candidate['variants']), 'important' => $candidate['important'], 'raw' => $candidate['raw']];
        case 'functional':
            $value = null;
            if ($candidate['value']) {
                if ($candidate['value']['kind'] === 'arbitrary') {
                    $value = ['kind' => $candidate['value']['kind'], 'dataType' => $candidate['value']['dataType'], 'value' => $candidate['value']['value']];
                } else {
                    $value = ['kind' => $candidate['value']['kind'], 'value' => $candidate['value']['value'], 'fraction' => $candidate['value']['fraction']];
                }
            }
            return ['kind' => $candidate['kind'], 'root' => $candidate['root'], 'value' => $value, 'modifier' => $candidate['modifier'] ? ['kind' => $candidate['modifier']['kind'], 'value' => $candidate['modifier']['value']] : null, 'variants' => array_map('BlockstudioVendor\TailwindPHP\Candidate\cloneVariant', $candidate['variants']), 'important' => $candidate['important'], 'raw' => $candidate['raw']];
        default:
            throw new \Exception('Unknown candidate kind');
    }
}
/**
 * Clone a variant to create a deep copy.
 *
 * @param array $variant
 * @return array
 */
function cloneVariant(array $variant): array
{
    switch ($variant['kind']) {
        case 'arbitrary':
            return ['kind' => $variant['kind'], 'selector' => $variant['selector'], 'relative' => $variant['relative']];
        case 'static':
            return ['kind' => $variant['kind'], 'root' => $variant['root']];
        case 'functional':
            return ['kind' => $variant['kind'], 'root' => $variant['root'], 'value' => $variant['value'] ? ['kind' => $variant['value']['kind'], 'value' => $variant['value']['value']] : null, 'modifier' => $variant['modifier'] ? ['kind' => $variant['modifier']['kind'], 'value' => $variant['modifier']['value']] : null];
        case 'compound':
            return ['kind' => $variant['kind'], 'root' => $variant['root'], 'variant' => cloneVariant($variant['variant']), 'modifier' => $variant['modifier'] ? ['kind' => $variant['modifier']['kind'], 'value' => $variant['modifier']['value']] : null];
        default:
            throw new \Exception('Unknown variant kind');
    }
}
/**
 * Parse a candidate string into candidate structures.
 *
 * @param string $input
 * @param DesignSystemInterface $designSystem
 * @return iterable<array>
 */
function parseCandidate(string $input, DesignSystemInterface $designSystem): iterable
{
    // hover:focus:underline
    // ^^^^^ ^^^^^^           -> Variants
    //             ^^^^^^^^^  -> Base
    $rawVariants = segment($input, ':');
    $theme = $designSystem->getTheme();
    $utilities = $designSystem->getUtilities();
    // A prefix is a special variant used to prefix all utilities. When present,
    // all utilities must start with that variant which we will then remove from
    // the variant list so no other part of the codebase has to know about it.
    if ($theme->prefix) {
        if (count($rawVariants) === 1) {
            return;
        }
        if ($rawVariants[0] !== $theme->prefix) {
            return;
        }
        array_shift($rawVariants);
    }
    // Safety: At this point it is safe to use array_pop because even if the
    // input was an empty string, splitting an empty string by `:` will always
    // result in an array with at least one element.
    $base = array_pop($rawVariants);
    $parsedCandidateVariants = [];
    for ($i = count($rawVariants) - 1; $i >= 0; --$i) {
        $parsedVariant = $designSystem->parseVariant($rawVariants[$i]);
        if ($parsedVariant === null) {
            return;
        }
        $parsedCandidateVariants[] = $parsedVariant;
    }
    $important = \false;
    // Candidates that end with an exclamation mark are the important version with
    // higher specificity of the non-important candidate, e.g. `mx-4!`.
    if (strlen($base) > 0 && $base[strlen($base) - 1] === '!') {
        $important = \true;
        $base = substr($base, 0, -1);
    } elseif (strlen($base) > 0 && $base[0] === '!') {
        $important = \true;
        $base = substr($base, 1);
    }
    // Check for an exact match of a static utility first as long as it does not
    // look like an arbitrary value.
    if ($utilities->has($base, 'static') && strpos($base, '[') === \false) {
        yield ['kind' => 'static', 'root' => $base, 'variants' => $parsedCandidateVariants, 'important' => $important, 'raw' => $input];
    }
    // Figure out the new base and the modifier segment if present.
    //
    // E.g.:
    //
    // ```
    // bg-red-500/50
    // ^^^^^^^^^^    -> Base without modifier
    //            ^^ -> Modifier segment
    // ```
    $segments = segment($base, '/');
    $baseWithoutModifier = $segments[0];
    $modifierSegment = $segments[1] ?? null;
    $additionalModifier = $segments[2] ?? null;
    // If there's more than one modifier, the utility is invalid.
    //
    // E.g.:
    //
    // - `bg-red-500/50/50`
    if ($additionalModifier !== null) {
        return;
    }
    $parsedModifier = $modifierSegment === null ? null : parseModifier($modifierSegment);
    // Empty arbitrary values are invalid. E.g.: `[color:red]/[]` or `[color:red]/()`.
    //                                                        ^^                  ^^
    //                                           `bg-[#0088cc]/[]` or `bg-[#0088cc]/()`.
    //                                                         ^^                   ^^
    if ($modifierSegment !== null && $parsedModifier === null) {
        return;
    }
    // Arbitrary properties
    if (strlen($baseWithoutModifier) > 0 && $baseWithoutModifier[0] === '[') {
        // Arbitrary properties should end with a `]`.
        if ($baseWithoutModifier[strlen($baseWithoutModifier) - 1] !== ']') {
            return;
        }
        // The property part of the arbitrary property can only start with a-z
        // lowercase or a dash `-` in case of vendor prefixes such as `-webkit-`
        // or `-moz-`.
        //
        // Otherwise, it is an invalid candidate, and skip continue parsing.
        if (strlen($baseWithoutModifier) > 1) {
            $charCode = ord($baseWithoutModifier[1]);
            if ($charCode !== DASH && !($charCode >= LOWER_A && $charCode <= LOWER_Z)) {
                return;
            }
        }
        $baseWithoutModifier = substr($baseWithoutModifier, 1, -1);
        // Arbitrary properties consist of a property and a value separated by a
        // `:`. If the `:` cannot be found, then it is an invalid candidate, and we
        // can skip continue parsing.
        //
        // Since the property and the value should be separated by a `:`, we can
        // also verify that the colon is not the first or last character in the
        // candidate, because that would make it invalid as well.
        $idx = strpos($baseWithoutModifier, ':');
        if ($idx === \false || $idx === 0 || $idx === strlen($baseWithoutModifier) - 1) {
            return;
        }
        $property = substr($baseWithoutModifier, 0, $idx);
        $value = decodeArbitraryValue(substr($baseWithoutModifier, $idx + 1));
        // Values can't contain `;` or `}` characters at the top-level.
        if (!isValidArbitrary($value)) {
            return;
        }
        yield ['kind' => 'arbitrary', 'property' => $property, 'value' => $value, 'modifier' => $parsedModifier, 'variants' => $parsedCandidateVariants, 'important' => $important, 'raw' => $input];
        return;
    }
    // The different "versions"" of a candidate that are utilities
    // e.g. `['bg', 'red-500']` and `['bg-red', '500']`
    $roots = [];
    // If the base of the utility ends with a `]`, then we know it's an arbitrary
    // value. This also means that everything before the `[…]` part should be the
    // root of the utility.
    //
    // E.g.:
    //
    // ```
    // bg-[#0088cc]
    // ^^           -> Root
    //    ^^^^^^^^^ -> Arbitrary value
    //
    // border-l-[#0088cc]
    // ^^^^^^^^           -> Root
    //          ^^^^^^^^^ -> Arbitrary value
    // ```
    if (strlen($baseWithoutModifier) > 0 && $baseWithoutModifier[strlen($baseWithoutModifier) - 1] === ']') {
        $idx = strpos($baseWithoutModifier, '-[');
        if ($idx === \false) {
            return;
        }
        $root = substr($baseWithoutModifier, 0, $idx);
        // The root of the utility should exist as-is in the utilities map. If not,
        // it's an invalid utility and we can skip continue parsing.
        if (!$utilities->has($root, 'functional')) {
            return;
        }
        $value = substr($baseWithoutModifier, $idx + 1);
        $roots = [[$root, $value]];
    } elseif (strlen($baseWithoutModifier) > 0 && $baseWithoutModifier[strlen($baseWithoutModifier) - 1] === ')') {
        $idx = strpos($baseWithoutModifier, '-(');
        if ($idx === \false) {
            return;
        }
        $root = substr($baseWithoutModifier, 0, $idx);
        // The root of the utility should exist as-is in the utilities map. If not,
        // it's an invalid utility and we can skip continue parsing.
        if (!$utilities->has($root, 'functional')) {
            return;
        }
        $value = substr($baseWithoutModifier, $idx + 2, -1);
        $parts = segment($value, ':');
        $dataType = null;
        if (count($parts) === 2) {
            $dataType = $parts[0];
            $value = $parts[1];
        }
        // An arbitrary value with `(…)` should always start with `--` since it
        // represents a CSS variable.
        if (strlen($value) < 2 || $value[0] !== '-' || $value[1] !== '-') {
            return;
        }
        // Values can't contain `;` or `}` characters at the top-level.
        if (!isValidArbitrary($value)) {
            return;
        }
        $roots = [[$root, $dataType === null ? "[var({$value})]" : "[{$dataType}:var({$value})]"]];
    } else {
        $roots = findRoots($baseWithoutModifier, function (string $root) use ($utilities) {
            return $utilities->has($root, 'functional');
        });
    }
    foreach ($roots as [$root, $value]) {
        $candidate = ['kind' => 'functional', 'root' => $root, 'modifier' => $parsedModifier, 'value' => null, 'variants' => $parsedCandidateVariants, 'important' => $important, 'raw' => $input];
        if ($value === null) {
            yield $candidate;
            continue;
        }
        $startArbitraryIdx = strpos($value, '[');
        $valueIsArbitrary = $startArbitraryIdx !== \false;
        if ($valueIsArbitrary) {
            // Arbitrary values must end with a `]`.
            if ($value[strlen($value) - 1] !== ']') {
                return;
            }
            $arbitraryValue = decodeArbitraryValue(substr($value, $startArbitraryIdx + 1, -1));
            // Values can't contain `;` or `}` characters at the top-level.
            if (!isValidArbitrary($arbitraryValue)) {
                continue;
            }
            // Extract an explicit typehint if present, e.g. `bg-[color:var(--my-var)])`
            $typehint = null;
            $arbLen = strlen($arbitraryValue);
            for ($i = 0; $i < $arbLen; $i++) {
                $code = ord($arbitraryValue[$i]);
                // If we hit a ":", we're at the end of a typehint.
                if ($code === COLON) {
                    $typehint = substr($arbitraryValue, 0, $i);
                    $arbitraryValue = substr($arbitraryValue, $i + 1);
                    break;
                }
                // Keep iterating as long as we've only seen valid typehint characters.
                if ($code === DASH || $code >= LOWER_A && $code <= LOWER_Z) {
                    continue;
                }
                // If we see any other character, there's no typehint so break early.
                break;
            }
            // Empty arbitrary values are invalid. E.g.: `p-[]`
            //                                              ^^
            if (strlen($arbitraryValue) === 0 || strlen(trim($arbitraryValue)) === 0) {
                continue;
            }
            if ($typehint === '') {
                continue;
            }
            $candidate['value'] = ['kind' => 'arbitrary', 'dataType' => $typehint ?: null, 'value' => $arbitraryValue];
        } else {
            // Some utilities support fractions as values, e.g. `w-1/2`. Since it's
            // ambiguous whether the slash signals a modifier or not, we store the
            // fraction separately in case the utility matcher is interested in it.
            $fraction = null;
            if ($modifierSegment !== null && ($candidate['modifier'] === null || $candidate['modifier']['kind'] !== 'arbitrary')) {
                $fraction = "{$value}/{$modifierSegment}";
            }
            $candidate['value'] = ['kind' => 'named', 'value' => $value, 'fraction' => $fraction];
        }
        yield $candidate;
    }
}
/**
 * Parse a modifier string.
 *
 * @param string $modifier
 * @return array|null
 */
function parseModifier(string $modifier): ?array
{
    if (strlen($modifier) > 0 && $modifier[0] === '[' && $modifier[strlen($modifier) - 1] === ']') {
        $arbitraryValue = decodeArbitraryValue(substr($modifier, 1, -1));
        // Values can't contain `;` or `}` characters at the top-level.
        if (!isValidArbitrary($arbitraryValue)) {
            return null;
        }
        // Empty arbitrary values are invalid. E.g.: `data-[]:`
        //                                                 ^^
        if (strlen($arbitraryValue) === 0 || strlen(trim($arbitraryValue)) === 0) {
            return null;
        }
        return ['kind' => 'arbitrary', 'value' => $arbitraryValue];
    }
    if (strlen($modifier) > 0 && $modifier[0] === '(' && $modifier[strlen($modifier) - 1] === ')') {
        // Drop the `(` and `)` characters
        $modifier = substr($modifier, 1, -1);
        // A modifier with `(…)` should always start with `--` since it
        // represents a CSS variable.
        if (strlen($modifier) < 2 || $modifier[0] !== '-' || $modifier[1] !== '-') {
            return null;
        }
        // Values can't contain `;` or `}` characters at the top-level.
        if (!isValidArbitrary($modifier)) {
            return null;
        }
        // Wrap the value in `var(…)` to ensure that it is a valid CSS variable.
        $modifier = "var({$modifier})";
        $arbitraryValue = decodeArbitraryValue($modifier);
        return ['kind' => 'arbitrary', 'value' => $arbitraryValue];
    }
    return ['kind' => 'named', 'value' => $modifier];
}
/**
 * Parse a variant string.
 *
 * @param string $variant
 * @param DesignSystemInterface $designSystem
 * @return array|null
 */
function parseVariant(string $variant, DesignSystemInterface $designSystem): ?array
{
    $variants = $designSystem->getVariants();
    // Arbitrary variants
    if (strlen($variant) > 0 && $variant[0] === '[' && $variant[strlen($variant) - 1] === ']') {
        /**
         * TODO: Breaking change
         *
         * @deprecated Arbitrary variants containing at-rules with other selectors
         * are deprecated. Use stacked variants instead.
         *
         * Before:
         *  - `[@media(width>=123px){&:hover}]:`
         *
         * After:
         *  - `[@media(width>=123px)]:[&:hover]:`
         *  - `[@media(width>=123px)]:hover:`
         */
        if ($variant[1] === '@' && strpos($variant, '&') !== \false) {
            return null;
        }
        $selector = decodeArbitraryValue(substr($variant, 1, -1));
        // Values can't contain `;` or `}` characters at the top-level.
        if (!isValidArbitrary($selector)) {
            return null;
        }
        // Empty arbitrary values are invalid. E.g.: `[]:`
        //                                            ^^
        if (strlen($selector) === 0 || strlen(trim($selector)) === 0) {
            return null;
        }
        $relative = $selector[0] === '>' || $selector[0] === '+' || $selector[0] === '~';
        // Ensure `&` is always present by wrapping the selector in `&:is(…)`,
        // unless it's a relative selector like `> img`.
        //
        // E.g.:
        //
        // - `[p]:flex`
        if (!$relative && $selector[0] !== '@' && strpos($selector, '&') === \false) {
            $selector = "&:is({$selector})";
        }
        return ['kind' => 'arbitrary', 'selector' => $selector, 'relative' => $relative];
    }
    // Static, functional and compound variants
    // group-hover/group-name
    // ^^^^^^^^^^^            -> Variant without modifier
    //             ^^^^^^^^^^ -> Modifier
    $segments = segment($variant, '/');
    $variantWithoutModifier = $segments[0];
    $modifier = $segments[1] ?? null;
    $additionalModifier = $segments[2] ?? null;
    // If there's more than one modifier, the variant is invalid.
    //
    // E.g.:
    //
    // - `group-hover/foo/bar`
    if ($additionalModifier !== null) {
        return null;
    }
    $roots = findRoots($variantWithoutModifier, function (string $root) use ($variants) {
        return $variants->has($root);
    });
    foreach ($roots as [$root, $value]) {
        switch ($variants->kind($root)) {
            case 'static':
                // Static variants do not have a value
                if ($value !== null) {
                    return null;
                }
                // Static variants do not have a modifier
                if ($modifier !== null) {
                    return null;
                }
                return ['kind' => 'static', 'root' => $root];
            case 'functional':
                $parsedModifier = $modifier === null ? null : parseModifier($modifier);
                // Empty arbitrary values are invalid. E.g.: `@max-md/[]:` or `@max-md/():`
                //                                                    ^^               ^^
                if ($modifier !== null && $parsedModifier === null) {
                    return null;
                }
                if ($value === null) {
                    return ['kind' => 'functional', 'root' => $root, 'modifier' => $parsedModifier, 'value' => null];
                }
                if (strlen($value) > 0 && $value[strlen($value) - 1] === ']') {
                    // Discard values like `foo-[#bar]`
                    if ($value[0] !== '[') {
                        continue 2;
                    }
                    $arbitraryValue = decodeArbitraryValue(substr($value, 1, -1));
                    // Values can't contain `;` or `}` characters at the top-level.
                    if (!isValidArbitrary($arbitraryValue)) {
                        return null;
                    }
                    // Empty arbitrary values are invalid. E.g.: `data-[]:`
                    //                                                 ^^
                    if (strlen($arbitraryValue) === 0 || strlen(trim($arbitraryValue)) === 0) {
                        return null;
                    }
                    return ['kind' => 'functional', 'root' => $root, 'modifier' => $parsedModifier, 'value' => ['kind' => 'arbitrary', 'value' => $arbitraryValue]];
                }
                if (strlen($value) > 0 && $value[strlen($value) - 1] === ')') {
                    // Discard values like `foo-(--bar)`
                    if ($value[0] !== '(') {
                        continue 2;
                    }
                    $arbitraryValue = decodeArbitraryValue(substr($value, 1, -1));
                    // Values can't contain `;` or `}` characters at the top-level.
                    if (!isValidArbitrary($arbitraryValue)) {
                        return null;
                    }
                    // Empty arbitrary values are invalid. E.g.: `data-():`
                    //                                                 ^^
                    if (strlen($arbitraryValue) === 0 || strlen(trim($arbitraryValue)) === 0) {
                        return null;
                    }
                    // Arbitrary values must start with `--` since it represents a CSS variable.
                    if (strlen($arbitraryValue) < 2 || $arbitraryValue[0] !== '-' || $arbitraryValue[1] !== '-') {
                        return null;
                    }
                    return ['kind' => 'functional', 'root' => $root, 'modifier' => $parsedModifier, 'value' => ['kind' => 'arbitrary', 'value' => "var({$arbitraryValue})"]];
                }
                return ['kind' => 'functional', 'root' => $root, 'modifier' => $parsedModifier, 'value' => ['kind' => 'named', 'value' => $value]];
            case 'compound':
                if ($value === null) {
                    return null;
                }
                // Forward the modifier of the compound variants to its subVariant.
                // This allows for `not-group-hover/name:flex` to work.
                if ($modifier && ($root === 'not' || $root === 'has' || $root === 'in')) {
                    $value = "{$value}/{$modifier}";
                    $modifier = null;
                }
                $subVariant = $designSystem->parseVariant($value);
                if ($subVariant === null) {
                    return null;
                }
                // These two variants must be compatible when compounded
                if (!$variants->compoundsWith($root, $subVariant)) {
                    return null;
                }
                $parsedModifier = $modifier === null ? null : parseModifier($modifier);
                // Empty arbitrary values are invalid. E.g.: `group-focus/[]:` or `group-focus/():`
                //                                                        ^^                   ^^
                if ($modifier !== null && $parsedModifier === null) {
                    return null;
                }
                return ['kind' => 'compound', 'root' => $root, 'modifier' => $parsedModifier, 'variant' => $subVariant];
        }
    }
    return null;
}
/**
 * Find all possible roots from an input string.
 *
 * @param string $input
 * @param callable $exists
 * @return iterable<array{0: string, 1: string|null}>
 */
function findRoots(string $input, callable $exists): iterable
{
    // If there is an exact match, then that's the root.
    if ($exists($input)) {
        yield [$input, null];
    }
    // Otherwise test every permutation of the input by iteratively removing
    // everything after the last dash.
    $idx = strrpos($input, '-');
    // Determine the root and value by testing permutations of the incoming input.
    //
    // In case of a candidate like `bg-red-500`, this looks like:
    //
    // `bg-red-500` -> No match
    // `bg-red`     -> No match
    // `bg`         -> Match
    while ($idx !== \false && $idx > 0) {
        $maybeRoot = substr($input, 0, $idx);
        if ($exists($maybeRoot)) {
            $root = [$maybeRoot, substr($input, $idx + 1)];
            // If the leftover value is an empty string, it means that the value is an
            // invalid named value, e.g.: `bg-`. This makes the candidate invalid and we
            // can skip any further parsing.
            if ($root[1] === '') {
                break;
            }
            // Edge case: `@-…` is not valid as a variant or a utility so we want to
            // skip if an `@` is followed by a `-`. Otherwise `@-2xl:flex` and
            // `@-2xl:flex` would be considered the same.
            if ($root[0] === '@' && $exists('@') && $input[$idx] === '-') {
                break;
            }
            yield $root;
        }
        $idx = strrpos(substr($input, 0, $idx), '-');
        if ($idx === \false) {
            break;
        }
    }
    // Try '@' variant after permutations. This allows things like `@max` of `@max-foo-bar`
    // to match before looking for `@`.
    if (strlen($input) > 0 && $input[0] === '@' && $exists('@')) {
        yield ['@', substr($input, 1)];
    }
}
/**
 * Print a candidate back to a string.
 *
 * @param DesignSystemInterface $designSystem
 * @param array $candidate
 * @return string
 */
function printCandidate(DesignSystemInterface $designSystem, array $candidate): string
{
    $parts = [];
    foreach ($candidate['variants'] as $variant) {
        array_unshift($parts, printVariant($variant));
    }
    // Handle prefix
    $theme = $designSystem->getTheme();
    if ($theme->prefix) {
        array_unshift($parts, $theme->prefix);
    }
    $base = '';
    // Handle static
    if ($candidate['kind'] === 'static') {
        $base .= $candidate['root'];
    }
    // Handle functional
    if ($candidate['kind'] === 'functional') {
        $base .= $candidate['root'];
        if ($candidate['value']) {
            if ($candidate['value']['kind'] === 'arbitrary') {
                if ($candidate['value'] !== null) {
                    $isVarValue = isVar($candidate['value']['value']);
                    $value = $isVarValue ? substr($candidate['value']['value'], 4, -1) : $candidate['value']['value'];
                    [$open, $close] = $isVarValue ? ['(', ')'] : ['[', ']'];
                    if ($candidate['value']['dataType']) {
                        $base .= "-{$open}{$candidate['value']['dataType']}:" . printArbitraryValue($value) . $close;
                    } else {
                        $base .= "-{$open}" . printArbitraryValue($value) . $close;
                    }
                }
            } elseif ($candidate['value']['kind'] === 'named') {
                $base .= "-{$candidate['value']['value']}";
            }
        }
    }
    // Handle arbitrary
    if ($candidate['kind'] === 'arbitrary') {
        $base .= "[{$candidate['property']}:" . printArbitraryValue($candidate['value']) . ']';
    }
    // Handle modifier
    if ($candidate['kind'] === 'arbitrary' || $candidate['kind'] === 'functional') {
        $base .= printModifier($candidate['modifier']);
    }
    // Handle important
    if ($candidate['important']) {
        $base .= '!';
    }
    $parts[] = $base;
    return implode(':', $parts);
}
/**
 * Print a modifier back to a string.
 *
 * @param array|null $modifier
 * @return string
 */
function printModifier(?array $modifier): string
{
    if ($modifier === null) {
        return '';
    }
    $isVarValue = isVar($modifier['value']);
    $value = $isVarValue ? substr($modifier['value'], 4, -1) : $modifier['value'];
    [$open, $close] = $isVarValue ? ['(', ')'] : ['[', ']'];
    if ($modifier['kind'] === 'arbitrary') {
        return "/{$open}" . printArbitraryValue($value) . $close;
    } elseif ($modifier['kind'] === 'named') {
        return "/{$modifier['value']}";
    }
    return '';
}
/**
 * Print a variant back to a string.
 *
 * @param array $variant
 * @return string
 */
function printVariant(array $variant): string
{
    // Handle static variants
    if ($variant['kind'] === 'static') {
        return $variant['root'];
    }
    // Handle arbitrary variants
    if ($variant['kind'] === 'arbitrary') {
        return '[' . printArbitraryValue(simplifyArbitraryVariant($variant['selector'])) . ']';
    }
    $base = '';
    // Handle functional variants
    if ($variant['kind'] === 'functional') {
        $base .= $variant['root'];
        // `@` is a special case for functional variants. We want to print: `@lg`
        // instead of `@-lg`
        $hasDash = $variant['root'] !== '@';
        if ($variant['value']) {
            if ($variant['value']['kind'] === 'arbitrary') {
                $isVarValue = isVar($variant['value']['value']);
                $value = $isVarValue ? substr($variant['value']['value'], 4, -1) : $variant['value']['value'];
                [$open, $close] = $isVarValue ? ['(', ')'] : ['[', ']'];
                $base .= ($hasDash ? '-' : '') . $open . printArbitraryValue($value) . $close;
            } elseif ($variant['value']['kind'] === 'named') {
                $base .= ($hasDash ? '-' : '') . $variant['value']['value'];
            }
        }
    }
    // Handle compound variants
    if ($variant['kind'] === 'compound') {
        $base .= $variant['root'];
        $base .= '-';
        $base .= printVariant($variant['variant']);
    }
    // Handle modifiers
    if ($variant['kind'] === 'functional' || $variant['kind'] === 'compound') {
        $base .= printModifier($variant['modifier']);
    }
    return $base;
}
/**
 * Maximum cache size for candidate caches (FIFO eviction when exceeded).
 */
const CACHE_MAX_SIZE = 256;
/**
 * Cache for printArbitraryValue results.
 * @var array<string, string>
 */
$printArbitraryValueCache = [];
/**
 * Print an arbitrary value back to a string, handling whitespace normalization.
 *
 * @param string $input
 * @return string
 */
function printArbitraryValue(string $input): string
{
    global $printArbitraryValueCache;
    if (isset($printArbitraryValueCache[$input])) {
        return $printArbitraryValueCache[$input];
    }
    $ast = valueParse($input);
    $drop = [];
    walk($ast, function (&$node, $ctx) use (&$ast, &$drop) {
        $parentArray = $ctx['parent'] === null ? $ast : $ctx['parent']['nodes'] ?? [];
        // Handle operators (e.g.: inside of `calc(…)`)
        if ($node['kind'] === 'word' && ($node['value'] === '+' || $node['value'] === '-' || $node['value'] === '*' || $node['value'] === '/')) {
            $idx = array_search($node, $parentArray, \true);
            // This should not be possible
            if ($idx === \false) {
                return;
            }
            $previous = $parentArray[$idx - 1] ?? null;
            if (!$previous || $previous['kind'] !== 'separator' || $previous['value'] !== ' ') {
                return;
            }
            $next = $parentArray[$idx + 1] ?? null;
            if (!$next || $next['kind'] !== 'separator' || $next['value'] !== ' ') {
                return;
            }
            $drop[] = spl_object_hash((object) $previous);
            $drop[] = spl_object_hash((object) $next);
        } elseif ($node['kind'] === 'separator' && strlen($node['value']) > 0 && trim($node['value']) === '') {
            if ($parentArray[0] === $node || $parentArray[count($parentArray) - 1] === $node) {
                $drop[] = spl_object_hash((object) $node);
            }
        } elseif ($node['kind'] === 'separator' && trim($node['value']) === ',') {
            $node['value'] = ',';
        }
    });
    // Due to PHP's value semantics, we need a different approach.
    // Let's rebuild the AST without the dropped nodes.
    if (!empty($drop)) {
        $ast = filterAstNodes($ast, $drop);
    }
    recursivelyEscapeUnderscores($ast);
    $result = valueToCss($ast);
    // Cache with size limit (FIFO eviction)
    if (count($printArbitraryValueCache) >= CACHE_MAX_SIZE) {
        array_shift($printArbitraryValueCache);
    }
    $printArbitraryValueCache[$input] = $result;
    return $result;
}
/**
 * Filter out dropped nodes from an AST recursively.
 *
 * @param array $ast
 * @param array $dropHashes
 * @return array
 */
function filterAstNodes(array $ast, array $dropHashes): array
{
    $result = [];
    foreach ($ast as $node) {
        $hash = spl_object_hash((object) $node);
        if (in_array($hash, $dropHashes)) {
            continue;
        }
        if (isset($node['nodes'])) {
            $node['nodes'] = filterAstNodes($node['nodes'], $dropHashes);
        }
        $result[] = $node;
    }
    return $result;
}
/**
 * Cache for simplifyArbitraryVariant results.
 * @var array<string, string>
 */
$simplifyArbitraryVariantCache = [];
/**
 * Simplify an arbitrary variant selector.
 *
 * @param string $input
 * @return string
 */
function simplifyArbitraryVariant(string $input): string
{
    global $simplifyArbitraryVariantCache;
    if (isset($simplifyArbitraryVariantCache[$input])) {
        return $simplifyArbitraryVariantCache[$input];
    }
    $ast = valueParse($input);
    // &:is(…)
    if (count($ast) === 3 && $ast[0]['kind'] === 'word' && $ast[0]['value'] === '&' && $ast[1]['kind'] === 'separator' && $ast[1]['value'] === ':' && $ast[2]['kind'] === 'function' && $ast[2]['value'] === 'is') {
        $result = valueToCss($ast[2]['nodes']);
        // Cache with size limit (FIFO eviction)
        if (count($simplifyArbitraryVariantCache) >= CACHE_MAX_SIZE) {
            array_shift($simplifyArbitraryVariantCache);
        }
        $simplifyArbitraryVariantCache[$input] = $result;
        return $result;
    }
    // Cache with size limit (FIFO eviction)
    if (count($simplifyArbitraryVariantCache) >= CACHE_MAX_SIZE) {
        array_shift($simplifyArbitraryVariantCache);
    }
    $simplifyArbitraryVariantCache[$input] = $input;
    return $input;
}
/**
 * Recursively escape underscores in an AST.
 *
 * @param array &$ast
 * @return void
 */
function recursivelyEscapeUnderscores(array &$ast): void
{
    for ($i = 0; $i < count($ast); $i++) {
        $node =& $ast[$i];
        switch ($node['kind']) {
            case 'function':
                if ($node['value'] === 'url' || str_ends_with($node['value'], '_url')) {
                    // Don't decode underscores in url() but do decode the function name
                    $node['value'] = escapeUnderscore($node['value']);
                    break;
                }
                if ($node['value'] === 'var' || str_ends_with($node['value'], '_var') || $node['value'] === 'theme' || str_ends_with($node['value'], '_theme')) {
                    $node['value'] = escapeUnderscore($node['value']);
                    for ($j = 0; $j < count($node['nodes']); $j++) {
                        $subAst = [$node['nodes'][$j]];
                        recursivelyEscapeUnderscores($subAst);
                        $node['nodes'][$j] = $subAst[0];
                    }
                    break;
                }
                $node['value'] = escapeUnderscore($node['value']);
                recursivelyEscapeUnderscores($node['nodes']);
                break;
            case 'separator':
                $node['value'] = escapeUnderscore($node['value']);
                break;
            case 'word':
                // Dashed idents and variables `var(--my-var)` and `--my-var` should not
                // have underscores escaped
                if (!(strlen($node['value']) >= 2 && $node['value'][0] === '-' && $node['value'][1] === '-')) {
                    $node['value'] = escapeUnderscore($node['value']);
                }
                break;
        }
    }
}
/**
 * Cache for isVar results.
 * @var array<string, bool>
 */
$isVarCache = [];
/**
 * Check if a value is a var() function.
 *
 * @param string $value
 * @return bool
 */
function isVar(string $value): bool
{
    global $isVarCache;
    if (isset($isVarCache[$value])) {
        return $isVarCache[$value];
    }
    $ast = valueParse($value);
    $result = count($ast) === 1 && $ast[0]['kind'] === 'function' && $ast[0]['value'] === 'var';
    // Cache with size limit (FIFO eviction)
    if (count($isVarCache) >= CACHE_MAX_SIZE) {
        array_shift($isVarCache);
    }
    $isVarCache[$value] = $result;
    return $result;
}
/**
 * Escape underscores in a string.
 *
 * @param string $value
 * @return string
 */
function escapeUnderscore(string $value): string
{
    return str_replace(' ', '_', str_replace('_', '\_', $value));
}
