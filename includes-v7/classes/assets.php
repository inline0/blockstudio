<?php

namespace Blockstudio;

use BlockstudioVendor\ScssPhp\ScssPhp\Compiler;
use BlockstudioVendor\MatthiasMullie\Minify;
use BlockstudioVendor\ScssPhp\ScssPhp\Exception\SassException;

/**
 * Assets class.
 *
 * @date   04/09/2022
 * @since  3.0.0
 */
class Assets
{
    private static array $modules = [];

    /**
     * Construct.
     *
     * @date   04/09/2022
     * @since  3.0.0
     */
    function __construct()
    {
        add_action('template_redirect', [$this, 'maybeBufferOutput'], 3);
        add_filter(
            'blockstudio/buffer/output',
            [$this, 'parseOutput'],
            1000000
        );
        add_action('admin_footer', function () {
            $this->getAssets();
        });
        add_action('customize_preview_init', function () {
            $this->getAssets('customizer');
        });
        add_action('admin_init', function () {
            $this->getAdminAndEditorAssets();
        });
    }

    /**
     * Maybe return.
     *
     * @date   04/09/2022
     * @since  3.0.0
     */
    function maybeBufferOutput()
    {
        if (function_exists('is_customize_preview') && is_customize_preview()) {
            return false;
        }

        if (is_admin()) {
            return false;
        }

        ob_start([$this, 'returnBuffer']);
    }

    /**
     * Parse output and return assets.
     *
     * @date   04/09/2022
     * @since  3.0.0
     *
     * @param  $html
     *
     * @return string
     */
    function parseOutput($html): string
    {
        $blocks = Build::data();
        $blocksNative = Build::blocks();
        $ids = [];
        $blocksOnPage = [];
        $assetIds = [];

        $stylePattern =
            '/<style[^>]+data-blockstudio-asset[^>]*>(.*?)<\/style>/is';
        $scriptPattern =
            '/<script[^>]+data-blockstudio-asset[^>]*>(.*?)<\/script>/is';
        preg_match_all($stylePattern, $html, $styleMatches);
        $head = implode('', $styleMatches[0]);
        $html = preg_replace($stylePattern, '', $html);
        preg_match_all($scriptPattern, $html, $scriptMatches);
        $footer = implode('', $scriptMatches[0]);
        $html = preg_replace($scriptPattern, '', $html);

        foreach ($blocks as $block) {
            $id = Block::comment($block['name']);
            $ids[] = $id;

            if (stripos($html, $id) !== false) {
                $blocksOnPage[$block['name']] = $blocksNative[$block['name']];
            }

            if (!isset($block['assets'])) {
                continue;
            }

            $hasGlobal = array_reduce(
                array_keys($block['assets']),
                function ($carry, $key) {
                    return $carry || strpos($key, 'global') === 0;
                },
                false
            );

            if (strpos($html, $id) === false && !$hasGlobal) {
                continue;
            }

            self::getModuleCssAssets($block, $assetIds, $head);

            foreach ($block['assets'] as $k => $v) {
                $isAdmin = Files::startsWith($k, 'admin');
                $isBlockEditor = Files::startsWith($k, 'block-editor');

                if ($isAdmin || $isBlockEditor) {
                    continue;
                }

                $isGlobal = Files::startsWith($k, 'global');
                if (strpos($html, $id) === false && !$isGlobal) {
                    continue;
                }

                $assetId = $v['path'];
                if (in_array($assetId, $assetIds)) {
                    continue;
                }
                $assetIds[] = $assetId;

                if ($v['editor']) {
                    continue;
                }

                if ($v['type'] !== 'inline') {
                    if (self::isCss($k)) {
                        $head .= self::renderTag($k, $v, $block);
                    } else {
                        $footer .= self::renderTag($k, $v, $block);
                    }
                } else {
                    if (self::isCss($k)) {
                        $head .= self::renderInline($k, $v, $block, true);
                    } else {
                        $footer .= self::renderInline($k, $v, $block, true);
                    }
                }
            }
        }

        $head = apply_filters('blockstudio/render/head', $head, $blocksOnPage);
        $footer = apply_filters(
            'blockstudio/render/footer',
            $footer,
            $blocksOnPage
        );

        $output = strtr(str_replace($ids, '', $html), [
            '</body>' => $footer . '</body>',
            '</head>' => $head . '</head>',
            '</BODY>' => $footer . '</BODY>',
            '</HEAD>' => $head . '</HEAD>',
        ]);

        return apply_filters('blockstudio/render', $output, $blocksOnPage);
    }

