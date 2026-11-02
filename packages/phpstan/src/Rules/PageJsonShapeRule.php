<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates page.json files (file-based page definitions).
 *
 * @implements Rule<FileNode>
 */
final class PageJsonShapeRule implements Rule
{
    /** @var array<string, true> */
    private static array $validatedPaths = [];

    public function __construct(
        private readonly string $currentWorkingDirectory
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->findPageIndexFilesMissingMetadata() as $path) {
            $key = 'missing:' . $path;
            if (isset(self::$validatedPaths[$key])) {
                continue;
            }
            self::$validatedPaths[$key] = true;
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Blockstudio page directory has index.php but is missing page.json: %s',
                dirname($path)
            ))
                ->identifier('blockstudio.pageJson.missing')
                ->file($path)
                ->build();
        }

        foreach ($this->findPageJsonFiles() as $path) {
            if (isset(self::$validatedPaths[$path])) {
                continue;
            }
            self::$validatedPaths[$path] = true;
            $errors = array_merge($errors, $this->validateFile($path));
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function findPageJsonFiles(): array
    {
        $files = [];
        $candidates = [
            $this->currentWorkingDirectory . '/pages',
            $this->currentWorkingDirectory . '/wp-content/themes',
        ];

        foreach ($candidates as $root) {
            if (!is_dir($root)) {
                continue;
            }
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file instanceof \SplFileInfo && $file->getFilename() === 'page.json') {
                        $files[$file->getPathname()] = $file->getPathname();
                    }
                }
            } catch (\Throwable) {
            }
        }

        return array_values($files);
    }

    /**
     * @return list<string>
     */
    private function findPageIndexFilesMissingMetadata(): array
    {
        $files = [];
        $candidates = [
            $this->currentWorkingDirectory . '/pages',
            $this->currentWorkingDirectory . '/wp-content/themes',
        ];

        foreach ($candidates as $root) {
            if (!is_dir($root)) {
                continue;
            }
            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if (!$file instanceof \SplFileInfo || $file->getFilename() !== 'index.php') {
                        continue;
                    }

                    $path = $file->getPathname();
                    if (!$this->isDirectPageIndexFile($path)) {
                        continue;
                    }

                    if (!file_exists(dirname($path) . '/page.json')) {
                        $files[$path] = $path;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return array_values($files);
    }

    private function isDirectPageIndexFile(string $path): bool
    {
        return basename(dirname(dirname($path))) === 'pages';
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateFile(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [
                RuleErrorBuilder::message(sprintf('Invalid page.json: %s', $path))
                    ->identifier('blockstudio.pageJson')
                    ->file($path)
                    ->build(),
            ];
        }

        $errors = [];

        if (!isset($data['title']) || !is_string($data['title']) || $data['title'] === '') {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'page.json missing required "title": %s',
                $path
            ))
                ->identifier('blockstudio.pageJson.title')
                ->file($path)
                ->build();
        }

        if (!isset($data['slug']) || !is_string($data['slug']) || $data['slug'] === '') {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'page.json missing required "slug": %s',
                $path
            ))
                ->identifier('blockstudio.pageJson.slug')
                ->file($path)
                ->build();
        }

        if (array_key_exists('render', $data)) {
            $errors = array_merge($errors, $this->validateRender($data['render'], $path));
        }

        $validStatuses = ['publish', 'draft', 'private', 'pending', 'future', 'trash'];
        if (isset($data['postStatus']) && is_string($data['postStatus']) && !in_array($data['postStatus'], $validStatuses, true)) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'page.json has invalid "postStatus" "%s" (expected: %s): %s',
                $data['postStatus'],
                implode(', ', $validStatuses),
                $path
            ))
                ->identifier('blockstudio.pageJson.postStatus')
                ->file($path)
                ->build();
        }

        return $errors;
    }

    /**
     * Validate a page render definition: its shape, that its entry and data
     * files exist, and that they stay inside the analysed root.
     *
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateRender(mixed $render, string $path): array
    {
        if ($render === null || $render === false) {
            return [];
        }

        if (is_string($render)) {
            $render = ['entry' => $render];
        }

        if (
            !is_array($render)
            || !isset($render['entry'])
            || !is_string($render['entry'])
            || trim($render['entry']) === ''
            || (isset($render['data']) && !is_string($render['data']))
            || (isset($render['renderer']) && !is_string($render['renderer']))
            || array_diff(array_keys($render), ['entry', 'data', 'renderer']) !== []
        ) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'page.json "render" must be an entry path or an object with "entry" and optional "data" and "renderer" strings: %s',
                    $path
                ))
                    ->identifier('blockstudio.pageJson.render')
                    ->file($path)
                    ->build(),
            ];
        }

        $errors = [];
        $files = ['entry' => $render['entry']];
        if (isset($render['data']) && trim($render['data']) !== '') {
            $files['data'] = $render['data'];
        }

        foreach ($files as $role => $file) {
            $candidate = str_starts_with($file, '/') ? $file : dirname($path) . '/' . $file;
            $resolved = realpath($candidate);

            if ($resolved === false || !is_file($resolved)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'page.json render %s "%s" does not exist: %s',
                    $role,
                    $file,
                    $path
                ))
                    ->identifier('blockstudio.pageJson.render' . ucfirst($role))
                    ->file($path)
                    ->build();
                continue;
            }

            if (!$this->isInsideAnalysedRoot($resolved)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'page.json render %s "%s" resolves outside the analysed root: %s',
                    $role,
                    $file,
                    $path
                ))
                    ->identifier('blockstudio.pageJson.renderPath')
                    ->file($path)
                    ->build();
            }
        }

        return $errors;
    }

    private function isInsideAnalysedRoot(string $resolved): bool
    {
        $root = realpath($this->currentWorkingDirectory);
        if ($root === false) {
            return true;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/');
        $resolved = str_replace('\\', '/', $resolved);

        return $resolved === $root || str_starts_with($resolved, $root . '/');
    }
}
