<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Agents;

use Blockstudio\PHPStan\Theme\AnalysisConfiguration;
use Blockstudio\PHPStan\Theme\ProjectScanner;

/**
 * Describes one project from its configuration and the files it really has.
 */
final class ProjectProfile
{
    private const DEFAULT_PRESET = 'base';

    private const SURFACES = [
        ['label' => 'Blocks', 'file' => 'block.json'],
        ['label' => 'Reusable fields', 'file' => 'field.json'],
        ['label' => 'Pages', 'file' => 'page.json'],
        ['label' => 'Page collections', 'file' => 'pages.json'],
        ['label' => 'Patterns', 'file' => 'pattern.json'],
        ['label' => 'Site Editor templates', 'file' => 'template.json'],
        ['label' => 'Site Editor template parts', 'file' => 'part.json'],
        ['label' => 'Database schemas', 'file' => 'db.php'],
        ['label' => 'RPC endpoints', 'file' => 'rpc.php'],
        ['label' => 'Cron definitions', 'file' => 'cron.php'],
    ];

    private const TEMPLATES = [
        'index.php' => 'PHP',
        'index.twig' => 'Twig',
        'index.blade.php' => 'Blade',
        'index.html' => 'HTML',
    ];

    /** @var array<string, list<string>> */
    private array $named = [];

    /** @var list<string>|null */
    private ?array $extensions = null;

    /**
     * @param array<string, mixed> $configuration
     * @param list<string> $roots
     * @param list<string> $excludes
     */
    private function __construct(
        private readonly string $root,
        private readonly ?string $configurationPath,
        private readonly array $configuration,
        private readonly string $preset,
        private readonly array $roots,
        private readonly array $excludes,
        private readonly ProjectScanner $scanner
    ) {}

    public static function discover(
        string $root,
        ?string $configurationPath = null
    ): self {
        $root = self::existingDirectory($root);
        $analysis = (new AnalysisConfiguration())->read(
            $root,
            $configurationPath
        );
        $roots = $analysis['roots'] ?? [$root];
        $excludes = $analysis['excludes'] ?? [];

        return new self(
            $root,
            $analysis['path'],
            $analysis['path'] === null
                ? []
                : self::configuration($analysis['path']),
            $analysis['preset'] ?? self::DEFAULT_PRESET,
            $roots,
            $excludes,
            new ProjectScanner(
                $root,
                $roots,
                $excludes,
                $analysis['maxFiles'] ?? 10000
            )
        );
    }

    public function root(): string
    {
        return $this->root;
    }

    public function name(): string
    {
        return $this->header('style.css', 'Theme Name')
            ?? $this->pluginHeader()
            ?? basename($this->root);
    }

    public function kind(): string
    {
        if ($this->header('style.css', 'Theme Name') !== null) {
            return 'WordPress theme';
        }
        if ($this->pluginHeader() !== null) {
            return 'WordPress plugin';
        }

        return 'Blockstudio project';
    }

    public function preset(): string
    {
        return $this->preset;
    }

    public function configurationPath(): ?string
    {
        return $this->configurationPath === null
            ? null
            : $this->relative($this->configurationPath);
    }

    /**
     * @return list<string>
     */
    public function roots(): array
    {
        return array_map(
            fn(string $root): string => $this->relative($root),
            $this->roots
        );
    }

    /**
     * @return list<string>
     */
    public function excludes(): array
    {
        return $this->excludes;
    }

    /**
     * @return list<Surface>
     */
    public function surfaces(): array
    {
        $surfaces = [];

        foreach (self::SURFACES as $surface) {
            $files = $this->files($surface['file']);
            if ($files === []) {
                continue;
            }

            $surfaces[] = new Surface(
                $surface['label'],
                count($files),
                $this->directories($files)
            );
        }

        $extensions = $this->extensionFiles();
        if ($extensions !== []) {
            $surfaces[] = new Surface(
                'Block extensions',
                count($extensions),
                $this->directories($extensions)
            );
        }

        return $surfaces;
    }

