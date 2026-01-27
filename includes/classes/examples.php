<?php

namespace Blockstudio;

/**
 * Examples.
 *
 * @date   05/03/2022
 * @since  5.5.0
 */
class Examples
{
    /**
     * Get examples.
     *
     * @date   05/03/2022
     * @since  5.5.0
     *
     * @return array
     */
    public static function get(): array
    {
        $basePath = BLOCKSTUDIO_DIR . '/includes/templates';
        $results = [];

        if (is_dir($basePath)) {
            $subdirectories = glob($basePath . '/*', GLOB_ONLYDIR);
            foreach ($subdirectories as $subdir) {
                $subdirName = basename($subdir);
                $results[$subdirName] = [];
                $files = array_merge(
                    glob($subdir . '/*.php'),
                    glob($subdir . '/*.twig')
                );

                foreach ($files as $file) {
                    $fileName = basename($file);
                    $content = file_get_contents($file);
                    $results[$subdirName][$fileName] = $content;
                }
            }
        }

        return $results;
    }
}
