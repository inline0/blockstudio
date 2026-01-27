<?php

namespace Blockstudio;

/**
 * Builder class.
 *
 * @date   11/05/2024
 * @since  5.6.0
 */
class Builder
{
    private static array $blocks = [
        'blockstudio/container',
        'blockstudio/element',
        'blockstudio/text',
    ];

    /**
     * Construct.
     *
     * @date   11/05/2024
     * @since  5.6.0
     */
    function __construct()
    {
        add_action('enqueue_block_editor_assets', function () {
            if (!Settings::get('builderDeprecated/enabled')) {
                return;
            }

            $blockScripts = include BLOCKSTUDIO_DIR .
                '/includes/admin/assets/builder/index.tsx.asset.php';
            wp_enqueue_script(
                'blockstudio-builder',
                plugin_dir_url(__FILE__) .
                    '../admin/assets/builder/index.tsx.js',
                $blockScripts['dependencies'],
                $blockScripts['version']
            );
            wp_localize_script(
                'blockstudio-builder',
                'blockstudioAdmin',
                Admin::data(false)
            );
        });

        add_filter('render_block', [__CLASS__, 'renderBlocks'], 10, 2);
    }

    /**
     * Render blocks.
     *
     * @date   11/05/2024
     * @since  5.6.0
     *
     * @param  $blockContent
     * @param  $block
     *
     * @return string
     */
    public static function renderBlocks($blockContent, $block): string
    {
        if (!in_array($block['blockName'], self::$blocks)) {
            return $blockContent;
        }

        $name = $block['blockName'];
        $data = $block['attrs']['blockstudio']['data'] ?? [];

        if ($block['blockName'] === 'blockstudio/container') {
            $tag = $data['tag'] ?? 'div';
            $content = $blockContent ?? '';
        } elseif ($block['blockName'] === 'blockstudio/element') {
            $tag = $data['tag'] ?? 'img';
            $content = '';
        } else {
            $tag = $data['tag'] ?? 'p';
            $content = $data['content'] ?? '';
        }

        $className = $data['className'] ?? '';

        $class = '';
        if (!is_array($className) && trim($className ?? '') !== '') {
            $class = "class='$className'";
        }

        $attribute = Utils::dataAttributes($data['attributes'] ?? []);

        $attributeBlockstudio = "data-blockstudio='$name'";

        if ($block['blockName'] === 'blockstudio/element') {
            return "<$tag $class $attributeBlockstudio $attribute />";
        }

        return "<$tag $class $attributeBlockstudio $attribute>$content</$tag>";
    }
}

new Builder();
