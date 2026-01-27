<?php

namespace Blockstudio;

/**
 * Build class.
 *
 * @date   28/09/2020
 * @since  1.0.0
 */

use BlockstudioVendor\ScssPhp\ScssPhp\Exception\SassException;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_Block_Type;

class Build
{
    private static bool $interactivityApiRendered = false;
    private static array $assetsAdmin = [];
    private static array $assetsBlockEditor = [];
    private static array $assetsGlobal = [];
    private static array $assetsRegister = [];
    private static array $blade = [];
    private static array $blocks = [];
    private static array $blocksOverrides = [];
    private static array $data = [];
    private static array $dataOverrides = [];
    private static array $extensions = [];
    private static array $files = [];
    private static array $instances = [];
    private static array $overrides = [];
    private static array $paths = [];
    private static bool $isTailwindActive = false;

    /**
     * Filter deep array everything but a given string.
     *
     * @date   05/12/2022
     * @since  3.1.1
     *
     * @param  $array
     * @param  $key
     * @param  $val
     */
    public static function filterNotKey(&$array, $key, $val)
    {
        foreach ($array as $k => $v) {
            if (isset($v[$key]) && $v[$key] === $val) {
                unset($array[$k]);
            } elseif (is_array($v)) {
                self::filterNotKey($array[$k], $key, $val);
            }
            if (empty($array[$k])) {
                unset($array[$k]);
            }
        }
        foreach ($array as $k => $v) {
            if ($k === 'attributes') {
                $array[$k] = array_values($v);
            } elseif (is_array($v)) {
                self::filterNotKey($array[$k], $key, $val);
            }
        }
    }

