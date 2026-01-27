<?php

namespace Blockstudio;

use DOMDocument;
use ErrorException;
use Throwable;
use Timber\Timber;
use WP_HTML_Tag_Processor;

/**
 * Block class.
 *
 * @date   02/07/2022
 * @since  2.4.0
 */
class Block
{
    private static int $count = 0;
    private static array $countByBlock = [];

    /**
     * Get unique ID.
     *
     * @date   06/05/2024
     * @since  5.5.0
     *
     * @param  $block
     * @param  $attributes
     *
     * @return string
     */
    public static function id($block, $attributes): string
    {
        return 'blockstudio-' .
            substr(
                md5(uniqid() . json_encode($block) . json_encode($attributes)),
                0,
                12
            );
    }

    /**
     * Get block ID as an HTML comment.
     *
     * @date   04/09/2022
     * @since  3.0.0
     *
     * @param  $name
     *
     * @return string
     */
    public static function comment($name): string
    {
        return '<!--blockstudio/' . Build::data()[$name]['name'] . '-->';
    }

    /**
     * Get option value.
     *
     * @date   22/09/2022
     * @since  3.0.4
     *
     * @param  $data
     * @param  $returnFormat
     * @param  $v
     * @param  array  $populate
     *
     * @return string
     */
    public static function getOptionValue(
        $data,
        $returnFormat,
        $v,
        array $populate = []
    ) {
        $fetch = $populate['fetch'] ?? false;
        $optionsMap = [];
        $options = $fetch ? [$v] : $data['options'] ?? [];

        foreach ($options as $option) {
            $value = $option['value'] ?? false;
            $queryOptions = ['posts', 'users', 'terms'];
            if (
                isset($populate['type']) &&
                $populate['type'] === 'query' &&
                isset($populate['query']) &&
                ((in_array($populate['query'], $queryOptions) &&
                    in_array($value, $data['optionsPopulate'])) ||
                    $fetch)
            ) {
                $isObject =
                    (isset($populate['returnFormat']['value']) &&
                        $populate['returnFormat']['value'] !== 'id') ||
                    !isset($populate['returnFormat']['value']);

                $queryFunctionMap = [
                    'posts' => 'get_post',
                    'users' => 'get_user_by',
                    'terms' => 'get_term',
                ];

                if ($isObject) {
                    $value =
                        $populate['query'] === 'users'
                            ? get_user_by('id', $value)
                            : call_user_func(
                                $queryFunctionMap[$populate['query']],
                                $value
                            );
                }
            }

            if (isset($option['value'])) {
                $optionsMap[$option['value']] = [
                    'value' => $value,
                    'label' => $option['label'] ?? $value,
                ];
            } elseif (!$fetch) {
                $optionsMap[$option] = [
                    'value' => $option,
                    'label' => $option,
                ];
            }
        }

        try {
            if ($returnFormat === 'label') {
                return $optionsMap[$v['value'] ?? $v]['label'] ?? false;
            }
            if ($returnFormat === 'both') {
                return $optionsMap[$v['value'] ?? $v] ?? false;
            }

            return $optionsMap[$v['value'] ?? $v]['value'] ?? false;
        } catch (Throwable $err) {
            return false;
        }
    }

