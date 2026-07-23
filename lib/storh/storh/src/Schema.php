<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class Schema
{
    /** @var array<string, SchemaField> */
    private array $fields = array();

    private function __construct(private readonly string $collection)
    {
        if ('' === trim($collection)) {
            throw new StorageException('Schema collection cannot be empty.');
        }
    }

    public static function collection(string $collection): self
    {
        return new self($collection);
    }

    public function collection_name(): string
    {
        return $this->collection;
    }

    public function string(string $field): SchemaFieldBuilder
    {
        return $this->field($field, 'string');
    }

    public function int(string $field): SchemaFieldBuilder
    {
        return $this->field($field, 'int');
    }

    public function float(string $field): SchemaFieldBuilder
    {
        return $this->field($field, 'float');
    }

    public function bool(string $field): SchemaFieldBuilder
    {
        return $this->field($field, 'bool');
    }

    public function mixed(string $field): SchemaFieldBuilder
    {
        return $this->field($field, 'mixed');
    }

    /**
     * @param list<string> $fields
     */
    public function required(array $fields): self
    {
        foreach ($fields as $field) {
            $this->assert_field_name($field);
            $existing = $this->fields[ $field ] ?? new SchemaField($field, 'mixed');
            $this->fields[ $field ] = $existing->with_required();
        }

        return $this;
    }

    /**
     * @return array<string, SchemaField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validate(array $data): void
    {
        foreach ($this->fields as $field) {
            if ($field->required() && ! array_key_exists($field->name(), $data)) {
                throw new StorageException('Missing required schema field: ' . $field->name());
            }

            if (! array_key_exists($field->name(), $data) || null === $data[ $field->name() ]) {
                continue;
            }

            if (! $this->matches_type($field->type(), $data[ $field->name() ])) {
                throw new StorageException(
                    'Schema field ' . $field->name() . ' must be ' . $field->type() . '.'
                );
            }
        }
    }

    public function define(string $field, string $type): self
    {
        $this->assert_field_name($field);
        $this->fields[ $field ] = $this->fields[ $field ] ?? new SchemaField($field, $type);

        return $this;
    }

    public function update_field(SchemaField $field): self
    {
        $this->fields[ $field->name() ] = $field;

        return $this;
    }

    private function field(string $field, string $type): SchemaFieldBuilder
    {
        $this->assert_field_name($field);
        $schema_field = $this->fields[ $field ] ?? new SchemaField($field, $type);
        $this->fields[ $field ] = new SchemaField(
            $field,
            $type,
            $schema_field->required(),
            $schema_field->indexed(),
            $schema_field->unique(),
            $schema_field->range()
        );

        return new SchemaFieldBuilder($this, $this->fields[ $field ]);
    }

    private function assert_field_name(string $field): void
    {
        if ('' === trim($field)) {
            throw new StorageException('Schema field cannot be empty.');
        }
    }

    private function matches_type(string $type, mixed $value): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'mixed' => true,
            default => false,
        };
    }
}
