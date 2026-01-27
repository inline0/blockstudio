<?php

namespace Blockstudio;

use WP_HTML_Tag_Processor;

/**
 * Extends class.
 *
 * @date   14/02/2024
 * @since  5.4.0
 */
class Extensions
{
    /**
     * Construct.
     *
     * @date   14/02/2024
     * @since  5.4.0
     */
    function __construct()
    {
        add_filter('render_block', [__CLASS__, 'renderBlocks'], 10, 2);
    }

    /**
     * Render blocks.
     *
     * @date   14/02/2024
     * @since  5.4.0
     *
     * @param  $blockContent
     * @param  $block
     *
     * @return string
     */
    public static function renderBlocks($blockContent, $block): string
    {
        if (!class_exists('WP_HTML_Tag_Processor')) {
            return $blockContent;
        }

        $extensions = Build::extensions();
        $matches = self::getMatches($block['blockName'], $extensions);

        $attributes = [];
        $blockAttributes = $block['attrs']['blockstudio']['attributes'] ?? [];

        foreach ($matches as $match) {
            foreach ($match->attributes as $attributeId => $attribute) {
                if (isset($blockAttributes[$attributeId])) {
                    $attributes[$attributeId] = $attribute;
                }
            }
        }

        $blockstudioAttributes = [
            'blockstudio' => [
                'attributes' => $blockAttributes,
                'disabled' => $block['attrs']['blockstudio']['disabled'] ?? [],
            ],
        ];
        $ref = $blockstudioAttributes;

        $attributeData = Block::transform(
            $blockstudioAttributes,
            $ref,
            null,
            false,
            false,
            $attributes
        );

        if (empty($matches) && !($attributeData['hasCodeSelector'] ?? false)) {
            return $blockContent;
        }

        $content = new WP_HTML_Tag_Processor($blockContent);

        $isSequential = function ($arr) {
            return is_array($arr) &&
                array_keys($arr) === range(0, count($arr) - 1);
        };

        if ($content->next_tag()) {
            $class = '';
            $style = '';
            $currentClass = $content->get_attribute('class');
            $currentStyle = $content->get_attribute('style');

            if (
                trim($currentStyle ?? '') !== '' &&
                !Files::endsWith($currentStyle, ';')
            ) {
                $currentStyle .= ';';
            }

            if ($attributeData['hasCodeSelector']) {
                $content->set_attribute(
                    'data-assets',
                    $attributeData['selectorAttributeId']
                );
            }

            foreach ($attributes as $key => $value) {
                if (
                    !isset($value['set']) &&
                    isset($value['field']) &&
                    $value['field'] === 'attributes'
                ) {
                    if (is_array($blockstudioAttributes[$key])) {
                        foreach ($blockstudioAttributes[$key] as $attr) {
                            $content->set_attribute(
                                $attr['attribute'],
                                $attr['value']
                            );
                        }
                    }
                }

                foreach ($value['set'] ?? [] as $set) {
                    $val = $blockstudioAttributes[$key];

                    if (!$val) {
                        continue;
                    }

                    $applyValue = function ($value, $attr) use (
                        &$class,
                        &$style,
                        $set,
                        $content
                    ) {
                        if ($set['value'] ?? false) {
                            $value = self::parseTemplate($set['value'], [
                                'attributes' => $attr,
                            ]);
                        }

                        if ($set['attribute'] === 'class') {
                            $class .= ' ' . $value;
                        } elseif ($set['attribute'] === 'style') {
                            $style .= ' ' . $value . ';';
                            $style = str_replace(';;', ';', $style);
                        } else {
                            $content->set_attribute($set['attribute'], $value);
                        }
                    };

                    if ($isSequential($val)) {
                        $index = -1;
                        foreach ($val as $v) {
                            $index++;

                            if (!isset($blockstudioAttributes[$key][$index])) {
                                continue;
                            }

                            $applyValue($v, [
                                $key => $blockstudioAttributes[$key][$index],
                            ]);
                        }
                    } else {
                        $applyValue($value, $blockstudioAttributes);
                    }
                }
            }

            $combinedClass = trim($currentClass . $class);
            if ($combinedClass !== '') {
                $content->set_attribute('class', $combinedClass);
            }

            $combinedStyle = trim($currentStyle . $style);
            if ($combinedStyle !== '') {
                $content->set_attribute(
                    'style',
                    str_replace(';;', ';', $combinedStyle)
                );
            }
        }

        $element = $content->get_updated_html();
        $assets = Assets::renderCodeFieldAssets($attributeData);

        return $element . $assets;
    }

    /**
     * Get matches.
     *
     * @date   14/02/2024
     * @since  5.4.0
     *
     * @param  $string
     * @param  $extensions
     *
     * @return array
     */
    public static function getMatches($string, $extensions): array
    {
        $matches = [];
        $matchFound = function ($name, $string) {
            if (!$name || !$string) {
                return false;
            }

            if (substr($name, -1) == '*') {
                $prefix = substr($name, 0, -1);

                return strpos($string, $prefix) === 0;
            } else {
                return $name === $string;
            }
        };

        foreach ($extensions as $e) {
            if (is_array($e->name)) {
                foreach ($e->name as $name) {
                    if ($matchFound($name, $string)) {
                        $matches[] = $e;
                    }
                }
            } else {
                if ($matchFound($e->name, $string)) {
                    $matches[] = $e;
                }
            }
        }

        return $matches;
    }

    /**
     * Replace template string.
     *
     * @date   14/02/2024
     * @since  5.4.0
     *
     * @param  $templateString
     * @param  $values
     *
     * @return array|string|string[]|null
     */
    public static function parseTemplate($templateString, $values)
    {
        return preg_replace_callback(
            '/\{([^}]+)\}/',
            function ($matches) use ($values) {
                $path = $matches[1];

                return self::get($values, $path);
            },
            $templateString
        );
    }

    /**
     * Get a nested array element similar to Lodash/Get.
     *
     * @date   14/02/2024
     * @since  5.4.0
     *
     * @param  $target
     * @param  $key
     * @param  null  $default
     *
     * @return mixed|null
     */
    public static function get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $segment) {
            if (!is_array($target) && !is_object($target)) {
                return $default;
            }
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (
                is_object($target) &&
                property_exists($target, $segment)
            ) {
                $target = $target->$segment;
            } else {
                return $default;
            }
        }

        return $target;
    }
}

new Extensions();