    /**
     * Get Interactivity API.
     *
     * @date   07/05/2025
     * @since  6.0.0
     */
    public static function getInteractivityApiImportMap()
    {
        $string =
            '<script type="importmap"> { "imports": { "@wordpress/interactivity": "@path/@wordpress/interactivity/build-module/index.js", "preact": "@path/preact/dist/preact.module.js", "preact/hooks": "@path/preact/hooks/dist/hooks.module.js", "@preact/signals": "@path/@preact/signals/dist/signals.module.js", "@preact/signals-core": "@path/@preact/signals-core/dist/signals-core.module.js" } } </script>';
        $path = plugin_dir_url(__FILE__) . '../assets/interactivity';

        return str_replace('@path', $path, $string);
    }

    /**
     * Get admin and editor assets.
     *
     * @date   07/05/2024
     * @since  5.5.0
     */
    public static function getAdminAndEditorAssets()
    {
        $adminAssets = Build::assetsAdmin();
        foreach ($adminAssets as $asset) {
            add_action('admin_enqueue_scripts', function () use ($asset) {
                $path = self::getPath($asset['path']);
                $url = Files::getRelativeUrl($path);

                if (Assets::isCss($url)) {
                    wp_enqueue_style(
                        Assets::getId('admin', [
                            'name' => Block::id($asset, $asset),
                        ]),
                        $url,
                        [],
                        $asset['key']
                    );
                } else {
                    wp_enqueue_script(
                        Assets::getId('admin', [
                            'name' => Block::id($asset, $asset),
                        ]),
                        $url,
                        [],
                        $asset['key']
                    );
                }
            });
        }

        $blockEditorAssets = Build::assetsBlockEditor();
        foreach ($blockEditorAssets as $asset) {
            add_action('enqueue_block_editor_assets', function () use ($asset) {
                $path = self::getPath($asset['path']);
                $url = Files::getRelativeUrl($path);

                if (Assets::isCss($url)) {
                    wp_enqueue_style(
                        Assets::getId('block-editor', [
                            'name' => Block::id($asset, $asset),
                        ]),
                        $url,
                        [],
                        $asset['key']
                    );
                } else {
                    wp_enqueue_script(
                        Assets::getId('block-editor', [
                            'name' => Block::id($asset, $asset),
                        ]),
                        $url,
                        [],
                        $asset['key']
                    );
                }
            });
        }
    }

    /**
     * Get imported modification times.
     *
     * @date   04/11/2023
     * @since  5.2.12
     *
     * @param  $path
     * @param  $scopedClass
     *
     * @return string
     */
    public static function getImportedModificationTimes(
        $path,
        $scopedClass
    ): string {
        $mtimes = [filemtime($path)];

        if ($scopedClass !== '') {
            $mtimes[] = $scopedClass;
        }

        if (Files::endsWith($path, '.js') || !self::shouldProcessScss($path)) {
            return $mtimes[0];
        }

        $content = file_get_contents($path);
        preg_match_all(
            '/@import\s*([\'"])(.*?)(?<!\\\\)\1/',
            $content,
            $matches
        );

        foreach (
            apply_filters('blockstudio/assets/process/scss/importPaths', [])
            as $importPath
        ) {
            if (file_exists($importPath)) {
                $mtimes[] = filemtime($importPath);
            }
        }

        foreach ($matches[2] as $import) {
            $importPath = dirname($path) . '/' . $import;

            if (file_exists($importPath)) {
                $mtimes[] = filemtime($importPath);
            }
        }

        if (count($mtimes) === 1) {
            return $mtimes[0];
        }

        return md5(implode('-', $mtimes));
    }