    /**
     * Get attachment data for the file field.
     *
     * @date   05/11/2022
     * @since  3.0.11
     *
     * @param  null    $id
     * @param  bool    $example
     * @param  int     $index
     * @param  string  $size
     *
     * @return array|false
     */
    public static function getAttachmentData(
        $id = null,
        $example = false,
        $index = 0,
        $size = 'full'
    ) {
        $image = [];

        if ($example) {
            $url = Files::getRelativeUrl($example);
            $image['ID'] = $index;
            $image['title'] = "Image title $index";
            $image['alt'] = "Image alt $index";
            $image['caption'] = "Image caption $index";
            $image['description'] = "Image description $index";
            $image['href'] = $url;
            $image['url'] = $url;

            $xmlGet = simplexml_load_string(file_get_contents($example));
            $xmlAttributes = $xmlGet->attributes();
            $width = (string) $xmlAttributes->width;
            $height = (string) $xmlAttributes->height;

            if ($sizes = get_intermediate_image_sizes()) {
                array_unshift($sizes, 'full');

                foreach ($sizes as $size) {
                    $image['sizes'][$size] = $url;
                    $image['sizes'][$size . '-width'] = $width;
                    $image['sizes'][$size . '-height'] = $height;
                }
            }

            return $image;
        }

        if (!empty($id) && ($meta = get_post($id))) {
            $image['ID'] = $id;
            $image['title'] = $meta->post_title;
            $image['alt'] = ($alt = get_post_meta(
                $meta->ID,
                '_wp_attachment_image_alt',
                true
            ))
                ? $alt
                : $meta->post_title;
            $image['caption'] = $meta->post_excerpt;
            $image['description'] = $meta->post_content;
            $image['href'] = get_permalink($meta->ID);
            if ($size !== 'full') {
                $image['url'] =
                    wp_get_attachment_image_src($id, $size)[0] ?? '';
            } else {
                $image['url'] = $meta->guid;
            }

            if ($sizes = get_intermediate_image_sizes()) {
                array_unshift($sizes, 'full');

                foreach ($sizes as $size) {
                    $src = wp_get_attachment_image_src($id, $size);
                    if ($src) {
                        $image['sizes'][$size] = $src[0];
                        $image['sizes'][$size . '-width'] = $src[1];
                        $image['sizes'][$size . '-height'] = $src[2];
                    }
                }
            } else {
                $image['sizes'] = null;
            }

            return $image;
        }

        return false;
    }

    /**
     * Replace custom block tags.
     *
     * @date   08/04/2023
     * @since  4.2.0
     *
     * @param  $content
     * @param  $replace
     * @param  $block
     * @param  $blockAttributes
     * @param  $tag
     * @param  $type
     */
    public static function replaceCustomTag(
        &$content,
        $replace,
        $block,
        $blockAttributes,
        $tag,
        $type
    ) {
        $regex =
            $type !== 'InnerBlocks'
                ? '/<' .
                    preg_quote($tag) .
                    '(?=[^>]*(\battribute=["\']' .
                    preg_quote($type) .
                    '["\']))\s*(.*?)\s*\/?>/s'
                : '/<' . preg_quote($tag) . '\s*(.*?)\s*\/?>/s';
        $replace = str_replace('$', '\$', $replace);
        preg_match($regex, $content, $matches);

        $hasMatch = count($matches) >= 2;

        if ($hasMatch) {
            $attributeMap = [];
            $attributes =
                $tag === 'InnerBlocks'
                    ? $matches[1] ?? $matches[2]
                    : $matches[2] ?? $matches[1];

            $attributes = str_replace(['"', '"'], '"', $attributes);

            $pattern =
                '/([a-zA-Z_][a-zA-Z0-9\-_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^ \/>]+)))?/';

            preg_match_all($pattern, $attributes, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $attrName = $match[1];
                $attrValue = $match[2] ?? ($match[3] ?? ($match[4] ?? true));
                $attributeMap[$attrName] = $attrValue;
            }

            $attribute =
                $blockAttributes[$attributeMap['attribute'] ?? null] ?? null;
            $elementTag =
                $attributeMap['tag'] ?? ($type === 'InnerBlocks' ? 'div' : 'p');

            $attr = '';
            foreach ($attributeMap as $name => $value) {
                if ($value === true) {
                    $attr .= "$name ";
                } else {
                    $attr .= sprintf(
                        '%s="%s" ',
                        $name,
                        htmlspecialchars($value)
                    );
                }
            }
            $attr = trim($attr);

            if ($tag === 'RichText') {
                $richTextContent = apply_filters(
                    'blockstudio/blocks/components/richtext/render',
                    $attribute,
                    $block
                );

                $content = preg_replace(
                    $regex,
                    $attribute
                        ? "<$elementTag $attr>" .
                            $richTextContent .
                            "</$elementTag>"
                        : '',
                    $content
                );
            } else {
                $innerBlocksContent =
                    isset($block->blockstudioEditor) &&
                    isset($block->blockstudio['editor']['innerBlocks'])
                        ? $block->blockstudio['editor']['innerBlocks']
                        : $replace;
                $innerBlocksContent = apply_filters(
                    'blockstudio/blocks/components/innerblocks/render',
                    $innerBlocksContent,
                    $block
                );
                $content = preg_replace(
                    $regex,
                    apply_filters(
                        'blockstudio/blocks/components/innerblocks/frontend/wrap',
                        true,
                        $block
                    )
                        ? "<$elementTag $attr>" .
                            $innerBlocksContent .
                            "</$elementTag>"
                        : $innerBlocksContent,
                    $content
                );
            }
        }
    }

