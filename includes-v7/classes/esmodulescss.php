<?php

namespace Blockstudio;

use Exception;

/**
 * ES Modules CSS class.
 *
 * @date   03/11/2023
 * @since  5.2.12
 */
class ESModulesCSS
{
    /**
     * Get Blockstudio Regex.
     *
     * @date   03/11/2023
     * @since  5.2.12
     *
     * @return string
     */
    public static function getBlockstudioRegex(): string
    {
        return '/import\s*["\'](blockstudio\/.*\.css)["\']/';
    }

    /**
     * Replace module references in a string.
     *
     * @date   03/11/2023
     * @since  5.2.12
     *
     * @param  string  $str
     *
     * @return string
     */
    public static function replaceModuleReferences(string $str): string
    {
        $regex = self::getBlockstudioRegex();

        return preg_replace($regex, '', $str);
    }

    /**
     * Match all modules.
     *
     * @date   03/11/2023
     * @since  5.2.12
     *
     * @param  string  $str
     *
     * @return array
     */
    public static function getModuleMatches(string $str): array
    {
        $regex = self::getBlockstudioRegex();
        preg_match_all($regex, $str, $matches);
        $result = [];

        foreach ($matches[1] as $match) {
            $lastAtPosition = strrpos($match, '@');
            $name = str_replace(
                'blockstudio/',
                '',
                substr($match, 0, $lastAtPosition)
            );
            $versionAndFile = substr($match, $lastAtPosition + 1);

            list($version, $filename) = explode('/', $versionAndFile, 2);

            $result[] = [
                'name' => $name,
                'nameTransformed' => str_replace('/', '-', $name),
                'version' => $version,
                'nameVersion' => $name . '@' . $version,
                'filename' => $filename,
            ];
        }

        return $result;
    }

    /**
     * Fetch module.
     *
     * @since  5.2.12
     * @date   03/11/2023
     *
     * @param  $module
     *
     * @return string
     */
    public static function fetchModule($module)
    {
        try {
            $response = wp_remote_get(
                "https://esm.sh/{$module['nameVersion']}/{$module['filename']}"
            );
            if (wp_remote_retrieve_response_code($response) != 200) {
                return false;
            }

            return wp_remote_retrieve_body($response);
        } catch (Exception $error) {
            return false;
        }
    }

    /**
     * Write to file.
     *
     * @since  5.2.12
     * @date   03/11/2023
     *
     * @param  $module
     * @param  $folder
     *
     * @return false|string
     */
    public static function fetchModuleAndWriteToFile($module, $folder)
    {
        $folderDist = $folder . '/_dist';
        $folderModules = $folderDist . '/modules';
        $folderModule = $folderModules . '/' . $module['nameTransformed'];
        $filename =
            $folderModule .
            '/' .
            $module['version'] .
            '-' .
            str_replace('/', '-', $module['filename']);

        if (file_exists($filename)) {
            return $filename;
        }

        $data = self::fetchModule($module);

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
     * @since  5.2.12
     * @date   03/11/2023
     *
     * @param  $str
     * @param  $folder
     *
     * @return array
     */
    public static function fetchAllModulesAndWriteToFile($str, $folder): array
    {
        $modules = self::getModuleMatches($str);
        $objects = [];
        $filenames = [];

        try {
            foreach ($modules as $module) {
                $objects[] = $module;
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
