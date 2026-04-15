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
}
