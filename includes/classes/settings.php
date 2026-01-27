<?php

namespace Blockstudio;

/**
 * Settings.
 *
 * @date   13/08/2023
 * @since  5.2.0
 */
class Settings
{
    protected static array $defaults = [
        'builderDeprecated' => [
            'enabled' => false,
        ],
        'users' => [
            'ids' => [],
            'roles' => [],
        ],
        'assets' => [
            'enqueue' => true,
            'minify' => [
                'css' => false,
                'js' => false,
            ],
            'process' => [
                'scss' => false,
                'scssFiles' => true,
            ],
        ],
        'editor' => [
            'formatOnSave' => false,
            'library' => false,
            'assets' => [],
            'markup' => false,
        ],
        'tailwind' => [
            'enabled' => false,
            'customClasses' => [],
        ],
        'blockEditor' => [
            'disableLoading' => false,
            'cssClasses' => [],
            'cssVariables' => [],
        ],
        'library' => false,
        'ai' => [
            'enableContextGeneration' => false,
        ],
    ];

    private static ?Settings $instance = null;
    protected static array $settings = [];
    protected static array $settingsOptions = [];
    protected static $settingsJson = null;
    protected static array $settingsFilters = [];
    protected static array $settingsFiltersValues = [];

    /**
     * Get instance
     *
     * @date   13/08/2023
     * @since  5.2.0
     */
    public static function getInstance(): ?Settings
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Construct.
     *
     * @date   13/08/2023
     * @since  5.2.0
     */
    function __construct()
    {
        static::$settings = static::$defaults;
        static::$settingsFilters = static::$defaults;
        static::$settingsFilters['assets']['enqueue'] = false;
        static::$settingsFilters['assets']['process']['scssFiles'] = false;
        if (!self::json()) {
            static::$settingsOptions = static::$defaults;
            $this->loadSettingsFromOptions();
        }
        $this->migrateSettingsFromOldVersion(static::$settings);
        $this->migrateSettingsFromOldVersion(static::$settingsFilters);
        if (self::json()) {
            static::$settingsJson = static::$defaults;
            $this->loadSettingsFromJson();
        }
        $this->loadSettingsFromFilters();
    }

    /**
     * Migrate from 5.1.1.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @param  $settings
     *
     * @TODO   : Remove in 6.0.0
     */
    protected function migrateSettingsFromOldVersion(&$settings)
    {
        if (has_filter('blockstudio/library')) {
            $library = apply_filters('blockstudio/library', false);
            $settings['library'] = $library;
        }

        if (has_filter('blockstudio/assets')) {
            $assets = apply_filters('blockstudio/assets', true);
            $settings['assets']['enqueue'] = $assets;
        }

        if (has_filter('blockstudio/editor/library')) {
            $editorLibrary = apply_filters('blockstudio/editor/library', false);
            $settings['editor']['library'] = $editorLibrary;
        }

        if (has_filter('blockstudio/editor/assets')) {
            $editorAssets = apply_filters('blockstudio/editor/assets', false);
            $settings['editor']['assets'] = $editorAssets;
        }

        if (has_filter('blockstudio/editor/markup')) {
            $editorMarkup = apply_filters('blockstudio/editor/markup', false);
            $settings['editor']['markup'] = $editorMarkup;
        }

        if (has_filter('blockstudio/editor/users')) {
            $editorUserIds = apply_filters('blockstudio/editor/users', false);
            $settings['users']['ids'] = $editorUserIds;
        }

        if (has_filter('blockstudio/editor/users/roles')) {
            $editorUserRoles = apply_filters(
                'blockstudio/editor/users/roles',
                false
            );
            $settings['users']['roles'] = $editorUserRoles;
        }

        if (has_filter('blockstudio/editor/options')) {
            $editorOptions = apply_filters('blockstudio/editor/options', false);
            $formatOnSave = $editorOptions['formatOnSave'] ?? false;
            $processorScss = $editorOptions['processorScss'] ?? false;
            $processorEsbuild = $editorOptions['processorEsbuild'] ?? false;

            if ($formatOnSave) {
                $settings['editor']['formatOnSave'] = $formatOnSave;
            }
            if ($processorScss) {
                $settings['assets']['minify']['css'] = $processorScss;
                $settings['assets']['process']['scss'] = $processorScss;
            }
            if ($processorEsbuild) {
                $settings['assets']['minify']['js'] = $processorEsbuild;
            }
        }
    }

    /**
     * Object to array.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @param  $data
     *
     * @return array|mixed
     */
    protected function objectToArray($data)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        if (is_array($data)) {
            return array_map([$this, 'objectToArray'], $data);
        }

