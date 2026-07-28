<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Agents;

trait ProjectFixture
{
    /**
     * @var list<string>
     */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryDirectories) as $directory) {
            $this->removeDirectory($directory);
        }
        $this->temporaryDirectories = [];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function theme(array $configuration = []): string
    {
        $root = $this->temporaryDirectory('theme');

        $this->file($root . '/style.css', "/*\nTheme Name: Acme\n*/\n");
        $this->json($root . '/blockstudio.json', $configuration === []
            ? $this->defaultConfiguration()
            : $configuration);

        $this->json($root . '/blocks/hero/block.json', [
            'name' => 'acme/hero',
            'title' => 'Hero',
            'blockstudio' => [
                'attributes' => [
                    ['id' => 'title', 'type' => 'text', 'default' => ''],
                ],
            ],
        ]);
        $this->file($root . '/blocks/hero/index.php', "<h1>Hero</h1>\n");
        $this->file($root . '/blocks/hero/db.php', "<?php\n\nreturn [];\n");
        $this->json($root . '/blocks/card/block.json', [
            'name' => 'acme/card',
            'title' => 'Card',
            'blockstudio' => true,
        ]);
        $this->file($root . '/blocks/card/index.twig', "<div></div>\n");

        $this->json($root . '/fields/hero/field.json', [
            'name' => 'acme/hero',
            'attributes' => [
                ['id' => 'heading', 'type' => 'text', 'default' => ''],
            ],
        ]);
        $this->json($root . '/pages/about/page.json', [
            'name' => 'about',
            'title' => 'About',
            'slug' => 'about',
        ]);
        $this->json($root . '/pages/pages.json', ['collection' => 'docs']);
        $this->json($root . '/patterns/hero/pattern.json', ['title' => 'Hero']);
        $this->json($root . '/extensions/paragraph.json', [
            'name' => 'core/paragraph',
            'blockstudio' => ['extend' => []],
        ]);

        return $root;
    }

    private function plugin(): string
    {
        $root = $this->temporaryDirectory('plugin');

        $this->file(
            $root . '/acme-blocks.php',
            "<?php\n/**\n * Plugin Name: Acme Blocks\n */\n"
        );
        $this->json($root . '/blockstudio.json', [
            'phpstan' => ['preset' => 'base'],
        ]);
        $this->json($root . '/blocks/hero/block.json', [
            'name' => 'acme/hero',
            'title' => 'Hero',
            'blockstudio' => true,
        ]);
        $this->file($root . '/blocks/hero/index.php', "<h1>Hero</h1>\n");

        return $root;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultConfiguration(): array
    {
        return [
            'assets' => ['reset' => ['enabled' => true]],
            'tailwind' => ['enabled' => true],
            'ui' => ['enabled' => true],
            'cache' => ['enabled' => true],
            'blockTags' => [
                'enabled' => true,
                'prefixes' => ['acme' => ['theme-components', 'bsui']],
            ],
            'themeDefaults' => ['titleTag' => true],
            'performance' => [
                'profile' => 'speed',
                'staticPrerender' => [
                    'enabled' => true,
                    'earlyServe' => true,
                    'invalidate' => 'graph',
                    'ttl' => 3600,
                    'dynamicPaths' => ['account'],
                ],
            ],
            'phpstan' => [
                'preset' => 'extreme-theme',
                'roots' => ['.'],
                'excludePaths' => ['fixtures/**'],
            ],
            'githooks' => ['commit' => true],
        ];
    }

    /**
     * @param array<string, mixed> $contents
     */
    private function json(string $path, array $contents): void
    {
        $this->file(
            $path,
            json_encode(
                $contents,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n"
        );
    }

    private function file(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $contents);
    }

    private function temporaryDirectory(string $name): string
    {
        $directory = sys_get_temp_dir()
            . '/blockstudio-agents-'
            . $name
            . '-'
            . bin2hex(random_bytes(5));
        mkdir($directory, 0775, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
