<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Schema;

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

/**
 * Reads an rpc.php file via AST parsing.
 * Extracts function names from legacy arrays and attribute-based object returns.
 */
final class RpcSchemaReader
{
    /** @var array<string, array{mtime: int, functions: array<string, array<string, mixed>>}> */
    private array $cache = [];

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function getFunctions(string $rpcPath): ?array
    {
        $data = $this->load($rpcPath);
        return $data === null ? null : $data['functions'];
    }

    /**
     * @return array{mtime: int, functions: array<string, array<string, mixed>>}|null
     */
    public function load(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $mtime = (int) filemtime($path);
        if (isset($this->cache[$path]) && $this->cache[$path]['mtime'] === $mtime) {
            return $this->cache[$path];
        }

        $code = file_get_contents($path);
        if ($code === false) {
            return null;
        }

        $parser = (new ParserFactory())->createForHostVersion();
        try {
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            return null;
        }

        if ($ast === null) {
            return null;
        }

        $finder = new NodeFinder();
        $return = $finder->findFirstInstanceOf($ast, Return_::class);
        if (!$return instanceof Return_) {
            return null;
        }

        $functions = $this->extractFunctions($return->expr);
        if ($functions === []) {
            return null;
        }

        $entry = [
            'mtime' => $mtime,
            'functions' => $functions,
        ];

        $this->cache[$path] = $entry;
        return $entry;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function extractFunctions(?Node $expr): array
    {
        if ($expr instanceof Array_) {
            return $this->extractArrayFunctions($expr);
        }

        if ($expr instanceof New_ && $expr->class instanceof Class_) {
            return $this->extractAttributeFunctions($expr->class);
        }

        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function extractArrayFunctions(Array_ $array): array
    {
        $functions = [];

        foreach ($array->items as $item) {
            if ($item === null || !$item->key instanceof String_) {
                continue;
            }

            $name = $item->key->value;
            $value = $item->value;

            if ($value instanceof Closure) {
                $functions[$name] = ['callback' => true];
                continue;
            }

            if (!$value instanceof Array_) {
                continue;
            }

            $config = [];
            foreach ($value->items as $configItem) {
                if ($configItem === null || !$configItem->key instanceof String_) {
                    continue;
                }

                $config[$configItem->key->value] = $this->nodeToValue($configItem->value);
            }
            $functions[$name] = $config;
        }

        return $functions;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function extractAttributeFunctions(Class_ $class): array
    {
        $functions = [];

        foreach ($class->getMethods() as $method) {
            if (!$method->isPublic() || $method->isStatic()) {
                continue;
            }

            $attribute = $this->findAttribute($method, ['Rpc', 'Rpc_Definition']);
            if ($attribute === null) {
                continue;
            }

            $args = $this->argsToMap($attribute->args);
            $name = $args['name'] ?? $this->normalizeMethodName($method->name->toString());
            if (!is_string($name) || $name === '') {
                continue;
            }

            $functions[$name] = [
                'callback' => true,
                'public' => $this->normalizeAccess($args['access'] ?? null),
                'capability' => $args['capability'] ?? null,
                'methods' => $this->normalizeMethods($args['methods'] ?? ['POST']),
            ];
        }

        return $functions;
    }

    private function findAttribute(ClassMethod $method, array $names): ?Attribute
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $attributeName = $attribute->name->getLast();
                if (in_array($attributeName, $names, true)) {
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
     * @param mixed $access
     * @return bool|string
     */
    private function normalizeAccess($access): bool|string
    {
        return match ($access) {
            'open' => 'open',
            'session', true => true,
            default => false,
        };
    }

    /**
     * @param mixed $methods
     * @return list<string>
     */
    private function normalizeMethods($methods): array
    {
        $methods = is_array($methods) ? $methods : [$methods];
        $normalized = [];

        foreach ($methods as $method) {
            if (is_string($method) && $method !== '') {
                $normalized[] = strtoupper($method);
            }
        }

        return $normalized === [] ? ['POST'] : array_values(array_unique($normalized));
    }

    private function normalizeMethodName(string $name): string
    {
        $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);
        return strtolower($normalized ?? $name);
    }

    /**
     * @return mixed
     */
    private function nodeToValue(Node $node)
    {
        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof Node\Scalar\Int_) {
            return $node->value;
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

        if ($node instanceof Closure) {
            return ['__callback__' => true];
        }

        if ($node instanceof Array_) {
            $result = [];

            foreach ($node->items as $item) {
                if ($item === null) {
                    continue;
                }

                $val = $this->nodeToValue($item->value);

                if ($item->key === null) {
                    $result[] = $val;
                    continue;
                }

                $key = $this->nodeToValue($item->key);
                if (is_string($key) || is_int($key)) {
                    $result[$key] = $val;
                }
            }

            return $result;
        }

        if ($node instanceof ClassConstFetch) {
            return $this->classConstToValue($node);
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

        $className = $node->class->getLast();
        $constName = $node->name->toString();

        return match ($className) {
            'Http_Method' => match ($constName) {
                'Get' => 'GET',
                'Post' => 'POST',
                'Put' => 'PUT',
                'Patch' => 'PATCH',
                'Delete' => 'DELETE',
                default => null,
            },
            'Rpc_Access' => match ($constName) {
                'Authenticated' => 'authenticated',
                'Session' => 'session',
                'Open' => 'open',
                default => null,
            },
            default => null,
        };
    }
}
