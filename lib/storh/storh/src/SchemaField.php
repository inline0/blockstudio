<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class SchemaField
{
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly bool $required = false,
        private readonly bool $indexed = false,
        private readonly bool $unique = false,
        private readonly bool $range = false
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function indexed(): bool
    {
        return $this->indexed || $this->unique || $this->range;
    }

    public function unique(): bool
    {
        return $this->unique;
    }

    public function range(): bool
    {
        return $this->range;
    }

    public function with_required(bool $required = true): self
    {
        return new self($this->name, $this->type, $required, $this->indexed, $this->unique, $this->range);
    }

    public function with_indexed(bool $indexed = true): self
    {
        return new self($this->name, $this->type, $this->required, $indexed, $this->unique, $this->range);
    }

    public function with_unique(bool $unique = true): self
    {
        return new self($this->name, $this->type, $this->required, true, $unique, $this->range);
    }

    public function with_range(bool $range = true): self
    {
        return new self($this->name, $this->type, $this->required, true, $this->unique, $range);
    }
}