    /**
     * Get a compiled asset file name.
     *
     * @date   15/10/2022
     * @since  3.0.8
     *
     * @param  $path
     * @param  string  $scopedClass
     *
     * @return string
     */
    public static function getCompiledFilename(
        $path,
        string $scopedClass = ''
    ): string {
        $file = pathinfo($path);
        $dir = $file['dirname'];
        $file = $file['filename'];

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $id = self::getImportedModificationTimes(
            $path,
            Files::endsWith($file, '-scoped') ? $scopedClass : ''
        );

        if (Settings::get('assets/process/scssFiles') && $ext === 'scss') {
            $ext = 'css';
        }

        return $dir . '/_dist/' . $file . '-' . $id . '.' . $ext;
    }

    /**
     * Get all matches for a compiled asset name.
     *
     * @date   16/10/2022
     * @since  3.0.8
     *
     * @param  $path
     *
     * @return array
     */
    public static function getMatches($path): array
    {
        $file = pathinfo($path);
        $dir = $file['dirname'] . '/_dist';
        $name = $file['filename'];
        $ext = $file['extension'];

        if (Settings::get('assets/process/scssFiles') && $ext === 'scss') {
            $ext = 'css';
        }

        $allFiles = glob($dir . '/*.' . $ext);

        $matchedFiles = preg_grep(
            '/^' .
                preg_quote($dir . '/' . $name, '/') .
                '-(?:[a-f0-9]{32}|[0-9]+)' .
                '\.' .
                $ext .
                "$/",
            $allFiles
        );

        return array_values($matchedFiles);
    }

    /**
     * Get unique ID of a block.
     *
     * @date   05/09/2022
     * @since  3.0.0
     *
     * @param  $type
     * @param  $block
     *
     * @return string
     */
    public static function getId($type, $block): string
    {
        $name = $block['nameAlt'] ?? $block['name'];

        return str_replace(['/', '.', ' '], '-', "blockstudio-$name-$type");
    }

    /**
     * Get the path of a compiled asset name if it exists,
     * otherwise return the normal path.
     *
     * @date   15/10/2022
     * @since  3.0.8
     *
     * @param  $path
     *
     * @return string
     */
    public static function getPath($path): string
    {
        $match = self::getMatches($path);

        if (count($match) === 1) {
            return $match[0];
        }

        return $path;
    }

    /**
     * Get CSS compiler.
     *
     * @date   18/10/2023
     * @since  5.2.10
     *
     * @param  string  $path
     *
     * @return Compiler
     */
    public static function getScssCompiler(string $path): Compiler
    {
        $compiler = new Compiler();

        if ($path !== '') {
            $importPath = pathinfo($path, PATHINFO_DIRNAME);
            $compiler->setImportPaths($importPath);
        }

        foreach (
            apply_filters('blockstudio/assets/process/scss/importPaths', [])
            as $iPath
        ) {
            if (!is_dir($iPath)) {
                continue;
            }
            $compiler->addImportPath(function ($path) use ($iPath) {
                return $iPath . '/' . $path;
            });
        }

        return $compiler;
    }

