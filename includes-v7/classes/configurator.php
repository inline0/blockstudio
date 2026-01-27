<?php

namespace Blockstudio;

/**
 * Configurator class.
 *
 * @date   10/12/2022
 * @since  3.2.0
 */
class Configurator
{
    function __construct()
    {
        add_shortcode('blockstudio-configurator', function () {
            Admin::assets();

            $blockScripts = include BLOCKSTUDIO_DIR .
                '/includes-v7/admin/assets/configurator/index.tsx.asset.php';
            wp_enqueue_script(
                'blockstudio-configurator',
                plugin_dir_url(__FILE__) .
                    '../admin/assets/configurator/index.tsx.js',
                $blockScripts['dependencies'],
                $blockScripts['version']
            );

            wp_localize_script(
                'blockstudio-configurator',
                'blockstudioAdmin',
                Admin::data(false)
            );

            $site = esc_attr(get_site_url());

            return "<style>body { background: #fff !important; }</style><div class='configurator' style='width: 100%;' data-site='$site'></div>";
        });
    }
}

new Configurator();