    /**
     * Build attributes.
     *
     * @date   02/07/2022
     * @since  2.4.0
     *
     * @param  $attrs
     * @param  $attributes
     * @param  string  $id
     * @param  bool    $fromGroup
     * @param  bool    $fromRepeater
     * @param  bool    $isOverride
     * @param  bool    $isExtend
     */
    public static function buildAttributes(
        $attrs,
        &$attributes,
        string $id = '',
        bool $fromGroup = false,
        bool $fromRepeater = false,
        bool $isOverride = false,
        bool $isExtend = false
    ) {
        $index = 0;
        foreach ($attrs as $data) {
            $data = ['attributes' => $data];

            foreach ($data as $v) {
                $i = $id === '' ? '' : $id . '_';
                $fieldId = $fromRepeater ? $index : $i . ($v['id'] ?? '');
                $index++;

                if (
                    isset($v['type']) &&
                    $v['type'] !== 'message' &&
                    ((!isset($v['id']) &&
                        ($v['type'] === 'group' || $v['type'] === 'tabs')) ||
                        isset($v['id']))
                ) {
                    $type = $v['type'];

                    $isMultipleOptions =
                        $type === 'checkbox' ||
                        $type === 'token' ||
                        ($type === 'select' && ($v['multiple'] ?? false));

                    if ($type === 'tabs' && !$fromGroup && !$fromRepeater) {
                        foreach ($v['tabs'] as $tab) {
                            self::buildAttributes(
                                array_values($tab['attributes']),
                                $attributes,
                                '',
                                false,
                                false,
                                $isOverride
                            );
                        }
                    }

                    if (
                        ($type === 'group' && !$fromGroup) ||
                        $type === 'repeater'
                    ) {
                        if (
                            isset($v['attributes']) &&
                            count($v['attributes']) >= 1
                        ) {
                            self::filterNotKey(
                                $v['attributes'],
                                'type',
                                'group'
                            );
                        }

                        if ($type === 'group') {
                            self::buildAttributes(
                                array_values($v['attributes']),
                                $attributes,
                                $i . ($v['id'] ?? ''),
                                true,
                                false,
                                $isOverride,
                                $isExtend
                            );
                        }

                        if ($type === 'repeater') {
                            $attributes[$fieldId] = [
                                'blockstudio' => true,
                                'type' => 'array',
                                'field' => $type,
                                'attributes' =>
                                    count($v['attributes'] ?? []) >= 1
                                        ? array_values(
                                            array_filter(
                                                $v['attributes'],
                                                fn($val) => $val['type'] !==
                                                    'group'
                                            )
                                        )
                                        : [],
                            ];

                            if (
                                count(
                                    $attributes[$fieldId]['attributes'] ?? []
                                ) >= 1
                            ) {
                                self::buildAttributes(
                                    $attributes[$fieldId]['attributes'],
                                    $attributes[$fieldId]['attributes'],
                                    '',
                                    false,
                                    true,
                                    $isOverride,
                                    $isExtend
                                );
                            }
                        }
                    }

                    if ($type === 'attributes') {
                        $attributes[$fieldId] = [
                            'blockstudio' => true,
                            'type' => 'array',
                            'field' => $type,
                        ];
                    }

                    if (
                        $type === 'code' ||
                        $type === 'date' ||
                        $type === 'datetime' ||
                        $type === 'text' ||
                        $type === 'textarea' ||
                        $type === 'unit' ||
                        $type === 'classes'
                    ) {
                        $attributes[$fieldId] = [
                            'blockstudio' => true,
                            'type' => 'string',
                            'field' => $type,
                        ];

                        if ($type === 'classes' && ($v['tailwind'] ?? false)) {
                            self::$isTailwindActive = true;
                        }
                    }

                    if ($type === 'code') {
                        $attributes[$fieldId]['language'] =
                            $v['language'] ?? 'html';
                        $attributes[$fieldId]['asset'] = $v['asset'] ?? false;
                    }

                    if ($type === 'number' || $type === 'range') {
                        $attributes[$fieldId] = [
                            'blockstudio' => true,
                            'type' => 'number',
                            'field' => $type,
                        ];
                    }

                    if ($type === 'toggle') {
                        $attributes[$fieldId] = [
                            'blockstudio' => true,
                            'type' => 'boolean',
                            'field' => $type,
                        ];
                    }

                    if ($isMultipleOptions) {
                        $attributes[$fieldId] = [
                            'blockstudio' => true,
                            'type' => 'array',
                            'field' => $type,
                        ];

                        if ($type === 'select') {
                            $attributes[$fieldId]['multiple'] = true;
                        }
                    }

                    if (
                        $type === 'color' ||
                        $type === 'gradient' ||
                        $type === 'icon' ||
                        $type === 'link' ||
                        $type === 'radio' ||
                        ($type === 'select' &&
                            (!isset($v['multiple']) ||
                                $v['multiple'] === false))
                    ) {
                        $attributes[$fieldId] = [
                            'blockstudio' => true,
                            'type' => 'object',
                            'field' => $type,
                        ];
                    }

                    if ($type === 'files') {
                        $attributes[$fieldId] = [
                            'blockstudio' => true,
                            'type' => ['number', 'object', 'array'],
                            'field' => $type,
                            'multiple' => $v['multiple'] ?? false,
                            'returnSize' => $v['returnSize'] ?? 'full',
                        ];
                    }

                    if (
                        $type === 'select' ||
                        $type === 'radio' ||
                        $type === 'checkbox' ||
                        $type === 'color' ||
                        $type === 'gradient'
                    ) {
                        if (
                            ($isOverride && isset($v['options'])) ||
                            !$isOverride
                        ) {
                            $options = $v['options'] ?? [];
                            $attributes[$fieldId]['options'] = $options;
                        }
                    }

                    if (
                        $type === 'select' ||
                        $type === 'radio' ||
                        $type === 'checkbox' ||
                        $type === 'color' ||
                        $type === 'gradient'
                    ) {
                        if (
                            ($isOverride && isset($v['populate'])) ||
                            !$isOverride
                        ) {
                            $options =
                                $type === 'select' &&
                                ($v['populate']['fetch'] ?? false)
                                    ? []
                                    : $v['options'] ?? [];
                            $attributes[$fieldId]['options'] = $options;
                            $populateType = $v['populate']['type'] ?? false;

                            if (
                                $populateType === 'query' ||
                                $populateType === 'custom' ||
                                $populateType === 'function'
                            ) {
                                $optionsAddons = Populate::init(
                                    $v['populate'],
                                    $v['default'] ?? false
                                );
                                $optionsTransformed = [];
                                $optionsPopulate = [];
                                $optionsPopulateFull = [];

                                if ($v['populate']['type'] === 'query') {
                                    $q = $v['populate']['query'];
                                    $returnMapValue = [
                                        'posts' => 'ID',
                                        'users' => 'ID',
                                        'terms' => 'term_id',
                                    ];
                                    $returnMapLabel = [
                                        'posts' => 'post_title',
                                        'users' => 'display_name',
                                        'terms' => 'name',
                                    ];

                                    foreach ($optionsAddons as $opt) {
                                        $val = $opt->{$returnMapValue[$q]};

                                        $optionsPopulate[] = $val;
                                        $optionsTransformed[] = [
                                            'value' => $val,
                                            'label' =>
                                                $opt->{$v['populate'][
                                                    'returnFormat'
                                                ]['label'] ??
                                                    $returnMapLabel[$q]},
                                        ];
                                        $optionsPopulateFull[$val] = $opt;
                                    }
                                }

                                if ($v['populate']['type'] === 'function') {
                                    $val =
                                        $v['populate']['returnFormat'][
                                            'value'
                                        ] ?? false;
                                    $label =
                                        $v['populate']['returnFormat'][
                                            'label'
                                        ] ?? false;

                                    if (!$val && !$label) {
                                        $optionsAddons = array_values(
                                            $optionsAddons
                                        );
                                    }

                                    foreach ($optionsAddons as $opt) {
                                        $opt = (array) $opt;

                                        $val =
                                            $opt[$val] ??
                                            ($opt['value'] ??
                                                (array_values($opt)[0] ??
                                                    $opt));
                                        $optionsPopulate[] = $val;
                                        $optionsTransformed[] = [
                                            'value' => $val,
                                            'label' =>
                                                $opt[$label] ??
                                                ($opt['label'] ?? $val),
                                        ];
                                    }
                                }

                                if (count($optionsPopulate) >= 1) {
                                    $attributes[$fieldId][
                                        'optionsPopulate'
                                    ] = $optionsPopulate;
                                    $attributes[$fieldId][
                                        'optionsPopulateFull'
                                    ] = $optionsPopulateFull;
                                }

                                $isTransform =
                                    $v['populate']['type'] === 'query' ||
                                    $v['populate']['type'] === 'function';

                                $attributes[$fieldId]['options'] =
                                    isset($v['populate']['position']) &&
                                    $v['populate']['position'] === 'before'
                                        ? array_merge(
                                            $isTransform
                                                ? $optionsTransformed
                                                : $optionsAddons,
                                            $options
                                        )
                                        : array_merge(
                                            $options,
                                            $isTransform
                                                ? $optionsTransformed
                                                : $optionsAddons
                                        );
                            }
                        }
                    }

                    if ($type === 'richtext' || $type === 'wysiwyg') {
                        $attributes[$fieldId] = [
                            'blockstudio' => true,
                            'type' => 'string',
                            'field' => $type,
                            'source' => 'html',
                        ];
                    }

                    foreach (['default', 'fallback'] as $item) {
                        if (isset($v[$item])) {
                            if (
                                $type === 'code' ||
                                $type === 'date' ||
                                $type === 'datetime' ||
                                $type === 'files' ||
                                $type === 'icon' ||
                                $type === 'link' ||
                                $type === 'richtext' ||
                                $type === 'text' ||
                                $type === 'textarea' ||
                                $type === 'toggle' ||
                                $type === 'unit' ||
                                $type === 'wysiwyg' ||
                                $type === 'classes'
                            ) {
                                $attributes[$fieldId][$item] = $v[$item];
                            }
                            if ($type === 'number' || $type === 'range') {
                                $attributes[$fieldId][$item] =
                                    $v[$item] === 0 ? '0' : $v[$item];
                            }
                            if ($type === 'color' || $type === 'gradient') {
                                foreach ($v['options'] ?? [] as $value) {
                                    if ($value['value'] === $v[$item]) {
                                        $attributes[$fieldId][$item] = $value;
                                    }
                                }
                            }
                            if (
                                $type === 'checkbox' ||
                                $type === 'radio' ||
                                $type === 'select' ||
                                $type === 'token'
                            ) {
                                $defaultSelect = [];

                                foreach (
                                    is_array($v[$item])
                                        ? $v[$item]
                                        : [$v[$item]]
                                    as $value
                                ) {
                                    $option = fn($val) => Block::getOptionValue(
                                        [
                                            'options' =>
                                                $attributes[$fieldId][
                                                    'options'
                                                ] ?? $v['options'],
                                        ],
                                        $val,
                                        [
                                            'value' => $value,
                                        ]
                                    );

                                    $defaultSelect[] = [
                                        'value' => $option('value'),
                                        'label' => $option('label'),
                                    ];
                                }

                                $attributes[$fieldId][
                                    $item
                                ] = $isMultipleOptions
                                    ? $defaultSelect
                                    : $defaultSelect[0];
                            }
                        }
                    }

                    if (isset($v['returnFormat'])) {
                        $attributes[$fieldId]['returnFormat'] =
                            $v['returnFormat'] ?? 'value';
                    }

                    if (isset($v['populate'])) {
                        $attributes[$fieldId]['populate'] = $v['populate'];
                    }

                    if ($type !== 'tabs' && $type !== 'group') {
                        $attributes[$fieldId]['id'] = $i . ($v['id'] ?? '');
                    }

                    if ($v['set'] ?? false) {
                        $attributes[$fieldId]['set'] = $v['set'];
                    }
                }
            }
        }
    }

