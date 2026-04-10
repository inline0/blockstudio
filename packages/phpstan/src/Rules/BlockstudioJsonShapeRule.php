<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\BlockstudioJsonReader;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates blockstudio.json in the user's theme/plugin.
 * Catches the common mistake of using shorthand booleans instead of nested objects.
 *
 * @implements Rule<FileNode>
 */
final class BlockstudioJsonShapeRule implements Rule
{
    /** @var array<string, true> */
    private static array $validatedPaths = [];

    public function __construct(
        private readonly BlockstudioJsonReader $reader,
        private readonly string $currentWorkingDirectory
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $candidates = $this->findBlockstudioJsonFiles();
        $errors = [];

        foreach ($candidates as $path) {
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
    private function findBlockstudioJsonFiles(): array
    {
        $candidates = [
            $this->currentWorkingDirectory . '/blockstudio.json',
        ];

        $themesDir = $this->currentWorkingDirectory . '/wp-content/themes';
        if (is_dir($themesDir)) {
            foreach (scandir($themesDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $themesDir . '/' . $entry . '/blockstudio.json';
                if (file_exists($path)) {
                    $candidates[] = $path;
                }
            }
        }

        return array_values(array_filter($candidates, 'file_exists'));
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateFile(string $path): array
    {
        $data = $this->reader->load($path);
        if ($data === null) {
            return [
                RuleErrorBuilder::message(sprintf('Invalid blockstudio.json: %s', $path))
                    ->identifier('blockstudio.settings')
                    ->file($path)
                    ->build(),
            ];
        }

        $errors = [];

        if (isset($data['tailwind']) && is_bool($data['tailwind'])) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'blockstudio.json: "tailwind" shorthand boolean is not supported. Use {"enabled": true, "config": ""} in %s',
                $path
            ))
                ->identifier('blockstudio.settings.tailwind')
                ->file($path)
                ->build();
        }

        if (isset($data['assets']['reset']) && is_bool($data['assets']['reset'])) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'blockstudio.json: "assets.reset" shorthand boolean is not supported. Use {"enabled": true, "fullWidth": []} in %s',
                $path
            ))
                ->identifier('blockstudio.settings.assets')
                ->file($path)
                ->build();
        }

        return $errors;
    }
}
