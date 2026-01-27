<?php

namespace Blockstudio;

use Exception;

/**
 * ES Modules class.
 *
 * @date   08/07/2023
 * @since  5.2.0
 */
class ESModules
{
    /**
     * Get Blockstudio Regex.
     *
     * @date   08/07/2023
     * @since  5.2.0
     *
     * @return string
     */
    public static function getBlockstudioRegex(): string
    {
        return '/\bfrom\s*["\']?(blockstudio\/[^"\']*)["\']/';
    }

    /**
     * Get HTTP Regex.
     *
     * @date   08/07/2023
     * @since  5.2.0
     *
     * @return string
     */
    public static function getHttpRegex(): string
    {
        return '/^export \* from\s*"([^"]*)";$/m';
    }

    /**
     * Match all modules.
     *
     * @date   08/07/2023
     * @since  5.2.0
     *
     * @param  $str
     * @param  bool  $obj
     *
     * @return array|string|string[]
     */
    public static function getModuleMatches($str, bool $obj = false)
    {
        $replacer = function ($str) {
            $str = str_replace('from"blockstudio', 'from "blockstudio', $str);

            return str_replace("from'blockstudio", "from 'blockstudio", $str);
        };

        $getter = function ($str) use ($replacer) {
            $nameVersion = $replacer(trim($str, "'\""));
            $nameVersion = str_replace('blockstudio/', '', $nameVersion);

            $lastAtPosition = strrpos($nameVersion, '@');
            $name = substr($nameVersion, 0, $lastAtPosition);
            $version = substr($nameVersion, $lastAtPosition + 1);

            return [
                'name' => $name,
                'nameTransformed' => str_replace('/', '-', $name),
                'version' => $version,
                'nameVersion' => $name . '@' . $version,
            ];
        };

        if ($obj) {
            return $getter($str);
        }

        $str = $replacer($str);

        preg_match_all(self::getBlockstudioRegex(), $str, $matches);
        foreach ($matches[0] as $item) {
            $obj = $getter($item);
            $str = str_replace(
                str_replace('from ', '', $item),
                "\"https://esm.sh/{$obj['nameVersion']}?bundle\"",
                $str
            );
        }

        return $str;
    }

    /**
     * Get module strings.
     *
     * @date   08/07/2023
     * @since  5.2.0
     *
     * @param  $str
     *
     * @return array
     */
    public static function getModuleStrings($str): array
    {
        return preg_match_all(self::getBlockstudioRegex(), $str, $matches)
            ? $matches[1]
            : [];
    }

    /**
     * Fetch module.
     *
     * @since  5.2.0
     * @date   08/07/2023
     *
     * @param  $str
     *
     * @return string
     */
    public static function fetchModule($str)
    {
        $module = self::getModuleMatches($str, true);

        try {
            $response = wp_remote_get(
                "https://esm.sh/{$module['nameVersion']}?bundle"
            );
            if (wp_remote_retrieve_response_code($response) != 200) {
                return false;
            }

            $e = wp_remote_retrieve_body($response);

            $httpMatcher = self::getHttpRegex();
            preg_match_all($httpMatcher, $e, $matches);
            $match = reset($matches[1]);

            $url =
                strpos($match, 'http') === 0
                    ? $match
                    : "https://esm.sh{$match}";
            $urlResponse = wp_remote_get($url);

            if (wp_remote_retrieve_response_code($urlResponse) != 200) {
                return false;
            }

            $content = wp_remote_retrieve_body($urlResponse);

            if (stripos($content, '<html') !== false) {
                return false;
            }

            return $content;
        } catch (Exception $error) {
            return false;
        }
    }

    /**
     * Write to file.
     *
     * @since  5.2.0
     * @date   08/07/2023
     *
     * @param  $str
     * @param  $folder
     *
     * @return false|string
     */
    public static function fetchModuleAndWriteToFile($str, $folder)
    {
        $module = self::getModuleMatches($str, true);
        $folderDist = $folder . '/_dist';
        $folderModules = $folderDist . '/modules';
        $folderModule = $folderModules . '/' . $module['nameTransformed'];
        $filename = $folderModule . '/' . $module['version'] . '.js';

        if (file_exists($filename)) {
            return $filename;
        }

        $data = self::fetchModule($str);

        if (!$data) {
            return false;
        }

        if (!is_dir($folderDist)) {
            mkdir($folderDist);
        }

        if (!is_dir($folderModules)) {
            mkdir($folderModules);
        }

        if (!is_dir($folderModule)) {
            mkdir($folderModule);
        }

        file_put_contents($filename, $data);

        return $filename;
    }

    /**
     * Fetch all modules and write to file.
     *
     * @since  5.2.0
     * @date   08/07/2023
     *
     * @param  $str
     * @param  $folder
     *
     * @return array
     */
    public static function fetchAllModulesAndWriteToFile($str, $folder): array
    {
        $modules = self::getModuleStrings($str);
        $objects = [];
        $filenames = [];

        try {
            foreach ($modules as $module) {
                $objects[] = self::getModuleMatches($module, true);
                $filenames[] = self::fetchModuleAndWriteToFile(
                    $module,
                    $folder
                );
            }
        } catch (Exception $error) {
        }

        return [
            'objects' => $objects,
            'filenames' => $filenames,
        ];
    }
}