    /**
     * Remove component from block content.
     *
     * @date   17/12/2023
     * @since  5.3.0
     *
     * @param  $content
     * @param  $component
     */
    public static function removeCustomTag(&$content, $component)
    {
        $regex = '/<' . preg_quote($component) . '\s*(.*?)\s*\/?>/s';
        $content = preg_replace($regex, '', $content);
    }

    /**
     * Replace block content with components.
     *
     * @date   04/01/2023
     * @since  4.0.0
     *
     * @param  $content
     * @param  $innerBlocks
     * @param  $isEditorOrPreview
     * @param  $block
     * @param  $attributes
     * @param  $attributesBlock
     * @param  $attributeData
     *
     * @return array|string|string[]|null
     */
    public static function replaceComponents(
        $content,
        $innerBlocks,
        $isEditorOrPreview,
        $block,
        $attributes,
        $attributesBlock,
        $attributeData
    ) {
        if ($isEditorOrPreview) {
            if (
                class_exists('WP_HTML_Tag_Processor') &&
                strpos($content, 'useBlockProps') !== false
            ) {
                $content = new WP_HTML_Tag_Processor($content);
                if ($content->next_tag()) {
                    $classes = $content->get_attribute('class');
                    $content->set_attribute(
                        'class',
                        $classes . ' wp-block block-editor-block-list__block'
                    );
                }
            }

            return str_replace(
                'useBlockProps',
                'useblockprops="true"',
                $content
            );
        }

        self::replaceCustomTag(
            $content,
            $innerBlocks,
            $block,
            $attributes,
            'InnerBlocks',
            'InnerBlocks'
        );

        foreach ($block->attributes ?? [] as $attribute) {
            if (
                isset($attribute['id']) &&
                isset($attribute['type']) &&
                $attribute['type'] !== 'richtext'
            ) {
                self::replaceCustomTag(
                    $content,
                    $innerBlocks,
                    $block,
                    $attributes,
                    'RichText',
                    $attribute['id']
                );
            }
        }

        self::removeCustomTag($content, 'MediaPlaceholder');

        $attributesToRemove = [
            // General
            'useBlockProps',
            'tag',
            // InnerBlocks
            'allowedBlocks',
            'defaultBlock',
            'directInsert',
            'prioritizedInserterBlocks',
            'renderAppender',
            'template',
            'templateInsertUpdatesSelection',
            'templateLock',
            // RichText
            'attribute',
            'placeholder',
            'allowedFormats',
            'autocompleters',
            'multiline',
            'preserveWhiteSpace',
            'withoutInteractiveFormatting',
        ];

        $hasAttribute = false;
        foreach ($attributesToRemove as $attribute) {
            if (strpos($content, $attribute) !== false) {
                $hasAttribute = true;
                break;
            }
        }

        if ($hasAttribute) {
            $content = str_replace(
                'useBlockProps',
                'useblockprops="true"',
                $content
            );

            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML(
                mb_encode_numericentity(
                    $content,
                    [0x80, 0x10ffff, 0, 0xffffff],
                    'UTF-8'
                )
            );
            libxml_clear_errors();
            $elements = $doc->getElementsByTagName('*');
            foreach ($elements as $element) {
                if ($element->hasAttribute('useblockprops')) {
                    $classes = $element->getAttribute('class');

                    if ($attributeData['hasCodeSelector'] ?? false) {
                        $element->setAttribute(
                            'data-assets',
                            $attributeData['selectorAttributeId'] ?? ''
                        );
                    }

                    $attributes = [];
                    preg_match_all(
                        '/(\S+)="([^"]+)"/',
                        apply_filters(
                            'blockstudio/blocks/components/useblockprops/render',
                            get_block_wrapper_attributes([
                                'class' => $classes,
                                'id' =>
                                    $attributesBlock['anchor'] ??
                                    $element->getAttribute('id'),
                            ]),
                            $block
                        ),
                        $attributes,
                        PREG_SET_ORDER
                    );

                    foreach ($attributes as $attribute) {
                        $element->setAttribute($attribute[1], $attribute[2]);
                    }
                }
                foreach ($attributesToRemove as $attribute) {
                    $attr = strtolower($attribute);
                    if (
                        $element->hasAttribute($attr) &&
                        $element->nodeName !== 'input' &&
                        $element->nodeName !== 'textarea'
                    ) {
                        $element->removeAttribute($attr);
                    }
                }
            }
            $trimOffFront = strpos($doc->saveHTML(), '<body>') + 6;
            $trimOffEnd =
                strrpos($doc->saveHTML(), '</body>') - strlen($doc->saveHTML());

            $content = substr($doc->saveHTML(), $trimOffFront, $trimOffEnd);
        }

        return $content;
    }

