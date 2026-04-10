<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Schema;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Reads a db.php file safely via AST parsing (no eval).
 * Extracts the fields shape and returns a Type for record arrays.
 */
final class DbSchemaReader
{
    /** @var array<string, array{mtime: int, type: Type|null, fields: array<string, array<string, mixed>>}> */
    private array $cache = [];

    public function getRecordType(string $dbPath): ?Type
    {
        $data = $this->load($dbPath);
        return $data['type'] ?? null;
    }

    /**
     * @return array{type: Type|null, fields: array<string, array<string, mixed>>}|null
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

        $config = $this->arrayNodeToData($return->expr);
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];

        $type = $this->buildRecordType($fields);

        $entry = [
            'mtime' => $mtime,
            'type' => $type,
            'fields' => $fields,
        ];

        $this->cache[$path] = $entry;
        return $entry;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private function buildRecordType(array $fields): Type
    {
        $builder = ConstantArrayTypeBuilder::createEmpty();
        $builder->setOffsetValueType(new ConstantStringType('id'), new IntegerType());

        foreach ($fields as $name => $field) {
            $builder->setOffsetValueType(
                new ConstantStringType($name),
                $this->fieldType($field)
            );
        }

        return $builder->getArray();
    }

    /**
     * @param array<string, mixed> $field
     */
    private function fieldType(array $field): Type
    {
        $type = (string) ($field['type'] ?? 'string');
        $required = !empty($field['required']);

        $base = match ($type) {
            'string' => new StringType(),
            'number' => TypeCombinator::union(new IntegerType(), new FloatType()),
            'boolean' => new BooleanType(),
            'array' => new ArrayType(new MixedType(), new MixedType()),
            'object' => new ArrayType(new StringType(), new MixedType()),
            default => new MixedType(),
        };

        return $required ? $base : TypeCombinator::union($base, new NullType());
    }

    /**
     * Convert an Array_ AST node into a plain PHP value.
     * Only handles literals (string, int, float, bool, null, nested arrays).
     *
     * @return array<int|string, mixed>
     */
    private function arrayNodeToData(Array_ $node): array
    {
        $result = [];
        foreach ($node->items as $item) {
            $value = $this->nodeToValue($item->value);
            if ($item->key === null) {
                $result[] = $value;
            } else {
                $key = $this->nodeToValue($item->key);
                if (is_string($key) || is_int($key)) {
                    $result[$key] = $value;
                }
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
        if ($node instanceof Node\Scalar\Int_) {
            return $node->value;
        }
        if ($node instanceof Node\Scalar\Float_) {
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
        if ($node instanceof Array_) {
            return $this->arrayNodeToData($node);
        }
        return null;
    }
}
