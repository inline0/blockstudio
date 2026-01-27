<?php

namespace Blockstudio;

use WP_Block_Parser;

/**
 * Editor class.
 *
 * @date   05/03/2022
 * @since  2.3.0
 */
class Blocks
{
    /**
     * Construct.
     *
     * @date   05/03/2022
     * @since  2.3.0
     */
    function __construct()
    {
        add_action('enqueue_block_editor_assets', function () {
            global $post;
            $blockScripts = include BLOCKSTUDIO_DIR .
                '/includes-v7/admin/assets/blocks/index.tsx.asset.php';
            wp_enqueue_script(
                'blockstudio-blocks',
                plugin_dir_url(__FILE__) .
                    '../admin/assets/blocks/index.tsx.js',
                $blockScripts['dependencies'],
                $blockScripts['version']
            );

            $blockstudioBlocks = [];
            $blocks = Build::blocks();
            $blockNames = array_keys($blocks);

            $parser = new WP_Block_Parser();

            $content = $this->getContent($post);
            $parsedBlocks = $parser->parse($content);

            $blockRenderer = function ($block) use (
                &$blockRenderer,
                &$blockstudioBlocks,
                $blockNames,
                $blocks
            ) {
                if (in_array($block['blockName'], $blockNames)) {
                    $blockAttrs = $block['attrs'];
                    Block::transform(
                        $blockAttrs,
                        $block,
                        $block['blockName'],
                        false,
                        false,
                        $blocks[$block['blockName']]->attributes
                    );
                    $blockObj = [
                        'blockName' => $block['blockName'],
                        'attrs' => $blockAttrs,
                    ];
                    $id = str_replace(
                        ['{', '}', '[', ']', '"', '/', ' ', ':', ',', '\\'],
                        '_',
                        json_encode($blockObj)
                    );
                    $_GET['blockstudioMode'] = 'editor';
                    $blockstudioBlocks[$id] = [
                        'rendered' => render_block($block),
                        'block' => $blockObj,
                    ];
                }
                if (count($block['innerBlocks']) > 0) {
                    foreach ($block['innerBlocks'] as $innerBlock) {
                        $blockRenderer($innerBlock);
                    }
                }
            };

            foreach ($parsedBlocks as $block) {
                $blockRenderer($block);
            }

            $localizeArray = [
                'nonce' => wp_create_nonce('ajax-nonce'),
                'nonceRest' => wp_create_nonce('wp_rest'),
                'rest' => esc_url_raw(rest_url()),
                'blockstudioBlocks' => $blockstudioBlocks,
            ];

            wp_localize_script(
                'blockstudio-blocks',
                'blockstudio',
                $localizeArray
            );

            wp_localize_script(
                'blockstudio-blocks',
                'blockstudioAdmin',
                array_merge(
                    Admin::data(false),
                    apply_filters('blockstudio/blocks/conditions', [])
                )
            );
        });

        add_action('admin_footer', function () {
            $current_screen = get_current_screen();
            if ($current_screen->is_block_editor()) {
                $allAssets = Admin::getAllAssets();
                $chosenCssClassStyles = Settings::get('blockEditor/cssClasses');
                $cssClasses = [];
                $chosenCssVariablesStyles = Settings::get(
                    'blockEditor/cssVariables'
                );
                $cssVariables = [];

                $styles = $allAssets['styles'];

                if (count($chosenCssClassStyles) > 0) {
                    foreach ($styles as $key => $style) {
                        if (!in_array($key, $chosenCssClassStyles)) {
                            continue;
                        }

                        $cssClasses[] = $key;
                    }
                }

                if (count($chosenCssVariablesStyles) > 0) {
                    foreach ($styles as $key => $style) {
                        if (!in_array($key, $chosenCssVariablesStyles)) {
                            continue;
                        }

                        $cssVariables[] = $key;
                    }
                }

                echo '<script>';
                echo 'window.blockstudioAdmin.styles = ' .
                    json_encode($allAssets['styles']) .
                    ';';
                echo 'window.blockstudioAdmin.cssClasses = ' .
                    json_encode($cssClasses) .
                    ';';
                echo 'window.blockstudioAdmin.cssVariables = ' .
                    json_encode($cssVariables) .
                    ';';
                echo '</script>';
            }
        });
    }

    /**
     * Get content for parsing blocks.
     *
     * @param  object|null  $post
     *
     * @return string
     */
    private function getContent(?object $post): string
    {
        if ($post && !empty($post->post_content)) {
            return $post->post_content;
        }

        if (
            isset($_GET['p']) &&
            isset($_GET['canvas']) &&
            $_GET['canvas'] === 'edit'
        ) {
            $template_path = $_GET['p'];

            if (str_starts_with($template_path, '/wp_template/')) {
                $template_id = substr($template_path, strlen('/wp_template/'));
                $template = get_block_template($template_id);
                if ($template && !empty($template->content)) {
                    return $template->content;
                }
            }
        }

        return '';
    }
}

new Blocks();
