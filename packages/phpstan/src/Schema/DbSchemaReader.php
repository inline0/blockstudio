<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Schema;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
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
 * Extracts field shapes from legacy arrays and PHP-native schema builders.
 */
final class DbSchemaReader
{
    /** @var array<string, array{mtime: int, type: Type|null, fields: array<string, array<string, mixed>>, schemas: array<string, array{type: Type|null, fields: array<string, array<string, mixed>>}>}> */
    private array $cache = [];

    public function getRecordType(string $dbPath, string $schemaName = 'default'): ?Type
    {
        $data = $this->load($dbPath, $schemaName);
        return $data['type'] ?? null;
    }

    /**
     * @return array{mtime: int, type: Type|null, fields: array<string, array<string, mixed>>, schemas: array<string, array{type: Type|null, fields: array<string, array<string, mixed>>}>}|null
     */
    public function load(string $path, string $schemaName = 'default'): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $mtime = (int) filemtime($path);
        $cacheKey = $path . ':' . $schemaName;
        if (isset($this->cache[$cacheKey]) && $this->cache[$cacheKey]['mtime'] === $mtime) {
            return $this->cache[$cacheKey];
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

        $schemas = $this->extractSchemas($return->expr);
        if ($schemas === []) {
            return null;
        }

        $selected = $schemas[$schemaName] ?? ['type' => null, 'fields' => []];
        $entry = [
            'mtime' => $mtime,
            'type' => $selected['type'],
            'fields' => $selected['fields'],
            'schemas' => $schemas,
        ];

        $this->cache[$cacheKey] = $entry;
        return $entry;
    }

    /**
     * @return array<string, array{type: Type|null, fields: array<string, array<string, mixed>>}>
     */
    private function extractSchemas(?Node $expr): array
    {
        if ($expr === null) {
            return [];
        }

        $singleSchema = $this->schemaNodeToData($expr);
        if ($singleSchema !== null) {
            return [
                'default' => [
                    'type' => $this->buildRecordType($singleSchema['fields']),
                    'fields' => $singleSchema['fields'],
                ],
            ];
        }

        if (!$expr instanceof Array_) {
            return [];
        }

        $schemas = [];

        foreach ($expr->items as $item) {
            if ($item === null || $item->key === null) {
                continue;
            }

            $schemaName = $this->nodeToValue($item->key);
            if (!is_string($schemaName)) {
                continue;
            }

            $schema = $this->schemaNodeToData($item->value);
            if ($schema === null) {
                continue;
            }

            $schemas[$schemaName] = [
                'type' => $this->buildRecordType($schema['fields']),
                'fields' => $schema['fields'],
            ];
        }

        return $schemas;
    }

    /**
     * @return array{fields: array<string, array<string, mixed>>}|null
     */
    private function schemaNodeToData(Node $node): ?array
    {
        $config = $this->nodeToValue($node);
        if (!is_array($config)) {
            return null;
        }

        if (!$this->looksLikeSchemaConfig($config)) {
            return null;
        }

        $fields = $config['fields'] ?? null;
        $fields = is_array($fields) ? $fields : [];

        /** @var array<string, array<string, mixed>> $fields */
        return ['fields' => $fields];
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
            'string', 'text' => new StringType(),
            'integer' => new IntegerType(),
            'number' => TypeCombinator::union(new IntegerType(), new FloatType()),
            'boolean' => new BooleanType(),
            'array' => new ArrayType(new MixedType(), new MixedType()),
            'object' => new ArrayType(new StringType(), new MixedType()),
            default => new MixedType(),
        };