        return $data;
    }

    /**
     * Load settings from the options table.
     *
     * @date   13/08/2023
     * @since  5.2.0
     */
    protected function loadSettingsFromOptions()
    {
        $options = $this->objectToArray(get_option('blockstudio_settings', []));
        if (!is_array($options)) {
            $options = [];
        }
        static::$settings = $this->arrayDeepMerge(static::$settings, $options);
        static::$settingsOptions = $this->arrayDeepMerge(
            static::$settings,
            $options
        );
    }

    /**
     * Get JSON path.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @return string
     */
    public static function jsonPath(): string
    {
        return apply_filters(
            'blockstudio/settings/path',
            get_stylesheet_directory() . '/blockstudio.json'
        );
    }

    /**
     * Get JSON.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @return bool|string
     */
    public static function json()
    {
        if (file_exists(self::jsonPath())) {
            return self::jsonPath();
        }

        return false;
    }

    /**
     * Load settings from the blockstudio.json file.
     *
     * @date   13/08/2023
     * @since  5.2.0
     */
    protected function loadSettingsFromJson()
    {
        $this->mergeJsonSettingsFromPath(self::$settings);
        $this->mergeJsonSettingsFromPath(self::$settingsJson);
    }

    /**
     * Merge JSON settings from path.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @param  $settings
     */
    private function mergeJsonSettingsFromPath(&$settings)
    {
        $jsonData = file_get_contents(self::json());
        $jsonSettings = json_decode($jsonData, true);
        if ($jsonSettings && is_array($jsonSettings)) {
            $settings = $this->arrayDeepMerge($settings, $jsonSettings);
        }
    }

    /**
     * Recursively merge arrays.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @param  array  $base   The base array.
     * @param  array  $merge  The array to merge with base.
     *
     * @return array
     */
    protected function arrayDeepMerge(array $base, array $merge): array
    {
        foreach ($merge as $key => $value) {
            if (
                isset($base[$key]) &&
                is_array($base[$key]) &&
                is_array($value)
            ) {
                $base[$key] = $this->arrayDeepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Load settings by applying filters.
     *
     * @date   13/08/2023
     * @since  5.2.0
     */
    protected function loadSettingsFromFilters()
    {
        $this->applySettingsFilters(static::$settings);
        $this->applySettingsFilters(static::$settingsFilters);
    }

    /**
     * Apply settings filters.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @param  array  $settings
     * @param  array  $path
     */
    protected function applySettingsFilters(array &$settings, array $path = [])
    {
        foreach ($settings as $key => &$value) {
            $currentPath = array_merge($path, [$key]);

            if (is_array($value) && count($value) !== 0) {
                $this->applySettingsFilters($value, $currentPath);
            } else {
                $filterName =
                    'blockstudio/settings/' . implode('/', $currentPath);
                $value = apply_filters($filterName, $value);
                if (has_filter($filterName)) {
                    static::$settingsFiltersValues[$filterName] = $value;
                }
            }
        }
    }

    /**
     * Get a setting value.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @param  string  $key
     * @param  mixed   $default
     *
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = static::fetchValueFromKey($key, static::$settings);

        if ($value === null) {
            $value = static::fetchValueFromKey($key, static::$defaults);
        }

        if ($value === null) {
            $value = $default;
        }

        if (has_filter('blockstudio/settings/' . $key)) {
            return apply_filters('blockstudio/settings/' . $key, $value);
        }

        return $value;
    }

    /**
     * Fetch value from key.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @param  string  $key
     * @param  array   $array
     *
     * @return mixed
     */
    private static function fetchValueFromKey(string $key, array $array)
    {
        $keys = explode('/', $key);
        $temp = $array;

        foreach ($keys as $segment) {
            if (!isset($temp[$segment])) {
                return null;
            }
            $temp = $temp[$segment];
        }

        return $temp;
    }

    /**
     * Get all setting values.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @return array
     */
    public static function getAll(): array
    {
        return static::$settings;
    }

    /**
     * Get options setting values.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @return array
     */
    public static function getOptions(): array
    {
        return static::$settingsOptions;
    }

    /**
     * Get JSON setting values.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @return array|null
     */
    public static function getJson(): ?array
    {
        return static::$settingsJson;
    }

    /**
     * Get filters setting values.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @return array
     */
    public static function getFilters(): array
    {
        return static::$settingsFilters;
    }

    /**
     * Get filters setting values.
     *
     * @date   19/010/2023
     * @since  5.2.10
     *
     * @return array
     */
    public static function getFiltersValues(): array
    {
        return static::$settingsFiltersValues;
    }

    /**
     * Get schema.
     *
     * @date   13/08/2023
     * @since  5.2.0
     *
     * @return array
     */
    public static function getSchema(): array
    {
        return json_decode(
            file_get_contents(
                BLOCKSTUDIO_DIR . '/includes/schemas/blockstudio.json'
            ),
            true
        );
    }
}

foreach (['blockstudio/init/before', 'init'] as $hook) {
    add_action($hook, function () {
        Settings::getInstance();
    });
}
