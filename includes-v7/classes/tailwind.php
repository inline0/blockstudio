<?php

namespace Blockstudio;

/**
 * Tailwind.
 *
 * @date   08/07/2024
 * @since  5.5.0
 */
class Tailwind
{
    /**
     * Construct.
     *
     * @date   05/03/2022
     * @since  2.3.0
     */
    function __construct()
    {
        $enqueue = function () {
            if (
                Settings::get('tailwind/enabled') &&
                file_exists(self::getCSSPath())
            ) {
                wp_enqueue_style(
                    'blockstudio-tailwind',
                    self::getCSSUrl(),
                    [],
                    filemtime(self::getCSSPath())
                );
            }

            if (Build::isTailwindActive()) {
                $preflight =
                    BLOCKSTUDIO_DIR .
                    '/includes-v7/admin/assets/tailwind/preflight.css';
                wp_enqueue_style(
                    'blockstudio-tailwind-preflight',
                    Files::getRelativeUrl($preflight),
                    [],
                    filemtime($preflight)
                );
                if (file_exists(self::getCSSPath(get_the_ID()))) {
                    $id = get_the_ID();
                    wp_enqueue_style(
                        'blockstudio-tailwind-' . $id,
                        self::getCSSUrl($id),
                        [],
                        filemtime(self::getCSSPath($id))
                    );
                }
            }
        };

        add_action('wp_enqueue_scripts', $enqueue);
        add_action('enqueue_block_assets', $enqueue);
    }

    /**
     * Get CDN URL.
     *
     * @date   08/07/2024
     * @since  5.5.0
     *
     * @return string
     */
    public static function getCdnUrl(): string
    {
        $path = BLOCKSTUDIO_DIR . '/includes-v7/admin/assets/tailwind/cdn.js';

        return Files::getRelativeUrl($path);
    }

    /**
     * Get assets dir.
     *
     * @date   07/04/2023
     * @since  5.5.0
     *
     * @return string
     */
    public static function getAssetsDir(): string
    {
        return apply_filters(
            'blockstudio/tailwind/assets/dir',
            wp_upload_dir()['basedir'] . '/blockstudio/tailwind'
        );
    }

    /**
     * Get CSS path.
     *
     * @date   07/04/2023
     * @since  5.5.0
     *
     * @param  string  $id
     *
     * @return string
     */
    public static function getCSSPath(string $id = 'editor'): string
    {
        return self::getAssetsDir() . "/$id.css";
    }

    /**
     * Get CSS url.
     *
     * @date   07/04/2023
     * @since  5.5.0
     *
     * @param  string  $id
     *
     * @return string
     */
    public static function getCSSUrl(string $id = 'editor'): string
    {
        return Files::getRelativeUrl(self::getCSSPath($id));
    }
}

new Tailwind();
