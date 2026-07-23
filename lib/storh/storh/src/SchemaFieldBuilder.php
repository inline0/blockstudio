<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class SchemaFieldBuilder
{
    public function __construct(
        private readonly Schema $schema,
        private SchemaField $field
    ) {
    }

    public function index(): Schema
    {
        $this->field = $this->field->with_indexed();

        return $this->schema->update_field($this->field);
    }

    public function unique(): Schema
    {
        $this->field = $this->field->with_unique();

        return $this->schema->update_field($this->field);
    }

    public function range(): Schema
    {
        $this->field = $this->field->with_range();

        return $this->schema->update_field($this->field);
    }

    /**
     * @param list<string>|null $fields
     */
    public function required(?array $fields = null): Schema
    {
        if (null !== $fields) {
            return $this->schema->required($fields);
        }

        $this->field = $this->field->with_required();

        return $this->schema->update_field($this->field);
    }

    public function string(string $field): self
    {
        return $this->schema->string($field);
    }

    public function int(string $field): self
    {
        return $this->schema->int($field);
    }

    public function float(string $field): self
    {
        return $this->schema->float($field);
    }

    public function bool(string $field): self
    {
        return $this->schema->bool($field);
    }

    /**
     * @param list<string> $fields
     */
    public function required_fields(array $fields): Schema
    {
        return $this->schema->required($fields);
    }
}