        return $required ? $base : TypeCombinator::union($base, new NullType());
    }

    /**
     * @return array<int|string, mixed>
     */
    private function arrayNodeToData(Array_ $node): array
    {
        $result = [];

        foreach ($node->items as $item) {
            if ($item === null) {
                continue;
            }

            $value = $this->nodeToValue($item->value);
            if ($item->key === null) {
                $result[] = $value;
                continue;
            }

            $key = $this->nodeToValue($item->key);
            if (is_string($key) || is_int($key)) {
                $result[$key] = $value;
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

        if ($node instanceof ClassConstFetch) {
            return $this->classConstToValue($node);
        }

        if ($node instanceof Array_) {
            return $this->arrayNodeToData($node);
        }

        if ($node instanceof StaticCall) {
            return $this->staticCallToValue($node);
        }

        return null;
    }

    /**
     * @return mixed
     */
    private function staticCallToValue(StaticCall $node)
    {
        if (!$node->class instanceof Name || !$node->name instanceof Node\Identifier) {
            return null;
        }

        $className = $this->shortName($node->class);
        $methodName = $node->name->toString();
        $args = $this->argsToMap($node->args);

        if (in_array($className, ['Schema', 'Db_Schema'], true) && $methodName === 'make') {
            return $this->parseSchemaBuilderCall($args);
        }

        if (in_array($className, ['Field', 'Db_Field'], true)) {
            return $this->parseFieldBuilderCall($methodName, $args);
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $args
     * @return array<string, mixed>|null
     */
    private function parseSchemaBuilderCall(array $args): ?array
    {
        $fields = $args['fields'] ?? $args[0] ?? null;
        if (!is_array($fields)) {
            return null;
        }

        $schema = [
            'fields' => $fields,
        ];

        $storage = $args['storage'] ?? $args[1] ?? null;
        if (is_string($storage)) {
            $schema['storage'] = $storage;
        }

        $capability = $args['capability'] ?? $args[2] ?? null;
        if (is_array($capability)) {
            $schema['capability'] = $capability;
        }

        $realtime = $args['realtime'] ?? $args[3] ?? null;
        if (is_array($realtime) || is_bool($realtime)) {
            $schema['realtime'] = $realtime;
        }

        $userScoped = $args['userScoped'] ?? $args[4] ?? null;
        if (is_bool($userScoped)) {
            $schema['userScoped'] = $userScoped;
        }

        $postId = $args['postId'] ?? $args[5] ?? null;
        if (is_int($postId)) {
            $schema['postId'] = $postId;
        }

        $hooks = $args['hooks'] ?? $args[6] ?? null;
        if (is_array($hooks)) {
            $schema['hooks'] = $hooks;
        }

        $extra = $args['extra'] ?? $args[7] ?? null;
        if (is_array($extra)) {
            $schema = array_merge($schema, $extra);
        }

        return $schema;
    }

    /**
     * @param array<int|string, mixed> $args
     * @return array<string, mixed>
     */
    private function parseFieldBuilderCall(string $methodName, array $args): array
    {
        $isGenericMake = $methodName === 'make';
        $type = $methodName === 'make'
            ? (is_string($args['type'] ?? $args[0] ?? null) ? (string) ($args['type'] ?? $args[0]) : 'string')
            : $methodName;

        $field = ['type' => $type];

        $requiredIndex = $isGenericMake ? 1 : 0;
        $defaultIndex = $isGenericMake ? 2 : 1;
        $enumIndex = $isGenericMake ? 3 : 2;
        $formatIndex = $isGenericMake ? 4 : 3;
        $minLengthIndex = $isGenericMake ? 5 : 4;
        $maxLengthIndex = $isGenericMake ? 6 : 5;
        $extraIndex = $isGenericMake ? 8 : match ($methodName) {
            'string' => 7,
            default => 3,
        };

        $required = $args['required'] ?? $args[$requiredIndex] ?? false;
        if ($required === true) {
            $field['required'] = true;
        }

        $default = $args['default'] ?? $args[$defaultIndex] ?? null;
        if ($default !== null) {
            $field['default'] = $default;
        }

        $enum = $args['enum'] ?? $args[$enumIndex] ?? null;
        if (is_array($enum)) {
            $field['enum'] = $enum;
        }

        $format = $args['format'] ?? $args[$formatIndex] ?? null;
        if (is_string($format)) {
            $field['format'] = $format;
        }

        $minLength = $args['minLength'] ?? $args[$minLengthIndex] ?? null;
        if (is_int($minLength)) {
            $field['minLength'] = $minLength;
        }

        $maxLength = $args['maxLength'] ?? $args[$maxLengthIndex] ?? null;
        if (is_int($maxLength)) {
            $field['maxLength'] = $maxLength;
        }

        $extra = $args['extra'] ?? $args[$extraIndex] ?? null;
        if (is_array($extra)) {
            $field = array_merge($field, $extra);
        }

        return $field;
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
    private function classConstToValue(ClassConstFetch $node)
    {
        if (!$node->class instanceof Name || !$node->name instanceof Node\Identifier) {
            return null;
        }

        $className = $this->shortName($node->class);
        $constName = $node->name->toString();

        return match ($className) {
            'Storage', 'Db_Storage' => match ($constName) {
                'Table' => 'table',
                'Sqlite' => 'sqlite',
                'Jsonc' => 'jsonc',
                'Meta' => 'meta',
                'PostType' => 'post_type',
                default => null,
            },
            default => null,
        };
    }

    private function shortName(Name $name): string
    {
        $parts = $name->getParts();
        return (string) end($parts);
    }

    /**
     * @param array<int|string, mixed> $config
     */
    private function looksLikeSchemaConfig(array $config): bool
    {
        foreach (['fields', 'storage', 'capability', 'realtime', 'userScoped', 'postId', 'hooks'] as $key) {
            if (array_key_exists($key, $config)) {
                return true;
            }
        }

        return false;
    }
}