    /**
     * @return list<string>
     */
    public function namespaces(): array
    {
        $namespaces = [];

        foreach ($this->files('block.json') as $file) {
            $data = $this->scanner->jsonObject($file);
            $name = $data['name'] ?? null;
            if (!is_string($name) || !str_contains($name, '/')) {
                continue;
            }
            $namespaces[strstr($name, '/', true)] = true;
        }

        // Array keys normalise a numeric namespace such as "0" to an int, so
        // the keys are cast back before they leave as a list<string>.
        $namespaces = array_map(static fn($namespace): string => (string) $namespace, array_keys($namespaces));
        sort($namespaces);

        return $namespaces;
    }

    /**
     * @return list<string>
     */
    public function templateLanguages(): array
    {
        $languages = [];

        foreach ($this->files('block.json') as $file) {
            foreach (self::TEMPLATES as $template => $language) {
                if (is_file(dirname($file) . '/' . $template)) {
                    $languages[$language] = true;
                }
            }
        }

        return array_values(array_filter(
            array_values(self::TEMPLATES),
            static fn(string $language): bool => isset($languages[$language])
        ));
    }

    /**
     * @return list<Feature>
     */
    public function features(): array
    {
        return array_values(array_filter(
            [
                $this->blockTags(),
                $this->boundaryFeature('ui/enabled', 'Bundled UI', [
                    'Blockstudio registers its own UI blocks under the `bsui` namespace.',
                ]),
                $this->tailwind(),
                $this->boundaryFeature('assets/reset/enabled', 'Editor parity', [
                    'The editor canvas re-asserts utility-class display and position values, so editor previews match the frontend without theme-local CSS.',
                ]),
                $this->cache(),
                $this->performance(),
                $this->themeDefaults(),
                $this->boundaryFeature('content/enabled', 'Content Sync', [
                    'Selected database content is projected to portable files and pushed back through the Content Sync commands.',
                ]),
                $this->commitHook(),
            ],
            static fn(?Feature $feature): bool => $feature !== null
        ));
    }

    /**
     * @return list<Command>
     */
    public function commands(): array
    {
        $commands = [
            new Command(
                'vendor/bin/blockstudio-phpstan -- --no-progress',
                sprintf('Run the %s analysis this project selected.', $this->preset)
            ),
            new Command(
                'vendor/bin/blockstudio-agents',
                'Regenerate this contract after changing configuration or structure.'
            ),
        ];

        if ($this->flag('githooks/commit')) {
            $commands[] = new Command(
                'vendor/bin/blockstudio-githooks sync',
                'Install or refresh the analysis-only pre-commit hook.'
            );
        }
        if ($this->files('block.json') !== []) {
            $commands[] = new Command(
                'wp bs blocks list',
                'List the blocks WordPress registered from this project.'
            );
        }
        if ($this->files('field.json') !== []) {
            $commands[] = new Command(
                'wp bs fields list',
                'List the reusable field definitions.'
            );
        }
        if ($this->files('db.php') !== []) {
            $commands[] = new Command(
                'wp bs db schemas',
                'List the database schemas and their storage.'
            );
        }
        if ($this->files('rpc.php') !== []) {
            $commands[] = new Command(
                'wp bs rpc list',
                'List the registered RPC endpoints.'
            );
        }
        if ($this->files('cron.php') !== []) {
            $commands[] = new Command(
                'wp bs cron list',
                'List the registered cron tasks.'
            );
        }
        if ($this->flag('tailwind/enabled')) {
            $commands[] = new Command(
                'wp bs tailwind compile',
                'Compile the Tailwind stylesheet from the current sources.'
            );
        }
        if ($this->flag('content/enabled')) {
            $commands[] = new Command(
                'wp bs content status',
                'Report what Content Sync would pull or push.'
            );
        }
        if ($this->flag('performance/staticPrerender/enabled')) {
            $commands[] = new Command(
                'wp bs prerender status',
                'Report static prerender identity, counters, and queue state.'
            );
            $commands[] = new Command(
                'wp bs prerender warm',
                'Rebuild the stale static entries.'
            );
        }

        return $commands;
    }

