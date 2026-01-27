<?php

namespace Blockstudio;

/**
 * Library class.
 *
 * @date   03/11/2022
 * @since  3.0.11
 */
class Library
{
    /**
     * Construct.
     *
     * @date   03/11/2022
     * @since  3.0.11
     */
    function __construct()
    {
        add_filter(
            'block_categories_all',
            function ($categories, $post) {
                return array_merge($categories, [
                    [
                        'slug' => 'blockstudio-elements',
                        'title' => __('Blockstudio Elements', 'blockstudio'),
                    ],
                ]);
            },
            10,
            2
        );

        add_action('init', function () {
            if (Settings::get('library')) {
                Build::init([
                    'dir' => BLOCKSTUDIO_DIR . '/includes/library',
                    'library' => true,
                ]);
            }
        });

        add_filter('blockstudio/blocks/meta', function ($block) {
            if (strpos($block->name, 'blockstudio-element') === 0) {
                $block->blockstudio['icon'] =
                    '<svg style="padding: 2px;" width="321px" height="321px" viewBox="0 0 321 321" xmlns="http://www.w3.org/2000/svg"><g id="assets" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"><g fill="currentColor"><path d="M160.5,0 C288.912633,0 321,32.0873672 321,160.5 C321,288.912633 288.912633,321 160.5,321 C32.0873672,321 0,288.912633 0,160.5 C0,32.0873672 32.0873672,0 160.5,0 Z M54.9896927,132.862573 C32.4775364,216.879084 47.8460619,243.498151 131.862573,266.010307 C215.879084,288.522464 242.498151,273.153938 265.010307,189.137427 C287.522464,105.120916 272.153938,78.501849 188.137427,55.9896927 C104.120916,33.4775364 77.501849,48.8460619 54.9896927,132.862573 Z"></path><path d="M160,106.642665 C116.509854,106.642665 105.642665,117.509854 105.642665,161 C105.642665,204.490146 116.509854,215.357335 160,215.357335 C203.490146,215.357335 214.357335,204.490146 214.357335,161 C214.357335,117.509854 203.490146,106.642665 160,106.642665 Z" id="inner" transform="translate(160.000000, 161.000000) rotate(-45.000000) translate(-160.000000, -161.000000)"></path></g></g></svg>';
            }

            return $block;
        });
    }
}

new Library();