    /**
     * Transform attributes.
     *
     * @date   01/03/2023
     * @since  4.1.5
     *
     * @param  $attributes
     * @param  $attributeNames
     * @param  $disabled
     * @param  $name
     * @param  $block
     * @param  $repeater
     * @param  $blockAttributes
     * @param  $attributeData
     *
     * @return array
     */
    public static function transformAttributes(
        &$attributes,
        &$attributeNames,
        $disabled,
        $name,
        $block,
        $repeater = false,
        $blockAttributes = [],
        &$attributeData = []
    ): array {
        if ($attributeData['selectorAttributeId'] ?? false) {
            $selectorAttributeId = $attributeData['selectorAttributeId'];
        } else {
            $selectorAttributeId = self::id($block, $attributes);
            $attributeData['selectorAttributeId'] = $selectorAttributeId;
        }
        $selectorAttribute = "data-assets='$selectorAttributeId'";

        foreach ($attributes as $k => $v) {
            $att = $repeater
                ? array_values(
                        array_filter(
                            $repeater,
                            fn($item) => ($item['id'] ?? false) === $k
                        )
                    )[0] ?? false
                : $blockAttributes[$k] ?? false;

            if (isset($att['blockstudio']) && !$repeater) {
                $attributeNames[] = $k;
            }

            if (isset($att['type']) && $att && (!empty($v) || $v === '0')) {
                $returnFormat = $att['returnFormat'] ?? 'value';
                $populate = $att['populate'] ?? [];
                $type = $att['field'] ?? false;

                if (!$type) {
                    continue;
                }

                if (
                    $type === 'select' ||
                    $type === 'radio' ||
                    $type === 'checkbox' ||
                    $type === 'token'
                ) {
                    if (
                        $type === 'select' &&
                        isset($populate['type']) &&
                        $populate['type'] === 'fetch'
                    ) {
                        $attributes[$k] = $v;
                    } else {
                        if ($type === 'select' || $type === 'radio') {
                            $attributes[$k] = self::getOptionValue(
                                $att,
                                $returnFormat,
                                $v,
                                $populate
                            );
                        }
                        if (
                            $type === 'checkbox' ||
                            ($type === 'select' && ($att['multiple'] ?? false))
                        ) {
                            $newValues = [];
                            foreach ($v as $l) {
                                $val = self::getOptionValue(
                                    $att,
                                    $returnFormat,
                                    $l,
                                    $populate
                                );

                                if ($val) {
                                    $newValues[] = $val;
                                }
                            }

                            if ($type === 'checkbox') {
                                if (
                                    isset($newValues[0]->ID) ||
                                    isset($newValues[0]->term_id)
                                ) {
                                    $isId = isset($newValues[0]->ID);
                                    $isTerm = isset($newValues[0]->term_id);
                                    $key = $isId ? 'ID' : 'term_id';

                                    $sortingArr = array_column(
                                        $att['options'],
                                        'value'
                                    );

                                    if ($isId || $isTerm) {
                                        uasort($newValues, function (
                                            $a,
                                            $b
                                        ) use ($key, $sortingArr) {
                                            return array_search(
                                                $a->{$key} ??
                                                    ($a['value'] ?? $a),
                                                $sortingArr
                                            ) <=>
                                                array_search(
                                                    $b->{$key} ??
                                                        ($b['value'] ?? $b),
                                                    $sortingArr
                                                );
                                        });
                                    }
                                } else {
                                    if (
                                        isset($att['options'][0]['label']) &&
                                        $returnFormat === 'label'
                                    ) {
                                        $sortingArr = array_column(
                                            $att['options'],
                                            'label'
                                        );
                                    } elseif (
                                        isset($att['options'][0]['value'])
                                    ) {
                                        $sortingArr = array_column(
                                            $att['options'],
                                            'value'
                                        );
                                    } else {
                                        $sortingArr = $att['options'];
                                    }

                                    uasort($newValues, function ($a, $b) use (
                                        $sortingArr
                                    ) {
                                        return array_search(
                                            $a['value'] ?? $a,
                                            $sortingArr
                                        ) <=>
                                            array_search(
                                                $b['value'] ?? $b,
                                                $sortingArr
                                            );
                                    });
                                }
                            }
                            $attributes[$k] = array_values($newValues);
                        }
                        if ($type === 'token' && $returnFormat !== 'both') {
                            $newValues = [];
                            foreach ($v as $l) {
                                $newValues[] = $l[$returnFormat] ?? $l;
                            }
                            $attributes[$k] = $newValues;
                        }
                    }
                }

                if ($type === 'files') {
                    if (is_array($v)) {
                        foreach ($v as $fileId) {
                            if (in_array($k . '_' . $fileId, $disabled)) {
                                $attributes[$k] = array_filter(
                                    $attributes[$k],
                                    fn($val) => $val !== $fileId
                                );
                            }
                        }
                        $attributes[$k] = array_values($attributes[$k]);
                    } elseif (in_array($k . '_' . $v, $disabled)) {
                        $attributes[$k] = false;
                    }

                    $size = 'full';

                    if (isset($attributes[$k . '__size'])) {
                        $size = $attributes[$k . '__size'] ?? 'full';
                    }

                    if ($returnFormat !== 'id' && $returnFormat !== 'url') {
                        if (is_array($attributes[$k])) {
                            $objectArray = [];
                            foreach ($attributes[$k] as $o) {
                                $objectArray[] = self::getAttachmentData(
                                    $o,
                                    false,
                                    0,
                                    $size
                                );
                            }
                            $attributes[$k] = $objectArray;
                        } elseif ($attributes[$k]) {
                            $attributes[$k] = self::getAttachmentData(
                                $attributes[$k],
                                false,
                                0,
                                $size
                            );
                        }
                    }

                    if ($returnFormat === 'url') {
                        $media = fn($id, $size) => wp_attachment_is(
                            'image',
                            $id
                        )
                            ? wp_get_attachment_image_src($id, $size)[0] ??
                                false
                            : wp_get_attachment_url($id) ?? false;

                        if (is_array($attributes[$k])) {
                            $urlArray = [];
                            foreach ($attributes[$k] as $o) {
                                $urlArray[] = $media($o, $att['returnSize']);
                            }
                            $attributes[$k] = $urlArray;
                        } elseif ($attributes[$k]) {
                            $attributes[$k] = $media(
                                $attributes[$k],
                                $att['returnSize']
                            );
                        }

                        if (
                            ($att['multiple'] ?? false) &&
                            !is_array($attributes[$k])
                        ) {
                            $attributes[$k] = [$attributes[$k]];
                        }
                    }

                    if (
                        $attributes[$k] &&
                        ($att['multiple'] ?? false) &&
                        ($attributes[$k]['ID'] ??
                            (is_numeric($attributes[$k]) ?? false))
                    ) {
                        $attributes[$k] = [$attributes[$k]];
                    }
                }

                if ($type === 'number' || $type === 'range') {
                    $attributes[$k] = floatval($v);
                }

                if ($type === 'repeater') {
                    foreach ($attributes[$k] as $i => $r) {
                        self::transformAttributes(
                            $attributes[$k][$i],
                            $attributeNames,
                            [],
                            $name,
                            $block,
                            $att['attributes'],
                            [],
                            $attributeData
                        );
                    }
                }

                if ($type === 'icon') {
                    if ($returnFormat === 'element') {
                        $attributes[$k] = bs_icon($v);
                    } else {
                        $attributes[$k]['element'] = bs_icon($v);
                    }
                }

                if ($type === 'code') {
                    $lang = $att['language'];
                    $replacedValue = str_replace(
                        '%selector%',
                        "[$selectorAttribute]",
                        $v
                    );

                    if (Files::contains($v, '%selector%')) {
                        $attributeData['hasCodeSelector'] = true;
                    }

                    if ($lang === 'css' || $lang === 'javascript') {
                        $assetData = [
                            'language' => $lang,
                            'value' => $replacedValue,
                        ];
                        $attributeData['assets'][] = $assetData;
                        if ($att['asset'] ?? false) {
                            $attributeData['assetsAsset'][] = $assetData;
                        }
                    }

                    $attributes[$k] = $replacedValue;
                }
            }

            $isFalse =
                $v === '' ||
                (is_array($attributes[$k]) && count($attributes[$k]) === 0) ||
                in_array($k, $disabled);

            if (($att['fallback'] ?? false) && $isFalse) {
                $attributes[$k] = $att['fallback'];
            } elseif ($isFalse) {
                $attributes[$k] = false;
            }

            $attributes[$k] = apply_filters(
                'blockstudio/blocks/attributes/render',
                $attributes[$k],
                $k,
                $block
            );
        }

        return [
            'assets' => $attributeData['assets'] ?? [],
            'assetsAsset' => $attributeData['assetsAsset'] ?? [],
            'selectorAttribute' => $selectorAttribute,
            'selectorAttributeId' => $selectorAttributeId,
            'hasCodeSelector' => $attributeData['hasCodeSelector'] ?? false,
        ];
    }