    /**
     * Get all assets for a preview window in Gutenberg.
     *
     * @date   29/11/2022
     * @since  3.1.0
     *
     * @param  $block
     * @param  bool  $styles
     *
     * @return string
     */
    public static function getPreviewAssets($block, bool $styles = true): string
    {
        $style = '';
        $script = '';

        foreach ($block['assets'] ?? [] as $k => $v) {
            if ($v['type'] !== 'inline') {
                if (strpos($k, 'style') !== false) {
                    $style .= self::renderTag($k, $v, $block);
                } else {
                    $script .= self::renderTag($k, $v, $block);
                }
            } else {
                $k = str_replace('-inline', '', $k);

                if (strpos($k, 'style') !== false) {
                    $style .= self::renderInline($k, $v, $block, true);
                } else {
                    $script .= self::renderInline($k, $v, $block, true);
                }
            }
        }

        return $styles ? $style : $script;
    }

    /**
     * Get module CSS assets.
     *
     * @date   11/11/2023
     * @since  5.2.16
     */
    public static function getModuleCssAssets($block, &$assetIds, &$element)
    {
        foreach (
            Files::getFilesWithExtension(
                $block['file']['dirname'] . '/_dist/modules',
                'css'
            )
            as $filename
        ) {
            $file = pathinfo($filename);
            if (in_array($file['filename'], $assetIds)) {
                continue;
            }
            $assetIds[] = $file['filename'];

            $element .= self::renderTag(
                $file['basename'],
                [
                    'editor' => false,
                    'file' => $file,
                    'path' => $filename,
                    'type' => 'external',
                    'url' => Files::getRelativeUrl($filename),
                ],
                $block
            );
        }
    }

    /**
     * Get assets.
     *
     * @date   04/09/2022
     * @since  3.0.0
     */
    public static function getAssets($type = 'editor')
    {
        if ($type === 'editor' && !self::isEditorScreen()) {
            return;
        }

        $blocks = Build::data();

        $footer = '';
        $editorAssets = [];
        $assetIds = [];

        foreach ($blocks as $block) {
            if (isset($block['assets'])) {
                foreach ($block['assets'] as $k => $v) {
                    if (
                        strpos(
                            $k,
                            $type === 'customizer' ? 'editor' : 'view'
                        ) !== false
                    ) {
                        continue;
                    }

                    if (preg_match('/-editor\.(css|scss|js)$/', $k)) {
                        $editorAssets[] = [$k, $v, $block];
                        continue;
                    }

                    if ($type === 'customizer') {
                        if ($v['type'] !== 'inline') {
                            $footer .= self::renderTag($k, $v, $block);
                        } else {
                            $footer .= self::renderInline($k, $v, $block, true);
                        }
                    } else {
                        if (self::isCssExtension($v['file']['extension'])) {
                            $footer .= self::renderInline(
                                $k,
                                $v,
                                $block,
                                true,
                                true
                            );
                        } else {
                            $footer .= self::renderInline($k, $v, $block, true);
                        }
                    }

                    self::getModuleCssAssets($block, $assetIds, $footer);
                }
            }
        }

        foreach ($editorAssets as list($k, $v, $block)) {
            if ($type === 'customizer') {
                if ($v['type'] !== 'inline') {
                    $footer .= self::renderTag($k, $v, $block);
                } else {
                    $footer .= self::renderInline($k, $v, $block, true);
                }
            } else {
                if (self::isCssExtension($v['file']['extension'])) {
                    $footer .= self::renderInline($k, $v, $block, true, true);
                } else {
                    $footer .= self::renderInline($k, $v, $block, true);
                }
            }
        }

        echo $footer;
    }

    /**
     * Check if the editor screen is currently active.
     *
     * @date   22/08/2023
     * @since  5.2.0
     *
     * @return bool
     */
    public static function isEditorScreen(): bool
    {
        global $current_screen;
        if (function_exists('get_current_screen')) {
            $current_screen = get_current_screen();
        }

        if (!$current_screen) {
            return false;
        }

        return method_exists($current_screen, 'is_block_editor') &&
            $current_screen->is_block_editor();
    }

