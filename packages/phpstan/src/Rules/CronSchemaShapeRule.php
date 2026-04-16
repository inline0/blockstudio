<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\ProjectScanner;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates cron.php files (scheduled task definitions).
 *
 * @implements Rule<FileNode>
 */
final class CronSchemaShapeRule implements Rule
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

        foreach ($this->scanner->getBlockJsonPaths() as $blockJsonPath) {
            $cronPath = dirname($blockJsonPath) . '/cron.php';
            if (!file_exists($cronPath) || isset(self::$validatedPaths[$cronPath])) {
                continue;
            }

            self::$validatedPaths[$cronPath] = true;
            $errors = array_merge($errors, $this->validateFile($cronPath));
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateFile(string $path): array
    {
        $code = @file_get_contents($path);
        if ($code === false) {
            return [];
        }

        $parser = (new ParserFactory())->createForHostVersion();
        try {
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            return [];
        }

        if ($ast === null) {
            return [];
        }

        $finder = new NodeFinder();
        $return = $finder->findFirstInstanceOf($ast, Return_::class);
        if (!$return instanceof Return_) {
            return [];
        }

        return $this->extractErrors($return->expr, $path);
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function extractErrors(?Node $expr, string $path): array
    {
        if ($expr instanceof Array_) {
            return $this->validateArrayJobs($expr, $path);
        }

        if ($expr instanceof New_ && $expr->class instanceof Class_) {
            return $this->validateAttributeJobs($expr->class, $path);
        }

        return [];
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateArrayJobs(Array_ $array, string $path): array
    {
        $errors = [];

        foreach ($array->items as $item) {
            if ($item === null || !$item->key instanceof String_) {
                continue;
            }

            $name = $item->key->value;

            if ($item->value instanceof Closure) {
                continue;
            }

            if (!$item->value instanceof Array_) {
                continue;
            }

            $hasSchedule = false;
            $hasCallback = false;

            foreach ($item->value->items as $configItem) {
                if ($configItem === null || !$configItem->key instanceof String_) {
                    continue;
                }

                if ($configItem->key->value === 'schedule') {
                    $hasSchedule = true;
                }

                if ($configItem->key->value === 'callback') {
                    $hasCallback = true;
                }
            }

            if (!$hasCallback) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'cron.php task "%s" missing "callback": %s',
                    $name,
                    $path
                ))
                    ->identifier('blockstudio.cronSchema.callback')
                    ->file($path)
                    ->build();
            }

            if (!$hasSchedule) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'cron.php task "%s" missing "schedule": %s',
                    $name,
                    $path
                ))
                    ->identifier('blockstudio.cronSchema.schedule')
                    ->file($path)
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateAttributeJobs(Class_ $class, string $path): array
    {
        $errors = [];

        foreach ($class->getMethods() as $method) {
            if (!$method->isPublic() || $method->isStatic()) {
                continue;
            }

            $attribute = $this->findAttribute($method, ['Cron', 'Cron_Definition']);
            if ($attribute === null) {
                continue;
            }

            $args = $this->argsToMap($attribute->args);
            $schedule = $args['schedule'] ?? null;

            if ($schedule !== null && !is_string($schedule)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'cron.php task "%s" has invalid schedule in %s',
                    $method->name->toString(),
                    $path
                ))
                    ->identifier('blockstudio.cronSchema.schedule')
                    ->file($path)
                    ->build();
            }
        }

        return $errors;
    }

    private function findAttribute(ClassMethod $method, array $names): ?Attribute
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (in_array($attribute->name->getLast(), $names, true)) {
                    return $attribute;
                }
            }
        }

        return null;
    }

    /**
     * @param list<Arg> $args
     * @return array<int|string, mixed>
     */
    private function argsToMap(array $args): array
    {
        $result = [];

        foreach ($args as $index => $arg) {
            $value = $this->nodeToValue($arg->value);

            if ($arg->name !== null) {
                $result[$arg->name->toString()] = $value;
            } else {
                $result[$index] = $value;
            }
        }

        return $result;
    }

    /**
     * @return mixed
     */
    private function nodeToValue(Node $node)
    {
        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof ClassConstFetch) {
            return $this->classConstToValue($node);
        }

        if ($node instanceof Node\Expr\ConstFetch) {
            $name = strtolower((string) $node->name);
            return match ($name) {
                'true' => true,
                'false' => false,
                'null' => null,
                default => null,
            };
        }

        return null;
    }

    /**
     * @return mixed
     */
    private function classConstToValue(ClassConstFetch $node)
    {
        if (!$node->class instanceof Name || !$node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->class->getLast() !== 'Cron_Schedule') {
            return null;
        }

        return match ($node->name->toString()) {
            'Hourly' => 'hourly',
            'TwiceDaily' => 'twicedaily',
            'Daily' => 'daily',
            'Weekly' => 'weekly',
            default => null,
        };
    }
}