    /**
     * Filter attributes.
     *
     * @date   29/01/2023
     * @since  4.0.3
     *
     * @param  $block
     * @param  $attrs
     * @param  $attributes
     */
    public static function filterAttributes($block, $attrs, &$attributes)
    {
        foreach ($attrs as $k => $v) {
            $attributes[$k] = apply_filters(
                'blockstudio/blocks/attributes',
                $v,
                $block
            );

            $type = $attributes[$k]['type'] ?? false;

            if ($type === 'group' || $type === 'repeater') {
                self::filterAttributes(
                    $block,
                    $attributes[$k]['attributes'],
                    $attributes[$k]['attributes']
                );
            }
        }
    }

    /**
     * Build attributes IDs.
     *
     * @date   03/12/2022
     * @since  3.1.0
     *
     * @param  $attributes
     */
    public static function buildAttributeIds(&$attributes)
    {
        foreach ($attributes as &$b) {
            if (isset($b['type']) && isset($b['id'])) {
                if ($b['type'] === 'group') {
                    foreach ($b['attributes'] as &$d) {
                        $id = $d['id'];
                        $d['id'] = $b['id'] . '_' . $id;

                        if (isset($d['attributes'])) {
                            self::buildAttributeIds($d['attributes']);
                        }
                    }
                }
            }
        }
    }