    /**
     * Check if the path is a CSS file.
     *
     * @date   22/08/2023
     * @since  5.2.0
     *
     * @param  $path
     *
     * @return bool
     */
    public static function isCss($path): bool
    {
        return Files::endsWith($path, '.css') ||
            Files::endsWith($path, '.scss');
    }

    /**
     * Check if a path ends with a CSS extension.
     *
     * @date   22/08/2023
     * @since  5.2.0
     *
     * @param  $ext
     *
     * @return bool
     */
    public static function isCssExtension($ext): bool
    {
        return $ext === 'css' || $ext === 'scss';
    }

    /**
     * Prefix CSS.
     *
     * @date   18/11/2021
     * @since  2.1.2
     *
     * @param  $css
     * @param  $prefix
     *
     * @return string
     */
    public static function prefixCss($css, $prefix): string
    {
        $data = "$prefix { $css }";

        return self::compileScss($data, '');
    }

    /**
     * Prefix editor styles, similar to WordPress add_editor_style function.
     *
     * @date   24/08/2022
     * @since  5.2.0
     *
     * @param  $css
     *
     * @return string
     */
    public static function prefixEditorStyles($css): string
    {
        $css = self::prefixCss($css, '.editor-styles-wrapper');
        $css = preg_replace(
            '/\bbody(?=[\s{,]|$)/',
            '.editor-styles-wrapper',
            $css
        );
        $css = str_replace('.editor-styles-wrapper :root', ':root', $css);

        return str_replace(
            '.editor-styles-wrapper .editor-styles-wrapper',
            '.editor-styles-wrapper',
            $css
        );
    }

    /**
     * Compile CSS.
     *
     * @date   18/10/2023
     * @since  5.2.10
     *
     * @param  string  $scss
     * @param  string  $path
     *
     * @return Compiler|string
     */
    public static function compileScss(string $scss, string $path): string
    {
        $compiler = self::getScssCompiler($path);
        try {
            return $compiler->compileString($scss)->getCss();
        } catch (SassException $e) {
            return '';
        }
    }

    /**
     * Should process SCSS.
     *
     * @date   04/11/2023
     * @since  5.2.12
     *
     * @param  $path
     *
     * @return bool
     */
    public static function shouldProcessScss($path): bool
    {
        $isScssExt =
            Files::endsWith($path, '.scss') &&
            Settings::get('assets/process/scssFiles');

        return Settings::get('assets/process/scss') || $isScssExt;
    }

    /**
     * Transform CSS assets and print to file.
     *
     * @date   22/08/2023
     * @since  5.2.0
     *
     * @param  $path
     * @param  $distFolder
     * @param  $scopedClass
     *
     * @return string|void
     */
    public static function processCss($path, $distFolder, $scopedClass)
    {
        $file = pathinfo($path);
        $filename = $file['filename'];

        $minifyCss = Settings::get('assets/minify/css');
        $processScss = self::shouldProcessScss($path);
        $scopeCss = Files::endsWith($filename, '-scoped');
        $compiledFilename = self::getCompiledFilename($path, $scopedClass);

        if (
            file_exists($compiledFilename) &&
            ($minifyCss || $processScss || $scopeCss)
        ) {
            return $compiledFilename;
        }

        if (!$minifyCss && !$processScss && !$scopeCss) {
            return;
        }

        if ($minifyCss || $processScss || $scopeCss) {
            $data = apply_filters(
                'blockstudio/assets/process/css/content',
                file_get_contents($path)
            );

            if ($processScss) {
                $data = self::compileScss($data, $path);
            }

            if ($scopeCss) {
                $data = self::prefixCss($data, '.' . $scopedClass);
            }

            if ($minifyCss) {
                $minifier = new Minify\CSS();
                $minifier->add($data);
                $data = $minifier->minify();
            }

            if (!is_dir($distFolder)) {
                mkdir($distFolder);
            }

            file_put_contents($compiledFilename, $data);

            return $compiledFilename;
        }
    }

