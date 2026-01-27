<?php

namespace Blockstudio;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Files.
 *
 * @date   22/08/2023
 * @since  5.2.0
 */
class Files
{
    /**
     * Get render template.
     *
     * @date   26/12/2023
     * @since  5.3.0
     *
     * @param  $file
     *
     * @return false|string
     */
    public static function getRenderTemplate($file)
    {
        $directory = dirname($file);

        if (file_exists($directory . '/index.php')) {
            return $directory . '/index.php';
        }

        if (file_exists($directory . '/index.blade.php')) {
            return $directory . '/index.blade.php';
        }

        if (file_exists($directory . '/index.twig')) {
            return $directory . '/index.twig';
        }

        return false;
    }

    /**
     * Check if string starts with.
     *
     * @date   01/06/2023
     * @since  5.0.0
     *
     * @param  $haystack
     * @param  $needle
     *
     * @return bool
     */
    public static function startsWith($haystack, $needle): bool
    {
        if (function_exists('str_starts_with')) {
            return str_starts_with($haystack, $needle);
        }

        return strpos($haystack, $needle) === 0;
    }

    /**
     * Check if string ends with.
     *
     * @date   17/03/2022
     * @since  2.3.1
     *
     * @param  $haystack
     * @param  $needle
     *
     * @return bool
     */
    public static function endsWith($haystack, $needle): bool
    {
        if (function_exists('str_ends_with')) {
            return str_ends_with($haystack, $needle);
        }

        $length = strlen($needle);
        if (!$length) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }

    /**
     * Check if string contains another string.
     *
     * @date   17/03/2022
     * @since  2.3.1
     *
     * @param  $haystack
     * @param  $needle
     *
     * @return bool
     */
    public static function contains($haystack, $needle): bool
    {
        if (function_exists('str_contains')) {
            return str_contains($haystack, $needle);
        }

        return strpos($haystack, $needle) !== false;
    }

    /**
     * Delete all files.
     *
     * @param  string  $dir
     * @param  bool    $deleteDir
     *
     * @return bool
     */
    public static function deleteAllFiles(
        string $dir,
        bool $deleteDir = true
    ): bool {
        if (false === file_exists($dir)) {
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir()) {
                if (false === rmdir($fileInfo->getRealPath())) {
                    return false;
                }
            } else {
                if (false === unlink($fileInfo->getRealPath())) {
                    return false;
                }
            }
        }

        if ($deleteDir) {
            return rmdir($dir);
        }

        return true;
    }

    /**
     * Check if dir is empty.
     *
     * @param  string  $dir
     *
     * @return bool|null
     */
    public static function isDirectoryEmpty(string $dir): ?bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        if (!is_readable($dir)) {
            return null;
        }
        $files = scandir($dir);

        return count($files) == 2;
    }

    /**
     * Get relative URL for assets.
     *
     * @date   05/10/2022
     * @since  2.0.0
     *
     * @param  $url
     *
     * @return string
     */
    public static function getRelativeUrl($url): string
    {
        $url = str_replace('\\', '/', $url);
        $str = substr(
            $url,
            strpos(
                $url,
                substr(WP_CONTENT_DIR, strrpos(WP_CONTENT_DIR, '/') + 1)
            )
        );

        return WP_CONTENT_URL .
            substr(
                $str,
                strrpos($str, Files::getRootFolder()) +
                    strlen(Files::getRootFolder())
            );
    }

    /**
     * Get WordPress root folder name.
     *
     * @date   25/04/2022
     * @since  2.3.3
     *
     * @return string
     */
    public static function getRootFolder(): string
    {
        return array_slice(explode('/', WP_CONTENT_DIR), -1)[0];
    }

    /**
     * Get files recursively and delete empty folders.
     *
     * @date   18/10/2023
     * @since  5.2.10
     *
     * @param  $dir
     *
     * @return array
     */
    public static function getFilesRecursivelyAndDeleteEmptyFolders($dir): array
    {
        $files = [];

        if (!is_dir($dir)) {
            return $files;
        }

        $directoryIterator = new RecursiveDirectoryIterator(
            $dir,
            FilesystemIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getPathname();
            } elseif (
                $fileInfo->isDir() &&
                !(new FilesystemIterator($fileInfo->getPathname()))->valid()
            ) {
                rmdir($fileInfo->getPathname());
            }
        }

        return $files;
    }

    /**
     * Get all files recursively with a certain extension.
     *
     * @date   03/11/2023
     * @since  5.2.12
     *
     * @param  string  $dir
     * @param  string  $extension
     *
     * @return array
     */
    public static function getFilesWithExtension(
        string $dir,
        string $extension
    ): array {
        $files = [];

        if (!is_dir($dir)) {
            return $files;
        }

        $directoryIterator = new RecursiveDirectoryIterator(
            $dir,
            FilesystemIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator($directoryIterator);

        foreach ($iterator as $fileInfo) {
            if (
                $fileInfo->isFile() &&
                $fileInfo->getExtension() === $extension
            ) {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }

    /**
     * Get a folder structure as an associative array with file contents.
     *
     * @date   16/08/2024
     * @since  5.6.0
     *
     * @param  string  $dir
     *
     * @return array|false|string
     */
    public static function getFolderStructureWithContents(string $dir)
    {
        $structure = [];

        if (!is_dir($dir)) {
            return $structure;
        }

        $directoryIterator = new RecursiveDirectoryIterator(
            $dir,
            FilesystemIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            $innerIterator = $iterator->getInnerIterator();

            if (!$innerIterator instanceof RecursiveDirectoryIterator) {
                continue;
            }

            $subPath = $innerIterator->getSubPathName();

            $pathParts = explode(DIRECTORY_SEPARATOR, $subPath);
            $temp = &$structure;

            foreach ($pathParts as $part) {
                if (!isset($temp[$part])) {
                    $temp[$part] = [];
                }
                $temp = &$temp[$part];
            }

            if ($fileInfo->isFile()) {
                $temp = file_get_contents($fileInfo->getPathname());
            }
        }

        return $structure;
    }
}
