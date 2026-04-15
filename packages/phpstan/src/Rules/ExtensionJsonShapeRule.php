<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\ProjectScanner;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates extension JSON files (block extensions).
 *
 * Extension files live in an extensions/ directory and define additional
 * attributes for existing blocks.
 *
 * @implements Rule<FileNode>
 */
final class ExtensionJsonShapeRule implements Rule
{
    /** @var array<string, true> */
    private static array $validatedPaths = [];

    public function __construct(
        private readonly ProjectScanner $scanner
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->findExtensionFiles() as $path) {
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
    private function findExtensionFiles(): array
    {
        $files = [];
        foreach ($this->scanner->getBlockJsonPaths() as $blockJsonPath) {
            $projectRoot = dirname($blockJsonPath, 2);
            $extDir = $projectRoot . '/extensions';
            if (!is_dir($extDir)) {
                $extDir = dirname($blockJsonPath, 3) . '/extensions';
            }
            if (is_dir($extDir)) {
                foreach (scandir($extDir) ?: [] as $entry) {
                    if (str_ends_with($entry, '.json')) {
                        $files[$extDir . '/' . $entry] = $extDir . '/' . $entry;
                    }
                }
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
                RuleErrorBuilder::message(sprintf('Invalid extension JSON: %s', $path))
                    ->identifier('blockstudio.extensionJson')
                    ->file($path)
                    ->build(),
            ];
        }

        $errors = [];

        if (!isset($data['name']) || !is_string($data['name']) || $data['name'] === '') {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Extension JSON missing required "name" (target block): %s',
                $path
            ))
                ->identifier('blockstudio.extensionJson.name')
                ->file($path)
                ->build();
        }

        $bs = $data['blockstudio'] ?? null;
        if ($bs === null) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Extension JSON missing "blockstudio" key: %s',
                $path
            ))
                ->identifier('blockstudio.extensionJson.blockstudio')
                ->file($path)
                ->build();
            return $errors;
        }

        if (!is_array($bs)) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Extension JSON "blockstudio" must be an object: %s',
                $path
            ))
                ->identifier('blockstudio.extensionJson.blockstudio')
                ->file($path)
                ->build();
            return $errors;
        }

        if (!isset($bs['extend'])) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Extension JSON missing "blockstudio.extend" key: %s',
                $path
            ))
                ->identifier('blockstudio.extensionJson.extend')
                ->file($path)
                ->build();
        }

        return $errors;
    }
}
