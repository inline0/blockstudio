<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\DbSchemaReader;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates db.php files in the project.
 *
 * @implements Rule<FileNode>
 */
final class DbSchemaShapeRule implements Rule
{
    private const VALID_FIELD_TYPES = ['string', 'number', 'boolean', 'array', 'object'];

    /** @var array<string, true> */
    private static array $validatedPaths = [];

    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly DbSchemaReader $reader
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->scanner->getBlockJsonPaths() as $blockJsonPath) {
            $dbPath = dirname($blockJsonPath) . '/db.php';
            if (!file_exists($dbPath) || isset(self::$validatedPaths[$dbPath])) {
                continue;
            }
            self::$validatedPaths[$dbPath] = true;
            $errors = array_merge($errors, $this->validateFile($dbPath));
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateFile(string $path): array
    {
        $data = $this->reader->load($path);
        if ($data === null) {
            return [];
        }

        $errors = [];
        $fields = $data['fields'];

        if (empty($fields)) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'db.php missing or empty "fields": %s',
                $path
            ))
                ->identifier('blockstudio.dbSchema.fields')
                ->file($path)
                ->build();
            return $errors;
        }

        foreach ($fields as $name => $field) {
            $type = $field['type'] ?? null;
            if ($type === null) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'db.php field "%s" missing "type": %s',
                    $name,
                    $path
                ))
                    ->identifier('blockstudio.dbSchema.type')
                    ->file($path)
                    ->build();
            } elseif (is_string($type) && !in_array($type, self::VALID_FIELD_TYPES, true)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'db.php field "%s" has invalid type "%s" (must be one of: %s) in %s',
                    $name,
                    $type,
                    implode(', ', self::VALID_FIELD_TYPES),
                    $path
                ))
                    ->identifier('blockstudio.dbSchema.type')
                    ->file($path)
                    ->build();
            }
        }

        return $errors;
    }
}
