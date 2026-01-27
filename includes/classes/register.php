<?php

namespace Blockstudio;

/**
 * Register class.
 *
 * @date   11/06/2024
 * @since  5.5.5
 */
class Register
{
    function __construct()
    {
        add_action(
            'init',
            function () {
                foreach (Build::blocks() as $block) {
                    register_block_type(
                        apply_filters('blockstudio/blocks/meta', $block)
                    );
                }
            },
            PHP_INT_MAX
        );

        add_action('wp_enqueue_scripts', function () {
            foreach (Build::assets() as $type => $assets) {
                foreach ($assets as $handle => $data) {
                    if ($type === 'script') {
                        wp_register_script(
                            $handle,
                            $data['path'],
                            [],
                            $data['mtime'],
                            true
                        );
                    } else {
                        wp_register_style(
                            $handle,
                            $data['path'],
                            [],
                            $data['mtime']
                        );
                    }
                }
            }
        });
    }
}

new Register();