    /**
     * Merge attributes.
     *
     * @date   26/12/2023
     * @since  5.3.0
     *
     * @param  $originalAttributes
     * @param  $overrideAttributes
     */
    public static function mergeAttributes(
        &$originalAttributes,
        $overrideAttributes
    ) {
        $mergeAttributeByKeyOrId = function (
            $keyOrId,
            &$attributes,
            $override
        ) use (&$mergeAttributeByKeyOrId) {
            foreach ($attributes as &$attribute) {
                if (
                    (isset($attribute['key']) &&
                        $attribute['key'] === $keyOrId) ||
                    (isset($attribute['id']) && $attribute['id'] === $keyOrId)
                ) {
                    foreach ($override as $key => $value) {
                        if ($key !== 'attributes' && $key !== 'tabs') {
                            $attribute[$key] = $value;
                        } elseif (isset($attribute[$key]) && is_array($value)) {
                            self::mergeAttributes($attribute[$key], $value);
                        }
                    }

                    return true;
                }

                foreach (['attributes', 'tabs'] as $nestedKey) {
                    if (
                        isset($attribute[$nestedKey]) &&
                        is_array($attribute[$nestedKey])
                    ) {
                        if (
                            $mergeAttributeByKeyOrId(
                                $keyOrId,
                                $attribute[$nestedKey],
                                $override
                            )
                        ) {
                            return true;
                        }
                    }
                }
            }

            return false;
        };

        foreach ($overrideAttributes as $overrideAttribute) {
            $keyOrId =
                $overrideAttribute['key'] ?? ($overrideAttribute['id'] ?? null);
            if ($keyOrId !== null) {
                if (
                    !$mergeAttributeByKeyOrId(
                        $keyOrId,
                        $originalAttributes,
                        $overrideAttribute
                    )
                ) {
                    $originalAttributes[] = $overrideAttribute;
                }
            } else {
                $originalAttributes[] = $overrideAttribute;
            }
        }
    }

    /**
     * Get WordPress root folder name.
     *
     * @date   08/06/2023
     * @since  5.0.0
     *
     * @param  $path
     *
     * @return string
     */
    public static function getInstanceName($path): string
    {
        return wp_normalize_path(
            trim(explode(Files::getRootFolder(), $path)[1], '/\\')
        );
    }

    /**
     * Get WordPress root folder name.
     *
     * @date   25/04/2022
     * @since  2.3.3
     *
     * @param  string  $path
     * @param  string  $filter
     *
     * @return string
     */
    public static function getBuildDir(
        string $path = '/blockstudio',
        string $filter = 'path'
    ): string {
        $theme = is_child_theme()
            ? get_stylesheet_directory()
            : get_template_directory();

        return has_filter('blockstudio/' . $filter)
            ? apply_filters('blockstudio/' . $filter, '')
            : $theme . $path;
    }