    /**
     * Transform JS assets and print to file.
     *
     * @date   22/08/2023
     * @since  5.2.0
     *
     * @param  $path
     * @param  $distFolder
     *
     * @return array|void
     */
    public static function processJs($path, $distFolder)
    {
        $pathinfo = pathinfo($path);
        $minifyJs = Settings::get('assets/minify/js');
        $data = apply_filters(
            'blockstudio/assets/process/js/content',
            file_get_contents($path)
        );
        $compiledFilename = self::getCompiledFilename($path);

        $cssModules = ESModulesCSS::fetch_all_modules_and_write_to_file(
            $data,
            $pathinfo['dirname']
        );
        $hasCssModules = count($cssModules['objects']) >= 1;

        if ($hasCssModules) {
            $data = ESModulesCSS::replace_module_references($data);
        }

        $esModules = ESModules::fetch_all_modules_and_write_to_file(
            $data,
            $pathinfo['dirname']
        );
        $hasEsModules = count($esModules['objects']) >= 1;

        if ($hasEsModules) {
            foreach ($esModules['objects'] as $module) {
                $name = $module['name'];
                $version = $module['version'];
                $nameTransformed = $module['nameTransformed'];
                $data = str_replace(
                    "blockstudio/$name@$version",
                    "./modules/$nameTransformed/$version.js",
                    $data
                );
            }
        }

        if (
            file_exists($compiledFilename) &&
            ($minifyJs || $hasEsModules || $hasCssModules)
        ) {
            return array_merge(
                $esModules['filenames'],
                $cssModules['filenames'],
                [$compiledFilename]
            );
        }

        if ($minifyJs) {
            $minifier = new Minify\JS();
            $minifier->add($data);
            $data = $minifier->minify();
        }

        if (
            !file_exists($compiledFilename) &&
            ($minifyJs || $hasEsModules || $hasCssModules)
        ) {
            if (!is_dir($distFolder)) {
                mkdir($distFolder);
            }

            file_put_contents($compiledFilename, $data);

            return array_merge(
                $esModules['filenames'],
                $cssModules['filenames'],
                [$compiledFilename]
            );
        }
    }

    /**
     * Transform assets.
     *
     * @date   08/08/2023
     * @since  5.2.0
     *
     * @param  $path
     * @param  string  $scopedClass
     *
     * @return array|string|void|null
     */
    public static function process($path, string $scopedClass)
    {
        $pathinfo = pathinfo($path);
        $ext = $pathinfo['extension'];
        $distFolder = $pathinfo['dirname'] . '/_dist';

        if (self::isCssExtension($ext)) {
            return self::processCss($path, $distFolder, $scopedClass);
        }

        if ($ext === 'js') {
            return self::processJs($path, $distFolder);
        }
    }

