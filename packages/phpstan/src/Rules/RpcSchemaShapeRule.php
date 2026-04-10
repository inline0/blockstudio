<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\ProjectScanner;
use Blockstudio\PHPStan\Schema\RpcSchemaReader;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates rpc.php files in the project.
 *
 * @implements Rule<FileNode>
 */
final class RpcSchemaShapeRule implements Rule
{
    private const VALID_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    /** @var array<string, true> */
    private static array $validatedPaths = [];

    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly RpcSchemaReader $reader
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->scanner->getBlockJsonPaths() as $blockJsonPath) {
            $rpcPath = dirname($blockJsonPath) . '/rpc.php';
            if (!file_exists($rpcPath) || isset(self::$validatedPaths[$rpcPath])) {
                continue;
            }
            self::$validatedPaths[$rpcPath] = true;
            $errors = array_merge($errors, $this->validateFile($rpcPath));
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateFile(string $path): array
    {
        $functions = $this->reader->getFunctions($path);
        if ($functions === null || empty($functions)) {
            return [];
        }

        $errors = [];

        foreach ($functions as $name => $config) {
            if (isset($config['methods']) && is_array($config['methods'])) {
                foreach ($config['methods'] as $method) {
                    if (!is_string($method) || !in_array(strtoupper($method), self::VALID_METHODS, true)) {
                        $errors[] = RuleErrorBuilder::message(sprintf(
                            'rpc.php function "%s" has invalid HTTP method "%s" in %s',
                            $name,
                            (string) $method,
                            $path
                        ))
                            ->identifier('blockstudio.rpcSchema.method')
                            ->file($path)
                            ->build();
                    }
                }
            }

            if (isset($config['public'])) {
                $public = $config['public'];
                $valid = is_bool($public) || $public === 'open';
                if (!$valid) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'rpc.php function "%s" "public" must be bool or "open" in %s',
                        $name,
                        $path
                    ))
                        ->identifier('blockstudio.rpcSchema.public')
                        ->file($path)
                        ->build();
                }
            }
        }

        return $errors;
    }
}