    /**
     * Build.
     *
     * @date   28/09/2020
     * @since  1.0.0
     *
     * @param  bool|string|array  $args
     *
     * @return void
     * @throws  SassException
     */
    public static function init($args = false)
    {
        $editor = $args['editor'] ?? false;
        $library = $args['library'] ?? false;
        if (is_array($args)) {
            $p = $args;
            $args = $p['dir'] ?? false;
        }
        $arr = [];
        $path = $args === false ? self::getBuildDir() : $args;
        $store = [];
        $instance = '';
        $emptyDistFolders = [];

        if (is_dir($path)) {
            self::$instances[] = [
                'path' => $path,
                'library' => $library,
            ];

            $path = wp_normalize_path($path);
            $instance = self::getInstanceName($path);

            if (!$library && !Settings::get('editor/library')) {
                self::$paths[] = [
                    'instance' => $instance,
                    'path' => $path,
                ];
            }

            do_action('blockstudio/init/before');
            do_action("blockstudio/init/before/$instance");

            $files = new RecursiveDirectoryIterator($path);

            self::$blade[$instance]['path'] = $path;

            $checkKey = function ($contents, $flag) {
                try {
                    $data = json_decode($contents, true);

                    return isset($data['blockstudio'][$flag]) &&
                        $data['blockstudio'][$flag];
                } catch (Exception $e) {
                    return false;
                }
            };

            foreach (
                new RecursiveIteratorIterator($files)
                as $filename => $file
            ) {
                $filename = wp_normalize_path($filename);
                $file = wp_normalize_path($file);
                $fileDir = dirname($file);
                $pathinfo = pathinfo($file);
                $blockJson = [];
                $contents = is_file($file) ? file_get_contents($file) : '{}';

                $isBlockstudio =
                    pathinfo($file)['basename'] === 'block.json' &&
                    isset(json_decode($contents, true)['blockstudio']);
                $isExtend = $checkKey($contents, 'extend');
                $isBlock =
                    $isBlockstudio &&
                    Files::getRenderTemplate($file) &&
                    !$isExtend;
                $isOverride =
                    $isBlockstudio && $checkKey($contents, 'override');
                $isInit =
                    ($pathinfo['extension'] ?? '') === 'php' &&
                    Files::startsWith($pathinfo['basename'], 'init');
                $isDir =
                    is_dir($file) &&
                    !file_exists($file . '/block.json') &&
                    !Files::getRenderTemplate($file);
                $isPhp = !Files::endsWith($file, '.twig');
                $isBlade = Files::endsWith($file, '.blade.php');

                if (
                    Files::startsWith($pathinfo['basename'], '.') &&
                    (!$editor || $pathinfo['basename'] !== '.')
                ) {
                    continue;
                }

                if (
                    $isBlock ||
                    $isBlade ||
                    $isInit ||
                    $isDir ||
                    $isOverride ||
                    $isExtend ||
                    $editor
                ) {
                    if (!is_dir($file)) {
                        $blockJson = json_decode(
                            file_get_contents(
                                Files::endsWith($file, 'block.json')
                                    ? $file
                                    : str_replace(
                                        [
                                            'index.blade.php',
                                            'index.php',
                                            'index.twig',
                                        ],
                                        'block.json',
                                        $file
                                    )
                            ),
                            true
                        );
                    }
                    $blockJsonPath = Files::endsWith($file, 'block.json')
                        ? $file
                        : str_replace(
                            'block.json',
                            $isBlade
                                ? 'index.blade.php'
                                : ($isPhp
                                    ? 'index.php'
                                    : 'index.twig'),
                            $file
                        );

                    $name = $editor
                        ? $file
                        : ($isOverride
                            ? $blockJson['name'] . '-override'
                            : $blockJson['name'] ?? $file);

                    if ($isBlade) {
                        $relativePath = str_replace(
                            [$path, '.blade.php'],
                            '',
                            $filename
                        );
                        $relativePath = str_replace(
                            DIRECTORY_SEPARATOR,
                            '.',
                            $relativePath
                        );

                        self::$blade[$instance]['templates'][$name] = ltrim(
                            $relativePath,
                            '.'
                        );
                        self::$blade[$instance]['path'] = $path;

                        continue;
                    }

                    if (is_array($name)) {
                        $name = json_encode($name);
                    }
                    $nameFile = false;

                    if ($editor && file_exists($fileDir . '/block.json')) {
                        $nameFile =
                            json_decode(
                                file_get_contents($fileDir . '/block.json'),
                                true
                            )['name'] ?? false;
                        if (!$nameFile) {
                            $blockJson = [
                                'name' => 'test/test',
                            ];
                        }
                    }

                    $levelExplode = explode(Files::getRootFolder(), $path);
                    $level = explode(
                        $levelExplode[count($levelExplode) - 1],
                        $filename
                    )[1];

                    $blockArrFiles = array_values(
                        array_filter(
                            scandir($fileDir),
                            fn($item) => !is_dir($fileDir . '/' . $item) &&
                                $item[0] !== '.'
                        )
                    );
                    $blockArrFilesPaths = array_map(
                        fn($item) => $fileDir . '/' . $item,
                        $blockArrFiles
                    );
                    $blockArrFolders = array_values(
                        array_map(
                            'basename',
                            array_filter(glob(dirname($file) . '/*'), 'is_dir')
                        )
                    );
                    $blockArrStructureArray = explode(
                        '/',
                        str_replace(
                            [
                                '/' . pathinfo($file)['basename'],
                                pathinfo($file)['basename'],
                            ],
                            '',
                            $level
                        )
                    );
                    $blockArrStructureExplode = explode(
                        Files::getRootFolder() . '/',
                        $path
                    );
                    $blockArrStructure = ltrim(
                        explode(
                            $blockArrStructureExplode[
                                count($blockArrStructureExplode) - 1
                            ],
                            $blockJsonPath
                        )[1],
                        '/'
                    );

                    if ($isInit) {
                        $blockJson = [
                            'name' => 'init-' . str_replace('/', '-', $file),
                        ];
                    }

                    $data = [
                        'directory' => $isDir,
                        'example' => $blockJson['example'] ?? false,
                        'extend' => $isExtend,
                        'file' => pathinfo($blockJsonPath),
                        'files' => $blockArrFiles,
                        'filesPaths' => $blockArrFilesPaths,
                        'folders' => $blockArrFolders,
                        'init' => $isInit,
                        'instance' => $instance,
                        'instancePath' => $path,
                        'level' => substr_count($level, '/'),
                        'library' => $library,
                        'name' => $nameFile !== false ? $nameFile : $name,
                        'path' => $blockJsonPath,
                        'previewAssets' =>
                            $blockJson['blockstudio']['editor']['assets'] ?? [],
                        'scopedClass' => 'bs-' . md5($name),
                        'structure' => $blockArrStructure,
                        'structureArray' => $blockArrStructureArray,
                        'twig' => !$isPhp,
                    ];
                    if ($data['name'] === $data['path']) {
                        $data['nameAlt'] = Block::id($data, $data);
                    }

                    if ($editor && $pathinfo['basename'] !== '.') {
                        $data['value'] = file_get_contents($file);
                    }

                    $store[$name] = $data;
                    if ($isOverride && $editor) {
                        self::$dataOverrides[
                            $nameFile !== false ? $nameFile : $name
                        ] = $data;
                    }

                    if (Settings::get('assets/enqueue') || $editor) {
                        $assets = array_filter(
                            $blockArrFiles,
                            fn($e) => Assets::isCss($e) ||
                                Files::endsWith($e, '.js')
                        );
                        $processedAssets = [];

                        foreach ($assets as $asset) {
                            $isCss = Assets::isCss($asset);

                            $assetFn = fn($relative) => $relative
                                ? Files::getRelativeUrl($fileDir . '/' . $asset)
                                : $fileDir . '/' . $asset;

                            $assetFile = pathinfo($assetFn(false));
                            $assetPath = $assetFn(false);
                            $assetUrl = $assetFn(true);

                            if (
                                apply_filters(
                                    'blockstudio/assets/enable',
                                    true,
                                    [
                                        'file' => $assetFile,
                                        'path' => $assetPath,
                                        'url' => $assetUrl,
                                        'type' => $isCss ? 'css' : 'js',
                                    ]
                                ) === false
                            ) {
                                continue;
                            }

                            if (!$editor) {
                                $processedAsset = Assets::process(
                                    $assetPath,
                                    $data['scopedClass']
                                );

                                if (is_array($processedAsset)) {
                                    $processedAssets = array_merge(
                                        $processedAssets,
                                        $processedAsset
                                    );
                                } else {
                                    $processedAssets[] = $processedAsset;
                                }
                            }

                            $id = strtolower(
                                preg_replace('/(?<!^)[A-Z]/', '-$0', $asset)
                            );

                            if (
                                Files::startsWith(
                                    $assetFile['basename'],
                                    'admin'
                                ) &&
                                !$editor
                            ) {
                                self::$assetsAdmin[
                                    sanitize_title($assetPath)
                                ] = [
                                    'path' => $assetPath,
                                    'key' => filemtime($assetFn(false)),
                                ];
                            }

                            if (
                                Files::startsWith(
                                    $assetFile['basename'],
                                    'block-editor'
                                ) &&
                                !$editor
                            ) {
                                self::$assetsBlockEditor[
                                    sanitize_title($assetPath)
                                ] = [
                                    'path' => $assetPath,
                                    'key' => filemtime($assetFn(false)),
                                ];
                            }

                            if (
                                Files::startsWith(
                                    $assetFile['basename'],
                                    'global'
                                ) &&
                                !$editor
                            ) {
                                self::$assetsGlobal[
                                    sanitize_title($assetPath)
                                ] = $assetUrl;
                            }

                            $handle = Assets::getId(
                                $id,
                                $store[$name] ?? ['name' => $name]
                            );
                            $isEditor =
                                Files::endsWith($asset, '-editor.css') ||
                                Files::endsWith($asset, '-editor.scss') ||
                                Files::endsWith($asset, '-editor.js');

                            $store[$name]['assets'][$id] = [
                                'type' =>
                                    Files::endsWith($asset, '-inline.css') ||
                                    Files::endsWith($asset, '-inline.scss') ||
                                    Files::endsWith($asset, '-inline.js') ||
                                    Files::endsWith($asset, '-scoped.css') ||
                                    Files::endsWith($asset, '-scoped.scss')
                                        ? 'inline'
                                        : 'external',
                                'path' => $assetPath,
                                'url' => $assetUrl,
                                'editor' => $isEditor,
                                'instance' => $instance,
                                'file' => $assetFile,
                            ];

                            if (!$editor) {
                                if ($isCss) {
                                    self::$assetsRegister['style'][$handle] = [
                                        'path' => $assetFn(true),
                                        'mtime' => filemtime($assetFn(false)),
                                    ];
                                } else {
                                    self::$assetsRegister['script'][$handle] = [
                                        'path' => $assetFn(true),
                                        'mtime' => filemtime($assetFn(false)),
                                    ];
                                }
                            }
                        }

                        $distFolder = $fileDir . '/_dist';
                        $allProcessedAssets = Files::getFilesRecursivelyAndDeleteEmptyFolders(
                            $distFolder
                        );

                        if (!$editor) {
                            foreach ($allProcessedAssets as $filePath) {
                                if (
                                    !in_array($filePath, $processedAssets) &&
                                    file_exists($filePath)
                                ) {
                                    unlink($filePath);
                                }
                            }

                            foreach ($allProcessedAssets as $filePath) {
                                $directory = dirname($filePath);
                                if (
                                    glob($directory . '/*') !== false &&
                                    count(glob($directory . '/*')) !== 0
                                ) {
                                    continue;
                                }

                                if (is_dir($directory)) {
                                    rmdir($directory);
                                }
                            }

                            if (Files::isDirectoryEmpty($distFolder)) {
                                $emptyDistFolders[] = $distFolder;
                            }
                        }
                    }

                    if (($isBlock || $isOverride || $isExtend) && !$editor) {
                        $nativePath =
                            $isOverride && !$isBlock
                                ? $file
                                : Files::getRenderTemplate($file);

                        $attributes = [];
                        $filteredAttributes = [];
                        if (isset($blockJson['blockstudio']['attributes'])) {
                            if (!$isOverride) {
                                self::filterAttributes(
                                    $blockJson,
                                    $blockJson['blockstudio']['attributes'],
                                    $filteredAttributes
                                );
                            }

                            self::buildAttributes(
                                $blockJson['blockstudio']['attributes'],
                                $attributes,
                                '',
                                false,
                                false,
                                false,
                                $isExtend
                            );
                        }

                        $attributes['blockstudio'] = [
                            'type' => 'object',
                            'default' => [
                                'name' => $blockJson['name'],
                            ],
                        ];

                        $attributes['anchor'] = $isExtend
                            ? [
                                'type' => 'string',
                                'source' => 'attribute',
                                'attribute' => 'id',
                                'selector' => '*',
                            ]
                            : [
                                'type' => 'string',
                            ];

                        $attributes['className'] = [
                            'type' => 'string',
                        ];

                        $block = new WP_Block_Type(
                            $blockJson['name'],
                            $blockJson
                        );
                        $block->api_version = 3;
                        $block->render_callback = [
                            'Blockstudio\Block',
                            'render',
                        ];
                        $block->attributes = array_merge(
                            $blockJson['attributes'] ?? [],
                            $attributes
                        );
                        $block->uses_context = array_merge(
                            ['postId', 'postType'],
                            $blockJson['usesContext'] ?? []
                        );
                        $block->provides_context = array_merge(
                            [
                                $name => 'blockstudio',
                            ],
                            $blockJson['providesContext'] ?? []
                        );
                        $block->path = $nativePath;

                        if (isset($blockJson['variations'])) {
                            $variations = [];
                            foreach ($blockJson['variations'] as $variation) {
                                $variations[] =
                                    [
                                        'attributes' => [
                                            'blockstudio' => [
                                                'attributes' =>
                                                    $variation['attributes'],
                                            ],
                                        ],
                                    ] + $variation;
                            }

                            $block->variations = $variations;
                        }

                        $disableLoading =
                            $blockJson['blockstudio']['blockEditor'][
                                'disableLoading'
                            ] ??
                            (Settings::get('blockEditor/disableLoading') ??
                                false);

                        $block->blockstudio = [
                            'attributes' => $filteredAttributes,
                            'blockEditor' => [
                                'disableLoading' => $disableLoading,
                            ],
                            'conditions' => isset(
                                $block->blockstudio['conditions']
                            )
                                ? $block->blockstudio['conditions']
                                : true,
                            'editor' => isset($block->blockstudio['editor'])
                                ? $block->blockstudio['editor']
                                : false,
                            'extend' => isset($block->blockstudio['extend'])
                                ? $block->blockstudio['extend']
                                : false,
                            'group' => isset($block->blockstudio['group'])
                                ? $block->blockstudio['group']
                                : false,
                            'icon' => isset($block->blockstudio['icon'])
                                ? $block->blockstudio['icon']
                                : null,
                            'refreshOn' => isset(
                                $block->blockstudio['refreshOn']
                            )
                                ? $block->blockstudio['refreshOn']
                                : false,
                            'transforms' => isset(
                                $block->blockstudio['transforms']
                            )
                                ? $block->blockstudio['transforms']
                                : false,
                            'variations' => $block->variations ?? false,
                        ];

                        //						if (($blockJson['blockstudio']['interactivity']['enqueue'] ?? false) && !self::$interactivityApiRendered) {
                        //							add_filter( 'wp_head', function () {
                        //								echo Assets::getInteractivityApiImportMap();
                        //							} );
                        //							add_filter( 'admin_head', function () {
                        //								echo Assets::getInteractivityApiImportMap();
                        //							} );
                        //							self::$interactivityApiRendered = true;
                        //						}

                        if ($isOverride) {
                            self::$overrides[$blockJson['name']] = json_decode(
                                $contents,
                                true
                            );
                            self::$blocksOverrides[$blockJson['name']] = $block;
                        } elseif (!$isExtend) {
                            self::$blocks[$blockJson['name']] = $block;
                        } else {
                            self::$extensions[] = $block;
                        }
                    }
                }
            }
        }

        if ($editor) {
            self::$files = array_merge(self::$files, $store);
            foreach (self::$dataOverrides as $override) {
                foreach ($override['filesPaths'] as $path) {
                    self::$files[$path]['assets'] = array_merge(
                        self::$data[$override['name']]['assets'] ?? [],
                        $override['assets'] ?? []
                    );
                }
            }

            return;
        }

        self::$data = array_merge(self::$data, $store);

        foreach (self::$data as $file) {
            if ($file['init']) {
                include_once $file['path'];
            }
        }

        foreach (self::$blocks as $block) {
            if (self::$overrides[$block->name] ?? false) {
                foreach (self::$overrides[$block->name] as $key => $value) {
                    if ($key === 'blockstudio') {
                        $overrideAttributes = $value['attributes'] ?? [];
                        self::mergeAttributes(
                            $block->blockstudio['attributes'],
                            $overrideAttributes
                        );

                        $overrideBuiltAttributes = [];
                        self::buildAttributes(
                            $overrideAttributes,
                            $overrideBuiltAttributes,
                            '',
                            false,
                            false,
                            true
                        );
                        self::$blocksOverrides[
                            $block->name
                        ]->attributes = $overrideBuiltAttributes;
                        self::mergeAttributes(
                            $block->attributes,
                            $overrideBuiltAttributes
                        );

                        $mappedAttributes = [];
                        foreach ($block->attributes as $name => $attribute) {
                            if (isset($attribute['id'])) {
                                $mappedAttributes[
                                    $attribute['id']
                                ] = $attribute;
                            } else {
                                $mappedAttributes[$name] = $attribute;
                            }
                        }
                        $block->attributes = $mappedAttributes;

                        continue;
                    }

                    $block->{$key} = $value;
                }

                self::$data[$block->name]['assets'] = array_merge(
                    self::$data[$block->name]['assets'] ?? [],
                    self::$data[$block->name . '-override']['assets'] ?? []
                );
            }
        }

        foreach ($emptyDistFolders as $folder) {
            if (is_dir($folder)) {
                rmdir($folder);
            }
        }

        do_action('blockstudio/init');
        do_action("blockstudio/init/$instance");
    }