    /**
     * Render inline asset.
     *
     * @date   18/11/2021
     * @since  2.1.2
     *
     * @param  $type
     * @param  $data
     * @param  $block
     * @param  bool  $return
     * @param  bool  $prefix
     *
     * @return string|void|null
     */
    public static function renderInline(
        $type,
        $data,
        $block,
        $return = false,
        $prefix = false
    ) {
        $id = self::getId($type, $block);

        if (
            in_array($id, apply_filters('blockstudio/assets/disable', [])) &&
            $return !== 'gutenberg'
        ) {
            return null;
        }

        $tag = Files::endsWith($type, '.js') ? 'script' : 'style';
        $isScript = Files::endsWith($type, '.js');
        $isPrefix = $prefix && !$isScript;

        $processedString = '';
        $key = '';
        if ($return !== 'gutenberg') {
            $path = self::getPath($data['path']);
            $isProcessed = count(self::getMatches($data['path'])) === 1;
            $processedString = $isProcessed ? 'data-processed' : '';

            if (Files::endsWith($path, '.scss')) {
                return null;
            }

            $contents = file_get_contents($path);
            $key = "data-key='" . filemtime($path) . "'";
        } else {
            $contents = $data;
        }

        if ($isPrefix) {
            $contents = self::prefixEditorStyles($contents);
        }

        if ($isScript) {
            preg_match_all(
                "/[\"'](.\/modules\/)([a-zA-Z0-9.-@_-]*)[\"']/",
                $contents,
                $modules
            );

            foreach ($modules[2] as $module) {
                $name = explode('/', $module)[0];
                $version = str_replace('.js', '', explode('/', $module)[1]);
                $modulePath =
                    $block['file']['dirname'] .
                    '/_dist/modules/' .
                    $name .
                    '/' .
                    $version .
                    '.js';
                $moduleId = $name . '-' . $version;

                if (file_exists($modulePath)) {
                    if (!isset(self::$modules[$moduleId])) {
                        self::$modules[$moduleId] = Files::getRelativeUrl(
                            $modulePath
                        );
                    }
                    $contents = preg_replace(
                        "/[\"'](.\/modules\/)([a-zA-Z0-9.-@_-]*)[\"']/",
                        '"' . self::$modules[$moduleId] . '"',
                        $contents,
                        1
                    );
                }
            }
        }

        $type = $tag === 'script' ? 'type="module"' : '';
        $string =
            "<$tag id='$id' $processedString $type $key>" .
            $contents .
            "</$tag>";

        if ($return) {
            return $return === 'gutenberg' ? $contents : $string;
        }

        echo $string;
    }

    /**
     * Render tag asset.
     *
     * @date   05/09/2022
     * @since  3.0.0
     *
     * @param  $type
     * @param  $data
     * @param  $block
     *
     * @return string|null
     */
    public static function renderTag($type, $data, $block): ?string
    {
        $id = self::getId($type, $block);

        if (in_array($id, apply_filters('blockstudio/assets/disable', []))) {
            return null;
        }

        $path = $data['path'];
        $maybeCompiledPath = self::getPath($path);

        if (filesize($maybeCompiledPath) === 0) {
            return null;
        }

        $src = Files::getRelativeUrl($maybeCompiledPath);
        $key = filemtime($path);
        $processed =
            count(self::getMatches($path)) === 1 ? 'data-processed' : '';

        if (self::isCss($type)) {
            if (Files::endsWith($src, '.scss')) {
                return null;
            }

            return "<link rel='stylesheet' $processed id='$id' href='$src?ver=$key'>";
        }

        return "<script type='module' $processed id='$id' src='$src?ver=$key'></script>";
    }

    /**
     * Render code field assets.
     *
     * @date   07/05/2024
     * @since  5.5.0
     *
     * @param  $attributeData
     * @param  string  $key
     *
     * @return string|null
     */
    public static function renderCodeFieldAssets(
        $attributeData,
        string $key = 'assets'
    ): ?string {
        $assetsString = '';
        foreach ($attributeData[$key] as $asset) {
            $type = $asset['language'] ?? 'html';

            if ($type === 'javascript') {
                $assetsString .=
                    '<script id="' .
                    $attributeData['selectorAttributeId'] .
                    '-' .
                    uniqid() .
                    '" data-blockstudio-asset>' .
                    $asset['value'] .
                    '</script>';
            }
            if ($type === 'css') {
                $assetsString .=
                    '<style id="' .
                    $attributeData['selectorAttributeId'] .
                    '-' .
                    uniqid() .
                    '" data-blockstudio-asset>' .
                    $asset['value'] .
                    '</style>';
            }
        }

        return $assetsString;
    }

    /**
     * Return buffer.
     *
     * @date   04/09/2022
     * @since  3.0.0
     *
     * @param  $html
     *
     * @return mixed|null
     */
    public static function returnBuffer($html)
    {
        if (!$html) {
            return $html;
        }

        return apply_filters('blockstudio/buffer/output', $html);
    }
}