    private function blockTags(): ?Feature
    {
        if (!$this->flag('blockTags/enabled')) {
            return null;
        }

        $details = [
            'Blocks can be written as tags in templates. `<bs:namespace-slug />` is the canonical spelling and always resolves.',
        ];
        $prefixes = $this->value('blockTags/prefixes');

        if (is_array($prefixes)) {
            foreach ($prefixes as $prefix => $namespaces) {
                if (!is_string($prefix) || !is_array($namespaces)) {
                    continue;
                }
                $namespaces = array_values(array_filter($namespaces, 'is_string'));
                if ($namespaces === []) {
                    continue;
                }
                $details[] = sprintf(
                    'The `%s` prefix resolves through %s, so `<%s-slug />` is the same block as `<bs:%s-slug />`.',
                    $prefix,
                    $this->inlineCode($namespaces),
                    $prefix,
                    $namespaces[0]
                );
            }
        }

        foreach (['allow', 'deny'] as $list) {
            $value = $this->value('blockTags/' . $list);
            if (is_array($value) && $value !== []) {
                $details[] = sprintf(
                    'Tags are %s to %s.',
                    $list === 'allow' ? 'limited' : 'closed',
                    $this->inlineCode(array_values(array_filter($value, 'is_string')))
                );
            }
        }

        return new Feature('Block tags', $details);
    }

    private function tailwind(): ?Feature
    {
        if (!$this->flag('tailwind/enabled')) {
            return null;
        }

        $details = [
            'Tailwind is compiled server side from the classes this project writes. There is no separate build step.',
        ];
        $config = $this->value('tailwind/config');
        if (is_string($config) && $config !== '') {
            $details[] = sprintf('The Tailwind configuration is `%s`.', $config);
        }

        return new Feature('Tailwind CSS', $details);
    }

    private function cache(): ?Feature
    {
        if (!$this->flag('cache/enabled')) {
            return null;
        }

        $path = $this->value('cache/path');

        return new Feature('Runtime cache', [
            sprintf(
                'Generated output is cached below `%s`. Everything under it is disposable and must not be edited or committed.',
                is_string($path) && $path !== ''
                    ? $path
                    : 'wp-content/blockstudio/cache'
            ),
        ]);
    }

    private function performance(): ?Feature
    {
        $profile = $this->value('performance/profile');
        $details = [];

        if (is_string($profile) && $profile !== '') {
            $details[] = sprintf(
                'The performance profile is `%s`, so its frontend defaults apply unless a child value overrides them.',
                $profile
            );
        }
        if ($this->flag('performance/media/lazy')) {
            $details[] = 'Images rendered through `bs_media_image()` are lazy loaded behind a correctly sized placeholder.';
        }
        if ($this->flag('performance/staticPrerender/enabled')) {
            $details[] = sprintf(
                'Anonymous pages are served from prerendered HTML (%s).',
                $this->inlineValues([
                    'earlyServe' => 'performance/staticPrerender/earlyServe',
                    'invalidate' => 'performance/staticPrerender/invalidate',
                    'ttl' => 'performance/staticPrerender/ttl',
                ])
            );

            $dynamic = $this->value('performance/staticPrerender/dynamicPaths');
            if (is_array($dynamic) && $dynamic !== []) {
                $details[] = sprintf(
                    'These paths always bypass the static layer: %s.',
                    $this->inlineCode(array_values(array_filter($dynamic, 'is_string')))
                );
            }
        }

        return $details === [] ? null : new Feature('Performance', $details);
    }

    private function themeDefaults(): ?Feature
    {
        $defaults = $this->value('themeDefaults');
        if (!is_array($defaults)) {
            return null;
        }

        $enabled = array_keys(array_filter(
            $defaults,
            static fn(mixed $value): bool => $value === true
        ));
        if ($enabled === []) {
            return null;
        }

        return new Feature('Theme defaults', [
            sprintf(
                'Blockstudio owns these theme behaviours: %s.',
                $this->inlineCode($enabled)
            ),
        ]);
    }