    /**
     * Convert a path to array.
     *
     * @date   27/02/2022
     * @since  2.3.0
     *
     * @param  $array
     * @param  $path
     * @param  $value
     * @param  string  $delimiter
     *
     * @return mixed
     */
    public static function pathToArray(
        &$array,
        $path,
        $value,
        string $delimiter = '/'
    ) {
        $pathParts = explode($delimiter, $path);

        $current = &$array;
        foreach ($pathParts as $key) {
            $current = &$current[$key];
        }

        $backup = $current;
        $current = $value;

        return $backup;
    }

    /**
     * Recursive sort files.
     *
     * @date   17/06/2023
     * @since  5.0.0
     *
     * @param  $arr
     *
     * @return void
     */
    public static function recursiveSort(&$arr)
    {
        foreach ($arr as &$value) {
            if (is_array($value) && array_key_exists('.', $value)) {
                self::recursiveSort($value);
            }
        }

        uksort($arr, function ($a, $b) use (&$arr) {
            $aIsDir =
                isset($arr[$a]) &&
                is_array($arr[$a]) &&
                array_key_exists('.', $arr[$a]);
            $bIsDir =
                isset($arr[$b]) &&
                is_array($arr[$b]) &&
                array_key_exists('.', $arr[$b]);

            if ($aIsDir && !$bIsDir) {
                return -1;
            } elseif (!$aIsDir && $bIsDir) {
                return 1;
            } else {
                return $a <=> $b;
            }
        });
    }

