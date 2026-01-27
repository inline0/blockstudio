<?php

namespace Blockstudio;

use BlockstudioVendor\ScssPhp\ScssPhp\Exception\SassException;

/**
 * LLM.
 *
 * @date   08/05/2025
 * @since  6.0.0
 */
class LLM
{
    /**
     * Construct.
     *
     * @date   08/05/2025
     * @since  6.0.0
     */
    public function __construct()
    {
        add_action('template_redirect', [$this, 'serveCustomTxtFile']);
    }

    /**
     * Recursively transforms the block data tree.
     *
     * @date   17/05/2024
     * @since  6.0.0
     *
     * @param  array  $node
     *
     * @return array
     */
    private static function transformBlockTree(array $node): array
    {
        $result = [];
        foreach ($node as $key => $value) {
            if (
                $key === '.' &&
                is_array($value) &&
                isset($value['directory']) &&
                $value['directory'] === true
            ) {
                continue;
            }

            if (is_array($value)) {
                if (
                    isset($value['directory']) &&
                    $value['directory'] === false &&
                    isset($value['name'])
                ) {
                    $result[$key] = new \stdClass();
                } else {
                    $transformedChild = self::transformBlockTree($value);
                    if (!empty($transformedChild) || empty($value)) {
                        $result[$key] = $transformedChild;
                    } elseif (
                        is_array($value) &&
                        !empty($value) &&
                        empty($transformedChild)
                    ) {
                        if (
                            array_keys($value) !== range(0, count($value) - 1)
                        ) {
                            $result[$key] = new \stdClass();
                        } else {
                            $result[$key] = [];
                        }
                    }
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get .txt URL.
     *
     * @date   08/05/2025
     * @since  6.0.0
     *
     * @return string
     */
    public static function getTxtUrl(): string
    {
        return site_url() . '/blockstudio-llm.txt';
    }

    /**
     * Get compiled .txt data.
     *
     * @date   08/05/2025
     * @since  6.0.0
     *
     * @return string
     * @throws SassException
     */
    public static function getTxtData(): string
    {
        $txt = file_get_contents(BLOCKSTUDIO_DIR . '/includes/llm/llm.txt');
        $docs = file_get_contents(
            BLOCKSTUDIO_DIR . '/includes/llm/blockstudio.md'
        );
        $blockSchema = json_encode(
            json_decode(
                file_get_contents(
                    BLOCKSTUDIO_DIR . '/includes/schemas/block.json'
                )
            )
        );
        $extensionsSchema = json_encode(
            json_decode(
                file_get_contents(
                    BLOCKSTUDIO_DIR . '/includes/schemas/extensions.json'
                )
            )
        );

        $blocksRaw = Build::dataSorted();
        $transformedBlocks = [];
        foreach ($blocksRaw as $instanceKey => $instanceData) {
            if (is_array($instanceData)) {
                $transformedBlocks[$instanceKey] = self::transformBlockTree(
                    $instanceData
                );
            } else {
                $transformedBlocks[$instanceKey] = $instanceData;
            }
        }

        $txt = str_replace('%%docs%%', $docs, $txt);
        $txt = str_replace('%%schemaBlocks%%', $blockSchema, $txt);
        $txt = str_replace('%%schemaExtensions%%', $extensionsSchema, $txt);
        $txt = str_replace(
            '%%settings%%',
            json_encode(Settings::getAll()),
            $txt
        );
        $txt = str_replace('%%blocks%%', json_encode($transformedBlocks), $txt);

        $exampleBlocksBasePath =
            BLOCKSTUDIO_DIR . '/includes/library/blockstudio-element/';
        $exampleBlockNames = ['gallery', 'icon', 'image-comparison', 'slider'];
        $blockCreationExamplesContent = PHP_EOL;

        $fileTypeMappings = [
            'block.json' => 'json',
            'index.php' => 'php',
            'style.css' => 'css',
            'style-inline.css' => 'css',
            'style.scss' => 'scss',
            'style-inline.scss' => 'scss',
            'script.js' => 'javascript',
            'script-inline.js' => 'javascript',
        ];

        foreach ($exampleBlockNames as $blockName) {
            $blockPath = $exampleBlocksBasePath . $blockName . '/';
            if (!is_dir($blockPath)) {
                continue;
            }

            $blockCreationExamplesContent .=
                "--- Block Example: blockstudio-element/{$blockName} ---" .
                PHP_EOL .
                PHP_EOL;

            foreach ($fileTypeMappings as $fileName => $lang) {
                $filePath = $blockPath . $fileName;
                if (file_exists($filePath)) {
                    $content = trim(file_get_contents($filePath));
                    $relativePathForDisplay = "includes/library/blockstudio-element/{$blockName}/{$fileName}";
                    $blockCreationExamplesContent .=
                        "--- File: {$relativePathForDisplay} ---" . PHP_EOL;
                    $blockCreationExamplesContent .= "```{$lang}" . PHP_EOL;
                    $blockCreationExamplesContent .= $content . PHP_EOL;
                    $blockCreationExamplesContent .= '```' . PHP_EOL . PHP_EOL;
                }
            }
        }

        $txt = str_replace(
            '%%blockCreationExamples%%',
            $blockCreationExamplesContent,
            $txt
        );

        return $txt;
    }

    /**
     * Serve a custom .txt file.
     *
     * @date   08/05/2025
     * @since  6.0.0
     */
    public function serveCustomTxtFile()
    {
        $requestUri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '';

        $enabled = Settings::get('ai/enableContextGeneration');

        if (Files::endsWith($requestUri, '/blockstudio-llm.txt') && $enabled) {
            header('Content-Type: text/plain; charset=utf-8');
            status_header(200);
            echo self::getTxtData();
            die();
        }
    }
}

new LLM();
