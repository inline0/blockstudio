<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Plugin\Plugins;

use BlockstudioVendor\TailwindPHP\Plugin\PluginAPI;
use BlockstudioVendor\TailwindPHP\Plugin\PluginInterface;
/**
 * Forms Plugin - Provides form reset and styling utilities.
 *
 * Port of: @tailwindcss/forms
 *
 * This plugin provides a basic reset for form styles that makes form elements
 * easy to override with utilities.
 */
class FormsPlugin implements PluginInterface
{
    /**
     * Default theme values from Tailwind.
     */
    private array $spacing = ['0' => '0px', '1' => '0.25rem', '2' => '0.5rem', '3' => '0.75rem', '4' => '1rem', '5' => '1.25rem', '6' => '1.5rem', '8' => '2rem', '10' => '2.5rem'];
    private array $borderWidth = ['DEFAULT' => '1px'];
    private array $borderRadius = ['none' => '0px', 'DEFAULT' => '0.25rem'];
    private string $baseFontSize = '1rem';
    private string $baseLineHeight = '1.5rem';
    public function getName(): string
    {
        return '@tailwindcss/forms';
    }
    public function __invoke(PluginAPI $api, array $options = []): void
    {
        $strategyOption = $options['strategy'] ?? null;
        $strategy = $strategyOption === null ? ['base', 'class'] : [$strategyOption];
        $rules = $this->getRules();
        if (in_array('base', $strategy)) {
            $baseRules = $this->getStrategyRules($rules, 'base');
            $api->addBase($baseRules);
        }
        if (in_array('class', $strategy)) {
            $classRules = $this->getStrategyRules($rules, 'class');
            $api->addComponents($classRules);
        }
    }
    public function getThemeExtensions(array $options = []): array
    {
        return [];
    }
    /**
     * Get strategy-specific rules.
     */
    private function getStrategyRules(array $rules, string $strategy): array
    {
        $result = [];
        foreach ($rules as $rule) {
            if (!isset($rule[$strategy]) || $rule[$strategy] === null) {
                continue;
            }
            $selectors = $rule[$strategy];
            $styles = $rule['styles'];
            // Handle multiple selectors
            if (is_array($selectors)) {
                foreach ($selectors as $selector) {
                    // Merge styles with existing if selector already exists
                    if (isset($result[$selector])) {
                        $result[$selector] = $this->mergeStyles($result[$selector], $styles);
                    } else {
                        $result[$selector] = $styles;
                    }
                }
            } else if (isset($result[$selectors])) {
                $result[$selectors] = $this->mergeStyles($result[$selectors], $styles);
            } else {
                $result[$selectors] = $styles;
            }
        }
        return $result;
    }
    /**
     * Merge two style arrays, with later styles taking precedence.
     */
    private function mergeStyles(array $existing, array $new): array
    {
        foreach ($new as $property => $value) {
            if (is_array($value) && isset($existing[$property]) && is_array($existing[$property])) {
                // Recursively merge nested rules (like &:focus, @media, etc.)
                $existing[$property] = $this->mergeStyles($existing[$property], $value);
            } else {
                $existing[$property] = $value;
            }
        }
        return $existing;
    }
    /**
     * Convert SVG to data URI.
     */
    private function svgToDataUri(string $svg): string
    {
        // Encode the SVG for use in a data URI
        $svg = trim($svg);
        $svg = str_replace('"', "'", $svg);
        $svg = str_replace('%', '%25', $svg);
        $svg = str_replace('#', '%23', $svg);
        $svg = str_replace('{', '%7B', $svg);
        $svg = str_replace('}', '%7D', $svg);
        $svg = str_replace('<', '%3C', $svg);
        $svg = str_replace('>', '%3E', $svg);
        return "data:image/svg+xml,{$svg}";
    }
    /**
     * Get all form styling rules.
     */
    private function getRules(): array
    {
        // Colors from default Tailwind theme
        $gray500 = '#6b7280';
        $blue600 = '#2563eb';
        // Chevron SVG for select
        $chevronSvg = sprintf('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20"><path stroke="%s" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 8l4 4 4-4"/></svg>', $gray500);
        // Checkmark SVG
        $checkSvg = '<svg viewBox="0 0 16 16" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z"/></svg>';
        // Radio dot SVG
        $radioDotSvg = '<svg viewBox="0 0 16 16" fill="white" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="3"/></svg>';
        // Indeterminate dash SVG
        $indeterminateSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 16"><path stroke="white" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h8"/></svg>';
        return [
            // Text inputs, textareas, selects
            ['base' => ["[type='text']", 'input:where(:not([type]))', "[type='email']", "[type='url']", "[type='password']", "[type='number']", "[type='date']", "[type='datetime-local']", "[type='month']", "[type='search']", "[type='tel']", "[type='time']", "[type='week']", '[multiple]', 'textarea', 'select'], 'class' => ['.form-input', '.form-textarea', '.form-select', '.form-multiselect'], 'styles' => ['appearance' => 'none', 'background-color' => '#fff', 'border-color' => $gray500, 'border-width' => $this->borderWidth['DEFAULT'], 'border-radius' => $this->borderRadius['none'], 'padding-top' => $this->spacing['2'], 'padding-right' => $this->spacing['3'], 'padding-bottom' => $this->spacing['2'], 'padding-left' => $this->spacing['3'], 'font-size' => $this->baseFontSize, 'line-height' => $this->baseLineHeight, '--tw-shadow' => '0 0 #0000', '&:focus' => ['outline' => '2px solid transparent', 'outline-offset' => '2px', '--tw-ring-inset' => 'var(--tw-empty,/*!*/ /*!*/)', '--tw-ring-offset-width' => '0px', '--tw-ring-offset-color' => '#fff', '--tw-ring-color' => $blue600, '--tw-ring-offset-shadow' => 'var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color)', '--tw-ring-shadow' => 'var(--tw-ring-inset) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color)', 'box-shadow' => 'var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow)', 'border-color' => $blue600]]],
            // Placeholder styling
            ['base' => ['input::placeholder', 'textarea::placeholder'], 'class' => ['.form-input::placeholder', '.form-textarea::placeholder'], 'styles' => ['color' => $gray500, 'opacity' => '1']],
            // Webkit datetime wrapper
            ['base' => ['::-webkit-datetime-edit-fields-wrapper'], 'class' => ['.form-input::-webkit-datetime-edit-fields-wrapper'], 'styles' => ['padding' => '0']],
            // Webkit date and time value
            ['base' => ['::-webkit-date-and-time-value'], 'class' => ['.form-input::-webkit-date-and-time-value'], 'styles' => ['min-height' => '1.5em', 'text-align' => 'inherit']],
            // Webkit datetime edit
            ['base' => ['::-webkit-datetime-edit'], 'class' => ['.form-input::-webkit-datetime-edit'], 'styles' => ['display' => 'inline-flex']],
            // Webkit datetime edit fields padding
            ['base' => ['::-webkit-datetime-edit', '::-webkit-datetime-edit-year-field', '::-webkit-datetime-edit-month-field', '::-webkit-datetime-edit-day-field', '::-webkit-datetime-edit-hour-field', '::-webkit-datetime-edit-minute-field', '::-webkit-datetime-edit-second-field', '::-webkit-datetime-edit-millisecond-field', '::-webkit-datetime-edit-meridiem-field'], 'class' => ['.form-input::-webkit-datetime-edit', '.form-input::-webkit-datetime-edit-year-field', '.form-input::-webkit-datetime-edit-month-field', '.form-input::-webkit-datetime-edit-day-field', '.form-input::-webkit-datetime-edit-hour-field', '.form-input::-webkit-datetime-edit-minute-field', '.form-input::-webkit-datetime-edit-second-field', '.form-input::-webkit-datetime-edit-millisecond-field', '.form-input::-webkit-datetime-edit-meridiem-field'], 'styles' => ['padding-top' => '0', 'padding-bottom' => '0']],
            // Select with chevron
            ['base' => ['select'], 'class' => ['.form-select'], 'styles' => ['background-image' => "url(\"{$this->svgToDataUri($chevronSvg)}\")", 'background-position' => "right {$this->spacing['2']} center", 'background-repeat' => 'no-repeat', 'background-size' => '1.5em 1.5em', 'padding-right' => $this->spacing['10'], 'print-color-adjust' => 'exact']],
            // Multiple selects
            ['base' => ['[multiple]', '[size]:where(select:not([size="1"]))'], 'class' => ['.form-select:where([size]:not([size="1"]))'], 'styles' => ['background-image' => 'initial', 'background-position' => 'initial', 'background-repeat' => 'unset', 'background-size' => 'initial', 'padding-right' => $this->spacing['3'], 'print-color-adjust' => 'unset']],
            // Checkbox and radio base
            ['base' => ["[type='checkbox']", "[type='radio']"], 'class' => ['.form-checkbox', '.form-radio'], 'styles' => ['appearance' => 'none', 'padding' => '0', 'print-color-adjust' => 'exact', 'display' => 'inline-block', 'vertical-align' => 'middle', 'background-origin' => 'border-box', 'user-select' => 'none', 'flex-shrink' => '0', 'height' => $this->spacing['4'], 'width' => $this->spacing['4'], 'color' => $blue600, 'background-color' => '#fff', 'border-color' => $gray500, 'border-width' => $this->borderWidth['DEFAULT'], '--tw-shadow' => '0 0 #0000']],
            // Checkbox specific
            ['base' => ["[type='checkbox']"], 'class' => ['.form-checkbox'], 'styles' => ['border-radius' => $this->borderRadius['none']]],
            // Radio specific
            ['base' => ["[type='radio']"], 'class' => ['.form-radio'], 'styles' => ['border-radius' => '100%']],
            // Checkbox/radio focus
            ['base' => ["[type='checkbox']:focus", "[type='radio']:focus"], 'class' => ['.form-checkbox:focus', '.form-radio:focus'], 'styles' => ['outline' => '2px solid transparent', 'outline-offset' => '2px', '--tw-ring-inset' => 'var(--tw-empty,/*!*/ /*!*/)', '--tw-ring-offset-width' => '2px', '--tw-ring-offset-color' => '#fff', '--tw-ring-color' => $blue600, '--tw-ring-offset-shadow' => 'var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color)', '--tw-ring-shadow' => 'var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color)', 'box-shadow' => 'var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow)']],
            // Checkbox/radio checked
            ['base' => ["[type='checkbox']:checked", "[type='radio']:checked"], 'class' => ['.form-checkbox:checked', '.form-radio:checked'], 'styles' => ['border-color' => 'transparent', 'background-color' => 'currentColor', 'background-size' => '100% 100%', 'background-position' => 'center', 'background-repeat' => 'no-repeat']],
            // Checkbox checked image
            ['base' => ["[type='checkbox']:checked"], 'class' => ['.form-checkbox:checked'], 'styles' => ['background-image' => "url(\"{$this->svgToDataUri($checkSvg)}\")", '@media (forced-colors: active)' => ['appearance' => 'auto']]],
            // Radio checked image
            ['base' => ["[type='radio']:checked"], 'class' => ['.form-radio:checked'], 'styles' => ['background-image' => "url(\"{$this->svgToDataUri($radioDotSvg)}\")", '@media (forced-colors: active)' => ['appearance' => 'auto']]],
            // Checkbox/radio checked hover/focus
            ['base' => ["[type='checkbox']:checked:hover", "[type='checkbox']:checked:focus", "[type='radio']:checked:hover", "[type='radio']:checked:focus"], 'class' => ['.form-checkbox:checked:hover', '.form-checkbox:checked:focus', '.form-radio:checked:hover', '.form-radio:checked:focus'], 'styles' => ['border-color' => 'transparent', 'background-color' => 'currentColor']],
            // Checkbox indeterminate
            ['base' => ["[type='checkbox']:indeterminate"], 'class' => ['.form-checkbox:indeterminate'], 'styles' => ['background-image' => "url(\"{$this->svgToDataUri($indeterminateSvg)}\")", 'border-color' => 'transparent', 'background-color' => 'currentColor', 'background-size' => '100% 100%', 'background-position' => 'center', 'background-repeat' => 'no-repeat', '@media (forced-colors: active)' => ['appearance' => 'auto']]],
            // Checkbox indeterminate hover/focus
            ['base' => ["[type='checkbox']:indeterminate:hover", "[type='checkbox']:indeterminate:focus"], 'class' => ['.form-checkbox:indeterminate:hover', '.form-checkbox:indeterminate:focus'], 'styles' => ['border-color' => 'transparent', 'background-color' => 'currentColor']],
            // File input
            ['base' => ["[type='file']"], 'class' => null, 'styles' => ['background' => 'unset', 'border-color' => 'inherit', 'border-width' => '0', 'border-radius' => '0', 'padding' => '0', 'font-size' => 'unset', 'line-height' => 'inherit']],
            // File input focus
            ['base' => ["[type='file']:focus"], 'class' => null, 'styles' => ['outline' => '1px solid ButtonText']],
        ];
    }
}