    /**
     * Get sorted blocks data for the editor.
     *
     * @date   10/02/2022
     * @since  2.3.0
     *
     * @return array
     * @throws  SassException
     */
    public static function dataSorted(): array
    {
        self::files();
        $sorted = [];

        foreach (self::$files as $d) {
            if ($d['library'] && !Settings::get('editor/library')) {
                continue;
            }

            $sorted[$d['instance']]['instance'] = $d['instance'];
            $sorted[$d['instance']]['library'] = $d['library'];
            $sorted[$d['instance']]['path'] = $d['instancePath'];

            self::pathToArray(
                $sorted[$d['instance']]['children'],
                $d['structure'],
                $d
            );

            self::recursiveSort($sorted[$d['instance']]['children']);
        }

        ksort($sorted);

        return $sorted;
    }

    /**
     * Get native blocks data.
     *
     * @date   04/09/2022
     * @since  3.0.0
     *
     * @return array
     */
    public static function blocks(): array
    {
        return self::$blocks;
    }

    /**
     * Get blocks data.
     *
     * @date   10/02/2022
     * @since  2.3.0
     *
     * @return array
     */
    public static function data(): array
    {
        return self::$data;
    }

    /**
     * Get extends data.
     *
     * @date   12/02/2024
     * @since  5.3.3
     *
     * @return array
     */
    public static function extensions(): array
    {
        return self::$extensions;
    }