    private function commitHook(): ?Feature
    {
        if (!$this->flag('githooks/commit')) {
            return null;
        }

        return new Feature('Commit gate', [
            'A Blockstudio-owned pre-commit hook runs the analysis below. A commit that fails analysis is rejected.',
        ]);
    }

    /**
     * @param list<string> $details
     */
    private function boundaryFeature(
        string $path,
        string $label,
        array $details
    ): ?Feature {
        return $this->flag($path) ? new Feature($label, $details) : null;
    }

    /**
     * @return list<string>
     */
    private function files(string $filename): array
    {
        if (isset($this->named[$filename])) {
            return $this->named[$filename];
        }

        $extensions = array_fill_keys($this->extensionFiles(), true);

        return $this->named[$filename] = array_values(array_filter(
            $this->scanner->filesNamed($filename),
            static fn(string $file): bool => !isset($extensions[$file])
        ));
    }

    /**
     * @return list<string>
     */
    private function extensionFiles(): array
    {
        if ($this->extensions !== null) {
            return $this->extensions;
        }

        $files = [];
        foreach ($this->scanner->files(['json']) as $file) {
            if (!str_contains($file, '/extensions/')) {
                continue;
            }

            $data = $this->scanner->jsonObject($file);
            if ($data === null || !array_key_exists('blockstudio', $data)) {
                continue;
            }

            $files[] = $file;
        }

        return $this->extensions = $files;
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private function directories(array $files): array
    {
        $directories = [];

        foreach ($files as $file) {
            $relative = $this->scanner->relativePath($file);
            $segment = strstr($relative, '/', true);
            $directories[$segment === false ? '.' : $segment] = true;
        }

        $directories = array_keys($directories);
        sort($directories);

        return $directories;
    }

    private function header(string $file, string $field): ?string
    {
        $path = $this->root . '/' . $file;
        if (!is_file($path)) {
            return null;
        }

        return self::headerValue($this->scanner->read($path), $field);
    }

    private function pluginHeader(): ?string
    {
        foreach (scandir($this->root) ?: [] as $entry) {
            if (!str_ends_with($entry, '.php')) {
                continue;
            }

            $name = self::headerValue(
                $this->scanner->read($this->root . '/' . $entry),
                'Plugin Name'
            );
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    private static function headerValue(string $contents, string $field): ?string
    {
        $pattern = sprintf(
            '/^[ \t*#@]*%s\s*:\s*(\S.*)$/mi',
            preg_quote($field, '/')
        );

        return preg_match($pattern, $contents, $match) === 1
            ? trim($match[1])
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function configuration(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \InvalidArgumentException(
                'Unable to read Blockstudio configuration: ' . $path
            );
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    private function flag(string $path): bool
    {
        return $this->value($path) === true;
    }

    private function value(string $path): mixed
    {
        $value = $this->configuration;

        foreach (explode('/', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param array<string, string> $paths
     */
    private function inlineValues(array $paths): string
    {
        $values = [];

        foreach ($paths as $label => $path) {
            $value = $this->value($path);
            if ($value === null) {
                continue;
            }
            $values[] = sprintf(
                '%s `%s`',
                $label,
                is_bool($value) ? ($value ? 'true' : 'false') : (string) $value
            );
        }

        return implode(', ', $values);
    }

    /**
     * @param list<string> $values
     */
    private function inlineCode(array $values): string
    {
        return implode(
            ', ',
            array_map(static fn(string $value): string => '`' . $value . '`', $values)
        );
    }

    private function relative(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim($this->root, '/');

        if ($path === $root) {
            return '.';
        }
        if (str_starts_with($path, $root . '/')) {
            return substr($path, strlen($root) + 1);
        }

        $from = explode('/', $root);
        $to = explode('/', $path);

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return implode(
            '/',
            array_merge(array_fill(0, count($from), '..'), $to)
        );
    }

    private static function existingDirectory(string $path): string
    {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            throw new \InvalidArgumentException(
                sprintf('Project root does not exist: %s.', $path)
            );
        }

        return rtrim(str_replace('\\', '/', $real), '/');
    }
}
