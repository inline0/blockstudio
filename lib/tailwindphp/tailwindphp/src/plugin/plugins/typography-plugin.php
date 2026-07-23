<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Plugin\Plugins;

use BlockstudioVendor\TailwindPHP\Plugin\PluginAPI;
use BlockstudioVendor\TailwindPHP\Plugin\PluginInterface;
/**
 * Typography Plugin - Beautiful typographic defaults for HTML.
 *
 * Port of: @tailwindcss/typography
 *
 * This plugin provides the `prose` class and its modifiers for styling
 * article content, blog posts, documentation, etc.
 */
class TypographyPlugin implements PluginInterface
{
    public function getName(): string
    {
        return '@tailwindcss/typography';
    }
    public function __invoke(PluginAPI $api, array $options = []): void
    {
        $className = $options['className'] ?? 'prose';
        $target = $options['target'] ?? 'modern';
        // Register element variants
        $elements = [['headings', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'th'], ['h1'], ['h2'], ['h3'], ['h4'], ['h5'], ['h6'], ['p'], ['a'], ['blockquote'], ['figure'], ['figcaption'], ['strong'], ['em'], ['kbd'], ['code'], ['pre'], ['ol'], ['ul'], ['li'], ['dl'], ['dt'], ['dd'], ['table'], ['thead'], ['tr'], ['th'], ['td'], ['img'], ['picture'], ['video'], ['hr'], ['lead', '[class~="lead"]']];
        foreach ($elements as $element) {
            $name = $element[0];
            $selectors = count($element) > 1 ? array_slice($element, 1) : [$name];
            if ($target === 'legacy') {
                $selector = array_map(fn($s) => "& {$s}", $selectors);
            } else {
                $selectorStr = implode(', ', $selectors);
                $selector = "& :is({$this->inWhere($selectorStr, $className, 'DEFAULT')})";
            }
            $api->addVariant("{$className}-{$name}", $selector);
        }
        // Get typography styles from theme (custom config overrides defaults)
        $themeTypography = $api->theme('typography');
        $modifiers = !empty($themeTypography) ? $this->normalizeTypographyConfig($themeTypography) : $this->getStyles();
        // Add components for each modifier (DEFAULT, sm, lg, xl, 2xl, colors, invert)
        foreach ($modifiers as $modifier => $styles) {
            $componentClass = $modifier === 'DEFAULT' ? ".{$className}" : ".{$className}-{$modifier}";
            $css = $this->configToCss($styles, $target, $className, $modifier);
            $api->addComponents([$componentClass => $css]);
        }
    }
    public function getThemeExtensions(array $options = []): array
    {
        return ['typography' => $this->getStyles()];
    }
    /**
     * Normalize typography config from theme to internal format.
     *
     * The config format from theme is:
     * [
     *   'DEFAULT' => ['css' => [{ ... css rules ... }]],
     *   'lg' => ['css' => [{ ... css rules ... }]],
     * ]
     *
     * We need to flatten this to:
     * [
     *   'DEFAULT' => { ... css rules ... },
     *   'lg' => { ... css rules ... },
     * ]
     */
    private function normalizeTypographyConfig(array $config): array
    {
        $result = [];
        foreach ($config as $modifier => $modifierConfig) {
            // Skip internal metadata
            if (str_starts_with((string) $modifier, '_')) {
                continue;
            }
            if (isset($modifierConfig['css'])) {
                // Flatten the css array
                $styles = [];
                foreach ((array) $modifierConfig['css'] as $cssBlock) {
                    if (is_array($cssBlock)) {
                        $styles = array_merge($styles, $this->convertCamelCaseKeys($cssBlock));
                    }
                }
                $result[$modifier] = $styles;
            } else {
                // Already in expected format
                $result[$modifier] = $modifierConfig;
            }
        }
        return $result;
    }
    /**
     * Convert camelCase CSS property names to kebab-case.
     */
    private function convertCamelCaseKeys(array $styles): array
    {
        $result = [];
        foreach ($styles as $key => $value) {
            // Convert camelCase property names to kebab-case
            $kebabKey = $this->toKebabCase($key);
            if (is_array($value) && !$this->isArrayOfValues($value)) {
                // Nested selector - recursively convert
                $result[$kebabKey] = $this->convertCamelCaseKeys($value);
            } elseif (is_int($value) || is_float($value)) {
                // Convert numeric values to strings
                $result[$kebabKey] = (string) $value;
            } else {
                $result[$kebabKey] = $value;
            }
        }
        return $result;
    }
    /**
     * Check if array is a list of values (like textAlign: ['-webkit-match-parent', 'match-parent'])
     */
    private function isArrayOfValues(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }
    /**
     * Convert camelCase to kebab-case.
     */
    private function toKebabCase(string $str): string
    {
        // Don't convert if it's a CSS selector (contains special chars)
        if (preg_match('/[>\[\]~: ]/', $str)) {
            return $str;
        }
        return strtolower(preg_replace('/([A-Z])/', '-$1', $str));
    }
    /**
     * Wrap selector in :where() with not-prose exclusion.
     */
    private function inWhere(string $selector, string $className, string $modifier): string
    {
        $prefixedNot = "not-{$className}";
        $selectorPrefix = '';
        if (str_starts_with($selector, '>')) {
            $selectorPrefix = $modifier === 'DEFAULT' ? ".{$className} " : ".{$className}-{$modifier} ";
        }
        // Extract common trailing pseudo-elements (::before, ::after, ::marker, etc.)
        [$trailingPseudo, $rebuiltSelector] = $this->commonTrailingPseudos($selector);
        if ($trailingPseudo !== null) {
            return ":where({$selectorPrefix}{$rebuiltSelector}):not(:where([class~=\"{$prefixedNot}\"],[class~=\"{$prefixedNot}\"] *)){$trailingPseudo}";
        }
        return ":where({$selectorPrefix}{$selector}):not(:where([class~=\"{$prefixedNot}\"],[class~=\"{$prefixedNot}\"] *))";
    }
    /**
     * Extract common trailing pseudo-elements from selector.
     *
     * For "blockquote p:first-of-type::before" returns ["::before", "blockquote p:first-of-type"]
     * For "ol li::before, ul li::before" returns ["::before", "ol li, ul li"]
     * For "ol li::before, ul li::after" returns [null, "ol li::before, ul li::after"] (different pseudos)
     *
     * @return array{0: string|null, 1: string}
     */
    private function commonTrailingPseudos(string $selector): array
    {
        // Split by comma for multiple selectors
        $selectors = array_map('trim', explode(',', $selector));
        // For each selector, extract trailing pseudo-elements
        $pseudoMatrix = [];
        $rebuiltSelectors = [];
        foreach ($selectors as $i => $sel) {
            $pseudos = [];
            $remaining = $sel;
            // Extract all trailing pseudo-elements (::before, ::after, ::marker, etc.)
            while (preg_match('/(::[\w-]+)$/', $remaining, $match)) {
                array_unshift($pseudos, $match[1]);
                $remaining = substr($remaining, 0, -strlen($match[1]));
            }
            $pseudoMatrix[$i] = $pseudos;
            $rebuiltSelectors[$i] = $remaining;
        }
        // Find common trailing pseudos across all selectors
        if (empty($pseudoMatrix[0])) {
            return [null, $selector];
        }
        $commonPseudos = [];
        $maxLen = max(array_map('count', $pseudoMatrix));
        for ($j = 0; $j < $maxLen; $j++) {
            $values = [];
            foreach ($pseudoMatrix as $pseudos) {
                if (isset($pseudos[$j])) {
                    $values[] = $pseudos[$j];
                }
            }
            // Check if all selectors have the same pseudo at this position
            if (count($values) !== count($selectors) || count(array_unique($values)) !== 1) {
                break;
            }
            $commonPseudos[] = $values[0];
            // Remove this pseudo from rebuilt selectors tracking
            foreach ($pseudoMatrix as $i => $pseudos) {
                unset($pseudoMatrix[$i][$j]);
            }
        }
        if (empty($commonPseudos)) {
            return [null, $selector];
        }
        // Rebuild selectors with remaining pseudos
        $finalSelectors = [];
        foreach ($rebuiltSelectors as $i => $sel) {
            $remainingPseudos = array_values(array_filter($pseudoMatrix[$i] ?? [], fn($p) => $p !== null));
            $finalSelectors[] = $sel . implode('', $remainingPseudos);
        }
        return [implode('', $commonPseudos), implode(', ', $finalSelectors)];
    }
    /**
     * Convert config array to CSS declarations.
     */
    private function configToCss(array $config, string $target, string $className, string $modifier): array
    {
        $result = [];
        foreach ($config as $key => $value) {
            if ($key === 'css') {
                // Merge custom CSS
                foreach ((array) $value as $customCss) {
                    $result = array_merge($result, $customCss);
                }
                continue;
            }
            if (!is_array($value)) {
                // Direct declaration
                $result[$key] = $value;
                continue;
            }
            // Nested selector
            if ($target === 'legacy') {
                $result[$key] = $value;
            } else {
                $wrappedKey = $this->inWhere($key, $className, $modifier);
                $result[$wrappedKey] = $this->processNestedStyles($value, $target, $className, $modifier);
            }
        }
        return $result;
    }
    /**
     * Process nested style values.
     */
    private function processNestedStyles(array $styles, string $target, string $className, string $modifier): array
    {
        $result = [];
        foreach ($styles as $key => $value) {
            if (is_array($value) && !$this->isDeclarationValue($value)) {
                // Nested selector within nested selector
                if ($target === 'modern') {
                    $wrappedKey = $this->inWhere($key, $className, $modifier);
                    $result[$wrappedKey] = $this->processNestedStyles($value, $target, $className, $modifier);
                } else {
                    $result[$key] = $value;
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    /**
     * Check if a value is a CSS declaration value (not a nested selector).
     */
    private function isDeclarationValue(mixed $value): bool
    {
        if (!is_array($value)) {
            return \true;
        }
        // If all keys are CSS property names, it's a declaration block
        foreach (array_keys($value) as $key) {
            if (!is_string($key) || !preg_match('/^[a-z-]+$/', $key)) {
                return \false;
            }
        }
        return \true;
    }
    /**
     * Get the typography styles.
     *
     * These are the default prose styles from @tailwindcss/typography.
     */
    private function getStyles(): array
    {
        return [
            'DEFAULT' => $this->getDefaultStyles(),
            'sm' => $this->getSmStyles(),
            'base' => $this->getBaseStyles(),
            'lg' => $this->getLgStyles(),
            'xl' => $this->getXlStyles(),
            '2xl' => $this->get2xlStyles(),
            'invert' => $this->getInvertStyles(),
            // Color modifiers
            'slate' => $this->getColorStyles('slate'),
            'gray' => $this->getColorStyles('gray'),
            'zinc' => $this->getColorStyles('zinc'),
            'neutral' => $this->getColorStyles('neutral'),
            'stone' => $this->getColorStyles('stone'),
        ];
    }
    /**
     * Default prose styles.
     */
    private function getDefaultStyles(): array
    {
        return [
            '--tw-prose-body' => 'var(--color-gray-700)',
            '--tw-prose-headings' => 'var(--color-gray-900)',
            '--tw-prose-lead' => 'var(--color-gray-600)',
            '--tw-prose-links' => 'var(--color-gray-900)',
            '--tw-prose-bold' => 'var(--color-gray-900)',
            '--tw-prose-counters' => 'var(--color-gray-500)',
            '--tw-prose-bullets' => 'var(--color-gray-300)',
            '--tw-prose-hr' => 'var(--color-gray-200)',
            '--tw-prose-quotes' => 'var(--color-gray-900)',
            '--tw-prose-quote-borders' => 'var(--color-gray-200)',
            '--tw-prose-captions' => 'var(--color-gray-500)',
            '--tw-prose-kbd' => 'var(--color-gray-900)',
            '--tw-prose-kbd-shadows' => '17 24 39',
            '--tw-prose-code' => 'var(--color-gray-900)',
            '--tw-prose-pre-code' => 'var(--color-gray-200)',
            '--tw-prose-pre-bg' => 'var(--color-gray-800)',
            '--tw-prose-th-borders' => 'var(--color-gray-300)',
            '--tw-prose-td-borders' => 'var(--color-gray-200)',
            '--tw-prose-invert-body' => 'var(--color-gray-300)',
            '--tw-prose-invert-headings' => '#fff',
            '--tw-prose-invert-lead' => 'var(--color-gray-400)',
            '--tw-prose-invert-links' => '#fff',
            '--tw-prose-invert-bold' => '#fff',
            '--tw-prose-invert-counters' => 'var(--color-gray-400)',
            '--tw-prose-invert-bullets' => 'var(--color-gray-600)',
            '--tw-prose-invert-hr' => 'var(--color-gray-700)',
            '--tw-prose-invert-quotes' => 'var(--color-gray-100)',
            '--tw-prose-invert-quote-borders' => 'var(--color-gray-700)',
            '--tw-prose-invert-captions' => 'var(--color-gray-400)',
            '--tw-prose-invert-kbd' => '#fff',
            '--tw-prose-invert-kbd-shadows' => '255 255 255',
            '--tw-prose-invert-code' => '#fff',
            '--tw-prose-invert-pre-code' => 'var(--color-gray-300)',
            '--tw-prose-invert-pre-bg' => 'rgb(0 0 0 / 50%)',
            '--tw-prose-invert-th-borders' => 'var(--color-gray-600)',
            '--tw-prose-invert-td-borders' => 'var(--color-gray-700)',
            'color' => 'var(--tw-prose-body)',
            'max-width' => '65ch',
            '[class~="lead"]' => ['color' => 'var(--tw-prose-lead)', 'font-size' => '1.25em', 'line-height' => '1.6', 'margin-top' => '1.2em', 'margin-bottom' => '1.2em'],
            'a' => ['color' => 'var(--tw-prose-links)', 'text-decoration' => 'underline', 'font-weight' => '500'],
            'strong' => ['color' => 'var(--tw-prose-bold)', 'font-weight' => '600'],
            'a strong' => ['color' => 'inherit'],
            'blockquote strong' => ['color' => 'inherit'],
            'thead th strong' => ['color' => 'inherit'],
            'ol' => ['list-style-type' => 'decimal', 'margin-top' => '1.25em', 'margin-bottom' => '1.25em', 'padding-inline-start' => '1.625em'],
            'ol[type="A"]' => ['list-style-type' => 'upper-alpha'],
            'ol[type="a"]' => ['list-style-type' => 'lower-alpha'],
            'ol[type="A" s]' => ['list-style-type' => 'upper-alpha'],
            'ol[type="a" s]' => ['list-style-type' => 'lower-alpha'],
            'ol[type="I"]' => ['list-style-type' => 'upper-roman'],
            'ol[type="i"]' => ['list-style-type' => 'lower-roman'],
            'ol[type="I" s]' => ['list-style-type' => 'upper-roman'],
            'ol[type="i" s]' => ['list-style-type' => 'lower-roman'],
            'ol[type="1"]' => ['list-style-type' => 'decimal'],
            'ul' => ['list-style-type' => 'disc', 'margin-top' => '1.25em', 'margin-bottom' => '1.25em', 'padding-inline-start' => '1.625em'],
            'ol > li::marker' => ['font-weight' => '400', 'color' => 'var(--tw-prose-counters)'],
            'ul > li::marker' => ['color' => 'var(--tw-prose-bullets)'],
            'dt' => ['color' => 'var(--tw-prose-headings)', 'font-weight' => '600', 'margin-top' => '1.25em'],
            'hr' => ['border-color' => 'var(--tw-prose-hr)', 'border-top-width' => '1px', 'margin-top' => '3em', 'margin-bottom' => '3em'],
            'blockquote' => ['font-weight' => '500', 'font-style' => 'italic', 'color' => 'var(--tw-prose-quotes)', 'border-inline-start-width' => '.25rem', 'border-inline-start-color' => 'var(--tw-prose-quote-borders)', 'quotes' => '"\201C""\201D""\2018""\2019"', 'margin-top' => '1.6em', 'margin-bottom' => '1.6em', 'padding-inline-start' => '1em'],
            'blockquote p:first-of-type::before' => ['content' => 'open-quote'],
            'blockquote p:last-of-type::after' => ['content' => 'close-quote'],
            'h1' => ['color' => 'var(--tw-prose-headings)', 'font-weight' => '800', 'font-size' => '2.25em', 'margin-top' => '0', 'margin-bottom' => '.8888889em', 'line-height' => '1.1111111'],
            'h1 strong' => ['font-weight' => '900', 'color' => 'inherit'],
            'h2' => ['color' => 'var(--tw-prose-headings)', 'font-weight' => '700', 'font-size' => '1.5em', 'margin-top' => '2em', 'margin-bottom' => '1em', 'line-height' => '1.3333333'],
            'h2 strong' => ['font-weight' => '800', 'color' => 'inherit'],
            'h3' => ['color' => 'var(--tw-prose-headings)', 'font-weight' => '600', 'font-size' => '1.25em', 'margin-top' => '1.6em', 'margin-bottom' => '.6em', 'line-height' => '1.6'],
            'h3 strong' => ['font-weight' => '700', 'color' => 'inherit'],
            'h4' => ['color' => 'var(--tw-prose-headings)', 'font-weight' => '600', 'margin-top' => '1.5em', 'margin-bottom' => '.5em', 'line-height' => '1.5'],
            'h4 strong' => ['font-weight' => '700', 'color' => 'inherit'],
            'img' => ['margin-top' => '2em', 'margin-bottom' => '2em'],
            'picture' => ['display' => 'block', 'margin-top' => '2em', 'margin-bottom' => '2em'],
            'picture > img' => ['margin-top' => '0', 'margin-bottom' => '0'],
            'video' => ['margin-top' => '2em', 'margin-bottom' => '2em'],
            'kbd' => ['font-weight' => '500', 'font-family' => 'inherit', 'color' => 'var(--tw-prose-kbd)', 'box-shadow' => '0 0 0 1px rgb(var(--tw-prose-kbd-shadows) / 10%), 0 3px 0 rgb(var(--tw-prose-kbd-shadows) / 10%)', 'font-size' => '.875em', 'border-radius' => '.3125rem', 'padding-top' => '.1875em', 'padding-inline-end' => '.375em', 'padding-bottom' => '.1875em', 'padding-inline-start' => '.375em'],
            'code' => ['color' => 'var(--tw-prose-code)', 'font-weight' => '600', 'font-size' => '.875em'],
            'code::before' => ['content' => '"`"'],
            'code::after' => ['content' => '"`"'],
            'a code' => ['color' => 'inherit'],
            'h1 code' => ['color' => 'inherit'],
            'h2 code' => ['color' => 'inherit', 'font-size' => '.875em'],
            'h3 code' => ['color' => 'inherit', 'font-size' => '.9em'],
            'h4 code' => ['color' => 'inherit'],
            'blockquote code' => ['color' => 'inherit'],
            'thead th code' => ['color' => 'inherit'],
            'pre' => ['color' => 'var(--tw-prose-pre-code)', 'background-color' => 'var(--tw-prose-pre-bg)', 'overflow-x' => 'auto', 'font-weight' => '400', 'font-size' => '.875em', 'line-height' => '1.7142857', 'margin-top' => '1.7142857em', 'margin-bottom' => '1.7142857em', 'border-radius' => '.375rem', 'padding-top' => '.8571429em', 'padding-inline-end' => '1.1428571em', 'padding-bottom' => '.8571429em', 'padding-inline-start' => '1.1428571em'],
            'pre code' => ['background-color' => 'transparent', 'border-width' => '0', 'border-radius' => '0', 'padding' => '0', 'font-weight' => 'inherit', 'color' => 'inherit', 'font-size' => 'inherit', 'font-family' => 'inherit', 'line-height' => 'inherit'],
            'pre code::before' => ['content' => 'none'],
            'pre code::after' => ['content' => 'none'],
            'table' => ['width' => '100%', 'table-layout' => 'auto', 'margin-top' => '2em', 'margin-bottom' => '2em', 'font-size' => '.875em', 'line-height' => '1.7142857'],
            'thead' => ['border-bottom-width' => '1px', 'border-bottom-color' => 'var(--tw-prose-th-borders)'],
            'thead th' => ['color' => 'var(--tw-prose-headings)', 'font-weight' => '600', 'vertical-align' => 'bottom', 'padding-inline-end' => '.5714286em', 'padding-bottom' => '.5714286em', 'padding-inline-start' => '.5714286em'],
            'tbody tr' => ['border-bottom-width' => '1px', 'border-bottom-color' => 'var(--tw-prose-td-borders)'],
            'tbody tr:last-child' => ['border-bottom-width' => '0'],
            'tbody td' => ['vertical-align' => 'baseline'],
            'tfoot' => ['border-top-width' => '1px', 'border-top-color' => 'var(--tw-prose-th-borders)'],
            'tfoot td' => ['vertical-align' => 'top'],
            'figure > *' => ['margin-top' => '0', 'margin-bottom' => '0'],
            'figcaption' => ['color' => 'var(--tw-prose-captions)', 'font-size' => '.875em', 'line-height' => '1.4285714', 'margin-top' => '.8571429em'],
            // Base size typography
            'font-size' => '1rem',
            'line-height' => '1.75',
            'p' => ['margin-top' => '1.25em', 'margin-bottom' => '1.25em'],
            '> ul > li p' => ['margin-top' => '.75em', 'margin-bottom' => '.75em'],
            '> ul > li > p:first-child' => ['margin-top' => '1.25em'],
            '> ul > li > p:last-child' => ['margin-bottom' => '1.25em'],
            '> ol > li > p:first-child' => ['margin-top' => '1.25em'],
            '> ol > li > p:last-child' => ['margin-bottom' => '1.25em'],
            'ul ul, ul ol, ol ul, ol ol' => ['margin-top' => '.75em', 'margin-bottom' => '.75em'],
            'dl' => ['margin-top' => '1.25em', 'margin-bottom' => '1.25em'],
            'dd' => ['margin-top' => '.5em', 'padding-inline-start' => '1.625em'],
            'hr + *' => ['margin-top' => '0'],
            'h2 + *' => ['margin-top' => '0'],
            'h3 + *' => ['margin-top' => '0'],
            'h4 + *' => ['margin-top' => '0'],
            'thead th:first-child' => ['padding-inline-start' => '0'],
            'thead th:last-child' => ['padding-inline-end' => '0'],
            'tbody td, tfoot td' => ['padding-top' => '.5714286em', 'padding-inline-end' => '.5714286em', 'padding-bottom' => '.5714286em', 'padding-inline-start' => '.5714286em'],
            'tbody td:first-child, tfoot td:first-child' => ['padding-inline-start' => '0'],
            'tbody td:last-child, tfoot td:last-child' => ['padding-inline-end' => '0'],
            '> :first-child' => ['margin-top' => '0'],
            '> :last-child' => ['margin-bottom' => '0'],
        ];
    }
    /**
     * Small size modifier styles.
     */
    private function getSmStyles(): array
    {
        return ['font-size' => '.875rem', 'line-height' => '1.7142857', 'p' => ['margin-top' => '1.1428571em', 'margin-bottom' => '1.1428571em'], '[class~="lead"]' => ['font-size' => '1.2857143em', 'line-height' => '1.5555556', 'margin-top' => '.8888889em', 'margin-bottom' => '.8888889em'], 'blockquote' => ['margin-top' => '1.3333333em', 'margin-bottom' => '1.3333333em', 'padding-inline-start' => '1.1111111em'], 'h1' => ['font-size' => '2.1428571em', 'margin-top' => '0', 'margin-bottom' => '.8em', 'line-height' => '1.2'], 'h2' => ['font-size' => '1.4285714em', 'margin-top' => '1.6em', 'margin-bottom' => '.8em', 'line-height' => '1.4'], 'h3' => ['font-size' => '1.2857143em', 'margin-top' => '1.5555556em', 'margin-bottom' => '.4444444em', 'line-height' => '1.5555556'], 'h4' => ['margin-top' => '1.4285714em', 'margin-bottom' => '.5714286em', 'line-height' => '1.4285714'], 'img' => ['margin-top' => '1.7142857em', 'margin-bottom' => '1.7142857em'], 'picture' => ['margin-top' => '1.7142857em', 'margin-bottom' => '1.7142857em'], 'picture > img' => ['margin-top' => '0', 'margin-bottom' => '0'], 'video' => ['margin-top' => '1.7142857em', 'margin-bottom' => '1.7142857em'], 'kbd' => ['font-size' => '.8571429em', 'border-radius' => '.3125rem', 'padding-top' => '.1428571em', 'padding-inline-end' => '.3571429em', 'padding-bottom' => '.1428571em', 'padding-inline-start' => '.3571429em'], 'code' => ['font-size' => '.8571429em'], 'h2 code' => ['font-size' => '.9em'], 'h3 code' => ['font-size' => '.8888889em'], 'pre' => ['font-size' => '.8571429em', 'line-height' => '1.6666667', 'margin-top' => '1.6666667em', 'margin-bottom' => '1.6666667em', 'border-radius' => '.25rem', 'padding-top' => '.6666667em', 'padding-inline-end' => '1em', 'padding-bottom' => '.6666667em', 'padding-inline-start' => '1em'], 'ol' => ['margin-top' => '1.1428571em', 'margin-bottom' => '1.1428571em', 'padding-inline-start' => '1.5714286em'], 'ul' => ['margin-top' => '1.1428571em', 'margin-bottom' => '1.1428571em', 'padding-inline-start' => '1.5714286em'], 'li' => ['margin-top' => '.2857143em', 'margin-bottom' => '.2857143em'], 'ol > li' => ['padding-inline-start' => '.4285714em'], 'ul > li' => ['padding-inline-start' => '.4285714em'], '> ul > li p' => ['margin-top' => '.5714286em', 'margin-bottom' => '.5714286em'], '> ul > li > p:first-child' => ['margin-top' => '1.1428571em'], '> ul > li > p:last-child' => ['margin-bottom' => '1.1428571em'], '> ol > li > p:first-child' => ['margin-top' => '1.1428571em'], '> ol > li > p:last-child' => ['margin-bottom' => '1.1428571em'], 'ul ul, ul ol, ol ul, ol ol' => ['margin-top' => '.5714286em', 'margin-bottom' => '.5714286em'], 'dl' => ['margin-top' => '1.1428571em', 'margin-bottom' => '1.1428571em'], 'dt' => ['margin-top' => '1.1428571em'], 'dd' => ['margin-top' => '.2857143em', 'padding-inline-start' => '1.5714286em'], 'hr' => ['margin-top' => '2.8571429em', 'margin-bottom' => '2.8571429em'], 'hr + *' => ['margin-top' => '0'], 'h2 + *' => ['margin-top' => '0'], 'h3 + *' => ['margin-top' => '0'], 'h4 + *' => ['margin-top' => '0'], 'table' => ['font-size' => '.8571429em', 'line-height' => '1.5'], 'thead th' => ['padding-inline-end' => '1em', 'padding-bottom' => '.6666667em', 'padding-inline-start' => '1em'], 'thead th:first-child' => ['padding-inline-start' => '0'], 'thead th:last-child' => ['padding-inline-end' => '0'], 'tbody td, tfoot td' => ['padding-top' => '.6666667em', 'padding-inline-end' => '1em', 'padding-bottom' => '.6666667em', 'padding-inline-start' => '1em'], 'tbody td:first-child, tfoot td:first-child' => ['padding-inline-start' => '0'], 'tbody td:last-child, tfoot td:last-child' => ['padding-inline-end' => '0'], 'figure' => ['margin-top' => '1.7142857em', 'margin-bottom' => '1.7142857em'], 'figure > *' => ['margin-top' => '0', 'margin-bottom' => '0'], 'figcaption' => ['font-size' => '.8571429em', 'line-height' => '1.3333333', 'margin-top' => '.6666667em'], '> :first-child' => ['margin-top' => '0'], '> :last-child' => ['margin-bottom' => '0']];
    }
    /**
     * Base size modifier (default) styles.
     */
    private function getBaseStyles(): array
    {
        // Base is essentially the same as DEFAULT sizing
        return ['font-size' => '1rem', 'line-height' => '1.75'];
    }
    /**
     * Large size modifier styles.
     */
    private function getLgStyles(): array
    {
        return ['font-size' => '1.125rem', 'line-height' => '1.7777778', 'p' => ['margin-top' => '1.3333333em', 'margin-bottom' => '1.3333333em'], '[class~="lead"]' => ['font-size' => '1.2222222em', 'line-height' => '1.4545455', 'margin-top' => '1.0909091em', 'margin-bottom' => '1.0909091em'], 'blockquote' => ['margin-top' => '1.6666667em', 'margin-bottom' => '1.6666667em', 'padding-inline-start' => '1em'], 'h1' => ['font-size' => '2.6666667em', 'margin-top' => '0', 'margin-bottom' => '.8333333em', 'line-height' => '1'], 'h2' => ['font-size' => '1.6666667em', 'margin-top' => '1.8666667em', 'margin-bottom' => '1.0666667em', 'line-height' => '1.3333333'], 'h3' => ['font-size' => '1.3333333em', 'margin-top' => '1.6666667em', 'margin-bottom' => '.6666667em', 'line-height' => '1.5'], 'h4' => ['margin-top' => '1.7777778em', 'margin-bottom' => '.4444444em', 'line-height' => '1.5555556'], 'img' => ['margin-top' => '1.7777778em', 'margin-bottom' => '1.7777778em'], 'picture' => ['margin-top' => '1.7777778em', 'margin-bottom' => '1.7777778em'], 'picture > img' => ['margin-top' => '0', 'margin-bottom' => '0'], 'video' => ['margin-top' => '1.7777778em', 'margin-bottom' => '1.7777778em'], 'kbd' => ['font-size' => '.8888889em', 'border-radius' => '.3125rem', 'padding-top' => '.2222222em', 'padding-inline-end' => '.4444444em', 'padding-bottom' => '.2222222em', 'padding-inline-start' => '.4444444em'], 'code' => ['font-size' => '.8888889em'], 'h2 code' => ['font-size' => '.8666667em'], 'h3 code' => ['font-size' => '.875em'], 'pre' => ['font-size' => '.8888889em', 'line-height' => '1.75', 'margin-top' => '2em', 'margin-bottom' => '2em', 'border-radius' => '.375rem', 'padding-top' => '1em', 'padding-inline-end' => '1.5em', 'padding-bottom' => '1em', 'padding-inline-start' => '1.5em'], 'ol' => ['margin-top' => '1.3333333em', 'margin-bottom' => '1.3333333em', 'padding-inline-start' => '1.5555556em'], 'ul' => ['margin-top' => '1.3333333em', 'margin-bottom' => '1.3333333em', 'padding-inline-start' => '1.5555556em'], 'li' => ['margin-top' => '.6666667em', 'margin-bottom' => '.6666667em'], 'ol > li' => ['padding-inline-start' => '.4444444em'], 'ul > li' => ['padding-inline-start' => '.4444444em'], '> ul > li p' => ['margin-top' => '.8888889em', 'margin-bottom' => '.8888889em'], '> ul > li > p:first-child' => ['margin-top' => '1.3333333em'], '> ul > li > p:last-child' => ['margin-bottom' => '1.3333333em'], '> ol > li > p:first-child' => ['margin-top' => '1.3333333em'], '> ol > li > p:last-child' => ['margin-bottom' => '1.3333333em'], 'ul ul, ul ol, ol ul, ol ol' => ['margin-top' => '.8888889em', 'margin-bottom' => '.8888889em'], 'dl' => ['margin-top' => '1.3333333em', 'margin-bottom' => '1.3333333em'], 'dt' => ['margin-top' => '1.3333333em'], 'dd' => ['margin-top' => '.6666667em', 'padding-inline-start' => '1.5555556em'], 'hr' => ['margin-top' => '3.1111111em', 'margin-bottom' => '3.1111111em'], 'hr + *' => ['margin-top' => '0'], 'h2 + *' => ['margin-top' => '0'], 'h3 + *' => ['margin-top' => '0'], 'h4 + *' => ['margin-top' => '0'], 'table' => ['font-size' => '.8888889em', 'line-height' => '1.5'], 'thead th' => ['padding-inline-end' => '.75em', 'padding-bottom' => '.75em', 'padding-inline-start' => '.75em'], 'thead th:first-child' => ['padding-inline-start' => '0'], 'thead th:last-child' => ['padding-inline-end' => '0'], 'tbody td, tfoot td' => ['padding-top' => '.75em', 'padding-inline-end' => '.75em', 'padding-bottom' => '.75em', 'padding-inline-start' => '.75em'], 'tbody td:first-child, tfoot td:first-child' => ['padding-inline-start' => '0'], 'tbody td:last-child, tfoot td:last-child' => ['padding-inline-end' => '0'], 'figure' => ['margin-top' => '1.7777778em', 'margin-bottom' => '1.7777778em'], 'figure > *' => ['margin-top' => '0', 'margin-bottom' => '0'], 'figcaption' => ['font-size' => '.8888889em', 'line-height' => '1.5', 'margin-top' => '1em'], '> :first-child' => ['margin-top' => '0'], '> :last-child' => ['margin-bottom' => '0']];
    }
    /**
     * Extra large size modifier styles.
     */
    private function getXlStyles(): array
    {
        return ['font-size' => '1.25rem', 'line-height' => '1.8', 'p' => ['margin-top' => '1.2em', 'margin-bottom' => '1.2em'], '[class~="lead"]' => ['font-size' => '1.2em', 'line-height' => '1.5', 'margin-top' => '1em', 'margin-bottom' => '1em'], 'blockquote' => ['margin-top' => '1.6em', 'margin-bottom' => '1.6em', 'padding-inline-start' => '1.0666667em'], 'h1' => ['font-size' => '2.8em', 'margin-top' => '0', 'margin-bottom' => '.8571429em', 'line-height' => '1'], 'h2' => ['font-size' => '1.8em', 'margin-top' => '1.5555556em', 'margin-bottom' => '.8888889em', 'line-height' => '1.1111111'], 'h3' => ['font-size' => '1.5em', 'margin-top' => '1.6em', 'margin-bottom' => '.6666667em', 'line-height' => '1.3333333'], 'h4' => ['margin-top' => '1.8em', 'margin-bottom' => '.6em', 'line-height' => '1.6'], 'img' => ['margin-top' => '2em', 'margin-bottom' => '2em'], 'picture' => ['margin-top' => '2em', 'margin-bottom' => '2em'], 'picture > img' => ['margin-top' => '0', 'margin-bottom' => '0'], 'video' => ['margin-top' => '2em', 'margin-bottom' => '2em'], 'kbd' => ['font-size' => '.9em', 'border-radius' => '.3125rem', 'padding-top' => '.25em', 'padding-inline-end' => '.4em', 'padding-bottom' => '.25em', 'padding-inline-start' => '.4em'], 'code' => ['font-size' => '.9em'], 'h2 code' => ['font-size' => '.8611111em'], 'h3 code' => ['font-size' => '.9em'], 'pre' => ['font-size' => '.9em', 'line-height' => '1.7777778', 'margin-top' => '2em', 'margin-bottom' => '2em', 'border-radius' => '.5rem', 'padding-top' => '1.1111111em', 'padding-inline-end' => '1.3333333em', 'padding-bottom' => '1.1111111em', 'padding-inline-start' => '1.3333333em'], 'ol' => ['margin-top' => '1.2em', 'margin-bottom' => '1.2em', 'padding-inline-start' => '1.6em'], 'ul' => ['margin-top' => '1.2em', 'margin-bottom' => '1.2em', 'padding-inline-start' => '1.6em'], 'li' => ['margin-top' => '.6em', 'margin-bottom' => '.6em'], 'ol > li' => ['padding-inline-start' => '.4em'], 'ul > li' => ['padding-inline-start' => '.4em'], '> ul > li p' => ['margin-top' => '.8em', 'margin-bottom' => '.8em'], '> ul > li > p:first-child' => ['margin-top' => '1.2em'], '> ul > li > p:last-child' => ['margin-bottom' => '1.2em'], '> ol > li > p:first-child' => ['margin-top' => '1.2em'], '> ol > li > p:last-child' => ['margin-bottom' => '1.2em'], 'ul ul, ul ol, ol ul, ol ol' => ['margin-top' => '.8em', 'margin-bottom' => '.8em'], 'dl' => ['margin-top' => '1.2em', 'margin-bottom' => '1.2em'], 'dt' => ['margin-top' => '1.2em'], 'dd' => ['margin-top' => '.6em', 'padding-inline-start' => '1.6em'], 'hr' => ['margin-top' => '2.8em', 'margin-bottom' => '2.8em'], 'hr + *' => ['margin-top' => '0'], 'h2 + *' => ['margin-top' => '0'], 'h3 + *' => ['margin-top' => '0'], 'h4 + *' => ['margin-top' => '0'], 'table' => ['font-size' => '.9em', 'line-height' => '1.5555556'], 'thead th' => ['padding-inline-end' => '.6666667em', 'padding-bottom' => '.8888889em', 'padding-inline-start' => '.6666667em'], 'thead th:first-child' => ['padding-inline-start' => '0'], 'thead th:last-child' => ['padding-inline-end' => '0'], 'tbody td, tfoot td' => ['padding-top' => '.8888889em', 'padding-inline-end' => '.6666667em', 'padding-bottom' => '.8888889em', 'padding-inline-start' => '.6666667em'], 'tbody td:first-child, tfoot td:first-child' => ['padding-inline-start' => '0'], 'tbody td:last-child, tfoot td:last-child' => ['padding-inline-end' => '0'], 'figure' => ['margin-top' => '2em', 'margin-bottom' => '2em'], 'figure > *' => ['margin-top' => '0', 'margin-bottom' => '0'], 'figcaption' => ['font-size' => '.9em', 'line-height' => '1.5555556', 'margin-top' => '1em'], '> :first-child' => ['margin-top' => '0'], '> :last-child' => ['margin-bottom' => '0']];
    }
    /**
     * 2xl size modifier styles.
     */
    private function get2xlStyles(): array
    {
        return ['font-size' => '1.5rem', 'line-height' => '1.6666667', 'p' => ['margin-top' => '1.3333333em', 'margin-bottom' => '1.3333333em'], '[class~="lead"]' => ['font-size' => '1.25em', 'line-height' => '1.4666667', 'margin-top' => '1.0666667em', 'margin-bottom' => '1.0666667em'], 'blockquote' => ['margin-top' => '1.7777778em', 'margin-bottom' => '1.7777778em', 'padding-inline-start' => '1.1111111em'], 'h1' => ['font-size' => '2.6666667em', 'margin-top' => '0', 'margin-bottom' => '.875em', 'line-height' => '1'], 'h2' => ['font-size' => '2em', 'margin-top' => '1.5em', 'margin-bottom' => '.8333333em', 'line-height' => '1.0833333'], 'h3' => ['font-size' => '1.5em', 'margin-top' => '1.5555556em', 'margin-bottom' => '.6666667em', 'line-height' => '1.2222222'], 'h4' => ['margin-top' => '1.6666667em', 'margin-bottom' => '.6666667em', 'line-height' => '1.5'], 'img' => ['margin-top' => '2em', 'margin-bottom' => '2em'], 'picture' => ['margin-top' => '2em', 'margin-bottom' => '2em'], 'picture > img' => ['margin-top' => '0', 'margin-bottom' => '0'], 'video' => ['margin-top' => '2em', 'margin-bottom' => '2em'], 'kbd' => ['font-size' => '.8333333em', 'border-radius' => '.3125rem', 'padding-top' => '.25em', 'padding-inline-end' => '.4166667em', 'padding-bottom' => '.25em', 'padding-inline-start' => '.4166667em'], 'code' => ['font-size' => '.8333333em'], 'h2 code' => ['font-size' => '.875em'], 'h3 code' => ['font-size' => '.8888889em'], 'pre' => ['font-size' => '.8333333em', 'line-height' => '1.8', 'margin-top' => '2em', 'margin-bottom' => '2em', 'border-radius' => '.5rem', 'padding-top' => '1.2em', 'padding-inline-end' => '1.6em', 'padding-bottom' => '1.2em', 'padding-inline-start' => '1.6em'], 'ol' => ['margin-top' => '1.3333333em', 'margin-bottom' => '1.3333333em', 'padding-inline-start' => '1.5833333em'], 'ul' => ['margin-top' => '1.3333333em', 'margin-bottom' => '1.3333333em', 'padding-inline-start' => '1.5833333em'], 'li' => ['margin-top' => '.5em', 'margin-bottom' => '.5em'], 'ol > li' => ['padding-inline-start' => '.4166667em'], 'ul > li' => ['padding-inline-start' => '.4166667em'], '> ul > li p' => ['margin-top' => '.8333333em', 'margin-bottom' => '.8333333em'], '> ul > li > p:first-child' => ['margin-top' => '1.3333333em'], '> ul > li > p:last-child' => ['margin-bottom' => '1.3333333em'], '> ol > li > p:first-child' => ['margin-top' => '1.3333333em'], '> ol > li > p:last-child' => ['margin-bottom' => '1.3333333em'], 'ul ul, ul ol, ol ul, ol ol' => ['margin-top' => '.6666667em', 'margin-bottom' => '.6666667em'], 'dl' => ['margin-top' => '1.3333333em', 'margin-bottom' => '1.3333333em'], 'dt' => ['margin-top' => '1.3333333em'], 'dd' => ['margin-top' => '.6666667em', 'padding-inline-start' => '1.5833333em'], 'hr' => ['margin-top' => '3em', 'margin-bottom' => '3em'], 'hr + *' => ['margin-top' => '0'], 'h2 + *' => ['margin-top' => '0'], 'h3 + *' => ['margin-top' => '0'], 'h4 + *' => ['margin-top' => '0'], 'table' => ['font-size' => '.8333333em', 'line-height' => '1.4'], 'thead th' => ['padding-inline-end' => '.6em', 'padding-bottom' => '.8em', 'padding-inline-start' => '.6em'], 'thead th:first-child' => ['padding-inline-start' => '0'], 'thead th:last-child' => ['padding-inline-end' => '0'], 'tbody td, tfoot td' => ['padding-top' => '.8em', 'padding-inline-end' => '.6em', 'padding-bottom' => '.8em', 'padding-inline-start' => '.6em'], 'tbody td:first-child, tfoot td:first-child' => ['padding-inline-start' => '0'], 'tbody td:last-child, tfoot td:last-child' => ['padding-inline-end' => '0'], 'figure' => ['margin-top' => '2em', 'margin-bottom' => '2em'], 'figure > *' => ['margin-top' => '0', 'margin-bottom' => '0'], 'figcaption' => ['font-size' => '.8333333em', 'line-height' => '1.6', 'margin-top' => '1em'], '> :first-child' => ['margin-top' => '0'], '> :last-child' => ['margin-bottom' => '0']];
    }
    /**
     * Invert modifier for dark mode.
     */
    private function getInvertStyles(): array
    {
        return ['--tw-prose-body' => 'var(--tw-prose-invert-body)', '--tw-prose-headings' => 'var(--tw-prose-invert-headings)', '--tw-prose-lead' => 'var(--tw-prose-invert-lead)', '--tw-prose-links' => 'var(--tw-prose-invert-links)', '--tw-prose-bold' => 'var(--tw-prose-invert-bold)', '--tw-prose-counters' => 'var(--tw-prose-invert-counters)', '--tw-prose-bullets' => 'var(--tw-prose-invert-bullets)', '--tw-prose-hr' => 'var(--tw-prose-invert-hr)', '--tw-prose-quotes' => 'var(--tw-prose-invert-quotes)', '--tw-prose-quote-borders' => 'var(--tw-prose-invert-quote-borders)', '--tw-prose-captions' => 'var(--tw-prose-invert-captions)', '--tw-prose-kbd' => 'var(--tw-prose-invert-kbd)', '--tw-prose-kbd-shadows' => 'var(--tw-prose-invert-kbd-shadows)', '--tw-prose-code' => 'var(--tw-prose-invert-code)', '--tw-prose-pre-code' => 'var(--tw-prose-invert-pre-code)', '--tw-prose-pre-bg' => 'var(--tw-prose-invert-pre-bg)', '--tw-prose-th-borders' => 'var(--tw-prose-invert-th-borders)', '--tw-prose-td-borders' => 'var(--tw-prose-invert-td-borders)'];
    }
    /**
     * Color modifier styles.
     */
    private function getColorStyles(string $color): array
    {
        $colors = ['slate' => ['body' => 'var(--color-slate-700)', 'headings' => 'var(--color-slate-900)', 'lead' => 'var(--color-slate-600)', 'links' => 'var(--color-slate-900)', 'bold' => 'var(--color-slate-900)', 'counters' => 'var(--color-slate-500)', 'bullets' => 'var(--color-slate-300)', 'hr' => 'var(--color-slate-200)', 'quotes' => 'var(--color-slate-900)', 'quote-borders' => 'var(--color-slate-200)', 'captions' => 'var(--color-slate-500)', 'kbd' => 'var(--color-slate-900)', 'kbd-shadows' => '15 23 42', 'code' => 'var(--color-slate-900)', 'pre-code' => 'var(--color-slate-200)', 'pre-bg' => 'var(--color-slate-800)', 'th-borders' => 'var(--color-slate-300)', 'td-borders' => 'var(--color-slate-200)', 'invert-body' => 'var(--color-slate-300)', 'invert-headings' => '#fff', 'invert-lead' => 'var(--color-slate-400)', 'invert-links' => '#fff', 'invert-bold' => '#fff', 'invert-counters' => 'var(--color-slate-400)', 'invert-bullets' => 'var(--color-slate-600)', 'invert-hr' => 'var(--color-slate-700)', 'invert-quotes' => 'var(--color-slate-100)', 'invert-quote-borders' => 'var(--color-slate-700)', 'invert-captions' => 'var(--color-slate-400)', 'invert-kbd' => '#fff', 'invert-kbd-shadows' => '255 255 255', 'invert-code' => '#fff', 'invert-pre-code' => 'var(--color-slate-300)', 'invert-pre-bg' => 'rgb(0 0 0 / 50%)', 'invert-th-borders' => 'var(--color-slate-600)', 'invert-td-borders' => 'var(--color-slate-700)'], 'gray' => ['body' => 'var(--color-gray-700)', 'headings' => 'var(--color-gray-900)', 'lead' => 'var(--color-gray-600)', 'links' => 'var(--color-gray-900)', 'bold' => 'var(--color-gray-900)', 'counters' => 'var(--color-gray-500)', 'bullets' => 'var(--color-gray-300)', 'hr' => 'var(--color-gray-200)', 'quotes' => 'var(--color-gray-900)', 'quote-borders' => 'var(--color-gray-200)', 'captions' => 'var(--color-gray-500)', 'kbd' => 'var(--color-gray-900)', 'kbd-shadows' => '17 24 39', 'code' => 'var(--color-gray-900)', 'pre-code' => 'var(--color-gray-200)', 'pre-bg' => 'var(--color-gray-800)', 'th-borders' => 'var(--color-gray-300)', 'td-borders' => 'var(--color-gray-200)', 'invert-body' => 'var(--color-gray-300)', 'invert-headings' => '#fff', 'invert-lead' => 'var(--color-gray-400)', 'invert-links' => '#fff', 'invert-bold' => '#fff', 'invert-counters' => 'var(--color-gray-400)', 'invert-bullets' => 'var(--color-gray-600)', 'invert-hr' => 'var(--color-gray-700)', 'invert-quotes' => 'var(--color-gray-100)', 'invert-quote-borders' => 'var(--color-gray-700)', 'invert-captions' => 'var(--color-gray-400)', 'invert-kbd' => '#fff', 'invert-kbd-shadows' => '255 255 255', 'invert-code' => '#fff', 'invert-pre-code' => 'var(--color-gray-300)', 'invert-pre-bg' => 'rgb(0 0 0 / 50%)', 'invert-th-borders' => 'var(--color-gray-600)', 'invert-td-borders' => 'var(--color-gray-700)'], 'zinc' => ['body' => 'var(--color-zinc-700)', 'headings' => 'var(--color-zinc-900)', 'lead' => 'var(--color-zinc-600)', 'links' => 'var(--color-zinc-900)', 'bold' => 'var(--color-zinc-900)', 'counters' => 'var(--color-zinc-500)', 'bullets' => 'var(--color-zinc-300)', 'hr' => 'var(--color-zinc-200)', 'quotes' => 'var(--color-zinc-900)', 'quote-borders' => 'var(--color-zinc-200)', 'captions' => 'var(--color-zinc-500)', 'kbd' => 'var(--color-zinc-900)', 'kbd-shadows' => '24 24 27', 'code' => 'var(--color-zinc-900)', 'pre-code' => 'var(--color-zinc-200)', 'pre-bg' => 'var(--color-zinc-800)', 'th-borders' => 'var(--color-zinc-300)', 'td-borders' => 'var(--color-zinc-200)', 'invert-body' => 'var(--color-zinc-300)', 'invert-headings' => '#fff', 'invert-lead' => 'var(--color-zinc-400)', 'invert-links' => '#fff', 'invert-bold' => '#fff', 'invert-counters' => 'var(--color-zinc-400)', 'invert-bullets' => 'var(--color-zinc-600)', 'invert-hr' => 'var(--color-zinc-700)', 'invert-quotes' => 'var(--color-zinc-100)', 'invert-quote-borders' => 'var(--color-zinc-700)', 'invert-captions' => 'var(--color-zinc-400)', 'invert-kbd' => '#fff', 'invert-kbd-shadows' => '255 255 255', 'invert-code' => '#fff', 'invert-pre-code' => 'var(--color-zinc-300)', 'invert-pre-bg' => 'rgb(0 0 0 / 50%)', 'invert-th-borders' => 'var(--color-zinc-600)', 'invert-td-borders' => 'var(--color-zinc-700)'], 'neutral' => ['body' => 'var(--color-neutral-700)', 'headings' => 'var(--color-neutral-900)', 'lead' => 'var(--color-neutral-600)', 'links' => 'var(--color-neutral-900)', 'bold' => 'var(--color-neutral-900)', 'counters' => 'var(--color-neutral-500)', 'bullets' => 'var(--color-neutral-300)', 'hr' => 'var(--color-neutral-200)', 'quotes' => 'var(--color-neutral-900)', 'quote-borders' => 'var(--color-neutral-200)', 'captions' => 'var(--color-neutral-500)', 'kbd' => 'var(--color-neutral-900)', 'kbd-shadows' => '23 23 23', 'code' => 'var(--color-neutral-900)', 'pre-code' => 'var(--color-neutral-200)', 'pre-bg' => 'var(--color-neutral-800)', 'th-borders' => 'var(--color-neutral-300)', 'td-borders' => 'var(--color-neutral-200)', 'invert-body' => 'var(--color-neutral-300)', 'invert-headings' => '#fff', 'invert-lead' => 'var(--color-neutral-400)', 'invert-links' => '#fff', 'invert-bold' => '#fff', 'invert-counters' => 'var(--color-neutral-400)', 'invert-bullets' => 'var(--color-neutral-600)', 'invert-hr' => 'var(--color-neutral-700)', 'invert-quotes' => 'var(--color-neutral-100)', 'invert-quote-borders' => 'var(--color-neutral-700)', 'invert-captions' => 'var(--color-neutral-400)', 'invert-kbd' => '#fff', 'invert-kbd-shadows' => '255 255 255', 'invert-code' => '#fff', 'invert-pre-code' => 'var(--color-neutral-300)', 'invert-pre-bg' => 'rgb(0 0 0 / 50%)', 'invert-th-borders' => 'var(--color-neutral-600)', 'invert-td-borders' => 'var(--color-neutral-700)'], 'stone' => ['body' => 'var(--color-stone-700)', 'headings' => 'var(--color-stone-900)', 'lead' => 'var(--color-stone-600)', 'links' => 'var(--color-stone-900)', 'bold' => 'var(--color-stone-900)', 'counters' => 'var(--color-stone-500)', 'bullets' => 'var(--color-stone-300)', 'hr' => 'var(--color-stone-200)', 'quotes' => 'var(--color-stone-900)', 'quote-borders' => 'var(--color-stone-200)', 'captions' => 'var(--color-stone-500)', 'kbd' => 'var(--color-stone-900)', 'kbd-shadows' => '28 25 23', 'code' => 'var(--color-stone-900)', 'pre-code' => 'var(--color-stone-200)', 'pre-bg' => 'var(--color-stone-800)', 'th-borders' => 'var(--color-stone-300)', 'td-borders' => 'var(--color-stone-200)', 'invert-body' => 'var(--color-stone-300)', 'invert-headings' => '#fff', 'invert-lead' => 'var(--color-stone-400)', 'invert-links' => '#fff', 'invert-bold' => '#fff', 'invert-counters' => 'var(--color-stone-400)', 'invert-bullets' => 'var(--color-stone-600)', 'invert-hr' => 'var(--color-stone-700)', 'invert-quotes' => 'var(--color-stone-100)', 'invert-quote-borders' => 'var(--color-stone-700)', 'invert-captions' => 'var(--color-stone-400)', 'invert-kbd' => '#fff', 'invert-kbd-shadows' => '255 255 255', 'invert-code' => '#fff', 'invert-pre-code' => 'var(--color-stone-300)', 'invert-pre-bg' => 'rgb(0 0 0 / 50%)', 'invert-th-borders' => 'var(--color-stone-600)', 'invert-td-borders' => 'var(--color-stone-700)']];
        $colorData = $colors[$color] ?? $colors['gray'];
        return ['--tw-prose-body' => $colorData['body'], '--tw-prose-headings' => $colorData['headings'], '--tw-prose-lead' => $colorData['lead'], '--tw-prose-links' => $colorData['links'], '--tw-prose-bold' => $colorData['bold'], '--tw-prose-counters' => $colorData['counters'], '--tw-prose-bullets' => $colorData['bullets'], '--tw-prose-hr' => $colorData['hr'], '--tw-prose-quotes' => $colorData['quotes'], '--tw-prose-quote-borders' => $colorData['quote-borders'], '--tw-prose-captions' => $colorData['captions'], '--tw-prose-kbd' => $colorData['kbd'], '--tw-prose-kbd-shadows' => $colorData['kbd-shadows'], '--tw-prose-code' => $colorData['code'], '--tw-prose-pre-code' => $colorData['pre-code'], '--tw-prose-pre-bg' => $colorData['pre-bg'], '--tw-prose-th-borders' => $colorData['th-borders'], '--tw-prose-td-borders' => $colorData['td-borders'], '--tw-prose-invert-body' => $colorData['invert-body'], '--tw-prose-invert-headings' => $colorData['invert-headings'], '--tw-prose-invert-lead' => $colorData['invert-lead'], '--tw-prose-invert-links' => $colorData['invert-links'], '--tw-prose-invert-bold' => $colorData['invert-bold'], '--tw-prose-invert-counters' => $colorData['invert-counters'], '--tw-prose-invert-bullets' => $colorData['invert-bullets'], '--tw-prose-invert-hr' => $colorData['invert-hr'], '--tw-prose-invert-quotes' => $colorData['invert-quotes'], '--tw-prose-invert-quote-borders' => $colorData['invert-quote-borders'], '--tw-prose-invert-captions' => $colorData['invert-captions'], '--tw-prose-invert-kbd' => $colorData['invert-kbd'], '--tw-prose-invert-kbd-shadows' => $colorData['invert-kbd-shadows'], '--tw-prose-invert-code' => $colorData['invert-code'], '--tw-prose-invert-pre-code' => $colorData['invert-pre-code'], '--tw-prose-invert-pre-bg' => $colorData['invert-pre-bg'], '--tw-prose-invert-th-borders' => $colorData['invert-th-borders'], '--tw-prose-invert-td-borders' => $colorData['invert-td-borders']];
    }
}