    /**
     * Transform block data.
     *
     * @date   29/11/2022
     * @since  3.1.0
     *
     * @param  $attributes
     * @param  $block
     * @param  $name
     * @param  $editor
     * @param  $isPreview
     * @param  array  $blockAttributes
     *
     * @return array
     */
    public static function transform(
        &$attributes,
        &$block,
        $name,
        $editor,
        $isPreview,
        $blockAttributes = []
    ): array {
        $attr = $blockAttributes;
        $disabled = $attributes['blockstudio']['disabled'] ?? [];

        // Defaults
        foreach ($attr as $k => $v) {
            $attr[$k] = $v['default'] ?? false;
        }
        $attributes = array_merge(
            $attr ?? [],
            $attributes['blockstudio']['attributes'] ?? []
        );

        // Transform
        $attributeNames = [];
        $attributeData = self::transformAttributes(
            $attributes,
            $attributeNames,
            $disabled,
            $name,
            $block,
            false,
            $blockAttributes
        );

        //  Examples
        if (
            isset(Build::blocks()[$name]->example['attributes']) &&
            ($editor || $isPreview)
        ) {
            foreach (
                Build::blocks()[$name]->example['attributes']
                as $k => $v
            ) {
                if (
                    isset($v['blockstudio']) &&
                    isset($v['type']) &&
                    $v['type'] === 'image'
                ) {
                    $files = [];
                    $index = 0;
                    $indexTotal = 0;
                    foreach (range(1, $v['amount'] ?? 1) as $i) {
                        $indexTotal++;
                        $index++;
                        if ($index === 12) {
                            $index = 1;
                        }
                        $files[] = self::getAttachmentData(
                            null,
                            BLOCKSTUDIO_DIR .
                                '/includes-v7/examples/images/' .
                                $index .
                                '.svg',
                            $indexTotal
                        );
                    }
                    $attributes[$k] = $files;
                } elseif ($isPreview) {
                    $attributes[$k] = $v;
                }
            }
        }

        unset($attributes['blockstudio']);

        foreach ($attributes as $k => $v) {
            if (
                !in_array($k, $attributeNames) &&
                strpos($k, '__size') === false
            ) {
                unset($attributes[$k]);
            } else {
                unset($block[$k]);
            }
        }

        return $attributeData;
    }