    /**
     * Get all block files.
     *
     * @date   10/02/2022
     * @since  2.3.0
     *
     * @return array
     * @throws  SassException
     */
    public static function files(): array
    {
        foreach (self::$instances as $instance) {
            self::init([
                'dir' => $instance['path'],
                'library' => $instance['library'],
                'editor' => true,
            ]);
        }

        return self::$files;
    }

    /**
     * Get admin assets.
     *
     * @date   07/05/2024
     * @since  5.5.0
     *
     * @return array
     */
    public static function assetsAdmin(): array
    {
        return self::$assetsAdmin;
    }

    /**
     * Get admin assets.
     *
     * @date   07/05/2024
     * @since  5.5.0
     *
     * @return array
     */
    public static function assetsBlockEditor(): array
    {
        return self::$assetsBlockEditor;
    }

    /**
     * Get global assets.
     *
     * @date   17/06/2023
     * @since  5.0.0
     *
     * @return array
     */
    public static function assetsGlobal(): array
    {
        return self::$assetsGlobal;
    }

    /**
     * Get instance paths.
     *
     * @date   28/07/2022
     * @since  2.5.0
     *
     * @return array
     */
    public static function paths(): array
    {
        return self::$paths;
    }

    /**
     * Get overrides.
     *
     * @date   21/12/2023
     * @since  5.3.0
     *
     * @return array
     */
    public static function overrides(): array
    {
        return self::$blocksOverrides;
    }

    /**
     * Get assets data.
     *
     * @date   20/07/2024
     * @since  5.5.7
     *
     * @return array
     */
    public static function assets(): array
    {
        return self::$assetsRegister;
    }

    /**
     * Blade templates.
     *
     * @date   18/08/2024
     * @since  5.6.0
     *
     * @return array
     */
    public static function blade(): array
    {
        return self::$blade;
    }

    /**
     * Check if Tailwind is active.
     *
     * @date   18/08/2024
     * @since  5.6.0
     *
     * @return bool
     */
    public static function isTailwindActive(): bool
    {
        return self::$isTailwindActive;
    }
}
