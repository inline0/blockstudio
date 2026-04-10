<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Schema;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Reads an rpc.php file via AST parsing.
 * Extracts the function names and basic shape information.
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
        if (!$return instanceof Return_ || !$return->expr instanceof Array_) {
            return null;
        }

        $functions = [];
        foreach ($return->expr->items as $item) {
            if (!$item->key instanceof String_) {
                continue;
            }
            $name = $item->key->value;
            $value = $item->value;

            if ($value instanceof Closure) {
                $functions[$name] = ['callback' => true];
                continue;
            }

            if ($value instanceof Array_) {
                $config = [];
                foreach ($value->items as $configItem) {
                    if (!$configItem->key instanceof String_) {
                        continue;
                    }
                    $config[$configItem->key->value] = $this->nodeToValue($configItem->value);
                }
                $functions[$name] = $config;
            }
        }

        $entry = [
            'mtime' => $mtime,
            'functions' => $functions,
        ];

        $this->cache[$path] = $entry;
        return $entry;
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
                $val = $this->nodeToValue($item->value);
                if ($item->key === null) {
                    $result[] = $val;
                } else {
                    $key = $this->nodeToValue($item->key);
                    if (is_string($key) || is_int($key)) {
                        $result[$key] = $val;
                    }
                }
            }
            return $result;
        }
        return null;
    }
}