  /**
   * Native render.
   *
   * @date   02/07/2022
   * @since  2.4.0
   *
   * @param  $attributes
   * @param  $innerBlocks
   * @param  $wpBlock
   * @param  $content
   *
   * @return false|string
   * @throws ErrorException
   */
    public static function render(
        $attributes,
        $innerBlocks = '',
        $wpBlock = '',
        $content = ''
    ) {
        $isInlineEditor =
            isset($_GET['blockstudioEditor']) &&
            $_GET['blockstudioEditor'] === 'true';
        $isEditor =
            isset($_GET['blockstudioMode']) &&
            $_GET['blockstudioMode'] === 'editor';
        $isPreview =
            isset($_GET['blockstudioMode']) &&
            $_GET['blockstudioMode'] === 'preview';

        $postId = isset($_GET['postId'])
            ? intval($_GET['postId'])
            : get_the_ID();
        $post_id = $postId;
        $objectId = isset($_GET['postId'])
            ? intval($_GET['postId'])
            : get_queried_object_id();

        $name =
            $attributes['blockstudio']['name'] ??
            ($wpBlock->parsed_block['blockName'] ?? false);
        if (!$name) {
            return false;
        }

        self::$count++;
        if (!isset(self::$countByBlock[$name])) {
            self::$countByBlock[$name] = 1;
        } else {
            self::$countByBlock[$name]++;
        }

        $extensionAttributes = [];
        $matches = Extensions::getMatches($name, Build::extensions());
        if (count($matches) >= 1) {
            foreach ($matches as $match) {
                foreach ($match->attributes as $key => $value) {
                    if ($value['field'] ?? false) {
                        $extensionAttributes[$key] = $value;
                    }
                }
            }
        }

        $BLOCKSTUDIO_ID = self::comment($name);
        $blockData = Build::data()[$name];
        $data = Build::blocks()[$name] ?? false;
        $overrideData = Build::overrides()[$name] ?? false;
        $hasOverridePath =
            $overrideData &&
            isset($overrideData->path) &&
            Files::getRenderTemplate($overrideData->path);
        $path =
            $hasOverridePath && isset($overrideData->path)
                ? Files::getRenderTemplate($overrideData->path)
                : $data->path ?? false;

        if (!$path) {
            return null;
        }

        $editor = $attributes['blockstudio']['editor'] ?? false;
        if ($editor && ($data->name ?? false)) {
            $data->blockstudioEditor = true;
        }

        $block = $attributes;
        unset($block['blockstudio']);
        unset($block['__internalWidgetId']);
        $block['id'] = self::id($block, $attributes);
        $block['name'] = $name;
        $block['postId'] = $objectId;
        $block['postType'] = get_post_type($objectId);
        $block['index'] = self::$countByBlock[$name];
        $block['indexTotal'] = self::$count;

        $compiledContext = [];
        $blockNames = array_keys(Build::blocks());
        foreach ($data->usesContext ?? [] as $contextProvider) {
            if (!in_array($contextProvider, $blockNames)) {
                continue;
            }

            if ($block['_BLOCKSTUDIO_CONTEXT'][$contextProvider] ?? false) {
                $traceAttributes = [
                    'blockstudio' => [
                        'attributes' =>
                            $block['_BLOCKSTUDIO_CONTEXT'][$contextProvider][
                                'attributes'
                            ],
                    ],
                ];
                $attributeData = self::transform(
                    $traceAttributes,
                    $block,
                    $contextProvider,
                    $editor,
                    $isPreview,
                    Build::blocks()[$contextProvider]->attributes
                );
                $compiledContext[$contextProvider] = $traceAttributes;
            } else {
                $stackTrace = debug_backtrace();

                foreach ($stackTrace as $trace) {
                    $traceName = $trace['object']->block_type->name ?? '';
                    if ($traceName === $contextProvider) {
                        $traceAttributes = $trace['object']->attributes;
                        $attributeData = self::transform(
                            $traceAttributes,
                            $block,
                            $contextProvider,
                            $editor,
                            $isPreview,
                            Build::blocks()[$contextProvider]->attributes
                        );
                        $compiledContext[$contextProvider] = $traceAttributes;
                    }
                }
            }
        }

        $block['context'] =
            $block['_BLOCKSTUDIO_CONTEXT'] ?? ($wpBlock->context ?? []);

        unset($block['_BLOCKSTUDIO_CONTEXT']);

        $context = $compiledContext;

        $attributeData = self::transform(
            $attributes,
            $block,
            $name,
            $editor,
            $isPreview,
            Build::blocks()[$name]->attributes + $extensionAttributes
        );
        $assets = Assets::renderCodeFieldAssets($attributeData, 'assetsAsset');

        $filterData = $data;
        if ($filterData) {
            $filterData->blockstudio['data']['block'] = $block;
            $filterData->blockstudio['data']['context'] = $context;
            $filterData->blockstudio['data']['attributes'] = $attributes;
            $filterData->blockstudio['data']['path'] = $path;
            $filterData->blockstudio['data']['blade'] =
                Build::blade()[$blockData['instance']] ?? [];
        }

        if (
            substr_compare($path, '.twig', -strlen('.twig')) === 0 &&
            class_exists('Timber\Site')
        ) {
            Timber::init();
            $twigContext = Timber::context();

            $twigContext['attributes'] = $attributes;
            $twigContext['a'] = $attributes;
            $twigContext['block'] = $block;
            $twigContext['b'] = $block;
            $twigContext['context'] = $context;
            $twigContext['c'] = $context;
            $twigContext['content'] = $content;
            $twigContext['isEditor'] = $isEditor;
            $twigContext['isPreview'] = $isPreview;
            $twigContext['postId'] = $postId;
            $twigContext['post_id'] = $postId;

            $addCustomPath = function ($paths) use (
                $hasOverridePath,
                $overrideData,
                $data
            ) {
                if (!isset($paths[0])) {
                    $paths[0] = [];
                }
                $paths[0][] = dirname($data->path);
                if ($hasOverridePath) {
                    $paths[0][] = dirname($overrideData->path);
                }

                return $paths;
            };

            add_filter('timber/locations', $addCustomPath);

            try {
                $compiledString = Timber::compile_string(
                    $isInlineEditor
                        ? get_transient(
                            'blockstudio_gutenberg_' . $name . '_index.twig'
                        )
                        : ($editor ?:
                        file_get_contents($path)),
                    $twigContext
                );
            } catch (Throwable $e) {
                $previousError = $e->getPrevious();
                if (
                    $previousError &&
                    str_starts_with(
                        $e->getMessage(),
                        'An exception has been thrown during the rendering'
                    )
                ) {
                    $e = $previousError;
                }

                throw new ErrorException(
                    $e->getMessage(),
                    $e->getCode() ?? 0,
                    $e instanceof ErrorException ? $e->getSeverity() : E_ERROR,
                    $e->getFile(),
                    $e->getLine()
                );
            }

            $render = self::replaceComponents(
                $compiledString,
                $innerBlocks,
                $isEditor || $isPreview,
                $data,
                $attributes,
                $block,
                $attributeData
            );

            $renderedBlock =
                (trim($render ?? '') !== '' ? $BLOCKSTUDIO_ID : '') .
                ($isPreview ? Assets::getPreviewAssets($blockData) : '') .
                $render .
                ($isPreview ? Assets::getPreviewAssets($blockData, false) : '');

            remove_filter('timber/locations', $addCustomPath);
        } else {
            ob_start();
            $a = $attributes;
            $b = $block;
            $c = $context;

            $render = true;

            if ($editor) {
                @eval(' ?>' . $editor . '<?php ');
            } else {
                ob_start();
                $render = trim(include $path);
                ob_end_clean();

                if ($isPreview) {
                    echo Assets::getPreviewAssets($blockData);
                }
                $isInlineEditor
                    ? @eval(
                        ' ?>' .
                            get_transient(
                                'blockstudio_gutenberg_' . $name . '_index.php'
                            ) .
                            '<?php '
                    )
                    : include $path;
                if ($isPreview) {
                    echo Assets::getPreviewAssets($blockData, false);
                }
            }

            $renderedBlock =
                ($render !== '' ? $BLOCKSTUDIO_ID : '') .
                self::replaceComponents(
                    ob_get_clean(),
                    $innerBlocks,
                    $isEditor || $isPreview,
                    $filterData,
                    $attributes,
                    $block,
                    $attributeData
                );
        }

        $renderedBlock = $renderedBlock . (!$isEditor ? $assets : '');

        return apply_filters(
            'blockstudio/blocks/render',
            $renderedBlock,
            $filterData,
            $isEditor,
            $isPreview
        );
    }
}
