<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\ProjectScanner;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Scalar\String_;
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
    private const VALID_SCHEDULES = [
        'hourly', 'twicedaily', 'daily', 'weekly',
    ];

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
        if (!$return instanceof Return_ || !$return->expr instanceof Array_) {
            return [];
        }

        $errors = [];

        foreach ($return->expr->items as $item) {
            if (!$item->key instanceof String_) {
                continue;
            }
            $name = $item->key->value;

            if ($item->value instanceof Closure) {
                continue;
            }

            if ($item->value instanceof Array_) {
                $hasSchedule = false;
                $hasCallback = false;
                $scheduleValue = null;

                foreach ($item->value->items as $configItem) {
                    if (!$configItem->key instanceof String_) {
                        continue;
                    }
                    if ($configItem->key->value === 'schedule') {
                        $hasSchedule = true;
                        if ($configItem->value instanceof String_) {
                            $scheduleValue = $configItem->value->value;
                        }
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
                } elseif ($scheduleValue !== null && !in_array($scheduleValue, self::VALID_SCHEDULES, true)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'cron.php task "%s" has unknown schedule "%s" (standard: %s) in %s',
                        $name,
                        $scheduleValue,
                        implode(', ', self::VALID_SCHEDULES),
                        $path
                    ))
                        ->identifier('blockstudio.cronSchema.schedule')
                        ->file($path)
                        ->build();
                }
            }
        }

        return $errors;
    }
}
